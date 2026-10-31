import 'dart:convert';

import '../services/api_config.dart';
import '../services/api_error.dart';
import '../services/rider_session.dart';

class RiderPortalApi {
  final RiderSession session;
  RiderPortalApi(this.session);
  Future<Map<String, dynamic>> request(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? body,
    bool ready = true,
  }) async {
    final token = ready
        ? await session.requireReadyToken()
        : await session.tokens.read();
    if (token == null || token.isEmpty) {
      throw const SessionFailure(
        'Please sign in to view your account.',
        accessDenied: true,
      );
    }
    final response = await session.send(
      method,
      Uri.parse('$apiBaseUrl/$path'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: body == null ? null : jsonEncode(body),
    );
    if (response.statusCode == 401) {
      await session.tokens.clear();
      throw const SessionFailure(
        'Your session has expired. Please sign in.',
        accessDenied: true,
      );
    }
    if (response.statusCode == 403 && ready) await session.requireReadyToken();
    if (response.statusCode != 200 && response.statusCode != 201) {
      throw SessionFailure(apiErrorMessage(response));
    }
    try {
      return Map<String, dynamic>.from(jsonDecode(response.body) as Map);
    } catch (_) {
      throw const SessionFailure(
        'The service returned an unexpected response. Please refresh.',
      );
    }
  }
}
