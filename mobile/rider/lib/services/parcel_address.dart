String _text(dynamic value) => value is String ? value.trim() : '';

String _structuredAddress(Map<dynamic, dynamic> data, List<String> keys) => keys
    .map((key) => _text(data[key]))
    .where((part) => part.isNotEmpty)
    .join(', ');

String pickupAddress(Map<String, dynamic> parcel) {
  final collection = _text(parcel['collection_address']);
  if (collection.isNotEmpty) return collection;

  // Legacy seller addresses are safe collection fallbacks; buyer/destination
  // fields must never be used for pickup navigation.
  final requests = parcel['pickup_requests'];
  final sellers = <dynamic>[
    parcel['seller'],
    if (requests is List)
      for (final request in requests)
        if (request is Map) request['seller'],
  ];
  for (final seller in sellers) {
    if (seller is! Map) continue;
    final full = _text(seller['address']);
    if (full.isNotEmpty) return full;
    final structured = _structuredAddress(seller, [
      'house_number',
      'street_address',
      'barangay',
      'municipality',
      'province',
    ]);
    if (structured.isNotEmpty) return structured;
  }
  return '';
}

String deliveryAddress(Map<String, dynamic> parcel) {
  final destination = _text(parcel['delivery_address']);
  if (destination.isNotEmpty) return destination;
  return _structuredAddress(parcel, [
    'delivery_house_number',
    'delivery_street',
    'delivery_barangay',
    'delivery_municipality',
    'delivery_province',
  ]);
}
