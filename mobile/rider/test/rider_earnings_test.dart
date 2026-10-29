import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/profit_dashboard_page.dart';
import 'package:rider_app/pages/rider_portal_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

Map<String, dynamic> earnings({int page = 1, bool empty = false}) => {
  'summary': {
    'currency': 'PHP',
    'total': empty ? '0.00' : '1234.57',
    'paid': empty ? '0.00' : '1000.01',
    'outstanding': empty ? '0.00' : '234.56',
  },
  'meta': {'current_page': page, 'last_page': empty ? 1 : 2},
  'data': empty
      ? []
      : [
          {
            'id': page,
            'amount': '17.13',
            'payout_status': page == 1 ? 'unpaid' : 'paid',
            'earned_at': '2026-10-15T01:00:00Z',
            'paid_at': page == 1 ? null : '2026-10-15T02:00:00Z',
            'charge': {'seller_shipment_id': page + 60, 'currency': 'PHP'},
          },
        ],
};

RiderSession sessionFor(
  Future<http.Response> Function(http.Request) answer, {
  MemoryTokens? tokens,
}) => RiderSession(
  tokens: tokens ?? MemoryTokens(),
  client: MockClient((request) async {
    expect(request.headers['Authorization'], 'Bearer test-token');
    if (request.url.path.endsWith('/partner/profile')) {
      return http.Response(jsonEncode({'data': readyProfile()}), 200);
    }
    expect(request.url.path, '/api/rider/earnings');
    expect(request.method, 'GET');
    return answer(request);
  }),
);

void main() {
  testWidgets(
    'shows backend totals and paginated shipment earnings without calculations',
    (tester) async {
      final pages = <String>[];
      final session = sessionFor((request) async {
        final page = request.url.queryParameters['page']!;
        pages.add(page);
        return http.Response(jsonEncode(earnings(page: int.parse(page))), 200);
      });
      await tester.pumpWidget(
        MaterialApp(home: ProfitDashboardPage(session: session)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Total earned'), findsOneWidget);
      expect(find.text('Pending payout'), findsWidgets);
      expect(find.text('Paid earnings'), findsOneWidget);
      expect(find.text('₱1234.57'), findsOneWidget);
      expect(find.text('₱234.56'), findsOneWidget);
      expect(find.text('₱1000.01'), findsOneWidget);
      expect(find.text('₱17.13'), findsOneWidget);
      expect(find.text('Shipment 61'), findsOneWidget);
      await tester.ensureVisible(find.text('Next'));
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      expect(pages, ['1', '2']);
      expect(find.text('Shipment 62'), findsOneWidget);
      expect(find.textContaining('Paid ·'), findsOneWidget);
    },
  );

  testWidgets(
    'empty result uses backend zero totals and has no invented entries',
    (tester) async {
      final session = sessionFor(
        (_) async => http.Response(jsonEncode(earnings(empty: true)), 200),
      );
      await tester.pumpWidget(
        MaterialApp(home: ProfitDashboardPage(session: session)),
      );
      await tester.pumpAndSettle();
      expect(find.text('₱0.00'), findsNWidgets(3));
      expect(find.text('No delivery earnings yet.'), findsOneWidget);
    },
  );

  testWidgets(
    'failed refresh hides stale money, sanitizes errors and supports retry',
    (tester) async {
      var calls = 0;
      final session = sessionFor(
        (_) async => ++calls == 2
            ? http.Response('<html>private SQL trace</html>', 500)
            : http.Response(jsonEncode(earnings()), 200),
      );
      await tester.pumpWidget(
        MaterialApp(home: ProfitDashboardPage(session: session)),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Refresh earnings'));
      await tester.pumpAndSettle();
      expect(find.text('₱1234.57'), findsNothing);
      expect(find.text('₱0.00'), findsNothing);
      expect(find.textContaining('private SQL'), findsNothing);
      await tester.tap(find.text('Retry'));
      await tester.pumpAndSettle();
      expect(find.text('₱1234.57'), findsOneWidget);
    },
  );

  testWidgets(
    'missing or numeric money is rejected instead of showing fallback totals',
    (tester) async {
      final broken = earnings();
    broken['summary'] = Map<String, dynamic>.from(broken['summary'] as Map)
      ..['total'] = 1234.57;
      final session = sessionFor(
        (_) async => http.Response(jsonEncode(broken), 200),
      );
      await tester.pumpWidget(
        MaterialApp(home: ProfitDashboardPage(session: session)),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('Earnings could not be read'), findsOneWidget);
      expect(find.text('₱0.00'), findsNothing);
    },
  );

  testWidgets('expired earnings request clears token and returns to login', (
    tester,
  ) async {
    final tokens = MemoryTokens();
    final session = sessionFor(
      (_) async => http.Response('{}', 401),
      tokens: tokens,
    );
    await tester.pumpWidget(
      MaterialApp(home: ProfitDashboardPage(session: session)),
    );
    await tester.pumpAndSettle();
    expect(tokens.token, isNull);
    expect(find.text('Welcome back'), findsOneWidget);
  });

  testWidgets('earnings is reachable through existing Rider tools', (
    tester,
  ) async {
    final session = sessionFor(
      (_) async => http.Response(jsonEncode(earnings(empty: true)), 200),
    );
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder: (context) =>
              Scaffold(appBar: AppBar(actions: [riderTools(context, session)])),
        ),
      ),
    );
    await tester.tap(find.byTooltip('Rider tools'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Earnings'));
    await tester.pumpAndSettle();
    expect(find.text('Delivery earnings'), findsOneWidget);
  });

  testWidgets('older request cannot overwrite totals from a resumed refresh', (
    tester,
  ) async {
    final first = Completer<http.Response>();
    final firstRequested = Completer<void>();
    var calls = 0;
    final session = sessionFor((_) async {
      if (++calls == 1) {
        firstRequested.complete();
        return first.future;
      }
      return http.Response(jsonEncode(earnings(empty: true)), 200);
    });
    await tester.pumpWidget(
      MaterialApp(home: ProfitDashboardPage(session: session)),
    );
    await tester.pump();
    await firstRequested.future;
    await tester.runAsync(() async {
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await Future<void>.delayed(const Duration(milliseconds: 50));
    });
    await tester.pumpAndSettle();
    expect(calls, 2);
    first.complete(http.Response(jsonEncode(earnings()), 200));
    await tester.pumpAndSettle();
    expect(find.text('₱0.00'), findsNWidgets(3));
    expect(find.text('₱1234.57'), findsNothing);
  });
}
