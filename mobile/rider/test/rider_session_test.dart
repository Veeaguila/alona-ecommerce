import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/services/rider_session.dart';
import 'package:rider_app/services/token_store.dart';
import 'package:rider_app/pages/login_page.dart';
import 'package:rider_app/pages/pickup_dashboard_page.dart';
import 'package:rider_app/pages/delivery_dashboard_page.dart';

class MemoryTokens extends TokenStore {
  String? token = 'test-token';
  int clears = 0;
  @override
  Future<String?> read() async => token;
  @override
  Future<void> save(String value) async {
    token = value;
  }

  @override
  Future<void> clear() async {
    token = null;
    clears++;
  }
}

Map<String, dynamic> readyProfile([Map<String, dynamic> changes = const {}]) =>
    {
      'role': 'rider',
      'status': 'approved',
      'approvalStatus': 'approved',
      'is_active': true,
      'verification_required': true,
      'verified': true,
      ...changes,
    };

void main() {
  for (final code in [200, 401]) {
    test('logout $code clears credentials after server response', () async {
      final tokens = MemoryTokens();
      final session = RiderSession(
        tokens: tokens,
        client: MockClient((request) async {
          expect(request.method, 'POST');
          expect(request.url.path, '/api/logistics/logout');
          expect(request.headers['Authorization'], 'Bearer test-token');
          expect(request.headers['Accept'], 'application/json');
          expect(tokens.clears, 0);
          return http.Response('{"message":"Signed out."}', code);
        }),
      );
      await session.logout();
      expect(tokens.token, isNull);
      expect(tokens.clears, 1);
    });
  }
  for (final code in [403, 429, 500]) {
    test(
      'logout $code preserves token for retry and reports uncertainty',
      () async {
        final tokens = MemoryTokens();
        final session = RiderSession(
          tokens: tokens,
          client: MockClient(
            (_) async => http.Response('<html>private error</html>', code),
          ),
        );
        await expectLater(
          session.logout(),
          throwsA(
            isA<SessionFailure>().having(
              (e) => e.message,
              'message',
              contains('may still be active'),
            ),
          ),
        );
        expect(tokens.token, 'test-token');
        expect(tokens.clears, 0);
      },
    );
  }
  test('network and timeout failures never claim revocation', () async {
    for (final timeout in [false, true]) {
      final tokens = MemoryTokens();
      final session = RiderSession(
        tokens: tokens,
        timeout: const Duration(milliseconds: 5),
        client: MockClient((_) async {
          if (timeout) return Completer<http.Response>().future;
          throw http.ClientException('private server details');
        }),
      );
      await expectLater(
        session.logout(),
        throwsA(
          isA<SessionFailure>().having(
            (e) => e.message,
            'message',
            contains('may still be active'),
          ),
        ),
      );
      expect(tokens.token, 'test-token');
    }
  });
  test(
    'missing token logout clears local credentials without an HTTP call',
    () async {
      final tokens = MemoryTokens()..token = null;
      await RiderSession(
        tokens: tokens,
        client: MockClient((_) async {
          fail('No server request expected');
        }),
      ).logout();
      expect(tokens.clears, 1);
    },
  );

  test(
    'an unexpected 200 logout response does not claim token revocation',
    () async {
      for (final body in ['<html>login</html>', '{}']) {
        final tokens = MemoryTokens();
        final session = RiderSession(
          tokens: tokens,
          client: MockClient((_) async => http.Response(body, 200)),
        );
        await expectLater(session.logout(), throwsA(isA<SessionFailure>()));
        expect(tokens.token, 'test-token');
      }
    },
  );

  final cases = <String, Map<String, dynamic>>{
    'pending': {'approvalStatus': 'pending'},
    'pending user': {'status': 'pending'},
    'inactive': {'is_active': false},
    'suspended': {'status': 'suspended'},
    'wrong role': {'role': 'buyer'},
    'unverified': {'verified': false},
  };
  for (final entry in cases.entries) {
    test('${entry.key} profile blocks operations', () async {
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((request) async {
          expect(request.method, 'GET');
          expect(request.url.path, '/api/partner/profile');
          expect(request.headers['Authorization'], 'Bearer test-token');
          return http.Response(
            jsonEncode({'data': readyProfile(entry.value)}),
            200,
          );
        }),
      );
      await expectLater(
        session.requireReadyToken(),
        throwsA(
          isA<SessionFailure>().having((e) => e.accessDenied, 'denied', true),
        ),
      );
    });
    for (final pickup in [true, false]) {
      testWidgets(
        '${entry.key} cannot enter ${pickup ? 'pickup' : 'delivery'} dashboard',
        (tester) async {
          final session = RiderSession(
            tokens: MemoryTokens(),
            client: MockClient((request) async {
              expect(request.url.path, '/api/partner/profile');
              return http.Response(
                jsonEncode({'data': readyProfile(entry.value)}),
                200,
              );
            }),
          );
          await tester.pumpWidget(
            MaterialApp(
              home: pickup
                  ? PickupDashboardPage(session: session)
                  : DeliveryDashboardPage(session: session),
            ),
          );
          await tester.pumpAndSettle();
          expect(find.byType(LoginPage), findsOneWidget);
          expect(find.byType(PickupDashboardPage), findsNothing);
          expect(find.byType(DeliveryDashboardPage), findsNothing);
        },
      );
    }
  }
  test('verified new and unverified legacy riders pass readiness', () async {
    for (final profile in [
      readyProfile(),
      readyProfile({'verified': false, 'verification_required': false}),
    ]) {
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient(
          (_) async => http.Response(jsonEncode({'data': profile}), 200),
        ),
      );
      expect(await session.requireReadyToken(), 'test-token');
    }
  });
  test('profile 401 clears expired token', () async {
    final tokens = MemoryTokens();
    final session = RiderSession(
      tokens: tokens,
      client: MockClient((_) async => http.Response('{}', 401)),
    );
    await expectLater(
      session.requireReadyToken(),
      throwsA(isA<SessionFailure>()),
    );
    expect(tokens.token, isNull);
  });
  test('malformed/non-JSON profiles and network errors fail closed', () async {
    for (final body in [
      '<html>private details</html>',
      '{"data":{}}',
      '{"data":null}',
    ]) {
      final session = RiderSession(
        tokens: MemoryTokens(),
        client: MockClient((_) async => http.Response(body, 200)),
      );
      await expectLater(
        session.requireReadyToken(),
        throwsA(
          isA<SessionFailure>().having(
            (e) => e.message,
            'safe message',
            isNot(contains('private')),
          ),
        ),
      );
    }
    final session = RiderSession(
      tokens: MemoryTokens(),
      client: MockClient((_) async {
        throw http.ClientException('private connection details');
      }),
    );
    await expectLater(
      session.requireReadyToken(),
      throwsA(isA<SessionFailure>()),
    );
  });

  for (final pickup in [true, false]) {
    testWidgets(
      'failed logout can be retried on ${pickup ? 'pickup' : 'delivery'} dashboard',
      (tester) async {
        final tokens = MemoryTokens();
        var failLogout = true;
        final session = RiderSession(
          tokens: tokens,
          client: MockClient((request) async {
            if (request.url.path.endsWith('/partner/profile')) {
              throw http.ClientException('offline');
            }
            expect(request.url.path, '/api/logistics/logout');
            if (failLogout) throw http.ClientException('offline');
            return http.Response('{"message":"Signed out."}', 200);
          }),
        );
        await tester.pumpWidget(
          MaterialApp(
            home: pickup
                ? PickupDashboardPage(session: session)
                : DeliveryDashboardPage(session: session),
          ),
        );
        await tester.pumpAndSettle();
        await tester.tap(find.byIcon(Icons.logout));
        await tester.pumpAndSettle();
        expect(tokens.token, 'test-token');
        expect(find.textContaining('may still be active'), findsOneWidget);
        expect(find.byType(LoginPage), findsNothing);
        failLogout = false;
        await tester.tap(find.byIcon(Icons.logout));
        await tester.pumpAndSettle();
        expect(tokens.token, isNull);
        expect(find.byType(LoginPage), findsOneWidget);
      },
    );
  }

  testWidgets('resuming rechecks account status before operational requests', (
    tester,
  ) async {
    var suspended = false;
    var checks = 0;
    final session = RiderSession(
      tokens: MemoryTokens(),
      client: MockClient((request) async {
        checks++;
        expect(request.url.path, '/api/partner/profile');
        if (!suspended) throw http.ClientException('offline');
        return http.Response(
          jsonEncode({
            'data': readyProfile({'status': 'suspended'}),
          }),
          200,
        );
      }),
    );
    await tester.pumpWidget(
      MaterialApp(home: PickupDashboardPage(session: session)),
    );
    await tester.pumpAndSettle();
    suspended = true;
    await tester.runAsync(() async {
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await Future<void>.delayed(const Duration(milliseconds: 50));
    });
    await tester.pumpAndSettle();
    expect(checks, greaterThan(1));
    expect(find.byType(LoginPage), findsOneWidget);
  });
}
