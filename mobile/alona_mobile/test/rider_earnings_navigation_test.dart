import 'dart:convert';

import 'package:alona_mobile/features/rider/pages/cod_and_earnings_tracker_page.dart';
import 'package:alona_mobile/features/rider/pages/rider_shell_page.dart';
import 'package:alona_mobile/features/rider/services/rider_session.dart';
import 'package:alona_mobile/features/rider/services/token_store.dart';
import 'package:alona_mobile/features/rider/theme/rider_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class _FixtureTokens extends TokenStore {
  @override
  Future<String?> read() async => 'synthetic-rider-token';
}

RiderSession _session({void Function(String)? onRequest, double balance = 0}) {
  final client = MockClient((request) async {
    if (request.method != 'GET') {
      throw StateError('The navigation fixture only supports reads.');
    }
    onRequest?.call(request.url.path);
    final Object data = switch (request.url.path) {
      '/api/partner/profile' => {
        'role': 'rider',
        'status': 'approved',
        'approvalStatus': 'approved',
        'is_active': true,
        'verification_required': false,
        'verified': true,
      },
      '/api/rider/cod' => {
        'balances': [
          {'name': 'Sorting Center Hub', 'balance': balance},
        ],
        'collections': <Object>[],
        'remittances': <Object>[],
      },
      _ => <Object>[],
    };
    return http.Response(
      jsonEncode({'data': data}),
      200,
      headers: {'content-type': 'application/json'},
    );
  });
  addTearDown(client.close);
  return RiderSession(tokens: _FixtureTokens(), client: client);
}

