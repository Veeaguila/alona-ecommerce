import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_otp_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/cart/cart_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_chat_detail_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/order_list_screen.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';

http.Response response(Object data, {int status = 200}) => http.Response(
  jsonEncode(data),
  status,
  headers: {'content-type': 'application/json'},
);
Map<String, dynamic> orders(String number, {String status = 'pending'}) => {
  'orders': {
    'current_page': 1,
    'last_page': 1,
    'data': [
      {'id': 1, 'order_number': number, 'status': status},
    ],
  },
  'counts': <String, dynamic>{},
};
Map<String, dynamic> cart(int quantity) => {
  'items': [
    {
      'id': 1,
      'product_id': 1,
      'product_name': 'Synthetic item',
      'quantity': quantity,
      'stock': 10,
      'unit_price': 125,
      'is_in_stock': true,
    },
  ],
};
Map<String, dynamic> conversation([List<Object> messages = const []]) => {
  'conversation': {'id': 1, 'buyer_id': 5, 'seller_id': 4},
  'messages': messages,
};
const chat = BuyerChatDetailScreen(conversationId: 1, title: 'Synthetic store');

class PendingSession extends AuthState {
  final pending = Completer<void>();
  int saves = 0;
  @override
  Future<void> setBuyerSession(String token, Map<String, dynamic> user) async {
    saves++;
    await pending.future;
  }
}

