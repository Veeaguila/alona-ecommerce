import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/features/logistics/presentation/payroll/rider_payouts_screen.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';

http.Response reply(Object value) => http.Response(
  jsonEncode({'data': value}),
  200,
  headers: {'content-type': 'application/json'},
);
Map<String, dynamic> fixture() => {
  'summary': {'unpaid': '65.00', 'processing': '0.00', 'paid': '0.00'},
  'settings': {
    'enabled': false,
    'revision': 0,
    'pickup': null,
    'delivery': null,
    'transfer': null,
  },
  'riders': [
    {'id': 7, 'name': 'Synthetic Rider'},
  ],
  'payouts': {'data': <Object>[], 'current_page': 1, 'last_page': 1},
};
void main() {
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    ApiConfig.onServerChanged = null;
    ApiClient.onUnauthorized = null;
    await ApiConfig.setBaseUrl('http://localhost:8000');
    ApiClient.setLogisticsToken('synthetic');
    ApiClient.setCurrentRole('logistics');
  });
  testWidgets(
    'owner rates are initially blank and a payout cannot prepare without review',
    (tester) async {
      ApiClient.client = MockClient((_) async => reply(fixture()));
      await tester.pumpWidget(const MaterialApp(home: RiderPayoutsScreen()));
      await tester.pumpAndSettle();
      expect(find.text('Rider payouts'), findsOneWidget);
      for (final field in tester.widgetList<TextField>(
        find.byType(TextField),
      )) {
        expect(field.controller!.text, isEmpty);
      }
      expect(find.text('Prepare grouped payout'), findsNothing);
      await tester.scrollUntilVisible(
        find.widgetWithText(AlonaButton, 'Review unpaid jobs'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      final review = tester.widget<AlonaButton>(
        find.widgetWithText(AlonaButton, 'Review unpaid jobs'),
      );
      expect(review.onPressed, isNull);
    },
  );
  testWidgets('a closed owner page ignores late payroll responses', (
    tester,
  ) async {
    final pending = Completer<http.Response>();
    ApiClient.client = MockClient((_) => pending.future);
    await tester.pumpWidget(const MaterialApp(home: RiderPayoutsScreen()));
    await tester.pump();
    await tester.pumpWidget(const MaterialApp(home: SizedBox()));
    pending.complete(reply(fixture()));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
  testWidgets('gallery failure stays retryable and never records a payment', (
    tester,
  ) async {
    final picking = Completer<XFile?>();
    var picks = 0;
    final row = <String, dynamic>{
      'id': 3,
      'amount': '65.00',
      'items_count': 2,
      'destination': {'label': 'Synthetic cash', 'method': 'cash'},
      'status': 'prepared',
    };
    final page = fixture();
    page['settings'] = {
      'enabled': true,
      'revision': 1,
      'pickup': '25.00',
      'delivery': '40.00',
      'transfer': null,
    };
    page['payouts'] = {
      'data': [row],
      'current_page': 1,
      'last_page': 1,
    };
    var writes = 0;
    ApiClient.client = MockClient((request) async {
      if (request.method != 'GET') writes++;
      return reply(
        request.url.path.endsWith('/payouts/3')
            ? {
                ...row,
                'instructions': {'account_name': 'Synthetic Rider'},
              }
            : page,
      );
    });
    await tester.pumpWidget(
      MaterialApp(
        home: RiderPayoutsScreen(
          pickPaymentProof: () {
            picks++;
            return picking.future;
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Review & record actual payment'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Review & record actual payment'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Choose payment proof (image)'));
    await tester.tap(find.text('Choose payment proof (image)'));
    await tester.pump();
    await tester.tap(
      find.text('Choose payment proof (image)'),
      warnIfMissed: false,
    );
    await tester.pump();
    expect(picks, 1);
    picking.completeError(PlatformException(code: 'photo_access_denied'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(
      find.text(
        'Could not open your photo library. Check permissions and try again.',
      ),
      findsOneWidget,
    );
    expect(find.text('Choose payment proof (image)'), findsOneWidget);
    expect(writes, 0);
  });
}
