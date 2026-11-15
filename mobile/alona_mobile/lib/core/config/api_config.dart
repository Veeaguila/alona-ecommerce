import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ApiConfig {
  static const String liveProductionUrl = 'https://shopalona.online';
  static const String googleServerClientId =
      '205454384304-bfdpb445o92v968jlgf3jesi4hvoc280.apps.googleusercontent.com';

  // Configure the target explicitly; opening the app must not target production in isolated contract tests.
  static const String fallbackBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );

  /// Default backend URL for mobile app runtime:
  /// Uses API_BASE_URL if passed via --dart-define, otherwise defaults to
  /// https://shopalona.online (which works on physical devices, emulators, and desktop),
  /// with automatic fallback to local dev servers in ApiClient when offline/local.
  static String get defaultBaseUrl =>
      fallbackBaseUrl.isNotEmpty ? fallbackBaseUrl : liveProductionUrl;

  static String _baseUrl = defaultBaseUrl;
  static bool _allowAutoFailover = false;

  static String get baseUrl => _baseUrl;
  static bool get allowAutoFailover => _allowAutoFailover;
  static Future<void> Function()? onServerChanged;

  /// Ordered list of candidate servers to try if the primary baseUrl cannot be reached.
  static List<String> get candidateBaseUrls {
    if (!_allowAutoFailover) {
      return [_baseUrl];
    }
    final raw = <String>[
      _baseUrl,
      liveProductionUrl,
      if (!kReleaseMode) ...[
        if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) ...[
          'http://10.0.2.2:8000',
          'http://127.0.0.1:8000',
        ] else ...[
          'http://127.0.0.1:8000',
          'http://localhost:8000',
        ],
      ],
    ];
    final unique = <String>[];
    for (final item in raw) {
      final cleaned = _cleanBaseUrl(item);
      if (cleaned.isNotEmpty && !unique.contains(cleaned)) {
        unique.add(cleaned);
      }
    }
    return unique;
  }

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

    String target;
    if (kReleaseMode) {
      target = (saved != null &&
              saved.trim().isNotEmpty &&
              saved.trim().startsWith('https://'))
          ? saved
          : liveProductionUrl;
    } else {
      target = (saved != null && saved.trim().isNotEmpty)
          ? saved
          : defaultBaseUrl;
    }

    _baseUrl = _cleanBaseUrl(target);
    _allowAutoFailover = true;
  }

  /// Updates the active baseUrl in memory when automatic failover finds a reachable server
  /// without clearing the user's authentication session.
  static void adoptReachableBaseUrl(String url) {
    final cleaned = _cleanBaseUrl(url);
    if (cleaned.isEmpty) return;
    try {
      validate(cleaned);
      _baseUrl = cleaned;
    } catch (_) {}
  }

  static Future<void> setBaseUrl(String url) async {
    final cleaned = _cleanBaseUrl(url);
    validate(cleaned);
    if (cleaned != _baseUrl) await onServerChanged?.call();
    _baseUrl = cleaned;
    _allowAutoFailover = false;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('custom_base_url', _baseUrl);
  }

  static String url(String path, {String? baseOverride}) {
    final targetBase = baseOverride != null ? _cleanBaseUrl(baseOverride) : _baseUrl;
    validate(targetBase);
    if (path.contains('://') || path.startsWith('//') || path.contains('..')) {
      throw const FormatException(
        'Only API paths on the configured server are allowed.',
      );
    }
    final cleanPath = path.startsWith('/') ? path : '/$path';
    return '$targetBase$cleanPath';
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
      final parsed = Uri.tryParse(value);
      if (parsed != null &&
          ['127.0.0.1', 'localhost', '10.0.2.2', 'shopalona.online']
              .contains(parsed.host) &&
          (parsed.path.startsWith('/storage/') ||
              parsed.path.startsWith('/images/'))) {
        final query = parsed.hasQuery ? '?${parsed.query}' : '';
        return '$_baseUrl${parsed.path}$query';
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
