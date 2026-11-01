import 'package:flutter/foundation.dart';

const _configuredBaseUrl = String.fromEnvironment('API_BASE_URL');

String normalizeApiBaseUrl(String value, {bool requireHttps = false}) {
  final base = value.trim().replaceFirst(RegExp(r'/+$'), '');
  final uri = Uri.tryParse(base);
  if (uri == null ||
      !uri.hasAuthority ||
      uri.host.isEmpty ||
      !['http', 'https'].contains(uri.scheme) ||
      uri.userInfo.isNotEmpty ||
      uri.hasQuery ||
      uri.hasFragment ||
      !uri.path.endsWith('/api')) {
    throw const FormatException(
      'Set API_BASE_URL to the ALONA server URL ending in /api.',
    );
  }
  if (requireHttps && uri.scheme != 'https') {
    throw const FormatException(
      'Release builds require an HTTPS ALONA API URL.',
    );
  }
  return base;
}

String resolveApiBaseUrl(
  String configured, {
  required bool web,
  required bool release,
}) {
  final value = configured.trim().isEmpty && web && !release
      ? 'http://localhost:8000/api'
      : configured;
  return normalizeApiBaseUrl(value, requireHttps: release);
}

String get apiBaseUrl =>
    resolveApiBaseUrl(_configuredBaseUrl, web: kIsWeb, release: kReleaseMode);

String get apiUrl => '$apiBaseUrl/rider/register';
String get sortingCentersUrl => '$apiBaseUrl/sorting-centers';
String get loginApiUrl => '$apiBaseUrl/rider/login';
String get pickupsUrl => '$apiBaseUrl/rider/pickups';
String get pickupsAvailableUrl => '$pickupsUrl/available';
String get pickupsMineUrl => '$pickupsUrl/mine';
String get deliveriesUrl => '$apiBaseUrl/rider/deliveries';

String get logoutApiUrl => '$apiBaseUrl/logistics/logout';
String get partnerProfileUrl => '$apiBaseUrl/partner/profile';
