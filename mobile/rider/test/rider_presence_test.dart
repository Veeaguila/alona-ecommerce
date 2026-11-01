import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/services/rider_session.dart';
import 'package:rider_app/widgets/rider_presence_control.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  testWidgets(
    'explicit Online choice sends heartbeats, Offline stops them, missing signals remain Unknown',
    (tester) async {
      final actions = <String>[];
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          if (request.method == 'GET') {
            return http.Response(
              jsonEncode({
                'data': {'presence': 'Unknown', 'is_current_session': false},
              }),
              200,
            );
          }
          final action = jsonDecode(request.body)['action'] as String;
          actions.add(action);
          return http.Response(
            jsonEncode({
              'data': {
                'presence': action == 'go_offline' ? 'Offline' : 'Online',
              },
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: RiderPresenceControl(session: session)),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Availability: Unknown'), findsOneWidget);
      expect(actions, isEmpty);
      await tester.tap(find.text('Go Online'));
      await tester.pumpAndSettle();
      expect(find.text('Availability: Online'), findsOneWidget);
      await tester.pump(const Duration(seconds: 31));
      await tester.pumpAndSettle();
      expect(actions, ['go_online', 'heartbeat']);
      await tester.tap(find.text('Go Offline'));
      await tester.pumpAndSettle();
      expect(find.text('Availability: Offline'), findsOneWidget);
      await tester.pump(const Duration(seconds: 91));
      await tester.pumpAndSettle();
      expect(actions, ['go_online', 'heartbeat', 'go_offline']);
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pumpAndSettle();
    },
  );

  testWidgets(
    'two dashboard controls share one presence timer and never invent account approval',
    (tester) async {
      final actions = <String>[];
      var suspended = false;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/partner/profile')) {
            return http.Response(
              jsonEncode({
                'data': readyProfile({'is_active': !suspended}),
              }),
              200,
            );
          }
          if (request.method == 'GET') {
            return http.Response(
              jsonEncode({
                'data': {'presence': 'Unknown'},
              }),
              200,
            );
          }
          final action = jsonDecode(request.body)['action'] as String;
          actions.add(action);
          return http.Response(
            jsonEncode({
              'data': {
                'presence': action == 'go_offline' ? 'Offline' : 'Online',
              },
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Column(
              children: [
                RiderPresenceControl(session: session),
                RiderPresenceControl(session: session),
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Go Online').first);
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 31));
      await tester.pumpAndSettle();
      expect(actions.where((a) => a == 'heartbeat').length, 1);
      suspended = true;
      await tester.pump(const Duration(seconds: 31));
      await tester.pumpAndSettle();
      expect(actions.where((a) => a == 'heartbeat').length, 1);
      expect(find.textContaining('deactivated'), findsNWidgets(2));
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pumpAndSettle();
    },
  );
}
