import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/services/rider_session.dart';
import 'package:rider_app/widgets/rider_pickup_location.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

// Synthetic provider only: these tests never establish real GPS acceptance.
class SyntheticLocation extends GeolocatorPlatform {
  LocationPermission permission = LocationPermission.denied;
  bool grant = false;
  bool mocked = false;
  int reads = 0;
  @override
  Future<bool> isLocationServiceEnabled() async => true;
  @override
  Future<LocationPermission> checkPermission() async => permission;
  @override
  Future<LocationPermission> requestPermission() async =>
      grant ? LocationPermission.whileInUse : LocationPermission.denied;
  @override
  Future<Position> getCurrentPosition({
    LocationSettings? locationSettings,
  }) async {
    reads++;
    return Position(
      latitude: 14.6,
      longitude: 120.98,
      timestamp: DateTime.now().toUtc(),
      accuracy: 5,
      altitude: 0,
      altitudeAccuracy: 0,
      heading: 0,
      headingAccuracy: 0,
      speed: 0,
      speedAccuracy: 0,
      isMocked: mocked,
    );
  }
}

void main() {
  late GeolocatorPlatform original;
  setUp(() {
    original = GeolocatorPlatform.instance;
  });
  tearDown(() {
    GeolocatorPlatform.instance = original;
  });
  testWidgets(
    'no location is read before explicit consent; denied or mocked signals are not uploaded',
    (tester) async {
      final provider = SyntheticLocation();
      GeolocatorPlatform.instance = provider;
      final uploads = <String>[];
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          uploads.add(request.method);
          return http.Response(jsonEncode({'message': 'Saved'}), 200);
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: RiderPickupLocation(session: session)),
        ),
      );
      expect(provider.reads, 0);
      expect(uploads, isEmpty);
      await tester.tap(find.text('Share location for pickup offers'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(provider.reads, 0);
      await tester.tap(find.text('Share location for pickup offers'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Share my location'));
      await tester.pumpAndSettle();
      expect(find.textContaining('permission was not granted'), findsOneWidget);
      expect(uploads, isEmpty);
      provider.grant = true;
      provider.mocked = true;
      await tester.tap(find.text('Share location for pickup offers'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Share my location'));
      await tester.pumpAndSettle();
      expect(find.textContaining('real device location'), findsOneWidget);
      expect(uploads, isEmpty);
    },
  );
  testWidgets(
    'consented provider coordinates and timestamp are sent once, stop clears the shared point',
    (tester) async {
      final provider = SyntheticLocation()..grant = true;
      GeolocatorPlatform.instance = provider;
      final uploads = <http.Request>[];
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          if (request.url.path.endsWith('/partner/profile')) {
            return http.Response(jsonEncode({'data': readyProfile()}), 200);
          }
          uploads.add(request);
          return http.Response(
            jsonEncode({
              'message': request.method == 'DELETE'
                  ? 'Sharing stopped'
                  : 'Shared for 90 seconds',
            }),
            200,
          );
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: RiderPickupLocation(session: session)),
        ),
      );
      await tester.tap(find.text('Share location for pickup offers'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Share my location'));
      await tester.pumpAndSettle();
      expect(uploads.length, 1);
      final body = jsonDecode(uploads.single.body);
      expect(body['latitude'], 14.6);
      expect(body['accuracy'], 5);
      expect(body['permission_confirmed'], true);
      expect(find.text('Shared for 90 seconds'), findsOneWidget);
      expect(provider.reads, 1);
      await tester.tap(find.text('Stop sharing pickup location'));
      await tester.pumpAndSettle();
      expect(uploads.last.method, 'DELETE');
      expect(find.text('Sharing stopped'), findsOneWidget);
    },
  );
}
