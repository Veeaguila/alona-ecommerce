import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/pickup_dashboard_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  testWidgets('rider accepts then confirms the same parcel using ALONA IDs', (
    tester,
  ) async {
    var accepted = false, confirmed = false;
    final calls = <String>[];
    final parcel = {
      'id': 42,
      'order_id': 'ITEM-7',
      'status': 'PLACED',
      'collection_address': 'Seller warehouse',
      'delivery_address': 'Buyer destination',
      'pickup_requests': [],
    };
    final session = RiderSession(
      tokens: MemoryTokens(),
      client: MockClient((request) async {
        expect(request.headers['Authorization'], 'Bearer test-token');
        calls.add('${request.method} ${request.url.path}');
        final path = request.url.path;
        if (path.endsWith('/partner/profile')) {
          return http.Response(jsonEncode({'data': readyProfile()}), 200);
        }
        if (path.endsWith('/available')) {
          return http.Response(
            jsonEncode({
              'data': accepted ? [] : [parcel],
            }),
            200,
          );
        }
        if (path.endsWith('/mine')) {
          return http.Response(
            jsonEncode({
              'data': accepted && !confirmed ? [parcel] : [],
            }),
            200,
          );
        }
        if (path.endsWith('/42/accept')) {
          accepted = true;
          return http.Response('{"data":{"id":42}}', 200);
        }
        if (path.endsWith('/42/confirm')) {
          confirmed = true;
          return http.Response('{"data":{"id":42,"status":"PICKED_UP"}}', 200);
        }
        throw StateError('Unexpected request $path');
      }),
    );
    await tester.pumpWidget(
      MaterialApp(home: PickupDashboardPage(session: session)),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Accept pickup'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Accept pickup'));
    await tester.pumpAndSettle();
    expect(accepted, true);
    expect(find.textContaining('Seller warehouse'), findsOneWidget);
    expect(find.textContaining('Buyer destination'), findsNothing);
    final confirm = find.text('Confirm Pickup');
    await tester.ensureVisible(confirm);
    await tester.pumpAndSettle();
    await tester.tap(confirm);
    await tester.pumpAndSettle();
    expect(confirmed, true);
    expect(
      calls.where((c) => c == 'POST /api/rider/pickups/42/accept').length,
      1,
    );
    expect(
      calls.where((c) => c == 'POST /api/rider/pickups/42/confirm').length,
      1,
    );
  });
  testWidgets(
    'duplicate taps send one acceptance and conflicts refresh safely',
    (tester) async {
      final pending = Completer<http.Response>();
      var posts = 0;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          final path = request.url.path;
          if (path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          if (path.endsWith('/available')) {
            return http.Response(
              '{"data":[{"id":42,"order_id":"ITEM-7","status":"PLACED","pickup_requests":[]}]}',
              200,
            );
          }
          if (path.endsWith('/mine')) return http.Response('{"data":[]}', 200);
          if (path.endsWith('/presence')) return http.Response('{"data":{"presence":"Unknown","is_current_session":false}}', 200);
          posts++;
          return pending.future;
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: PickupDashboardPage(session: session)),
      );
      await tester.pumpAndSettle();
      final button = find.text('Accept pickup');
      await tester.ensureVisible(button);
      await tester.pumpAndSettle();
      await tester.tap(button);
      await tester.pump();
      await tester.tap(button);
      await tester.pump();
      expect(posts, 1);
      pending.complete(
        http.Response('<html>internal debug details</html>', 409),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('accepted by another rider'), findsOneWidget);
      expect(find.textContaining('debug details'), findsNothing);
    },
  );
  testWidgets('manual pickup requires acknowledgment before collection', (
    tester,
  ) async {
    var acknowledged = false, collected = false;
    final posts = <String>[];
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
        if (path.endsWith('/available')) {
          return http.Response('{"data":[]}', 200);
        }
        if (path.endsWith('/mine')) {
          return http.Response(
            jsonEncode({
              'data': collected
                  ? []
                  : [
                      {
                        'id': 42,
                        'order_id': 'ITEM-7',
                        'status': 'PLACED',
                        'pickup_assignment_state': acknowledged
                            ? 'accepted'
                            : 'assigned',
                        'pickup_requests': [],
                      },
                    ],
            }),
            200,
          );
        }
        posts.add(path);
        if (path.endsWith('/42/acknowledge')) {
          acknowledged = true;
          return http.Response('{"data":{"id":42}}', 200);
        }
        if (path.endsWith('/42/confirm')) {
          expect(acknowledged, true);
          collected = true;
          return http.Response('{"data":{"id":42,"status":"PICKED_UP"}}', 200);
        }
        throw StateError('Unexpected request $path');
      }),
    );
    await tester.pumpWidget(
      MaterialApp(home: PickupDashboardPage(session: session)),
    );
    await tester.pumpAndSettle();
    expect(find.text('Confirm Pickup'), findsNothing);
    final accept = find.text('Accept assigned pickup');
    await tester.ensureVisible(accept);
    await tester.pumpAndSettle();
    await tester.tap(accept);
    await tester.pumpAndSettle();
    expect(acknowledged, true);
    expect(collected, false);
    final confirm = find.text('Confirm Pickup');
    await tester.ensureVisible(confirm);
    await tester.pumpAndSettle();
    await tester.tap(confirm);
    await tester.pumpAndSettle();
    expect(collected, true);
    expect(posts, [
      '/api/rider/pickups/42/acknowledge',
      '/api/rider/pickups/42/confirm',
    ]);
    await tester.pumpWidget(const SizedBox.shrink());
  });
}
