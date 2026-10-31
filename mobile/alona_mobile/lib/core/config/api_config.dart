import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ApiConfig {
  // Configure the target explicitly; opening the app must not target production.
  static const String fallbackBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );
  static String _baseUrl = fallbackBaseUrl;

  static String get baseUrl => _baseUrl;
  static Future<void> Function()? onServerChanged;

  static String _cleanBaseUrl(String url) {
    var cleaned = url.trim().replaceAll(RegExp(r'/+$'), '');
    if (cleaned.endsWith('/api')) {
      cleaned = cleaned.substring(0, cleaned.length - 4);
    }
    return cleaned;
  }

  static Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('custom_base_url');
    _baseUrl = _cleanBaseUrl(
      fallbackBaseUrl.isNotEmpty ? fallbackBaseUrl : (saved ?? ''),
    );
  }

  static Future<void> setBaseUrl(String url) async {
    final cleaned = _cleanBaseUrl(url);
    validate(cleaned);
    if (cleaned != _baseUrl) await onServerChanged?.call();
    _baseUrl = cleaned;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('custom_base_url', _baseUrl);
  }

  static String url(String path) {
    validate(_baseUrl);
    if (path.contains('://') || path.startsWith('//') || path.contains('..')) {
      throw const FormatException(
        'Only API paths on the configured server are allowed.',
      );
    }
    final cleanPath = path.startsWith('/') ? path : '/$path';
    return '$_baseUrl$cleanPath';
  }

  static void validate(String value) {
    final uri = Uri.tryParse(value);
    if (uri == null ||
        !['http', 'https'].contains(uri.scheme) ||
        uri.host.isEmpty ||
        uri.userInfo.isNotEmpty ||
        uri.hasQuery ||
        uri.hasFragment ||
        (uri.path.isNotEmpty && uri.path != '/')) {
      throw const FormatException(
        'Configure a valid server URL in Account > Server API Configuration.',
      );
    }
    if (kReleaseMode && uri.scheme != 'https') {
      throw const FormatException('Release builds require HTTPS.');
    }
  }

  static String imageUrl(String? path) {
    if (path == null || path.trim().isEmpty) {
      return '';
    }
    final value = path.trim();
    if (value.startsWith('http://') || value.startsWith('https://')) {
      if (_baseUrl.contains('10.0.2.2') || !_baseUrl.contains('127.0.0.1')) {
        return value
            .replaceFirst('http://127.0.0.1:8000', _baseUrl)
            .replaceFirst('http://localhost:8000', _baseUrl);
      }
      return value;
    }
    if (value.startsWith('/storage/') || value.startsWith('storage/')) {
      final clean = value.startsWith('/') ? value : '/$value';
      return '$_baseUrl$clean';
    }
    if (value.startsWith('/images/') || value.startsWith('images/')) {
      final clean = value.startsWith('/') ? value : '/$value';
      return '$_baseUrl$clean';
    }
    final clean = value.replaceFirst(RegExp(r'^/+'), '');
    return '$_baseUrl/storage/$clean';
  }
}