void main() {
  for (final textScale in [1.0, 1.3]) {
    for (final balance in [0.0, 9500.0]) {
      testWidgets('Finance fits a phone at scale $textScale with COD $balance', (
        tester,
      ) async {
        tester.view.physicalSize = const Size(360, 800);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          MaterialApp(
            theme: RiderTheme.theme,
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context).copyWith(
                textScaler: TextScaler.linear(textScale),
              ),
              child: child!,
            ),
            home: CodAndEarningsTrackerPage(session: _session(balance: balance)),
          ),
        );
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 100));
        await tester.pump(const Duration(milliseconds: 100));
        expect(tester.takeException(), isNull);

        final cashList = find.descendant(
          of: find.byType(TabBarView),
          matching: find.byType(ListView),
        ).first;
        await tester.scrollUntilVisible(
          find.text('₱1000'),
          200,
          scrollable: find.descendant(
            of: cashList,
            matching: find.byType(Scrollable),
          ).first,
        );
        await tester.pumpAndSettle();
        final thousandRow = find.ancestor(
          of: find.text('₱1000'),
          matching: find.byType(Column),
        ).first;
        final thousandAdd = find.descendant(
          of: thousandRow,
          matching: find.widgetWithIcon(IconButton, Icons.add_circle),
        );
        await tester.ensureVisible(thousandAdd);
        await tester.pumpAndSettle();
        await tester.tap(thousandAdd);
        await tester.pumpAndSettle();
        final thousandAmount = find.descendant(
          of: thousandRow,
          matching: find.text('₱1000.00'),
        );
        expect(thousandAmount, findsOneWidget);
        final countParagraph = tester.renderObject<RenderParagraph>(
          find.text('x 1 piraso'),
        );
        final amountParagraph = tester.renderObject<RenderParagraph>(
          thousandAmount,
        );
        expect(
          countParagraph.getBoxesForSelection(
            const TextSelection(baseOffset: 0, extentOffset: 10),
          ).map((box) => box.top).toSet(),
          hasLength(1),
        );
        expect(
          amountParagraph.getBoxesForSelection(
            const TextSelection(baseOffset: 0, extentOffset: 8),
          ).map((box) => box.top).toSet(),
          hasLength(1),
        );
        expect(tester.takeException(), isNull);

        final fiveHundredRow = find.ancestor(
          of: find.text('₱500'),
          matching: find.byType(Column),
        ).first;
        final fiveHundredAdd = find.descendant(
          of: fiveHundredRow,
          matching: find.widgetWithIcon(IconButton, Icons.add_circle),
        );
        await tester.ensureVisible(fiveHundredAdd);
        await tester.pumpAndSettle();
        await tester.tap(fiveHundredAdd);
        await tester.pumpAndSettle();
        expect(find.text('₱1500.00'), findsOneWidget);
        expect(find.text('₱500.00'), findsOneWidget);
        expect(tester.takeException(), isNull);

        final quantityInput = find.byKey(
          const ValueKey('denomination-count-1000'),
        );
        await tester.ensureVisible(quantityInput);
        await tester.pumpAndSettle();
        await tester.enterText(quantityInput, '12');
        await tester.pumpAndSettle();
        expect(find.text('x 12 piraso'), findsOneWidget);
        expect(find.text('₱12500.00'), findsOneWidget);
        await tester.testTextInput.receiveAction(TextInputAction.done);
        await tester.pumpAndSettle();

        await tester.tap(thousandAdd);
        await tester.pumpAndSettle();
        expect(
          tester.widget<TextField>(quantityInput).controller!.text,
          '13',
        );
        expect(find.text('₱13500.00'), findsOneWidget);

        await tester.enterText(quantityInput, '');
        await tester.pumpAndSettle();
        expect(find.text('₱500.00'), findsNWidgets(2));
        await tester.enterText(quantityInput, 'abc');
        await tester.pumpAndSettle();
        expect(tester.widget<TextField>(quantityInput).controller!.text, isEmpty);
        await tester.testTextInput.receiveAction(TextInputAction.done);
        await tester.pumpAndSettle();
        expect(tester.widget<TextField>(quantityInput).controller!.text, '0');

        await tester.ensureVisible(find.text('Reset'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Reset'));
        await tester.pumpAndSettle();
        expect(tester.widget<TextField>(quantityInput).controller!.text, '0');
        expect(
          tester.widget<TextField>(find.byKey(
            const ValueKey('denomination-count-500'),
          )).controller!.text,
          '0',
        );

        if (balance > 0) {
          await tester.ensureVisible(find.text('Auto-Match'));
          await tester.pumpAndSettle();
          await tester.tap(find.text('Auto-Match'));
          await tester.pumpAndSettle();
          expect(tester.widget<TextField>(quantityInput).controller!.text, '9');
          expect(find.text('₱9500.00'), findsOneWidget);
        }
        expect(tester.takeException(), isNull);

        for (var step = 0; step < 10; step++) {
          await tester.drag(cashList, const Offset(0, -400));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
        }

        await tester.tap(find.text('Kinita / Earnings'));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);

        final earningsList = find.descendant(
          of: find.byType(TabBarView),
          matching: find.byType(ListView),
        ).first;
        await tester.drag(earningsList, const Offset(0, -500));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      });
    }
  }

  testWidgets('Earnings loads on first opening from Deliveries and on return', (
    tester,
  ) async {
    final requests = <String>[];
    await tester.pumpWidget(
      MaterialApp(
        theme: RiderTheme.theme,
        home: RiderShellPage(session: _session(onRequest: requests.add)),
      ),
    );
    await tester.pump(const Duration(milliseconds: 100));
    expect(requests.where((path) => path == '/api/rider/cod'), isEmpty);

    await tester.tap(find.text('Earnings'));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 100));

    expect(
      requests.where((path) => path == '/api/rider/cod'),
      hasLength(1),
    );
    expect(
      requests.where((path) => path == '/api/rider/earnings'),
      hasLength(1),
    );
    expect(find.text('Designated Hub: Sorting Center Hub'), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsNothing);

    await tester.tap(find.text('Kinita / Earnings'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump();
    expect(find.text('Kabuuang Kinita (Total Earnings)'), findsOneWidget);

    await tester.tap(find.text('Messages'));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.tap(find.text('Earnings'));
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 100));

    expect(
      requests.where((path) => path == '/api/rider/earnings'),
      hasLength(2),
    );
    expect(find.text('Kabuuang Kinita (Total Earnings)'), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsNothing);
    expect(find.byType(BottomNavigationBar), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Earnings uses one menu when switching Rider shell tabs', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: RiderTheme.theme,
        home: RiderShellPage(session: _session(), initialTab: 2),
      ),
    );
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('COD Cash & Earnings'), findsOneWidget);
    expect(find.byType(BottomNavigationBar), findsOneWidget);

    await tester.tap(find.text('Messages'));
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.text('New message to'), findsOneWidget);

    await tester.tap(find.text('Earnings'));
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.text('COD Cash & Earnings'), findsOneWidget);
    expect(find.byType(BottomNavigationBar), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Standalone Earnings keeps a working Rider menu', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: RiderTheme.theme,
        home: CodAndEarningsTrackerPage(session: _session()),
      ),
    );
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.byType(BottomNavigationBar), findsOneWidget);
    await tester.tap(find.text('Messages'));
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump();

    expect(find.text('New message to'), findsOneWidget);
    expect(find.byType(BottomNavigationBar), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
