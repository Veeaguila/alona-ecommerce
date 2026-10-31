import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/job_earnings_page.dart';
import 'package:rider_app/services/rider_job_pay_api.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

Map<String, dynamic> fixture() => {
  'data': {
    'summary': {
      'currency': 'PHP',
      'earned': '65.00',
      'unpaid': '25.00',
      'processing': '0.00',
      'paid': '40.00',
    },
    'earnings': {
      'data': [
        {
          'id': 1,
          'reference': 'STORE-1',
          'leg': 'pickup',
          'amount': '25.00',
          'status': 'earned',
          'batch_id': null,
        },
      ],
      'current_page': 1,
      'last_page': 1,
    },
    'payouts': {
      'data': [
        {
          'id': 2,
          'amount': '40.00',
          'items_count': 1,
          'destination': {'label': 'Synthetic GCash'},
          'status': 'paid',
          'reference': 'SYNTHETIC-123',
          'acknowledged_at': null,
        },
      ],
      'current_page': 1,
      'last_page': 1,
    },
    'destinations': <Object>[],
  },
};
http.Response reply(Object value) => http.Response(
  jsonEncode(value),
  200,
  headers: {'content-type': 'application/json'},
);
RiderSession session(Future<http.Response> Function(http.Request) answer) =>
    RiderSession(
      tokens: MemoryTokens(),
      client: MockClient((request) async {
        if (request.url.path.endsWith('/partner/profile')) {
          return reply({'data': readyProfile()});
        }
        return answer(request);
      }),
    );

void main() {
  testWidgets(
    'entering Earnings refreshes its jobs and failed reads hide stale amounts',
    (tester) async {
      var reads = 0;
      final s = session(
        (_) async => ++reads == 1 ? reply(fixture()) : http.Response('{}', 500),
      );
      await tester.pumpWidget(
        MaterialApp(home: JobEarningsPage(session: s, active: false)),
      );
      await tester.pump();
      expect(reads, 0);
      await tester.pumpWidget(MaterialApp(home: JobEarningsPage(session: s)));
      await tester.pumpAndSettle();
      expect(reads, 1);
      expect(find.text('PHP 25.00'), findsOneWidget);
      await tester.tap(find.byTooltip('Refresh earnings'));
      await tester.pumpAndSettle();
      expect(find.text('PHP 25.00'), findsNothing);
      expect(find.text('Confirm received'), findsNothing);
    },
  );
  testWidgets(
    'new job earnings use server totals and keep historical Seller earnings separate',
    (tester) async {
      final paths = <String>[];
      final s = session((request) async {
        paths.add(request.url.path);
        return reply(fixture());
      });
      await tester.pumpWidget(MaterialApp(home: JobEarningsPage(session: s)));
      await tester.pumpAndSettle();
      expect(find.text('My Earnings'), findsOneWidget);
      expect(find.text('PHP 25.00'), findsOneWidget);
      expect(paths, ['/api/rider/job-pay']);
      await tester.scrollUntilVisible(
        find.text('Historical Seller-funded earnings'),
        300,
      );
      expect(find.text('Historical Seller-funded earnings'), findsOneWidget);
    },
  );
  testWidgets(
    'confirming receipt waits for actual confirmation and blocks a pending duplicate',
    (tester) async {
      final pending = Completer<http.Response>();
      var writes = 0;
      final s = session((request) async {
        if (request.method == 'POST') {
          writes++;
          return pending.future;
        }
        return reply(fixture());
      });
      await tester.pumpWidget(MaterialApp(home: JobEarningsPage(session: s)));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.text('Confirm received'), 200);
      await tester.tap(find.text('Confirm received'));
      await tester.pumpAndSettle();
      expect(writes, 0);
      await tester.tap(find.text('I received it'));
      await tester.pump();
      expect(writes, 1);
      final button = tester.widget<FilledButton>(
        find.widgetWithText(FilledButton, 'Confirm received'),
      );
      expect(button.onPressed, isNull);
      pending.complete(
        reply({
          'data': {'id': 2},
        }),
      );
      await tester.pumpAndSettle();
    },
  );
  testWidgets(
    'closing earnings during a slow read cannot update disposed state',
    (tester) async {
      final pending = Completer<http.Response>();
      await tester.pumpWidget(
        MaterialApp(
          home: JobEarningsPage(session: session((_) => pending.future)),
        ),
      );
      await tester.pump();
      await tester.pumpWidget(const MaterialApp(home: SizedBox()));
      pending.complete(reply(fixture()));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    },
  );
  test('malformed monetary amounts never become invented zero earnings', () {
    final input = Map<String, dynamic>.from(fixture()['data'] as Map);
    input['summary'] = Map<String, dynamic>.from(input['summary'] as Map)
      ..['unpaid'] = 25;
    expect(() => JobPayPage(input), throwsFormatException);
  });
}
