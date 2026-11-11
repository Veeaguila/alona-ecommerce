import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ApiConfig {
  static const String liveProductionUrl = 'https://shopalona.online';
  static const String googleServerClientId =
      '205454384304-bfdpb445o92v968jlgf3jesi4hvoc280.apps.googleusercontent.com';

  /// Automatically resolves the correct backend URL:
  /// - In Release mode (Production APK/Live): Always connects to https://shopalona.online
  /// - In Debug mode (Development): Connects to local dev server (10.0.2.2:8000 for Android, 127.0.0.1:8000 for Desktop/Web)
  static String get defaultBaseUrl {
    const fromEnv = String.fromEnvironment('API_BASE_URL');
    if (fromEnv.isNotEmpty) return fromEnv;

    if (kReleaseMode) {
      return liveProductionUrl;
    }

    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      return 'http://10.0.2.2:8000';
    }
    return 'http://127.0.0.1:8000';
  }

  static const String fallbackBaseUrl = liveProductionUrl;
  static String _baseUrl = fallbackBaseUrl;

  static String get baseUrl => _baseUrl;
  static Future<void> Function()? onServerChanged;

  static String _cleanBaseUrl(String url) {
    var cleaned = url.trim().replaceAll(RegExp(r'/+$'), '');
    if (cleaned.endsWith('/api')) {
      cleaned = cleaned.substring(0, cleaned.length - 4);
    }
    // On Android debug emulator, translate 127.0.0.1/localhost to 10.0.2.2
    if (!kReleaseMode && !kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      if (cleaned.contains('127.0.0.1:8000')) {
        cleaned = cleaned.replaceAll('127.0.0.1:8000', '10.0.2.2:8000');
      } else if (cleaned.contains('localhost:8000')) {
        cleaned = cleaned.replaceAll('localhost:8000', '10.0.2.2:8000');
      }
    }
    return cleaned;
  }

  static Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('custom_base_url');

    String target;
    if (kReleaseMode) {
      // In live release mode, default to official live production URL
      target = (saved != null && saved.trim().isNotEmpty && !saved.contains('127.0.0.1') && !saved.contains('10.0.2.2'))
          ? saved
          : liveProductionUrl;
    } else {
      // In debug mode, default to local development server unless custom specified
      target = (saved != null && saved.trim().isNotEmpty && saved != liveProductionUrl)
          ? saved
          : defaultBaseUrl;
    }

    _baseUrl = _cleanBaseUrl(target);
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
