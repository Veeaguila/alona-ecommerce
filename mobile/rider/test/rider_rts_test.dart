import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/delivery_dashboard_page.dart';
import 'package:rider_app/pages/rider_returns_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  testWidgets(
    'three distinct failures stay tracked and require explicit retries before RTS',
    (tester) async {
      tester.view.physicalSize = const Size(1000, 1600);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var attempts = 0, awaitingRetry = false;
      final writes = <String>[];
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          final path = request.url.path;
          if (path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          if (path.endsWith('/presence')) {
            return http.Response(
              '{"data":{"presence":"Unknown","is_current_session":false}}',
              200,
            );
          }
          if (request.method == 'GET') {
            return http.Response(
              jsonEncode({
                'data': [
                  {
                    'id': 42,
                    'order_id': 'ITEM-7',
                    'status': 'OUT_FOR_DELIVERY',
                    'attempt_count': attempts,
                    'awaiting_retry': awaitingRetry,
                    'rts_eligible': attempts == 3,
                    'return_status': attempts == 3 ? 'eligible' : null,
                    'has_valid_photo_proof': false,
                    'delivery_address': 'Synthetic Buyer destination',
                  },
                ],
              }),
              200,
            );
          }
          writes.add(path);
          if (path.endsWith('/fail')) {
            attempts++;
            awaitingRetry = attempts < 3;
          } else if (path.endsWith('/retry')) {
            awaitingRetry = false;
          } else {
            fail('Failed attempts cannot complete Buyer delivery');
          }
          return http.Response(
            '{"message":"Attempt recorded","data":{"id":42,"status":"OUT_FOR_DELIVERY"}}',
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: DeliveryDashboardPage(session: session)),
      );
      await tester.pumpAndSettle();
      for (var number = 1; number <= 3; number++) {
        await tester.ensureVisible(find.text('Mark Failed'));
        await tester.tap(find.text('Mark Failed'));
        await tester.pumpAndSettle();
        await tester.tap(find.byType(DropdownButtonFormField<String>));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Buyer not home').last);
        await tester.pumpAndSettle();
        await tester.tap(find.text('Confirm Failed'));
        await tester.pumpAndSettle();
        expect(find.text('#ITEM-7'), findsOneWidget);
        expect(find.text('Mark Delivered'), findsNothing);
        if (number < 3) {
          await tester.ensureVisible(find.text('Start next delivery attempt'));
          await tester.tap(find.text('Start next delivery attempt'));
          await tester.pumpAndSettle();
        }
      }
      expect(find.text('Start next delivery attempt'), findsNothing);
      expect(find.text('Return to Seller jobs'), findsOneWidget);
      expect(writes.where((p) => p.endsWith('/fail')).length, 3);
      expect(writes.where((p) => p.endsWith('/retry')).length, 2);
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump(const Duration(seconds: 4));
    },
  );
  testWidgets(
    'RTS pickup and Seller handover are separate physical confirmations and stay in return history',
    (tester) async {
      var status = 'initiated';
      final writes = <String>[];
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          final path = request.url.path;
          if (path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          if (request.method == 'POST') {
            writes.add(path);
            expect(jsonDecode(request.body), {'physical_confirmation': true});
            status = path.endsWith('/pickup') ? 'in_transit' : 'handed_over';
            return http.Response(
              '{"message":"Physical action recorded","data":{}}',
              200,
            );
          }
          return http.Response(
            jsonEncode({
              'data': {
                'current_page': 1,
                'last_page': 1,
                'data': [
                  {
                    'id': 5,
                    'status': status,
                    'reason': 'Three failed Buyer delivery attempts',
                    'parcel': {
                      'order_id': 'ITEM-7',
                      'collection_address': 'Synthetic Seller address',
                      'seller': {'name': 'Synthetic Seller'},
                    },
                    'picked_up_at': status != 'initiated'
                        ? '2026-10-20T12:00:00Z'
                        : null,
                    'handed_over_at': status == 'handed_over'
                        ? '2026-10-20T13:00:00Z'
                        : null,
                    'seller_received_at': null,
                  },
                ],
              },
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: RiderReturnsPage(session: session)),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm return pickup'));
      await tester.pumpAndSettle();
      expect(writes, isEmpty);
      await tester.tap(find.text('I confirm the physical action'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm handover to Seller'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('I confirm the physical action'));
      await tester.pumpAndSettle();
      expect(writes, [
        '/api/rider/returns/5/pickup',
        '/api/rider/returns/5/handover',
      ]);
      expect(
        find.textContaining(
          'Returned to Seller — awaiting receipt confirmation',
        ),
        findsOneWidget,
      );
      expect(find.text('Confirm handover to Seller'), findsNothing);
      expect(find.text('Mark Delivered'), findsNothing);
      await tester.pumpWidget(const SizedBox.shrink());
    },
  );
}
