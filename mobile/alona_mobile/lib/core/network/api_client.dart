import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:alona_mobile/core/config/api_config.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;
  ApiException(this.message, {this.statusCode, this.errors});
  @override
  String toString() => message;
}

class ApiClient {
  static http.Client client = http.Client();
  static String? _buyerToken;
  static String? _logisticsToken;
  static String _currentRole = 'buyer';
  static Future<void> Function(String role, String token)? onUnauthorized;
  static void setBuyerToken(String? token) => _buyerToken = token;
  static void setLogisticsToken(String? token) => _logisticsToken = token;
  static void setCurrentRole(String role) => _currentRole = role;
  static String? get activeToken => _token(_currentRole);
  static bool get isAuthenticated => activeToken?.isNotEmpty ?? false;
  static String? _token(String role) =>
      role == 'logistics' ? _logisticsToken : _buyerToken;

  // Background Buyer requests must never use a Logistics token, or vice versa.
  static String _role(String endpoint) => endpoint.startsWith('api/buyer/')
      ? 'buyer'
      : endpoint.startsWith('api/sorting-center/') ||
            endpoint == 'api/logistics/logout'
      ? 'logistics'
      : _currentRole;
  static Map<String, String> _headers(String? token) => {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
  };

  static Future<http.Response> _send(
    String endpoint,
    String method, {
    dynamic body,
    Map<String, dynamic>? queryParameters,
  }) async {
    final role = _role(endpoint);
    final token = _token(role);
    final server = ApiConfig.baseUrl;
    try {
      var uri = Uri.parse(ApiConfig.url(endpoint));
      if (queryParameters != null) {
        uri = uri.replace(
          queryParameters: queryParameters.map(
            (k, v) => MapEntry(k, v.toString()),
          ),
        );
      }
      final request = http.Request(method, uri)
        ..headers.addAll(_headers(token));
      if (body != null) request.body = jsonEncode(body);
      final response = await client
          .send(request)
          .then(http.Response.fromStream)
          .timeout(const Duration(seconds: 25));
      await _check(response, role, token, server);
      return response;
    } on ApiException {
      rethrow;
    } on TimeoutException {
      throw ApiException(
        'The server took too long to respond. Refresh before retrying an action.',
      );
    } on http.ClientException {
      throw ApiException(
        'Unable to connect to the server. Check your connection.',
      );
    }
  }

  static Future<void> _check(
    http.Response response,
    String role,
    String? token,
    String server,
  ) async {
    if (server != ApiConfig.baseUrl || token != _token(role)) {
      throw ApiException('The session changed. Refresh to continue.');
    }
    if (response.statusCode >= 200 && response.statusCode < 300) return;
    if (response.statusCode == 401 && token != null) {
      await onUnauthorized?.call(role, token);
    }
    dynamic data;
    try {
      data = jsonDecode(response.body);
    } catch (_) {}
    throw ApiException(
      data is Map
          ? data['message']?.toString() ?? 'Request failed.'
          : 'Request failed (${response.statusCode}).',
      statusCode: response.statusCode,
      errors: data is Map && data['errors'] is Map
          ? Map<String, dynamic>.from(data['errors'])
          : null,
    );
  }

  static dynamic _json(http.Response response) {
    if (response.body.isEmpty) return true;
    try {
      return jsonDecode(response.body);
    } catch (_) {
      throw ApiException('The server returned an invalid response.');
    }
  }

  static Future<dynamic> get(
    String endpoint, {
    Map<String, dynamic>? queryParameters,
  }) async =>
      _json(await _send(endpoint, 'GET', queryParameters: queryParameters));
  static Future<dynamic> post(String endpoint, {dynamic body}) async =>
      _json(await _send(endpoint, 'POST', body: body));
  static Future<dynamic> put(String endpoint, {dynamic body}) async =>
      _json(await _send(endpoint, 'PUT', body: body));
  static Future<dynamic> patch(String endpoint, {dynamic body}) async =>
      _json(await _send(endpoint, 'PATCH', body: body));
  static Future<dynamic> delete(String endpoint, {dynamic body}) async =>
      _json(await _send(endpoint, 'DELETE', body: body));
  static Future<Uint8List> bytes(String endpoint) async =>
      (await _send(endpoint, 'GET')).bodyBytes;

  static Future<dynamic> multipart(
    String endpoint, {
    required String method,
    Map<String, String>? fields,
    List<http.MultipartFile>? files,
  }) async {
    final role = _role(endpoint),
        token = _token(role),
        server = ApiConfig.baseUrl;
    final request =
        http.MultipartRequest(method, Uri.parse(ApiConfig.url(endpoint)))
          ..headers.addAll(_headers(token)..remove('Content-Type'))
          ..fields.addAll(fields ?? {})
          ..files.addAll(files ?? []);
    try {
      final response = await client
          .send(request)
          .then(http.Response.fromStream)
          .timeout(const Duration(seconds: 45));
      await _check(response, role, token, server);
      return _json(response);
    } on TimeoutException {
      throw ApiException('Upload timed out. Refresh before retrying.');
    } on http.ClientException {
      throw ApiException('Unable to connect to the server.');
    }
  }
}
