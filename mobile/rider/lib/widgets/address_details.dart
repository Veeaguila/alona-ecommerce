import 'package:flutter/material.dart';

import '../services/parcel_address.dart';

class AddressDetails extends StatelessWidget {
  final Map<String, dynamic> parcel;
  final bool pickup;
  const AddressDetails({super.key, required this.parcel, this.pickup = false});

  String text(Object? value) => value is String ? value.trim() : '';

  @override
  Widget build(BuildContext context) {
    final value = parcel['address_details'];
    final details = value is Map ? value : const <String, dynamic>{};
    var name = text(details['name']);
    if (pickup && name.isEmpty && parcel['pickup_requests'] is List) {
      for (final request in parcel['pickup_requests'] as List) {
        if (request is Map && request['seller'] is Map) {
          name = text((request['seller'] as Map)['name']);
          if (name.isNotEmpty) break;
        }
      }
    }
    String field(String key, String legacy) => text(details[key]).isNotEmpty
        ? text(details[key])
        : pickup
        ? ''
        : text(parcel[legacy]);
    final house = field('house_number', 'delivery_house_number');
    final street = field('street', 'delivery_street');
    final rows = <String, String>{
      pickup ? 'Seller' : 'Buyer': name,
      'Contact number': text(details['contact_no']),
      'House / street': [house, street].where((s) => s.isNotEmpty).join(', '),
      'Barangay': field('barangay', 'delivery_barangay'),
      'City / municipality': field('city', 'delivery_municipality'),
      'Province': field('province', 'delivery_province'),
      'Postal code': text(details['postal_code']),
      'Complete address': text(details['complete_address']).isNotEmpty
          ? text(details['complete_address'])
          : pickup
          ? pickupAddress(parcel)
          : deliveryAddress(parcel),
      'Instructions': text(details['instructions']),
    };
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xffeaf4f4),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Address Details',
            style: TextStyle(
              fontWeight: FontWeight.w700,
              color: Color(0xff006f6f),
            ),
          ),
          const SizedBox(height: 8),
          for (final row in rows.entries)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 3),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    row.key,
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xff546767),
                    ),
                  ),
                  SelectableText(
                    row.value.isEmpty ? 'Not provided' : row.value,
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
