import 'dart:convert';
import 'api_config.dart';
import 'api_error.dart';
import 'rider_session.dart';

class RiderFinanceApi {
  final RiderSession session;
  RiderFinanceApi(this.session);

  /// Fetch COD collections, outstanding balances, and remittance logs
  Future<Map<String, dynamic>> getCodLedger() async {
    final token = await session.requireReadyToken();
    final response = await session.get(
      Uri.parse(codUrl),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 401) {
      await session.tokens.clear();
      throw const SessionFailure(
        'Your session has expired. Please sign in.',
        accessDenied: true,
      );
    }

    if (response.statusCode != 200) {
      throw SessionFailure(apiErrorMessage(response));
    }

    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map && decoded['data'] is Map) {
        return Map<String, dynamic>.from(decoded['data'] as Map);
      }
      return {};
    } catch (_) {
      throw const SessionFailure('Could not parse COD ledger. Refresh to retry.');
    }
  }

  /// Fetch Rider Earnings and Delivery Job Pay breakdown
  Future<Map<String, dynamic>> getEarnings() async {
    final token = await session.requireReadyToken();
    final response = await session.get(
      Uri.parse(earningsUrl),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 401) {
      await session.tokens.clear();
      throw const SessionFailure(
        'Your session has expired. Please sign in.',
        accessDenied: true,
      );
    }

    if (response.statusCode != 200) {
      throw SessionFailure(apiErrorMessage(response));
    }

    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }
      return {};
    } catch (_) {
      throw const SessionFailure('Could not parse earnings details.');
    }
  }
}
