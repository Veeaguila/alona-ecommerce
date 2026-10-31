import 'package:flutter_test/flutter_test.dart';
import 'package:rider_app/services/parcel_address.dart';

void main() {
  test(
    'pickup uses collection location while delivery uses order destination',
    () {
      final parcel = <String, dynamic>{
        'collection_address': ' Seller warehouse ',
        'delivery_address': ' Order shipping destination ',
        'delivery_municipality': 'Legacy destination city',
      };
      expect(pickupAddress(parcel), 'Seller warehouse');
      expect(deliveryAddress(parcel), 'Order shipping destination');
    },
  );

  test(
    'missing pickup address never falls back to buyer or delivery fields',
    () {
      expect(
        pickupAddress({
          'collection_address': ' ',
          'delivery_address': 'Buyer home',
          'delivery_street': 'Buyer street',
          'buyer': {'address': 'Buyer profile address'},
          'pickup_requests': [
            {
              'seller': {'name': 'Seller'},
            },
          ],
        }),
        isEmpty,
      );
    },
  );

  test('legacy pickup can use its seller address', () {
    expect(
      pickupAddress({
        'pickup_requests': [
          {
            'seller': {'address': ' Seller shop '},
          },
        ],
      }),
      'Seller shop',
    );
    expect(
      pickupAddress({
        'seller': {
          'street_address': ' Seller street ',
          'municipality': 'Seller city',
        },
      }),
      'Seller street, Seller city',
    );
  });

  test(
    'legacy delivery uses destination fields without collection fallback',
    () {
      expect(
        deliveryAddress({
          'delivery_address': ' ',
          'delivery_house_number': '12',
          'delivery_street': ' Main Street ',
          'delivery_barangay': null,
          'delivery_municipality': 'City',
          'collection_address': 'Seller warehouse',
        }),
        '12, Main Street, City',
      );
      expect(
        deliveryAddress({'collection_address': 'Seller warehouse'}),
        isEmpty,
      );
    },
  );

  test(
    'missing or malformed legacy values do not create bogus map addresses',
    () {
      expect(
        pickupAddress({
          'pickup_requests': [
            null,
            'invalid',
            {'seller': null},
          ],
        }),
        isEmpty,
      );
      expect(
        deliveryAddress({'delivery_address': 42, 'delivery_street': null}),
        isEmpty,
      );
      expect(pickupAddress({}), isEmpty);
      expect(deliveryAddress({}), isEmpty);
    },
  );
}
