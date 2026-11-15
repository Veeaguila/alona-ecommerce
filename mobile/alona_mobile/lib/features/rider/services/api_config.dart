import 'package:flutter/foundation.dart';
import 'package:alona_mobile/core/config/api_config.dart' as core_config;

const _configuredBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://shopalona.online/api',
);

String normalizeApiBaseUrl(String value, {bool requireHttps = false}) {
  var base = value.trim().replaceFirst(RegExp(r'/+$'), '');
  var uri = Uri.tryParse(base);
  if (uri == null ||
      !uri.hasAuthority ||
      uri.host.isEmpty ||
      !['http', 'https'].contains(uri.scheme) ||
      uri.userInfo.isNotEmpty ||
      uri.hasQuery ||
      uri.hasFragment) {
    throw const FormatException(
      'Set API_BASE_URL to a valid ALONA server URL.',
    );
  }
  if (!uri.path.endsWith('/api')) {
    base = '$base/api';
    uri = Uri.parse(base);
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

String get apiBaseUrl {
  const fromEnv = String.fromEnvironment('API_BASE_URL');
  final configured = fromEnv.isNotEmpty
      ? fromEnv
      : (core_config.ApiConfig.baseUrl.isNotEmpty
          ? '${core_config.ApiConfig.baseUrl}/api'
          : _configuredBaseUrl);
  return resolveApiBaseUrl(configured, web: kIsWeb, release: kReleaseMode);
}

String get apiUrl => '$apiBaseUrl/rider/register';
String get sortingCentersUrl => '$apiBaseUrl/sorting-centers';
String get loginApiUrl => '$apiBaseUrl/rider/login';
String get pickupsUrl => '$apiBaseUrl/rider/pickups';
String get pickupsAvailableUrl => '$pickupsUrl/available';
String get pickupsMineUrl => '$pickupsUrl/mine';
String get deliveriesUrl => '$apiBaseUrl/rider/deliveries';
String get smartScanUrl => '$apiBaseUrl/rider/smart-scan';
String get codUrl => '$apiBaseUrl/rider/cod';
String get earningsUrl => '$apiBaseUrl/rider/earnings';

String get logoutApiUrl => '$apiBaseUrl/logistics/logout';
String get partnerProfileUrl => '$apiBaseUrl/partner/profile';
