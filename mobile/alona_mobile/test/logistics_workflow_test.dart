import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/logistics/presentation/assignment/rider_assignment_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/incoming/incoming_parcels_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/hubs/hub_workspace_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/logistics_access_gate.dart';
import 'package:alona_mobile/features/logistics/presentation/outbound/outbound_parcels_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/pickups/pickup_requests_screen.dart';

http.Response reply(Object data, {int status = 200}) => http.Response(
  jsonEncode(data),
  status,
  headers: {'content-type': 'application/json'},
);
void main() {
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    ApiConfig.onServerChanged = null;
    ApiClient.onUnauthorized = null;
    ApiClient.setLogisticsToken('fixture');
    ApiClient.setCurrentRole('logistics');
    await ApiConfig.setBaseUrl('http://localhost:8000');
  });
  testWidgets(
    'An empty assignment queue never substitutes monitoring parcels',
    (tester) async {
      final paths = <String>[];
      ApiClient.client = MockClient((request) async {
        paths.add(request.url.path);
        return reply({'data': []});
      });
      await tester.pumpWidget(const MaterialApp(home: RiderAssignmentScreen()));
      await tester.pumpAndSettle();
      expect(find.text('No parcels awaiting assignment'), findsOneWidget);
      expect(paths, ['/api/sorting-center/parcels']);
    },
  );
  testWidgets(
    'No eligible Rider never falls back to the entire approved fleet',
    (tester) async {
      final paths = <String>[];
      ApiClient.client = MockClient((request) async {
        paths.add(request.url.path);
        return reply({
          'data': request.url.path.endsWith('eligible-riders')
              ? []
              : [
                  {
                    'id': 7,
                    'order_id': 'ITEM-7',
                    'status': 'AT_SORTING_CENTER',
                    'sorted_area_id': 3,
                  },
                ],
        });
      });
      await tester.pumpWidget(const MaterialApp(home: RiderAssignmentScreen()));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Assign Rider'));
      await tester.pumpAndSettle();
      expect(paths, [
        '/api/sorting-center/parcels',
        '/api/sorting-center/parcels/7/eligible-riders',
      ]);
      expect(find.textContaining('No eligible Rider'), findsOneWidget);
    },
  );
  testWidgets(
    'Incoming confirmation uses the looked-up reference, even if the input changes',
    (tester) async {
      String? arrived;
      ApiClient.client = MockClient((request) async {
        if (request.method == 'POST') {
          arrived = jsonDecode(request.body)['order_id'];
          return reply({'message': 'Arrived'});
        }
        return reply({
          'data': {'order_id': 'ITEM-7', 'status': 'PICKED_UP'},
        });
      });
      await tester.pumpWidget(const MaterialApp(home: IncomingParcelsScreen()));
      await tester.enterText(find.byType(TextField).first, 'ITEM-7');
      await tester.tap(find.text('Lookup'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).first, 'ITEM-8');
      await tester.ensureVisible(find.text('Confirm Parcel Arrived at Center'));
      await tester.tap(find.text('Confirm Parcel Arrived at Center'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.tap(find.text('I confirm'));
      await tester.pumpAndSettle();
      expect(arrived, 'ITEM-7');
    },
  );
  testWidgets(
    'Pickup acceptance blocks double taps while waiting for the server',
    (tester) async {
      final pending = Completer<http.Response>();
      var posts = 0;
      ApiClient.client = MockClient((request) async {
        if (request.method == 'POST') {
          posts++;
          return pending.future;
        }
        return reply({
          'data': {
            'last_page': 1,
            'data': [
              {
                'id': 4,
                'status': 'pending',
                'parcel': {'id': 7, 'order_id': 'ITEM-7', 'status': 'PLACED'},
              },
            ],
          },
        });
      });
      await tester.pumpWidget(const MaterialApp(home: PickupRequestsScreen()));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Accept pickup request'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.tap(find.text('I confirm'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.tap(find.text('Accept pickup request'));
      await tester.pump();
      expect(posts, 1);
      pending.complete(reply({'message': 'Accepted'}));
      await tester.pumpAndSettle();
    },
  );
  testWidgets(
    'Hub staff only receives the staff workspace; pending owners see no operational UI',
    (tester) async {
      Map<String, dynamic> profile = {
        'role': 'sorting_center',
        'status': 'approved',
        'approvalStatus': 'approved',
        'operator': {'role': 'hub_staff'},
      };
      ApiClient.client = MockClient((_) async => reply({'data': profile}));
      final auth = AuthState();
      Widget gate() => MaterialApp(
        home: LogisticsAccessGate(
          authState: auth,
          owner: const Text('Company operations'),
          staff: const Text('Staff workspace'),
        ),
      );
      await tester.pumpWidget(gate());
      await tester.pumpAndSettle();
      expect(find.text('Staff workspace'), findsOneWidget);
      expect(find.text('Company operations'), findsNothing);
      profile = {
        'role': 'sorting_center',
        'status': 'pending',
        'approvalStatus': 'pending',
      };
      await tester.pumpWidget(const SizedBox());
      await tester.pumpWidget(gate());
      await tester.pumpAndSettle();
      expect(find.text('Company operations'), findsNothing);
      expect(find.text('Check again'), findsOneWidget);
    },
  );
  testWidgets(
    'Hub dispatch uses server next action and requires physical confirmation plus transport',
    (tester) async {
      Map<String, dynamic>? action;
      ApiClient.client = MockClient((request) async {
        if (request.method == 'POST') {
          action = jsonDecode(request.body);
          return reply({'message': 'Recorded'});
        }
        if (request.url.path.endsWith('/lookup')) {
          return reply({
            'data': {
              'parcel': {'order_id': 'ITEM-7', 'status': 'AT_SORTING_CENTER'},
              'operating_hub': {'name': 'Origin'},
              'action': 'dispatch',
              'action_label': 'Dispatch to next hub',
              'location_label': 'Origin',
              'instruction': 'Confirm physical dispatch.',
            },
          });
        }
        if (request.url.path.endsWith('/queue')) {
          return reply({
            'data': {'data': []},
          });
        }
        return reply({
          'data': {
            'hubs': [
              {'id': 2, 'name': 'Origin'},
            ],
            'operating_hub': {'id': 2},
          },
        });
      });
      await tester.pumpWidget(const MaterialApp(home: HubWorkspaceScreen()));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).first, 'ITEM-7');
      await tester.tap(find.text('Look up parcel'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Dispatch to next hub'));
      await tester.pumpAndSettle();
      expect(action, isNull);
      expect(
        find.text('Enter the actual transport reference.'),
        findsOneWidget,
      );
      await tester.enterText(find.byType(TextField).last, 'Truck-actual-123');
      await tester.tap(find.text('Dispatch to next hub'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(action, isNull);
      await tester.tap(find.text('I confirm'));
      await tester.pumpAndSettle();
      expect(action?['reference'], 'ITEM-7');
      expect(action?['hub_id'], 2);
      expect(action?['physical_confirmation'], true);
      expect(action?['transport_reference'], 'Truck-actual-123');
      expect(action?['request_key'], matches(RegExp(r'^[0-9a-f-]{36}$')));
    },
  );
  testWidgets('Outbound remains unavailable until sorted and assigned', (
    tester,
  ) async {
    var posts = 0;
    ApiClient.client = MockClient((request) async {
      if (request.method == 'POST') posts++;
      return reply({
        'data': {'order_id': 'ITEM-7', 'status': 'AT_SORTING_CENTER'},
      });
    });
    await tester.pumpWidget(const MaterialApp(home: OutboundParcelsScreen()));
    await tester.enterText(find.byType(TextField), 'ITEM-7');
    await tester.tap(find.text('Look up parcel'));
    await tester.pumpAndSettle();
    final button = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Confirm outbound readiness'),
    );
    expect(button.onPressed, isNull);
    expect(posts, 0);
  });
}
