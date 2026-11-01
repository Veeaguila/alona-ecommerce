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
  testWidgets(
    'collected parcels remain in transit then completed handoff history without Rider arrival actions',
    (tester) async {
      var arrived = false;
      final calls = <String>[];
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          calls.add(
            '${request.method} ${request.url.path}?${request.url.query}',
          );
          expect(request.method, 'GET');
          if (request.url.path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          final view = request.url.queryParameters['view'];
          final show =
              view == 'transit' && !arrived || view == 'history' && arrived;
          return http.Response(
            jsonEncode({
              'data': show
                  ? [
                      {
                        'id': 42,
                        'order_id': 'ITEM-42',
                        'status': arrived ? 'AT_SORTING_CENTER' : 'PICKED_UP',
                        'collection_address': 'Seller collection location',
                      },
                    ]
                  : [],
              'meta': view == 'history'
                  ? {
                      'current_page': 1,
                      'last_page': 1,
                      'total': arrived ? 1 : 0,
                    }
                  : null,
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: PickupDashboardPage(session: session)),
      );
      await tester.pumpAndSettle();
      var tab = find.widgetWithText(ChoiceChip, 'In Transit to Center');
      await tester.ensureVisible(tab);
      await tester.tap(tab);
      await tester.pumpAndSettle();
      expect(find.text('#ITEM-42'), findsOneWidget);
      expect(find.text('Confirm Pickup'), findsNothing);
      expect(find.text('Confirm Arrival'), findsNothing);
      expect(find.textContaining('Only Logistics staff'), findsOneWidget);
      arrived = true;
      await tester.tap(find.byTooltip('Refresh'));
      await tester.pumpAndSettle();
      expect(find.text('#ITEM-42'), findsNothing);
      tab = find.widgetWithText(ChoiceChip, 'Pickup History');
      await tester.ensureVisible(tab);
      await tester.tap(tab);
      await tester.pumpAndSettle();
      expect(find.text('#ITEM-42'), findsOneWidget);
      expect(find.text('Arrived at Sorting Center'), findsOneWidget);
      expect(find.textContaining('Pickup handoff completed'), findsOneWidget);
      expect(calls.any((call) => call.contains('view=history')), true);
      expect(find.text('Start Delivery'), findsNothing);
    },
  );
  testWidgets('history pagination survives a stale transit failure', (
    tester,
  ) async {
    final stale = Completer<http.Response>();
    final pages = <String?>[];
    final session = RiderSession(
      tokens: MemoryTokens(),
      client: MockClient((request) async {
        if (request.url.path.endsWith('/partner/profile')) {
          return http.Response(jsonEncode({'data': readyProfile()}), 200);
        }
        final view = request.url.queryParameters['view'];
        if (view == 'transit') return stale.future;
        if (view == 'history') {
          final page = request.url.queryParameters['page'];
          pages.add(page);
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': page == '2' ? 43 : 42,
                  'order_id': page == '2' ? 'ITEM-43' : 'ITEM-42',
                  'status': 'AT_SORTING_CENTER',
                },
              ],
              'meta': {
                'current_page': int.parse(page ?? '1'),
                'last_page': 2,
                'total': 21,
              },
            }),
            200,
          );
        }
        return http.Response('{"data":[]}', 200);
      }),
    );
    await tester.pumpWidget(
      MaterialApp(home: PickupDashboardPage(session: session)),
    );
    await tester.pumpAndSettle();
    var tab = find.widgetWithText(ChoiceChip, 'In Transit to Center');
    await tester.ensureVisible(tab);
    await tester.tap(tab);
    await tester.pump();
    tab = find.widgetWithText(ChoiceChip, 'Pickup History');
    await tester.ensureVisible(tab);
    await tester.tap(tab);
    await tester.pumpAndSettle();
    stale.complete(http.Response('server failure', 500));
    await tester.pumpAndSettle();
    expect(
      find.text(
        'The service could not complete this request. Please retry shortly.',
      ),
      findsNothing,
    );
    expect(find.text('#ITEM-42'), findsOneWidget);
    final next = find.text('Next');
    await tester.scrollUntilVisible(
      next,
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.ensureVisible(next);
    await tester.pumpAndSettle();
    await tester.tap(next);
    await tester.pumpAndSettle();
    expect(find.text('#ITEM-43'), findsOneWidget);
    expect(pages, ['1', '2']);
  });
}
