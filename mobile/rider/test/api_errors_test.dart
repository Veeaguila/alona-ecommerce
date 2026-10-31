import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/login_page.dart';
import 'package:rider_app/services/api_error.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens;

void main() {
  test('known status errors are clear and never display server traces', () {
    for (final code in [401, 403, 404, 409, 413, 422, 429, 500, 502]) {
      final message = apiErrorMessage(
        http.Response('<html>SQL password stacktrace</html>', code),
      );
      expect(message, isNot(contains('SQL')));
      expect(message, isNot(contains('stacktrace')));
      expect(message, isNotEmpty);
    }
    expect(
      apiErrorMessage(
        http.Response(
          '{"errors":{"contact_no":["The contact number is invalid."]}}',
          422,
        ),
      ),
      contains('contact number is invalid'),
    );
  });
  test(
    'credentials cannot be sent outside the configured ALONA origin',
    () async {
      final session = RiderSession(
        client: MockClient((_) async {
          fail('No foreign request allowed');
        }),
      );
      await expectLater(
        session.post(
          Uri.parse('https://foreign.example/api/rider/login'),
          body: {'password': 'test'},
        ),
        throwsA(isA<SessionFailure>()),
      );
    },
  );
  testWidgets(
    'non-JSON login failure is safe and does not overwrite local credentials',
    (tester) async {
      final tokens = MemoryTokens();
      final session = RiderSession(
        tokens: tokens,
        client: MockClient((request) async {
          expect(request.url.path, '/api/rider/login');
          return http.Response('<html>private database error</html>', 500);
        }),
      );
      await tester.pumpWidget(MaterialApp(home: LoginPage(session: session)));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(TextFormField).at(0),
        'rider@example.test',
      );
      await tester.enterText(find.byType(TextFormField).at(1), 'test-password');
      await tester.ensureVisible(find.text('Log in'));
      await tester.tap(find.text('Log in'));
      await tester.pumpAndSettle();
      expect(find.textContaining('private database'), findsNothing);
      expect(find.textContaining('could not complete'), findsOneWidget);
      expect(tokens.token, 'test-token');
    },
  );
}