void main() {
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    ApiConfig.onServerChanged = null;
    ApiClient.onUnauthorized = null;
    await ApiConfig.setBaseUrl('http://localhost:8000');
    ApiClient.setBuyerToken('synthetic');
    ApiClient.setCurrentRole('buyer');
  });

  testWidgets('Older order response cannot replace a newer filter', (
    tester,
  ) async {
    final old = Completer<http.Response>();
    ApiClient.client = MockClient(
      (request) async => request.url.queryParameters['status'] == 'all'
          ? old.future
          : response(orders('NEW-FILTER')),
    );
    await tester.pumpWidget(const MaterialApp(home: OrderListScreen()));
    await tester.pump();
    await tester.tap(find.text('To Pay'));
    await tester.pumpAndSettle();
    old.complete(response(orders('STALE-ALL')));
    await tester.pumpAndSettle();
    expect(find.textContaining('NEW-FILTER'), findsOneWidget);
    expect(find.textContaining('STALE-ALL'), findsNothing);
  });

  testWidgets('Leaving cart during its load is safe', (tester) async {
    final pending = Completer<http.Response>();
    ApiClient.client = MockClient((_) => pending.future);
    await tester.pumpWidget(const MaterialApp(home: CartScreen()));
    await tester.pump();
    await tester.pumpWidget(const MaterialApp(home: SizedBox()));
    pending.complete(response(cart(1)));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Pending quantity write blocks duplicates and checkout, then reloads server cart',
    (tester) async {
      final pending = Completer<http.Response>();
      var writes = 0, reads = 0;
      ApiClient.client = MockClient((request) async {
        if (request.method == 'GET') {
          return response(cart(++reads == 1 ? 1 : 3));
        }
        writes++;
        return pending.future;
      });
      await tester.pumpWidget(const MaterialApp(home: CartScreen()));
      await tester.pumpAndSettle();
      await tester.tap(find.byIcon(Icons.add));
      await tester.pump();
      await tester.tap(find.byIcon(Icons.add));
      await tester.pump();
      expect(writes, 1);
      expect(
        tester
            .widget<AlonaButton>(find.widgetWithText(AlonaButton, 'Checkout'))
            .onPressed,
        isNull,
      );
      pending.complete(response({'message': 'Updated'}));
      await tester.pumpAndSettle();
      expect(reads, 2);
      expect(find.text('3'), findsOneWidget);
      expect(
        tester
            .widget<AlonaButton>(find.widgetWithText(AlonaButton, 'Checkout'))
            .onPressed,
        isNotNull,
      );
    },
  );

  testWidgets('Buy Again ignores duplicate taps while request is pending', (
    tester,
  ) async {
    final pending = Completer<http.Response>();
    var writes = 0;
    ApiClient.client = MockClient((request) async {
      if (request.method == 'GET') {
        return response(orders('DONE', status: 'completed'));
      }
      writes++;
      return pending.future;
    });
    await tester.pumpWidget(const MaterialApp(home: OrderListScreen()));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Buy Again'),
      250,
      scrollable: find.byWidgetPredicate(
        (w) => w is Scrollable && w.axisDirection == AxisDirection.down,
      ),
    );
    await tester.tap(find.text('Buy Again'));
    await tester.pump();
    await tester.tap(find.text('Buy Again'));
    await tester.pump();
    expect(writes, 1);
    pending.complete(response({'message': 'Added'}));
    await tester.pumpAndSettle();
  });

  testWidgets(
    'Closing login during a failed request cannot update disposed state',
    (tester) async {
      final pending = Completer<http.Response>();
      ApiClient.client = MockClient((_) => pending.future);
      await tester.pumpWidget(
        MaterialApp(home: BuyerLoginScreen(authState: AuthState())),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(TextField).at(0),
        'synthetic@example.test',
      );
      await tester.enterText(
        find.byType(TextField).at(1),
        'Synthetic-password',
      );
      await tester.ensureVisible(find.text('Log in'));
      await tester.tap(find.text('Log in'));
      await tester.pump();
      await tester.pumpWidget(const MaterialApp(home: SizedBox()));
      pending.complete(response({'message': 'Try again'}, status: 503));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Google button explains unavailable sign-in without client identity claims',
    (tester) async {
      var calls = 0;
      ApiClient.client = MockClient((_) async {
        calls++;
        return response({});
      });
      await tester.pumpWidget(
        MaterialApp(home: BuyerLoginScreen(authState: AuthState())),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Continue with Google'));
      await tester.tap(find.text('Continue with Google'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Google sign-in is not available'),
        findsOneWidget,
      );
      expect(calls, 0);
      await tester.tap(find.text('Close'));
      await tester.pumpAndSettle();
    },
  );

  testWidgets('Login stays disabled until session persistence finishes', (
    tester,
  ) async {
    final auth = PendingSession();
    var calls = 0;
    ApiClient.client = MockClient((_) async {
      calls++;
      return response({
        'token': 'synthetic',
        'user': {'id': 5},
      });
    });
    await tester.pumpWidget(
      MaterialApp(home: BuyerLoginScreen(authState: auth)),
    );
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byType(TextField).at(0),
      'synthetic@example.test',
    );
    await tester.enterText(find.byType(TextField).at(1), 'Synthetic-password');
    await tester.ensureVisible(find.text('Log in'));
    await tester.tap(find.text('Log in'));
    await tester.pump();
    expect(auth.saves, 1);
    final button = find.ancestor(
      of: find.byType(CircularProgressIndicator),
      matching: find.byType(ElevatedButton),
    );
    expect(tester.widget<ElevatedButton>(button).onPressed, isNull);
    expect(calls, 1);
    await tester.pumpWidget(const MaterialApp(home: SizedBox()));
    auth.pending.complete();
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'OTP auto-submit and a pending verify cannot issue a second request or update a closed screen',
    (tester) async {
      final pending = Completer<http.Response>();
      var calls = 0;
      ApiClient.client = MockClient((_) async {
        calls++;
        return pending.future;
      });
      await tester.pumpWidget(
        MaterialApp(
          home: BuyerOtpScreen(
            email: 'synthetic@example.test',
            authState: AuthState(),
          ),
        ),
      );
      await tester.pumpAndSettle();
      for (var i = 0; i < 6; i++) {
        await tester.enterText(find.byType(TextField).at(i), '${i + 1}');
      }
      await tester.pump();
      expect(calls, 1);
      await tester.enterText(find.byType(TextField).at(5), '6');
      await tester.pump();
      expect(calls, 1);
      await tester.pumpWidget(const MaterialApp(home: SizedBox()));
      pending.complete(response({'message': 'Try again'}, status: 503));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Closing password-reset sheet before response is safe', (
    tester,
  ) async {
    final pending = Completer<http.Response>();
    ApiClient.client = MockClient((_) => pending.future);
    await tester.pumpWidget(
      MaterialApp(home: BuyerLoginScreen(authState: AuthState())),
    );
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byType(TextField).at(0),
      'synthetic@example.test',
    );
    await tester.ensureVisible(find.text('Forgot password?'));
    await tester.tap(find.text('Forgot password?'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Send Reset Link'));
    await tester.pump();
    await tester.pumpWidget(const MaterialApp(home: SizedBox()));
    pending.complete(response({'message': 'Sent'}));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('Failed chat send restores the draft for retry', (tester) async {
    ApiClient.client = MockClient(
      (request) async => request.method == 'GET'
          ? response(conversation())
          : response({'message': 'Offline'}, status: 503),
    );
    await tester.pumpWidget(const MaterialApp(home: chat));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'Please keep my draft');
    await tester.pump();
    await tester.tap(find.byIcon(Icons.send_rounded));
    await tester.pumpAndSettle();
    expect(
      tester.widget<TextField>(find.byType(TextField)).controller!.text,
      'Please keep my draft',
    );
    await tester.pumpWidget(const MaterialApp(home: SizedBox()));
  });

  testWidgets(
    'A poll started before send cannot erase the newly sent message',
    (tester) async {
      final oldPoll = Completer<http.Response>();
      var reads = 0;
      ApiClient.client = MockClient((request) async {
        if (request.method == 'GET') {
          reads++;
          return reads == 2 ? oldPoll.future : response(conversation());
        }
        return response({
          'message': {
            'id': 7,
            'conversation_id': 1,
            'sender_id': 5,
            'body': 'New message',
          },
        });
      });
      await tester.pumpWidget(const MaterialApp(home: chat));
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 4));
      expect(reads, 2);
      await tester.enterText(find.byType(TextField), 'New message');
      await tester.pump();
      await tester.tap(find.byIcon(Icons.send_rounded));
      await tester.pumpAndSettle();
      oldPoll.complete(response(conversation()));
      await tester.pumpAndSettle();
      expect(find.text('New message'), findsOneWidget);
      await tester.pumpWidget(const MaterialApp(home: SizedBox()));
    },
  );

  testWidgets(
    'Background chat pauses polling and resumes an interrupted initial read',
    (tester) async {
      final initial = Completer<http.Response>();
      var reads = 0;
      ApiClient.client = MockClient(
        (_) async => ++reads == 1 ? initial.future : response(conversation()),
      );
      await tester.pumpWidget(const MaterialApp(home: chat));
      await tester.pump();
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      initial.complete(response(conversation()));
      await tester.pump(const Duration(seconds: 9));
      expect(reads, 1);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.hidden);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pumpAndSettle();
      expect(reads, 2);
      expect(find.byType(CircularProgressIndicator), findsNothing);
      await tester.pumpWidget(const MaterialApp(home: SizedBox()));
    },
  );
}
