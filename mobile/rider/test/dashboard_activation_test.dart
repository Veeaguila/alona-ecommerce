import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/delivery_dashboard_page.dart';
import 'package:rider_app/pages/pickup_dashboard_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  for (final pickup in [false, true]) {
    testWidgets(
      '${pickup ? 'Pickup' : 'Delivery'} activation loads assignments created while away',
      (tester) async {
        tester.view.physicalSize = const Size(1000, 1600);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        var assigned = false;
        final session = RiderSession(
          tokens: MemoryTokens(),
          client: MockClient((request) async {
            final p = request.url.path;
            if (p.endsWith('/partner/profile')) {
              return http.Response(jsonEncode({'data': readyProfile()}), 200);
            }
            if (p.endsWith('/presence')) {
              return http.Response(
                '{"data":{"presence":"Unknown","is_current_session":false}}',
                200,
              );
            }
            return http.Response(
              jsonEncode({
                'data': assigned && !p.endsWith('/available')
                    ? [
                        {
                          'id': 42,
                          'order_id': 'ITEM-42',
                          'status': pickup ? 'PLACED' : 'AT_SORTING_CENTER',
                          'pickup_requests': [],
                          'pickup_assignment_state': 'assigned',
                        },
                      ]
                    : [],
              }),
              200,
            );
          }),
        );
        Widget app(bool active) => MaterialApp(
          home: pickup
              ? PickupDashboardPage(session: session, active: active)
              : DeliveryDashboardPage(session: session, active: active),
        );
        await tester.pumpWidget(app(true));
        await tester.pumpAndSettle();
        expect(find.text('#ITEM-42'), findsNothing);
        await tester.pumpWidget(app(false));
        await tester.pumpAndSettle();
        assigned = true;
        await tester.pumpWidget(app(true));
        await tester.pumpAndSettle();
        expect(find.text('#ITEM-42'), findsOneWidget);
        if (!pickup) {
          assigned = false;
          await tester.tap(find.byTooltip('Refresh'));
          await tester.pumpAndSettle();
          expect(find.text('#ITEM-42'), findsNothing);
        }
        await tester.pumpWidget(const SizedBox.shrink());
      },
    );
  }
}
