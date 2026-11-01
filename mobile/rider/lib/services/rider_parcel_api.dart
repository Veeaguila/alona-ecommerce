import 'dart:convert';

import 'api_config.dart';
import 'api_error.dart';
import 'rider_session.dart';

class RiderParcelApi {
  final RiderSession session;
  RiderParcelApi(this.session);

  Future<Map<String, dynamic>> lookup(String rawReference) async {
    final reference = rawReference.trim().toUpperCase();
    if (reference.length > 50 ||
        !RegExp(r'^ITEM-[1-9][0-9]*$').hasMatch(reference)) {
      throw const SessionFailure(
        'Scan a parcel label or enter its ITEM- reference.',
      );
    }
    final token = await session.requireReadyToken();
    final response = await session.get(
      Uri.parse('$deliveriesUrl/lookup')
          .replace(queryParameters: {'reference': reference}),
      headers: {'Authorization': 'Bearer $token'},
    );
    if (response.statusCode == 401) {
      await session.tokens.clear();
      throw const SessionFailure(
        'Your session has expired. Please sign in.',
        accessDenied: true,
      );
    }
    if (response.statusCode == 403) await session.requireReadyToken();
    if (response.statusCode != 200) {
      String? code;
      try {
        code = (jsonDecode(response.body) as Map)['code'] as String?;
      } catch (_) {
        /* No raw server output. */
      }
      throw SessionFailure(switch (code) {
        'parcel_completed' => 'This parcel is already completed or returned. Check Delivery History.',
        'parcel_not_ready' =>
          'This parcel is not ready for delivery. Contact your sorting center.',
        'parcel_not_found' => 'Parcel not found in your assignments. Check the label or contact your sorting center.',
        _ => apiErrorMessage(response),
      });
    }
    try {
      final parcel = Map<String, dynamic>.from(
        jsonDecode(response.body)['data'] as Map,
      );
      if (parcel['id'] is! int ||
          parcel['id'] <= 0 ||
          parcel['order_id'] != reference ||
          ![
            'AT_SORTING_CENTER',
            'OUT_FOR_DELIVERY',
          ].contains(parcel['status'])) {
        throw const FormatException();
      }
      return parcel;
    } catch (_) {
      throw const SessionFailure(
        'Parcel details could not be verified. Refresh and retry.',
      );
    }
  }
}
