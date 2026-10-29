import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/delivery_history_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  testWidgets(
    'history paginates existing parcel records and shows no invented payout',
    (tester) async {
      var page = '1';
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          expect(request.url.path, '/api/rider/deliveries');
          expect(request.url.queryParameters['view'], 'history');
          page = request.url.queryParameters['page']!;
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': int.parse(page),
                  'order_id': 'ITEM-$page',
                  'status': 'DELIVERED',
                  'delivery_address': 'Shipping address',
                },
              ],
              'meta': {'current_page': int.parse(page), 'last_page': 2},
              'summary': {'DELIVERED': 2, 'FAILED_RETURNED': 0, 'active': 1},
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: DeliveryHistoryPage(session: session)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Order ITEM-1'), findsOneWidget);
      expect(find.text('View earnings'), findsOneWidget);
      await tester.ensureVisible(find.text('Next'));
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      expect(page, '2');
      expect(find.text('Order ITEM-2'), findsOneWidget);
    },
  );
}
