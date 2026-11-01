import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/delivery_dashboard_page.dart';
import 'package:rider_app/pages/order_detail_pages.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  for (final outcome in ['Buyer not home', 'Wrong address', 'Refused']) {
    testWidgets('detail selection sends the actual $outcome outcome', (
      tester,
    ) async {
      String? reason;
      await tester.pumpWidget(
        MaterialApp(
          home: DeliveryOrderPage(
            delivery: const {'order_id': 'ITEM-42'},
            session: null,
            statusLabel: 'OUT FOR DELIVERY',
            onDeliver: () async {},
            onFail: (value) async {
              reason = value;
            },
          ),
        ),
      );
      await tester.ensureVisible(find.text(outcome));
      await tester.tap(find.text(outcome));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Mark as failed'));
      await tester.tap(find.text('Mark as failed'));
      await tester.pumpAndSettle();
      expect(reason, outcome);
      expect(find.text('Mark Delivery as Failed'), findsNothing);
    });
  }

  for (final flag in ['awaiting_retry', 'rts_eligible', 'return_status']) {
    testWidgets(
      '$flag prevents normal delivery actions from detail navigation',
      (tester) async {
        tester.view.physicalSize = const Size(1000, 1600);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final posts = <String>[];
        final session = RiderSession(
          tokens: MemoryTokens(),
          client: MockClient((request) async {
            if (request.url.path.endsWith('/partner/profile')) {
              return http.Response(jsonEncode({'data': readyProfile()}), 200);
            }
            if (request.url.path.endsWith('/presence')) {
              return http.Response(
                '{"data":{"presence":"Unknown","is_current_session":false}}',
                200,
              );
            }
            if (request.method == 'POST') {
              posts.add(request.url.path);
              return http.Response('{}', 500);
            }
            return http.Response(
              jsonEncode({
                'data': [
                  {
                    'id': 42,
                    'order_id': 'ITEM-42',
                    'status': 'OUT_FOR_DELIVERY',
                    flag: flag == 'return_status' ? 'eligible' : true,
                  },
                ],
              }),
              200,
            );
          }),
        );
        await tester.pumpWidget(
          MaterialApp(home: DeliveryDashboardPage(session: session)),
        );
        await tester.pumpAndSettle();
        // Backend DTO omits recipient/payment amounts: the app must not invent them.
        expect(find.text('Carlo Mendoza'), findsNothing);
        expect(find.textContaining('Prepaid'), findsNothing);
        expect(find.textContaining('450'), findsNothing);
        expect(find.text('Mark Delivered'), findsNothing);
        await tester.ensureVisible(find.text('#ITEM-42'));
        await tester.tap(find.text('#ITEM-42'));
        await tester.pumpAndSettle();
        expect(find.byType(DeliveryOrderPage), findsOneWidget);
        expect(find.text('Delivery result'), findsNothing);
        expect(find.text('Start Delivery'), findsNothing);
        expect(find.text('Mark as delivered'), findsNothing);
        expect(posts, isEmpty);
        await tester.pumpWidget(const SizedBox.shrink());
      },
    );
  }
}
