import 'dart:async';
import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:alona_mobile/core/network/api_client.dart';

Future<void> testExecutable(FutureOr<void> Function() testMain) async {
  TestWidgetsFlutterBinding.ensureInitialized();
  GoogleFonts.config.allowRuntimeFetching = false;
  final secure = <String, String>{};
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
      .setMockMethodCallHandler(
        const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
        (call) async {
          final key = call.arguments['key'] as String;
          switch (call.method) {
            case 'read':
              return secure[key];
            case 'write':
              secure[key] = call.arguments['value'];
              return null;
            case 'delete':
              secure.remove(key);
              return null;
            default:
              throw StateError(
                'Unexpected secure-storage method ${call.method}',
              );
          }
        },
      );
  ApiClient.client = MockClient(
    (request) async => http.Response(
      jsonEncode({'data': []}),
      200,
      headers: {'content-type': 'application/json'},
    ),
  );
  await testMain();
}
