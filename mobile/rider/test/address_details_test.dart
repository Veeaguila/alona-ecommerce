import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rider_app/widgets/address_details.dart';

void main() {
  testWidgets(
    'pickup uses seller collection snapshot and never buyer destination',
    (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: AddressDetails(
                pickup: true,
                parcel: {
                  'collection_address': 'Seller collection location',
                  'delivery_address': 'Buyer destination',
                  'delivery_province': 'Buyer province',
                  'pickup_requests': [
                    {
                      'seller': {'name': 'Actual seller'},
                    },
                  ],
                },
              ),
            ),
          ),
        ),
      );
      expect(find.text('Actual seller'), findsOneWidget);
      expect(find.text('Seller collection location'), findsOneWidget);
      expect(find.text('Buyer destination'), findsNothing);
      expect(find.text('Buyer province'), findsNothing);
      expect(find.text('Not provided'), findsWidgets);
      expect(find.text('Open in Maps'), findsNothing);
    },
  );
  testWidgets(
    'delivery uses backend details and does not substitute collection address',
    (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: AddressDetails(
                parcel: {
                  'collection_address': 'Seller collection',
                  'delivery_address': 'Order destination',
                  'address_details': {
                    'name': 'Actual buyer',
                    'contact_no': '09170000000',
                    'house_number': '4',
                    'street': 'Order street',
                    'barangay': 'Order barangay',
                    'city': 'Order city',
                    'province': 'Order province',
                    'complete_address': 'Order destination',
                  },
                },
              ),
            ),
          ),
        ),
      );
      expect(find.text('Actual buyer'), findsOneWidget);
      expect(find.text('09170000000'), findsOneWidget);
      expect(find.text('4, Order street'), findsOneWidget);
      expect(find.text('Order destination'), findsOneWidget);
      expect(find.text('Seller collection'), findsNothing);
      expect(find.text('Not provided'), findsNWidgets(2));
    },
  );
}
