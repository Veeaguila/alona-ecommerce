import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/pages/delivery_dashboard_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  testWidgets(
    'start and complete use assigned parcel ID and server proof state',
    (tester) async {
      tester.view.physicalSize = const Size(1000, 1600);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var status = 'AT_SORTING_CENTER', complete = false;
      final calls = <String>[];
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          calls.add('${request.method} ${request.url.path}');
          final p = request.url.path;
          if (p.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          if (request.method == 'GET') {
            return http.Response(
              jsonEncode({
                'data': [
                  {
                    'id': 52,
                    'order_id': 'ITEM-7',
                    'status': status,
                    'delivery_address': 'Order shipping address',
                    'has_delivery_proof': true,
                    'has_valid_photo_proof': true,
                  },
                ],
              }),
              200,
            );
          }
          if (p.endsWith('/52/start')) {
            status = 'OUT_FOR_DELIVERY';
            return http.Response(
              '{"data":{"status":"OUT_FOR_DELIVERY"},"message":"Delivery started."}',
              200,
            );
          }
          if (p.endsWith('/52/deliver')) {
            complete = true;
            return http.Response(
              '{"data":{"status":"DELIVERED"},"message":"Delivery marked as delivered."}',
              200,
            );
          }
          throw StateError('Unexpected request');
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: DeliveryDashboardPage(session: session)),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('Order shipping address'), findsOneWidget);
      await tester.ensureVisible(find.text('Start Delivery'));
      await tester.tap(find.text('Start Delivery'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Mark Delivered'));
      await tester.tap(find.text('Mark Delivered'));
      await tester.pumpAndSettle();
      expect(complete, false);
      await tester.enterText(find.byType(TextFormField), '123456');
      await tester.tap(find.text('Confirm delivery'));
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 3));
      expect(complete, true);
      expect(
        calls,
        containsAll([
          'POST /api/rider/deliveries/52/start',
          'POST /api/rider/deliveries/52/deliver',
        ]),
      );
    },
  );
  testWidgets(
    'missing photo opens capture and cancellation preserves failure action',
    (tester) async {
      tester.view.physicalSize = const Size(1000, 1600);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      String? reason;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          if (request.method == 'GET') {
            return http.Response(
              '{"data":[{"id":52,"order_id":"ITEM-7","status":"OUT_FOR_DELIVERY","has_delivery_proof":false}]}',
              200,
            );
          }
          expect(request.url.path, '/api/rider/deliveries/52/fail');
          reason = jsonDecode(request.body)['reason'];
          return http.Response(
            '{"message":"Delivery marked as failed and returned."}',
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: DeliveryDashboardPage(
            session: session,
            capturePhoto: () async => null,
          ),
        ),
      );
      await tester.pumpAndSettle();
      final delivered = tester.widget<FilledButton>(
        find.widgetWithText(FilledButton, 'Mark Delivered'),
      );
      expect(delivered.onPressed, isNotNull);
      await tester.ensureVisible(find.text('Mark Delivered'));
      await tester.tap(find.text('Mark Delivered'));
      await tester.pumpAndSettle();
      expect(find.text('Capture Delivery Proof'), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Mark Failed'));
      await tester.tap(find.text('Mark Failed'));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Buyer not home').last);
      await tester.pumpAndSettle();
      await tester.pump();
      await tester.tap(find.text('Confirm Failed'));
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 3));
      expect(reason, 'Buyer not home');
    },
  );
  test('proof bytes use existing multipart file/type/notes contract without filesystem paths', () async {
    final session = RiderSession(
      tokens: MemoryTokens(),
      client: MockClient((request) async {
        expect(request.url.path, '/api/rider/deliveries/52/upload-proof');
        expect(request.headers['Authorization'], 'Bearer test-token');
        expect(request.body, contains('name="file"; filename="proof.png"'));
        expect(request.body, contains('name="type"'));
        expect(request.body, contains('name="notes"'));
        return http.Response('{"data":{"id":5}}', 201);
      }),
    );
    final request =
        http.MultipartRequest(
            'POST',
            Uri.parse(
              'http://localhost:8000/api/rider/deliveries/52/upload-proof',
            ),
          )
          ..headers['Authorization'] = 'Bearer test-token'
          ..fields['type'] = 'PHOTO'
          ..fields['notes'] = 'Received'
          ..files.add(
            http.MultipartFile.fromBytes(
              'file',
              Uint8List.fromList([1, 2, 3]),
              filename: 'proof.png',
            ),
          );
    expect((await session.sendMultipart(request)).statusCode, 201);
  });
}
