import 'dart:convert';

import 'package:alona_mobile/features/rider/pages/rider_portal_page.dart';
import 'package:alona_mobile/features/rider/services/rider_session.dart';
import 'package:alona_mobile/features/rider/services/token_store.dart';
import 'package:alona_mobile/features/rider/theme/rider_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class _FixtureTokens extends TokenStore {
  @override
  Future<String?> read() async => 'synthetic-rider-token';
}

void main() {
  for (final scale in [1.0, 1.3]) {
    testWidgets('Recipient search selects the intended person at text scale $scale', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final availableRecipients = [
        {'id': 11, 'name': 'North Hub'},
        {'id': 12, 'name': 'South Hub'},
        {'id': 13, 'name': 'North Hub'},
      ];
      final sent = <Map<String, dynamic>>[];
      final client = MockClient((request) async {
        Object payload;
        if (request.url.path.endsWith('/partner/profile')) {
          payload = {
            'data': {
              'role': 'rider',
              'status': 'approved',
              'approvalStatus': 'approved',
              'is_active': true,
              'verification_required': false,
              'verified': true,
            },
          };
        } else if (request.url.path.endsWith('/messages/recipients')) {
          payload = {'data': availableRecipients};
        } else if (request.method == 'POST' &&
            request.url.path.endsWith('/rider/messages')) {
          sent.add(Map<String, dynamic>.from(jsonDecode(request.body) as Map));
          payload = {'conversation_id': 100};
        } else {
          expect(request.method, 'GET');
          payload = {'data': <Object>[]};
        }
        return http.Response(
          jsonEncode(payload),
          200,
          headers: {'content-type': 'application/json'},
        );
      });
      addTearDown(client.close);

      await tester.pumpWidget(
        MaterialApp(
          theme: RiderTheme.theme,
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context).copyWith(
              textScaler: TextScaler.linear(scale),
            ),
            child: child!,
          ),
          home: RiderChatPage(
            session: RiderSession(tokens: _FixtureTokens(), client: client),
          ),
        ),
      );
      await tester.pumpAndSettle();
      final search = find.byKey(const ValueKey('rider-recipient-search'));
      final draft = find.widgetWithText(TextField, 'Write to the Logistics Hub');
      final send = find.widgetWithText(FilledButton, 'Send message');

      await tester.tap(search);
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('rider-recipient-12')), findsOneWidget);
      await tester.tap(find.text('Messages'));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('rider-recipient-12')), findsNothing);
      expect(tester.widget<TextField>(search).focusNode!.hasFocus, isFalse);

      await tester.enterText(draft, 'Synthetic message');
      await tester.enterText(search, '  sOuTh  ');
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('rider-recipient-12')), findsOneWidget);
      expect(find.byKey(const ValueKey('rider-recipient-11')), findsNothing);
      expect(tester.widget<FilledButton>(send).onPressed, isNull);

      await tester.tap(find.text('Message'));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('rider-recipient-12')), findsNothing);
      expect(tester.widget<TextField>(search).controller!.text, isEmpty);
      await tester.enterText(search, '  sOuTh  ');
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('rider-recipient-12')));
      await tester.pumpAndSettle();
      expect(tester.widget<TextField>(search).controller!.text, 'South Hub');
      expect(find.byKey(const ValueKey('rider-recipient-12')), findsNothing);
      expect(tester.widget<FilledButton>(send).onPressed, isNotNull);

      await tester.tap(search);
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('rider-recipient-12')), findsOneWidget);
      await tester.showKeyboard(draft);
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('rider-recipient-12')), findsNothing);
      expect(tester.widget<TextField>(search).controller!.text, 'South Hub');
      expect(tester.widget<FilledButton>(send).onPressed, isNotNull);

      await tester.enterText(search, 'north');
      await tester.pumpAndSettle();
      expect(tester.widget<FilledButton>(send).onPressed, isNull);
      expect(find.byKey(const ValueKey('rider-recipient-11')), findsOneWidget);
      expect(find.byKey(const ValueKey('rider-recipient-13')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('rider-recipient-11')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(send);
      await tester.pumpAndSettle();
      await tester.tap(send);
      await tester.pumpAndSettle();
      expect(sent, [{'body': 'Synthetic message', 'recipient_id': 11}]);

      await tester.ensureVisible(search);
      await tester.pumpAndSettle();
      await tester.enterText(search, 'unknown');
      await tester.pumpAndSettle();
      expect(find.text('No matching recipients'), findsOneWidget);
      expect(tester.widget<FilledButton>(send).onPressed, isNull);
      await tester.tap(find.byTooltip('Clear recipient search'));
      await tester.pumpAndSettle();
      expect(tester.widget<TextField>(search).controller!.text, isEmpty);
      expect(find.byKey(const ValueKey('rider-recipient-12')), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('rider-recipient-12')));
      await tester.pumpAndSettle();
      availableRecipients.clear();
      await tester.tap(find.byTooltip('Refresh'));
      await tester.pumpAndSettle();
      expect(tester.widget<TextField>(search).controller!.text, isEmpty);
      expect(tester.widget<FilledButton>(send).onPressed, isNull);
      await tester.tap(search);
      await tester.pumpAndSettle();
      expect(find.text('No available recipients'), findsOneWidget);
      expect(sent, hasLength(1));
      expect(tester.takeException(), isNull);
    });
  }
}
