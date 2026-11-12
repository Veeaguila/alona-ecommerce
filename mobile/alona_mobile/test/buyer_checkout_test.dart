import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/presentation/checkout/checkout_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/order_detail_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/order_list_screen.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';

http.Response reply(Object data, {int status = 200}) => http.Response(
  jsonEncode(data),
  status,
  headers: {'content-type': 'application/json'},
);
Map<String, dynamic> quote(double total) => {
  'subtotal': total - 50,
  'shipping_fee': 50,
  'shipping_fee_before_discount': 50,
  'discount': 0,
  'total': total,
  'expected_prices': {'1': 125},
};
const address = {
  'id': 1,
  'label': 'Home',
  'recipient_name': 'Synthetic Buyer',
  'recipient_phone': '09170000002',
  'address': '45 Kalayaan, Central, Quezon City, Metro Manila, NCR 1100',
  'municipality': 'Quezon City',
  'street_address': '45 Kalayaan',
  'is_default': true,
};
void main() {
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    ApiConfig.onServerChanged = null;
    ApiClient.onUnauthorized = null;
    await ApiConfig.setBaseUrl('http://localhost:8000');
    ApiClient.setBuyerToken('synthetic');
    ApiClient.setCurrentRole('buyer');
  });
  test(
    'Address DTO retains the canonical full snapshot and recipient phone',
    () {
      final parsed = AddressModel.fromJson(address);
      expect(parsed.fullAddress, address['address']);
      expect(parsed.phone, '09170000002');
      expect(parsed.city, 'Quezon City');
    },
  );
  test(
    'Checkout sends reviewed totals, prices, request reference and address',
    () async {
      final bodies = <Map<String, dynamic>>[];
      ApiClient.client = MockClient((request) async {
        bodies.add(jsonDecode(request.body));
        return reply(quote(300));
      });
      await BuyerService.previewCheckout(
        cartItemIds: [1],
        shippingAddress: 'Synthetic destination',
      );
      await BuyerService.placeOrder(
        cartItemIds: [1],
        shippingAddress: 'Synthetic destination',
        shippingMethod: 'standard',
        paymentMethod: 'cod',
        quote: quote(300),
        requestKey: 'same-reference',
      );
      expect(bodies[0]['shipping_address'], 'Synthetic destination');
      expect(bodies[1]['expected_total'], 300);
      expect(bodies[1]['expected_shipping_fee'], 50);
      expect(bodies[1]['expected_prices'], {'1': 125});
      expect(bodies[1]['request_key'], 'same-reference');
    },
  );
  testWidgets(
    'Original checkout form waits for a valid quote and never places after quote failure',
    (tester) async {
      var writes = 0;
      ApiClient.client = MockClient((request) async {
        if (request.url.path.endsWith('addresses')) {
          return reply({
            'addresses': [address],
          });
        }
        if (request.url.path.endsWith('preview')) {
          return reply({
            'message': 'Address is outside service coverage.',
          }, status: 422);
        }
        writes++;
        return reply({});
      });
      await tester.pumpWidget(
        const MaterialApp(home: CheckoutScreen(selectedCartItemIds: [1])),
      );
      await tester.pumpAndSettle();
      expect(find.text('Delivery Address'), findsOneWidget);
      final button = tester.widget<AlonaButton>(
        find.widgetWithText(AlonaButton, 'Place Order'),
      );
      expect(button.onPressed, isNull);
      expect(writes, 0);
    },
  );
  testWidgets(
    'Late quote responses cannot overwrite a newer shipping selection',
    (tester) async {
      final slow = Completer<http.Response>();
      var calls = 0;
      ApiClient.client = MockClient((request) async {
        if (request.url.path.endsWith('addresses')) {
          return reply({
            'addresses': [address],
          });
        }
        calls++;
        if (calls == 2) return slow.future;
        return reply(quote(calls == 1 ? 300 : 250));
      });
      await tester.pumpWidget(
        const MaterialApp(home: CheckoutScreen(selectedCartItemIds: [1])),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Express Delivery'));
      await tester.tap(find.text('Express Delivery'));
      await tester.pump();
      await tester.ensureVisible(find.text('Store Pickup'));
      await tester.tap(find.text('Store Pickup'));
      await tester.pumpAndSettle();
      slow.complete(reply(quote(900)));
      await tester.pumpAndSettle();
      final button = tester.widget<AlonaButton>(
        find.widgetWithText(AlonaButton, 'Place Order'),
      );
      expect(button.onPressed, isNotNull);
      expect(find.textContaining('900.00'), findsNothing);
      expect(find.textContaining('250.00'), findsWidgets);
    },
  );
  testWidgets(
    'Duplicate tap and uncertain HTTP result retain the same booking reference',
    (tester) async {
      final pending = Completer<http.Response>();
      final bodies = <Map<String, dynamic>>[];
      ApiClient.client = MockClient((request) async {
        if (request.url.path.endsWith('addresses')) {
          return reply({
            'addresses': [address],
          });
        }
        if (request.url.path.endsWith('preview')) return reply(quote(300));
        bodies.add(jsonDecode(request.body));
        if (bodies.length == 1) return pending.future;
        return reply({'message': 'Unavailable'}, status: 503);
      });
      await tester.pumpWidget(
        const MaterialApp(home: CheckoutScreen(selectedCartItemIds: [1])),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Place Order'));
      await tester.pump();
      await tester.tap(
        find.byWidgetPredicate(
          (widget) => widget is AlonaButton && widget.isLoading,
        ),
      );
      await tester.pump();
      expect(bodies.length, 1);
      pending.complete(reply({'message': 'Unavailable'}, status: 503));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Place Order'));
      await tester.pumpAndSettle();
      expect(bodies.length, 2);
      expect(bodies[0], bodies[1]);
    },
  );
  testWidgets(
    'Leaving checkout while addresses load cannot update a disposed screen',
    (tester) async {
      final pending = Completer<http.Response>();
      var calls = 0;
      ApiClient.client = MockClient((request) async {
        calls++;
        return pending.future;
      });
      await tester.pumpWidget(
        const MaterialApp(home: CheckoutScreen(selectedCartItemIds: [1])),
      );
      await tester.pump();
      await tester.pumpWidget(const MaterialApp(home: SizedBox()));
      pending.complete(
        reply({
          'addresses': [address],
        }),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(calls, 1);
    },
  );
  testWidgets(
    'Older orders remain reachable through the real server pagination',
    (tester) async {
      final pages = <String>[];
      ApiClient.client = MockClient((request) async {
        final page = request.url.queryParameters['page']!;
        pages.add(page);
        return reply({
          'orders': {
            'current_page': int.parse(page),
            'last_page': 2,
            'data': [
              {'id': int.parse(page), 'order_number': 'STORE-$page'},
            ],
          },
          'counts': {},
        });
      });
      await tester.pumpWidget(const MaterialApp(home: OrderListScreen()));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Next'),
        300,
        scrollable: find.byWidgetPredicate(
          (widget) =>
              widget is Scrollable &&
              widget.axisDirection == AxisDirection.down,
        ),
      );
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      expect(pages, ['1', '2']);
      expect(find.textContaining('STORE-2'), findsOneWidget);
    },
  );
  testWidgets(
    'Buyer detail reveals OTP only through the private parcel endpoint',
    (tester) async {
      final paths = <String>[];
      ApiClient.client = MockClient((request) async {
        paths.add(request.url.path);
        if (request.url.path.endsWith('delivery-otp')) {
          return reply({
            'data': {
              'otp': '123456',
              'expires_at': DateTime.now()
                  .add(const Duration(minutes: 10))
                  .toIso8601String(),
            },
          });
        }
        return reply({
          'order': {
            'id': 1,
            'status': 'out_for_delivery',
            'items': [
              {
                'id': 2,
                'delivery_parcel_id': 7,
                'delivery_parcel_status': 'OUT_FOR_DELIVERY',
              },
            ],
          },
          'status_histories': [
            {'status': 'packed', 'note': 'Packed by seller'},
          ],
        });
      });
      await tester.pumpWidget(
        const MaterialApp(home: OrderDetailScreen(orderId: 1)),
      );
      await tester.pumpAndSettle();
      expect(find.text('123456'), findsNothing);
      expect(paths, ['/api/buyer/orders/1']);
      await tester.tap(find.text('Show delivery OTP'));
      await tester.pumpAndSettle();
      expect(find.text('123456'), findsOneWidget);
      expect(paths.last, '/api/buyer/orders/1/parcels/7/delivery-otp');
      await tester.tap(find.text('Close'));
      await tester.pumpAndSettle();
      expect(find.text('123456'), findsNothing);
    },
  );
}
