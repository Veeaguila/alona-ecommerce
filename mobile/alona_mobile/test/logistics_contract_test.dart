import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/logistics/models/logistics_models.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';

http.Response reply(Object data, {int status = 200}) => http.Response(
  jsonEncode(data),
  status,
  headers: {'content-type': 'application/json'},
);

void main() {
  test(
    'A partial sorted city never replaces the complete Buyer address snapshot',
    () {
      final parcel = ParcelModel.fromJson({
        'id': 1,
        'order_id': 'ITEM-1',
        'delivery_address':
            '45 Kalayaan Avenue, Central, Quezon City, Metro Manila 1100',
        'delivery_municipality': 'Quezon City',
        'status': 'AT_SORTING_CENTER',
      });
      expect(
        parcel.fullDeliveryAddress,
        '45 Kalayaan Avenue, Central, Quezon City, Metro Manila 1100',
      );
    },
  );
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    ApiConfig.onServerChanged = null;
    ApiClient.onUnauthorized = null;
    ApiClient.setBuyerToken(null);
    ApiClient.setLogisticsToken(null);
    ApiClient.setCurrentRole('buyer');
    await ApiConfig.setBaseUrl('http://localhost:8000');
  });
  test(
    'No production fallback; reject malformed URLs and foreign API endpoints',
    () async {
      expect(ApiConfig.fallbackBaseUrl, '');
      for (final url in [
        'file:///tmp',
        'https://user:pass@example.invalid',
        'https://example.invalid?secret=x',
        'https://example.invalid/api/else',
      ]) {
        await expectLater(ApiConfig.setBaseUrl(url), throwsFormatException);
      }
      expect(
        () =>
            ApiConfig.url('https://other.invalid/api/sorting-center/dashboard'),
        throwsFormatException,
      );
    },
  );
  test(
    'Background requests keep their own role token when the module changes',
    () async {
      ApiClient.setBuyerToken('buyer-fixture');
      ApiClient.setLogisticsToken('logistics-fixture');
      ApiClient.setCurrentRole('logistics');
      final headers = <String, String?>{};
      ApiClient.client = MockClient((request) async {
        headers[request.url.path] = request.headers['authorization'];
        return reply({'data': []});
      });
      await ApiClient.get('api/buyer/orders');
      ApiClient.setCurrentRole('buyer');
      await ApiClient.get('api/sorting-center/dashboard');
      expect(headers['/api/buyer/orders'], 'Bearer buyer-fixture');
      expect(
        headers['/api/sorting-center/dashboard'],
        'Bearer logistics-fixture',
      );
    },
  );
  test('A delayed401 cannot sign out a newer Logistics login', () async {
    final pending = Completer<http.Response>();
    var signedOut = false;
    ApiClient.setLogisticsToken('old');
    ApiClient.onUnauthorized = (_, _) async {
      signedOut = true;
    };
    ApiClient.client = MockClient((_) => pending.future);
    final request = ApiClient.get('api/sorting-center/dashboard');
    await Future<void>.delayed(Duration.zero);
    ApiClient.setLogisticsToken('new');
    pending.complete(reply({'message': 'Expired'}, status: 401));
    await expectLater(request, throwsA(isA<ApiException>()));
    expect(signedOut, isFalse);
  });
  test(
    'Current Logistics401 clears only Logistics credentials and operator state',
    () async {
      final auth = AuthState();
      await auth.init();
      await auth.setBuyerSession('buyer-fixture', {'id': 1});
      await auth.setLogisticsSession(
        'logistics-fixture',
        {'id': 2},
        {'approval_status': 'approved'},
        operator: {'role': 'hub_staff'},
        accountStatus: 'approved',
      );
      ApiClient.client = MockClient(
        (_) async => reply({
          'message': 'Session expired after inactivity.',
        }, status: 401),
      );
      await expectLater(
        ApiClient.get('api/sorting-center/dashboard'),
        throwsA(isA<ApiException>()),
      );
      expect(auth.isLogisticsAuthenticated, isFalse);
      expect(auth.logisticsOperator, isNull);
      expect(auth.buyerToken, 'buyer-fixture');
    },
  );
  test('Switching servers clears both accounts before any request can target the new server', () async {
    final auth = AuthState();
    await auth.init();
    await auth.setBuyerSession('buyer-fixture', {'id': 1});
    await auth.setLogisticsSession('logistics-fixture', {'id': 2}, null);
    await ApiConfig.setBaseUrl('http://127.0.0.1:9000');
    expect(auth.buyerToken, isNull);
    expect(auth.logisticsToken, isNull);
    expect(ApiClient.activeToken, isNull);
  });
  test(
    'Monitoring consumes Laravel pagination; no page silently disappears',
    () async {
      final pages = <int>[];
      ApiClient.client = MockClient((request) async {
        final page = int.parse(request.url.queryParameters['page']!);
        pages.add(page);
        return reply({
          'data': {
            'current_page': page,
            'last_page': 2,
            'data': [
              {
                'id': page,
                'order_id': 'ITEM-$page',
                'status': 'AT_SORTING_CENTER',
                'assigned_rider': {'name': 'Assigned $page'},
              },
            ],
          },
        });
      });
      final parcels = await LogisticsService.getMonitoringParcels();
      expect(pages, [1, 2]);
      expect(parcels.map((p) => p.orderId), ['ITEM-1', 'ITEM-2']);
      expect(parcels.last.assignedRiderName, 'Assigned 2');
    },
  );
  test('Assignment uses the canonical sorted-unassigned queue', () async {
    ApiClient.client = MockClient((request) async {
      expect(request.url.path, '/api/sorting-center/parcels');
      expect(request.url.queryParameters['status'], 'sorted-unassigned');
      return reply({'data': []});
    });
    expect(await LogisticsService.getAssignableParcels(), isEmpty);
  });
  test(
    'Proof DTO uses private file_url; proof bytes carry scoped authorization',
    () async {
      ApiClient.setLogisticsToken('logistics-fixture');
      final model = DeliveryProofModel.fromJson({
        'id': 5,
        'file_url': '/api/sorting-center/proofs/5/file',
      });
      expect(model.filePath, '/api/sorting-center/proofs/5/file');
      ApiClient.client = MockClient((request) async {
        expect(request.url.path, '/api/sorting-center/proofs/5/file');
        expect(request.headers['authorization'], 'Bearer logistics-fixture');
        return http.Response.bytes([1, 2, 3], 200);
      });
      expect(await ApiClient.bytes('api/sorting-center/proofs/5/file'), [
        1,
        2,
        3,
      ]);
    },
  );
  test(
    'Malformed successful JSON stays an error, never a fake empty queue',
    () async {
      ApiClient.client = MockClient(
        (_) async => http.Response('<html>Unavailable</html>', 200),
      );
      await expectLater(
        LogisticsService.getAssignableParcels(),
        throwsA(isA<ApiException>()),
      );
    },
  );
  test(
    'Rejection and suspension preserve the operator-entered reason',
    () async {
      final requests = <Map<String, dynamic>>[];
      ApiClient.client = MockClient((request) async {
        requests.add(jsonDecode(request.body));
        return reply({'message': 'Saved'});
      });
      await LogisticsService.rejectRider(
        2,
        'The submitted license has expired.',
      );
      await LogisticsService.deactivateRider(
        2,
        'Account suspended pending review.',
      );
      expect(requests[0]['reason'], 'The submitted license has expired.');
      expect(requests[1]['reason'], 'Account suspended pending review.');
    },
  );
}
