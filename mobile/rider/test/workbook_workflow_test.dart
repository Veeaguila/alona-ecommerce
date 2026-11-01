import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/pickup_dashboard_page.dart';
import 'package:rider_app/services/rider_session.dart';
import 'package:rider_app/widgets/failure_reason_dialog.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  testWidgets(
    'failure choices require selection and Refused explains immediate RTS',
    (tester) async {
      String? chosen;
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () async {
                  chosen = await showDialog<String>(
                    context: context,
                    builder: (_) => const FailureReasonDialog(),
                  );
                },
                child: const Text('Fail delivery'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Fail delivery'));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<ElevatedButton>(
              find.widgetWithText(ElevatedButton, 'Confirm Failed'),
            )
            .onPressed,
        isNull,
      );
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      expect(find.text('Buyer not home'), findsOneWidget);
      expect(find.text('Wrong address'), findsOneWidget);
      await tester.tap(find.text('Refused').last);
      await tester.pumpAndSettle();
      expect(
        find.textContaining('directly to Return to Seller'),
        findsOneWidget,
      );
      await tester.tap(find.text('Confirm Failed'));
      await tester.pumpAndSettle();
      expect(chosen, 'Refused');
    },
  );

  testWidgets(
    'nearest pickup decline uses parcel ID then refreshes without claiming custody',
    (tester) async {
      tester.view.physicalSize = const Size(1000, 1600);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var declined = false;
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
            return http.Response(
              jsonEncode({
                'data': declined
                    ? []
                    : [
                        {
                          'id': 42,
                          'order_id': 'ITEM-42',
                          'status': 'PLACED',
                          'nearest_offer_expires_at': '2026-10-29T01:00:00Z',
                          'pickup_requests': [],
                        },
                      ],
              }),
              200,
            );
          }
          if (path.endsWith('/mine')) return http.Response('{"data":[]}', 200);
          if (request.method == 'POST') {
            posts.add(path);
            declined = true;
            return http.Response('{"message":"Offer declined."}', 200);
          }
          throw StateError('Unexpected request $path');
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: PickupDashboardPage(session: session)),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Decline nearest offer'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Decline nearest offer'));
      await tester.pumpAndSettle();
      expect(posts, ['/api/rider/pickups/42/decline']);
      expect(find.text('Nearest pickup offer declined.'), findsOneWidget);
      expect(find.text('Accept nearest pickup offer'), findsNothing);
    },
  );
}
