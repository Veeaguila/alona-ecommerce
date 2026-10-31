import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rider_app/pages/rider_cod_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens;

void main() {
  testWidgets(
    'actual collection and submitted remittance never auto-confirm cash or delivery',
    (tester) async {
      var collected = false, submitted = false;
      final posts = <String>[];
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          expect(request.headers['Authorization'], 'Bearer test-token');
          if (request.method == 'POST') {
            posts.add(request.url.path);
            final multipart = latin1.decode(request.bodyBytes);
            expect(multipart, contains('name="request_key"'));
            expect(multipart, contains('900.00'));
            expect(multipart, contains('name="file"'));
            expect(multipart, isNot(contains('received_amount')));
            if (request.url.path.endsWith('/collections/42')) {
              collected = true;
            } else if (request.url.path.endsWith('/remittances')) {
              submitted = true;
              expect(multipart, contains('name="sorting_center_id"'));
            } else {
              fail('Unexpected money/delivery write');
            }
            return http.Response('{"data":{"id":1}}', 201);
          }
          expect(request.url.path, '/api/rider/cod');
          return http.Response(
            jsonEncode({
              'data': {
                'balances': collected
                    ? [
                        {
                          'sorting_center_id': 1,
                          'name': 'Synthetic Center',
                          'outstanding': '900.00',
                          'pending': submitted ? '900.00' : '0.00',
                        },
                      ]
                    : [],
                'delivery_candidates': collected
                    ? []
                    : [
                        {
                          'id': 42,
                          'order_id': 'ITEM-7',
                          'status': 'OUT_FOR_DELIVERY',
                        },
                      ],
                'collections': collected
                    ? [
                        {
                          'id': 1,
                          'parcel': {'order_id': 'ITEM-7'},
                          'amount': '900.00',
                          'collected_at': '2026-10-20T12:00:00Z',
                        },
                      ]
                    : [],
                'remittances': submitted
                    ? [
                        {
                          'id': 1,
                          'status': 'submitted',
                          'submitted_amount': '900.00',
                          'received_amount': null,
                        },
                      ]
                    : [],
              },
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: RiderCodPage(
            session: session,
            chooseEvidence: () async => XFile.fromData(
              base64Decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2t8AAAAASUVORK5CYII=',
              ),
              name: 'cash.png',
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<int>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('ITEM-7').last);
      await tester.pumpAndSettle();
      expect(
        tester.widget<TextField>(find.byType(TextField)).controller!.text,
        isEmpty,
      );
      await tester.enterText(find.byType(TextField), '900.00');
      await tester.tap(find.text('Choose cash evidence'));
      await tester.pumpAndSettle();
      expect(posts, isEmpty);
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Record Buyer cash'));
      await tester.pumpAndSettle();
      expect(collected, true);
      expect(submitted, false);
      await tester.tap(find.text('Cash remittance'));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<int>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Synthetic Center').last);
      await tester.pumpAndSettle();
      expect(
        tester.widget<TextField>(find.byType(TextField)).controller!.text,
        isEmpty,
      );
      await tester.enterText(find.byType(TextField), '900.00');
      await tester.tap(find.text('Choose cash evidence'));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Submit cash for verification'));
      await tester.tap(find.text('Submit cash for verification'));
      await tester.pumpAndSettle();
      expect(submitted, true);
      expect(posts, [
        '/api/rider/cod/collections/42',
        '/api/rider/cod/remittances',
      ]);
      await tester.scrollUntilVisible(
        find.textContaining('Logistics received Not confirmed'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Logistics received Not confirmed'),
        findsOneWidget,
      );
      await tester.pumpWidget(const SizedBox.shrink());
    },
  );
}
