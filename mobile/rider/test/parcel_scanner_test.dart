import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/parcel_scanner_page.dart';
import 'package:rider_app/services/rider_parcel_api.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  Map<String, dynamic> parcel(int id, String reference) => {
    'id': id,
    'order_id': reference,
    'status': 'OUT_FOR_DELIVERY',
    'delivery_address': 'Order destination',
    'has_delivery_proof': false,
  };
  testWidgets(
    'camera duplicate frames submit once and open only matching delivery',
    (tester) async {
      final response = Completer<http.Response>();
      var lookups = 0;
      String? scannedReference;
      late ValueChanged<String> detect;
      final row = parcel(42, 'ITEM-101');
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          if (request.url.path.endsWith('/lookup')) {
            lookups++;
            scannedReference = request.url.queryParameters['reference'];
            return response.future;
          }
          return http.Response(
            jsonEncode({
              'data': [row, parcel(43, 'ITEM-102')],
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: ParcelScannerPage(
            session: session,
            scannerBuilder: (callback) {
              detect = callback;
              return const ColoredBox(color: Colors.black);
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      detect('ITEM-101');
      detect('ITEM-101');
      await tester.pump();
      expect(lookups, 1);
      expect(scannedReference, 'ITEM-101');
      await tester.pump();
      expect(
        find.text('Checking your assignment...'),
        findsOneWidget,
        reason: tester
            .widgetList<Text>(find.byType(Text))
            .map((text) => text.data)
            .join(' | '),
      );
      response.complete(http.Response(jsonEncode({'data': row}), 200));
      await tester.pumpAndSettle();
      expect(find.text('Delivery details'), findsOneWidget);
      expect(find.text('#ITEM-101'), findsOneWidget);
      expect(find.text('#ITEM-102'), findsNothing);
      await tester.pageBack();
      await tester.pumpAndSettle();
      detect('ITEM-101');
      await tester.pump();
      expect(lookups, 1);
      expect(find.text('Scan another parcel'), findsOneWidget);
    },
  );
  testWidgets(
    'manual fallback validates code and handles unknown or completed scans safely',
    (tester) async {
      var lookups = 0, completed = false;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          lookups++;
          return completed
              ? http.Response(
                  '{"code":"parcel_completed","message":"internal details"}',
                  409,
                )
              : http.Response('{"code":"parcel_not_found"}', 404);
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: ParcelScannerPage(
            session: session,
            scannerBuilder: (_) => const SizedBox(),
          ),
        ),
      );
      await tester.pumpAndSettle();
      Future<void> enter(String value) async {
        await tester.scrollUntilVisible(
          find.byType(TextField),
          180,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        await tester.enterText(find.byType(TextField), value);
        await tester.ensureVisible(find.text('Open delivery details'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Open delivery details'));
        await tester.pumpAndSettle();
      }

      await enter('https://example.test');
      expect(lookups, 0);
      await enter('item-101');
      expect(lookups, 1);
      expect(
        find.textContaining('not found in your assignments'),
        findsOneWidget,
      );
      completed = true;
      await enter('ITEM-101');
      expect(
        find.textContaining('already completed or returned'),
        findsOneWidget,
      );
      expect(find.textContaining('internal details'), findsNothing);
    },
  );
  test('malformed lookup cannot navigate to an unrelated parcel and expired token clears', () async {
    final tokens = MemoryTokens();
    var expired = false;
    final session = RiderSession(
      tokens: tokens,
      client: MockClient((request) async {
        if (request.url.path.endsWith('/partner/profile')) {
          return http.Response(jsonEncode({'data': readyProfile()}), 200);
        }
        return expired
            ? http.Response('unauthorized', 401)
            : http.Response(jsonEncode({'data': parcel(7, 'ITEM-999')}), 200);
      }),
    );
    await expectLater(
      RiderParcelApi(session).lookup('ITEM-101'),
      throwsA(isA<SessionFailure>()),
    );
    expired = true;
    await expectLater(
      RiderParcelApi(session).lookup('ITEM-101'),
      throwsA(
        isA<SessionFailure>().having((e) => e.accessDenied, 'expired', true),
      ),
    );
    expect(tokens.token, isNull);
  });
}
