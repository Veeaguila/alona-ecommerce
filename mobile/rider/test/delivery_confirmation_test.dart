import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rider_app/pages/delivery_dashboard_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  testWidgets(
    'photo lost after loading requires capture again and never shows delivery success',
    (tester) async {
      var posts = 0;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          if (request.method == 'GET') {
            return http.Response(
              '{"data":[{"id":52,"order_id":"ITEM-7","status":"OUT_FOR_DELIVERY","has_valid_photo_proof":true}]}',
              200,
            );
          }
          posts++;
          return http.Response('{"code":"valid_photo_required"}', 422);
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
      Future<void> deliver() async {
        await tester.scrollUntilVisible(
          find.text('Mark Delivered'),
          200,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        await tester.tap(find.text('Mark Delivered'));
        await tester.pumpAndSettle();
      }

      await deliver();
      await tester.enterText(find.byType(TextFormField), '123456');
      await tester.tap(find.text('Confirm delivery'));
      await tester.pumpAndSettle();
      expect(posts, 1);
      expect(find.text('Delivery confirmed'), findsNothing);
      expect(
        find.textContaining('Capture and upload the photo again'),
        findsOneWidget,
      );
      await deliver();
      expect(find.text('Capture Delivery Proof'), findsOneWidget);
      expect(posts, 1);
    },
  );
  for (final uploadFails in [false, true]) {
    testWidgets(
      uploadFails ? 'failed photo upload never confirms delivery' : 'missing photo must upload and revalidate before explicit delivery confirmation',
      (tester) async {
        final bytes = (await rootBundle.load(
          'assets/images/alona/alonalogo2.png',
        )).buffer.asUint8List();
        var uploaded = false, deliveries = 0, captures = 0;
        final session = RiderSession(
          tokens: MemoryTokens(),
          client: MockClient((request) async {
            if (request.url.path.endsWith('/partner/profile')) {
              return http.Response(jsonEncode({'data': readyProfile()}), 200);
            }
            if (request.method == 'GET') {
              return http.Response(
                jsonEncode({
                  'data': [
                    {
                      'id': 52,
                      'order_id': 'ITEM-7',
                      'status': 'OUT_FOR_DELIVERY',
                      'delivery_address': 'Order destination',
                      'has_delivery_proof': true, // A signature or stale row must not skip PHOTO capture.
                      'has_valid_photo_proof': uploaded,
                    },
                  ],
                }),
                200,
              );
            }
            if (request.url.path.endsWith('/upload-proof')) {
              if (uploadFails) {
                return http.Response('internal server details', 500);
              }
              uploaded = true;
              return http.Response('{"data":{"id":1}}', 201);
            }
            deliveries++;
            return http.Response(
              '{"data":{"status":"DELIVERED"},"message":"Delivery marked as delivered."}',
              200,
            );
          }),
        );
        await tester.pumpWidget(
          MaterialApp(
            home: DeliveryDashboardPage(
              session: session,
              capturePhoto: () async {
                captures++;
                return captures == 1
                    ? null
                    : XFile.fromData(
                        bytes,
                        path: 'proof.png',
                        name: 'proof.png',
                      );
              },
            ),
          ),
        );
        await tester.pumpAndSettle();
        await tester.scrollUntilVisible(
          find.text('Mark Delivered'),
          200,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        await tester.tap(find.text('Mark Delivered'));
        await tester.pumpAndSettle();
        expect(find.text('Capture Delivery Proof'), findsOneWidget);
        expect(deliveries, 0);
        await tester.ensureVisible(find.text('Capture Photo'));
        await tester.pumpAndSettle();
        await tester.runAsync(() async {
          final callback = tester
              .widget<ElevatedButton>(
                find.widgetWithText(ElevatedButton, 'Capture Photo'),
              )
              .onPressed!;
          final future = Function.apply(callback, const []);
          if (future is Future) await future;
        });
        await tester.pumpAndSettle();
        await tester.ensureVisible(find.text('Upload Proof'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Upload Proof'));
        await tester.pumpAndSettle();
        expect(deliveries, 0);
        if (uploadFails) {
          expect(find.text('Capture Delivery Proof'), findsOneWidget);
          expect(find.text('Confirm delivery'), findsNothing);
          expect(find.textContaining('internal server details'), findsNothing);
        } else {
          expect(uploaded, true);
          expect(find.text('Confirm delivery'), findsOneWidget);
          await tester.enterText(find.byType(TextFormField), '123456');
          await tester.tap(find.text('Confirm delivery'));
          await tester.pumpAndSettle();
          expect(deliveries, 1);
          expect(find.text('Delivery confirmed'), findsOneWidget);
          await tester.tap(find.text('Back to deliveries'));
          await tester.pumpAndSettle();
          expect(find.text('Mark Delivered'), findsNothing);
        }
        await tester.pump(const Duration(seconds: 3));
      },
    );
  }
}
