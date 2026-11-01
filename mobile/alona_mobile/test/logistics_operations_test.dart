import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/logistics/presentation/cod/cod_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/returns/returns_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/operations/parcel_operations_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/logistics_main_screen.dart';

http.Response reply(Object data) => http.Response(
  jsonEncode(data),
  200,
  headers: {'content-type': 'application/json'},
);
void main() {
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    ApiConfig.onServerChanged = null;
    ApiClient.onUnauthorized = null;
    ApiClient.setCurrentRole('logistics');
    ApiClient.setLogisticsToken('fixture');
    await ApiConfig.setBaseUrl('http://localhost:8000');
  });
  testWidgets(
    'A return uses the current custodian and does not restock from mobile',
    (tester) async {
      Map<String, dynamic>? body;
      final paths = <String>[];
      ApiClient.client = MockClient((request) async {
        paths.add(request.url.path);
        if (request.method == 'POST') {
          body = jsonDecode(request.body);
          return reply({'message': 'Return initiated'});
        }
        return reply({
          'data': {
            'last_page': 1,
            'data': [
              {
                'id': 6,
                'status': 'eligible',
                'parcel': {
                  'order_id': 'ITEM-7',
                  'assigned_rider_id': 4,
                  'assigned_rider': {'name': 'Current custodian'},
                  'delivery_attempts': [],
                },
              },
            ],
          },
        });
      });
      await tester.pumpWidget(const MaterialApp(home: ReturnsScreen()));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Initiate physical return'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.enterText(
        find.byType(TextField),
        'The Buyer refused this parcel.',
      );
      await tester.pump();
      await tester.tap(find.text('Continue'));
      await tester.pump(const Duration(milliseconds: 450));
      await tester.pumpAndSettle();
      expect(body, {'rider_id': 4, 'reason': 'The Buyer refused this parcel.'});
      expect(paths.where((p) => p.contains('initiate')), [
        '/api/sorting-center/returns/6/initiate',
      ]);
      expect(
        paths.any((p) => p.contains('receive') || p.contains('restock')),
        isFalse,
      );
    },
  );
  testWidgets(
    'Supervised handover cannot confirm while either Rider acknowledgment is missing',
    (tester) async {
      var posts = 0;
      ApiClient.client = MockClient((request) async {
        if (request.method == 'POST') posts++;
        if (request.url.path.endsWith('/handovers')) {
          return reply({
            'data': [
              {
                'id': 3,
                'status': 'pending',
                'from_rider': {'name': 'Source'},
                'to_rider': {'name': 'Receiving'},
                'source_acknowledged_at': '2026-10-30T00:00:00Z',
                'target_acknowledged_at': null,
              },
            ],
          });
        }
        return reply({
          'data': {
            'parcel': {
              'id': 7,
              'order_id': 'ITEM-7',
              'status': 'OUT_FOR_DELIVERY',
            },
            'events': [],
            'incidents': [],
          },
        });
      });
      await tester.pumpWidget(
        const MaterialApp(home: ParcelOperationsScreen(parcelId: 7)),
      );
      await tester.pumpAndSettle();
      final button = tester.widget<FilledButton>(
        find.widgetWithText(FilledButton, 'Confirm supervised handover'),
      );
      expect(button.onPressed, isNull);
      expect(posts, 0);
    },
  );
  testWidgets(
    'COD displays submitted amount and performs no automatic confirmation or payout',
    (tester) async {
      var posts = 0;
      ApiClient.client = MockClient((request) async {
        if (request.method != 'GET') posts++;
        expect(request.url.path, '/api/sorting-center/cod');
        return reply({
          'data': {
            'balances': [],
            'records': [],
            'pending_remittances': [
              {
                'id': 1,
                'rider': {'name': 'Cash custodian'},
                'submitted_amount': '350.00',
              },
            ],
            'pagination': {'last_page': 1},
          },
        });
      });
      await tester.pumpWidget(const MaterialApp(home: CodScreen()));
      await tester.pumpAndSettle();
      expect(find.textContaining('350.00'), findsOneWidget);
      expect(posts, 0);
    },
  );
  testWidgets(
    'Mobile Operations at320px keeps actions reachable and preserves server role gate',
    (tester) async {
      tester.view.physicalSize = const Size(320, 850);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      ApiClient.client = MockClient((request) async {
        if (request.url.path.endsWith('/profile')) {
          return reply({
            'data': {
              'role': 'sorting_center',
              'status': 'approved',
              'approvalStatus': 'approved',
            },
          });
        }
        return reply({'data': {}});
      });
      final auth = AuthState();
      await auth.setLogisticsSession(
        'fixture',
        {'name': 'Company operator'},
        {'business_name': 'Verified Logistics', 'approval_status': 'approved'},
        accountStatus: 'approved',
      );
      await tester.pumpWidget(
        MaterialApp(home: LogisticsMainScreen(authState: auth)),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Operations'));
      await tester.pumpAndSettle();
      expect(find.text('Hub workspace'), findsOneWidget);
      expect(find.text('COD cash receipt'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
