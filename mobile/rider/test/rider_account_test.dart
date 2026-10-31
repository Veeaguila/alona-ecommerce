import 'dart:convert';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rider_app/pages/rider_account_page.dart';
import 'package:rider_app/pages/rider_address_edit_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  testWidgets(
    'actual profile fields and authenticated real photo upload refresh the account',
    (tester) async {
      final original = (await rootBundle.load(
        'assets/images/alona/alonalogo2.png',
      )).buffer.asUint8List();
      // Match the gallery picker's real 1200px resize rather than bypassing upload limits.
      final bytes = (await tester.runAsync(() async {
        final codec = await ui.instantiateImageCodec(
          original,
          targetWidth: 512,
        );
        try {
          final frame = await codec.getNextFrame();
          try {
            return (await frame.image.toByteData(
              format: ui.ImageByteFormat.png,
            ))!.buffer.asUint8List();
          } finally {
            frame.image.dispose();
          }
        } finally {
          codec.dispose();
        }
      }))!;
      var uploaded = false;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          expect(request.headers['Authorization'], 'Bearer test-token');
          if (request.method == 'POST') {
            expect(request.url.path, '/api/rider/profile-photo');
            expect(latin1.decode(request.bodyBytes), contains('name="photo"'));
            expect(
              latin1.decode(request.bodyBytes),
              contains('filename="avatar.png"'),
            );
            uploaded = true;
            return http.Response('{"message":"Updated"}', 200);
          }
          if (request.url.path.endsWith('/profile-photo')) {
            return http.Response.bytes(bytes, 200);
          }
          return http.Response(
            jsonEncode({
              'data': readyProfile({
                'name': 'Actual Rider',
                'email': 'actual@example.test',
                'rider_profile': {
                  'contact_no': '09170000001',
                  'birthday': '2000-01-02',
                  'address': 'Actual address',
                  'vehicle_type': 'bicycle',
                  'plate_number': null,
                  'photo_available': uploaded,
                },
              }),
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: RiderAccountPage(
            session: session,
            choosePhoto: () async =>
                XFile.fromData(bytes, path: 'avatar.png', name: 'avatar.png'),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Actual Rider'), findsOneWidget);
      expect(find.text('actual@example.test'), findsOneWidget);
      await tester.runAsync(() async {
        final callback = tester
            .widget<TextButton>(
              find.widgetWithText(TextButton, 'Change profile picture'),
            )
            .onPressed!;
        final result = Function.apply(callback, const []);
        if (result is Future) await result;
      });
      await tester.pumpAndSettle();
      expect(uploaded, true);
      expect(find.text('Profile picture updated.'), findsOneWidget);
      expect(find.byType(Image), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('2000-01-02'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('2000-01-02'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('bicycle'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Actual address'), findsOneWidget);
      expect(find.text('Not provided'), findsOneWidget);
    },
  );
  testWidgets(
    'empty photo cannot upload and permission denial remains actionable',
    (tester) async {
      var denied = false, posts = 0;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.method == 'POST') posts++;
          return http.Response(
            jsonEncode({
              'data': readyProfile({'name': 'Rider'}),
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: RiderAccountPage(
            session: session,
            choosePhoto: () async {
              if (denied) throw PlatformException(code: 'photo_access_denied');
              return XFile.fromData(
                Uint8List(0),
                path: 'empty.png',
                name: 'empty.png',
              );
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Change profile picture'));
      await tester.pumpAndSettle();
      expect(find.textContaining('photo is empty'), findsOneWidget);
      denied = true;
      await tester.tap(find.text('Change profile picture'));
      await tester.pumpAndSettle();
      expect(find.textContaining('device Settings'), findsOneWidget);
      expect(posts, 0);
    },
  );
  testWidgets(
    'canonical address editor submits allowed fields and preserves backend validation',
    (tester) async {
      Map? saved;
      var invalid = true;
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.method == 'PATCH') {
            saved = jsonDecode(request.body);
            return invalid
                ? http.Response(
                    '{"errors":{"address.street":["Check this street."]}}',
                    422,
                  )
                : http.Response('{"requires_review":true}', 200);
          }
          final data = switch (request.url.path.split('/').last) {
            'regions' => [
              {'code': 'r1', 'name': 'Region'},
            ],
            'cities' => [
              {'code': 'c1', 'name': 'City', 'province_code': ''},
            ],
            'barangays' => [
              {'code': 'b1', 'name': 'Barangay', 'city_code': 'c1'},
            ],
            _ => [],
          };
          return http.Response(jsonEncode({'data': data}), 200);
        }),
      );
      const initial = {
        'region_code': 'r1',
        'province_code': '',
        'city_code': 'c1',
        'barangay_code': 'b1',
        'street': 'Existing street',
        'house_number': '1',
        'postal_code': '1000',
      };
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => RiderAddressEditPage(
                      session: session,
                      initial: initial,
                    ),
                  ),
                ),
                child: const Text('Edit'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Edit'));
      await tester.pumpAndSettle();
      expect(find.textContaining('reapproval'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Save address for review'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Save address for review'));
      await tester.pumpAndSettle();
      expect(find.text('Check this street.'), findsOneWidget);
      expect(saved!.keys.toList(), ['address']);
      expect(saved!['address']['city_code'], 'c1');
      expect(saved!['address']['street'], 'Existing street');
      invalid = false;
      await tester.ensureVisible(find.text('Save address for review'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Save address for review'));
      await tester.pumpAndSettle();
      expect(find.text('Edit'), findsOneWidget);
    },
  );
}
