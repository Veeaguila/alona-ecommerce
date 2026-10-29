import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/app.dart';
import 'package:rider_app/pages/delivery_dashboard_page.dart';
import 'package:rider_app/pages/pickup_dashboard_page.dart';
import 'package:rider_app/pages/registration_page.dart';
import 'package:rider_app/services/registration_api.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  for (final width in [320.0, 480.0, 1440.0]) {
    testWidgets('sign-in remains usable at width $width', (tester) async {
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = Size(width, 740);
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(const RiderApp());
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextFormField).last, 'local-test');
      expect(
        tester
            .widgetList<EditableText>(find.byType(EditableText))
            .last
            .obscureText,
        isTrue,
      );
      await tester.tap(find.byTooltip('Show password'));
      await tester.pump();
      expect(
        tester
            .widgetList<EditableText>(find.byType(EditableText))
            .last
            .obscureText,
        isFalse,
      );
      await tester.tap(find.byTooltip('Hide password'));
      await tester.pump();
      expect(
        tester
            .widgetList<EditableText>(find.byType(EditableText))
            .last
            .obscureText,
        isTrue,
      );

      FocusManager.instance.primaryFocus?.unfocus();
      await tester.pumpAndSettle(const Duration(seconds: 3));
      await tester.ensureVisible(find.text('Log in'));
      await tester.tap(find.text('Log in'));
      await tester.pumpAndSettle();
      expect(find.text('Enter a valid email'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets(
    'registration scrolls to consent on a narrow screen with large text',
    (tester) async {
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = const Size(320, 740);
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(const RiderApp());
      final theme = tester.widget<MaterialApp>(find.byType(MaterialApp)).theme;
      final api = RegistrationApi(
        client: MockClient((_) async => http.Response('{"data":[]}', 200)),
      );
      await tester.pumpWidget(
        MaterialApp(
          theme: theme,
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: TextScaler.linear(1.5)),
            child: child!,
          ),
          home: RegistrationPage(api: api),
        ),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Submit registration'));
      await tester.pumpAndSettle();
      expect(find.byType(CheckboxListTile), findsNWidgets(2));
    },
  );

  for (final deliveries in [false, true]) {
    testWidgets(
      '${deliveries ? 'delivery' : 'pickup'} controls fit a narrow viewport',
      (tester) async {
        tester.view.devicePixelRatio = 1;
        tester.view.physicalSize = const Size(320, 740);
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final parcel = {
          'id': 42,
          'order_id': 'ITEM-123456789',
          'status': 'OUT_FOR_DELIVERY',
          'collection_address': 'Seller warehouse',
          'delivery_address': 'Buyer destination',
        };
        final session = RiderSession(
          tokens: MemoryTokens(),
          client: MockClient(
            (request) async => http.Response(
              jsonEncode({
                'data': request.url.path.endsWith('/partner/profile')
                    ? readyProfile()
                    : request.url.path.endsWith('/presence')
                    ? {'presence': 'Unknown', 'is_current_session': false}
                    : request.url.path.endsWith('/mine')
                    ? []
                    : [parcel],
              }),
              200,
            ),
          ),
        );
        await tester.pumpWidget(const RiderApp());
        final theme = tester
            .widget<MaterialApp>(find.byType(MaterialApp))
            .theme;
        await tester.pumpWidget(
          MaterialApp(
            theme: theme,
            home: deliveries
                ? DeliveryDashboardPage(session: session)
                : PickupDashboardPage(session: session),
          ),
        );
        await tester.pumpAndSettle();
        final action = find.text(deliveries ? 'Mark Failed' : 'Accept pickup');
        await tester.scrollUntilVisible(
          action,
          200,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        expect(action, findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }
}
