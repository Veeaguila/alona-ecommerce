import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/features/rider/pages/rider_portal_page.dart';
import 'package:alona_mobile/features/rider/services/rider_session.dart';
import 'package:alona_mobile/features/rider/services/token_store.dart';
import 'package:alona_mobile/features/rider/theme/rider_theme.dart';

class _FixtureTokens extends TokenStore {
  @override
  Future<String?> read() async => 'synthetic-rider-token';
}

void main() {
  testWidgets('Rider routes keep their own theme when opened from Buyer', (
    tester,
  ) async {
    final client = MockClient((request) async {
      if (request.method != 'GET') {
        throw StateError('The theme fixture only supports reads.');
      }
      final Object data = request.url.path.endsWith('/partner/profile')
          ? {
              'role': 'rider',
              'status': 'approved',
              'approvalStatus': 'approved',
              'is_active': true,
              'verification_required': false,
              'verified': true,
            }
          : <Object>[];
      return http.Response(
        jsonEncode({'data': data}),
        200,
        headers: {'content-type': 'application/json'},
      );
    });
    addTearDown(client.close);
    final session = RiderSession(tokens: _FixtureTokens(), client: client);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.buyerTheme,
        home: RiderChatPage(session: session),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Messages'), findsOneWidget);
    expect(find.text('New message to'), findsOneWidget);
    expect(
      Theme.of(tester.element(find.byType(RiderChatPage))).colorScheme.primary,
      AppColors.primary,
    );
    expect(
      Theme.of(tester.element(find.byType(AppBar))).colorScheme.primary,
      RiderColors.teal,
    );
    expect(tester.takeException(), isNull);
  });
}
