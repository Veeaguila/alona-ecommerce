import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:rider_app/services/api_config.dart';

void main() {
  test('native builds require an explicit reachable URL; production requires HTTPS', () {
    expect(
      () => resolveApiBaseUrl('', web: false, release: false),
      throwsFormatException,
    );
    expect(
      resolveApiBaseUrl('', web: true, release: false),
      'http://localhost:8000/api',
    );
    expect(
      resolveApiBaseUrl('https://alona.example/api', web: false, release: true),
      'https://alona.example/api',
    );
    expect(
      () => resolveApiBaseUrl(
        'http://alona.example/api',
        web: false,
        release: true,
      ),
      throwsFormatException,
    );
  });
  test('normalizes ALONA base URL and rejects unsafe configuration', () {
    expect(
      normalizeApiBaseUrl(' https://alona.example/api/// '),
      'https://alona.example/api',
    );
    for (final value in [
      '',
      'alona.example/api',
      'ftp://alona.example/api',
      'https://user:password@alona.example/api',
      'https://alona.example/api?token=secret',
      'https://alona.example/api#fragment',
      'https://alona.example',
    ]) {
      expect(() => normalizeApiBaseUrl(value), throwsFormatException);
    }
    expect(
      () =>
          normalizeApiBaseUrl('http://localhost:8000/api', requireHttps: true),
      throwsFormatException,
    );
    expect(
      normalizeApiBaseUrl('https://alona.example/api', requireHttps: true),
      'https://alona.example/api',
    );
  });

  test('configured API paths preserve the rider contract', () {
    expect(loginApiUrl, '$apiBaseUrl/rider/login');
    expect(apiUrl, '$apiBaseUrl/rider/register');
    expect(pickupsAvailableUrl, '$apiBaseUrl/rider/pickups/available');
    expect(pickupsMineUrl, '$apiBaseUrl/rider/pickups/mine');
    expect(deliveriesUrl, '$apiBaseUrl/rider/deliveries');
  });

  test('HTTP client reaches a configured API base', () async {
    final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    addTearDown(() => server.close(force: true));
    server.listen((request) async {
      expect(request.uri.path, '/api/sorting-centers');
      expect(request.headers.value('Accept'), 'application/json');
      request.response.headers.contentType = ContentType.json;
      request.response.write('{"data":[]}');
      await request.response.close();
    });
    final base = normalizeApiBaseUrl('http://localhost:${server.port}/api/');
    final response = await http
        .get(
          Uri.parse('$base/sorting-centers'),
          headers: {'Accept': 'application/json'},
        )
        .timeout(const Duration(seconds: 5));
    expect(response.statusCode, 200);
    expect(response.body, '{"data":[]}');
  });
}
