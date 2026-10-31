import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/services/rider_session.dart';
import 'package:rider_app/widgets/rider_handovers.dart';

import 'rider_session_test.dart' show MemoryTokens;

void main() {
  testWidgets(
    'physical handover requires explicit confirmation and never marks parcels delivered',
    (tester) async {
      var acknowledged = false;
      final posts = <String>[];
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          expect(request.headers['Authorization'], 'Bearer test-token');
          expect(request.url.path.contains('partner/profile'), false);
          if (request.method == 'POST') {
            posts.add(request.url.path);
            expect(jsonDecode(request.body), {'physical_handover': true});
            acknowledged = true;
            return http.Response(
              jsonEncode({
                'data': {
                  'id': 9,
                  'status': 'pending',
                  'from_rider_id': 1,
                  'to_rider_id': 2,
                  'parcel_ids': [42, 43],
                  'source_acknowledged_at': '2026-10-20T12:00:00Z',
                },
              }),
              200,
            );
          }
          return http.Response(
            jsonEncode({
              'meta': {'rider_id': 1},
              'data': [
                {
                  'id': 9,
                  'status': 'pending',
                  'from_rider_id': 1,
                  'to_rider_id': 2,
                  'from_rider': {'name': 'Suspended source'},
                  'to_rider': {'name': 'Approved receiver'},
                  'parcel': {'order_id': 'ITEM-7', 'status': 'PICKED_UP'},
                  'parcel_ids': [42, 43],
                  'reason': 'Approved whole-shipment physical handover.',
                  'source_acknowledged_at': acknowledged
                      ? '2026-10-20T12:00:00Z'
                      : null,
                  'target_acknowledged_at': null,
                },
              ],
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: RiderHandovers(session: session)),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('I acknowledge physical handover'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('physically handed all listed parcels to'),
        findsOneWidget,
      );
      expect(posts, isEmpty);
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(posts, isEmpty);
      await tester.tap(find.text('I acknowledge physical handover'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('I confirm the physical handover'));
      await tester.pumpAndSettle();
      expect(posts, ['/api/rider/handovers/9/acknowledge']);
      expect(
        find.text('Acknowledged by you. Await the other Rider and Logistics.'),
        findsOneWidget,
      );
      expect(find.text('I acknowledge physical handover'), findsNothing);
      await tester.pumpWidget(const SizedBox.shrink());
    },
  );
  Map<String, dynamic> record() => {
    'id': 9,
    'status': 'pending',
    'from_rider_id': 1,
    'to_rider_id': 2,
    'from_rider': {'name': 'Source'},
    'to_rider': {'name': 'Receiver'},
    'parcel': {'order_id': 'ITEM-7'},
    'parcel_ids': [42, 43],
    'reason': 'Private custody reason',
    'source_acknowledged_at': null,
  };
  Future<void> showHandovers(WidgetTester tester, RiderSession session) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: RiderHandovers(session: session)),
      ),
    );
    await tester.pumpAndSettle();
  }

  for (final invalid in [
    'missing parcels',
    'foreign participant',
    'malformed relation',
  ]) {
    testWidgets('invalid handover $invalid is rejected before rendering', (
      tester,
    ) async {
      final row = record();
      if (invalid == 'missing parcels') row.remove('parcel_ids');
      if (invalid == 'foreign participant') {
        row['from_rider_id'] = 7;
        row['to_rider_id'] = 8;
      }
      if (invalid == 'malformed relation') row['parcel'] = 'wrong entity';
      var posts = 0;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.method == 'POST') posts++;
          return http.Response(
            jsonEncode({
              'data': [row],
              'meta': {'rider_id': 1},
            }),
            200,
          );
        }),
      );
      await showHandovers(tester, session);
      expect(tester.takeException(), isNull);
      expect(
        find.textContaining('Could not load supervised handovers'),
        findsOneWidget,
      );
      expect(find.textContaining('Private custody reason'), findsNothing);
      expect(find.text('No supervised handovers recorded.'), findsNothing);
      expect(find.text('I acknowledge physical handover'), findsNothing);
      expect(posts, 0);
    });
  }
  testWidgets(
    'forbidden handover refresh clears private records without expiring login',
    (tester) async {
      var denied = false;
      final tokens = MemoryTokens();
      final session = RiderSession(
        tokens: tokens,
        client: MockClient((request) async {
          return denied
              ? http.Response('{"message":"Forbidden."}', 403)
              : http.Response(
                  jsonEncode({
                    'data': [record()],
                    'meta': {'rider_id': 1},
                  }),
                  200,
                );
        }),
      );
      await showHandovers(tester, session);
      expect(find.textContaining('Private custody reason'), findsOneWidget);
      denied = true;
      await tester.tap(find.text('Refresh handovers'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Private custody reason'), findsNothing);
      expect(find.text('I acknowledge physical handover'), findsNothing);
      expect(find.text('No supervised handovers recorded.'), findsNothing);
      expect(tokens.token, 'test-token');
      expect(tokens.clears, 0);
    },
  );
  for (final mismatch in [
    'identifier',
    'duplicate parcels',
    'missing timestamp',
  ]) {
    testWidgets(
      'mismatched $mismatch acknowledgment cannot claim success or replay the mutation',
      (tester) async {
        var posts = 0, gets = 0;
        final session = RiderSession(
          tokens: MemoryTokens(),
          client: MockClient((request) async {
            if (request.method == 'POST') {
              posts++;
              return http.Response(
                jsonEncode({
                  'data': {
                    ...record(),
                    'id': mismatch == 'identifier' ? 900 : 9,
                    'parcel_ids': mismatch == 'duplicate parcels'
                        ? [42, 42]
                        : [42, 43],
                    'source_acknowledged_at': mismatch == 'missing timestamp'
                        ? null
                        : '2026-10-20T12:00:00Z',
                  },
                }),
                200,
              );
            }
            gets++;
            return http.Response(
              jsonEncode({
                'data': [record()],
                'meta': {'rider_id': 1},
              }),
              200,
            );
          }),
        );
        await showHandovers(tester, session);
        await tester.tap(find.text('I acknowledge physical handover'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('I confirm the physical handover'));
        await tester.pumpAndSettle();
        expect(
          find.textContaining('Acknowledgment response was not confirmed'),
          findsOneWidget,
        );
        expect(
          find.text(
            'Physical handover acknowledged. Await Logistics confirmation.',
          ),
          findsNothing,
        );
        expect(find.text('I acknowledge physical handover'), findsNothing);
        expect(posts, 1);
        expect(gets, 1);
      },
    );
  }
}
