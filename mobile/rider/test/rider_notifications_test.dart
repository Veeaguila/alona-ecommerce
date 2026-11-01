import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/rider_notifications_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

Map<String, dynamic> notice(String id, {bool read = false}) => {
  'id': id,
  'title': 'Account status updated $id',
  'body': 'Open My account',
  'created_at': '2026-10-16T01:00:00Z',
  'read_at': read ? '2026-10-16T01:00:00Z' : null,
  'data': {'screen': 'account'},
};

void main() {
  testWidgets(
    'mark read before opening own pending account and refresh on return',
    (tester) async {
      var read = false;
      final calls = <String>[];
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          calls.add('${request.method} ${request.url.path}');
          if (request.url.path.endsWith('/one/read')) {
            read = true;
            return http.Response('{"data":{}}', 200);
          }
          if (request.url.path.endsWith('/partner/profile')) {
            expect(read, true);
            return http.Response(
              jsonEncode({
                'data': readyProfile({
                  'name': 'Own rider',
                  'status': 'pending',
                  'approvalStatus': 'pending',
                }),
              }),
              200,
            );
          }
          return http.Response(
            jsonEncode({
              'data': [notice('one', read: read)],
              'unread_count': read ? 0 : 1,
              'current_page': 1,
              'last_page': 1,
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: RiderNotificationsPage(session: session)),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Account status updated one'));
      await tester.pumpAndSettle();
      expect(find.text('My account'), findsOneWidget);
      expect(
        calls
            .where((c) => c == 'POST /api/rider/notifications/one/read')
            .length,
        1,
      );
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('0 unread notifications'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'pagination deduplicates IDs and foreground polling refreshes server count',
    (tester) async {
      var gets = 0;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          gets++;
          final more = request.url.queryParameters['page'] == '2';
          return http.Response(
            jsonEncode({
              'data': more
                  ? [notice('one'), notice('two')]
                  : [notice('one'), notice('one')],
              'unread_count': 2,
              'current_page': more ? 2 : 1,
              'last_page': 2,
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: RiderNotificationsPage(session: session)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Account status updated one'), findsOneWidget);
      await tester.tap(find.text('Load older notifications'));
      await tester.pumpAndSettle();
      expect(find.text('Account status updated one'), findsOneWidget);
      expect(find.text('Account status updated two'), findsOneWidget);
      await tester.pump(const Duration(seconds: 30));
      await tester.pumpAndSettle();
      expect(gets, 3);
      expect(find.text('2 unread notifications'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'feed failures are visible and retryable without exposing server internals',
    (tester) async {
      var failed = true;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient(
          (_) async => failed
              ? http.Response('<html>SQL password traceback</html>', 500)
              : http.Response(
                  '{"data":[],"unread_count":0,"current_page":1,"last_page":1}',
                  200,
                ),
        ),
      );
      await tester.pumpWidget(
        MaterialApp(home: RiderNotificationsPage(session: session)),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('traceback'), findsNothing);
      expect(find.text('You are up to date.'), findsNothing);
      failed = false;
      await tester.tap(find.byTooltip('Refresh'));
      await tester.pumpAndSettle();
      expect(find.text('You are up to date.'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    },
  );
}
