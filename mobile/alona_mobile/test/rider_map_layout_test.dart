import 'package:alona_mobile/features/rider/theme/rider_theme.dart';
import 'package:alona_mobile/features/rider/widgets/rider_interactive_map_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Future<void> pumpMap(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 500));
}

void main() {
  for (final scale in [1.0, 1.3]) {
    testWidgets('Map navigation works in compact and fullscreen layouts at $scale', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var navigationRequests = 0;
      await tester.pumpWidget(
        MaterialApp(
          theme: RiderTheme.theme,
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context).copyWith(
              textScaler: TextScaler.linear(scale),
            ),
            child: child!,
          ),
          home: Scaffold(
            body: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                RiderInteractiveMapCard(
                  destinationAddress: 'A long destination address in Santa Rosa, Laguna',
                  destinationName: 'Sorting Center Hub standby pickup destination',
                  height: 200,
                  onOpenExternalMap: () => navigationRequests++,
                ),
              ],
            ),
          ),
        ),
      );
      await pumpMap(tester);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byTooltip('Zoom In'));
      await pumpMap(tester);
      await tester.tap(find.byTooltip('Zoom Out'));
      await pumpMap(tester);
      await tester.tap(find.byTooltip('Puntahan ang Drop-off'));
      await pumpMap(tester);
      await tester.tap(find.byTooltip('Open Waze / Maps'));
      expect(navigationRequests, 1);
      expect(tester.takeException(), isNull);

      await tester.tap(find.byTooltip('Fullscreen Map'));
      await pumpMap(tester);
      expect(find.text('Route Map Navigation'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.tap(find.byTooltip('Zoom In'));
      await pumpMap(tester);
      await tester.tap(find.byTooltip('Open Waze / Maps'));
      expect(navigationRequests, 2);

      tester.view.physicalSize = const Size(800, 360);
      await pumpMap(tester);
      expect(tester.takeException(), isNull);
      await tester.tap(find.byIcon(Icons.close));
      await pumpMap(tester);
      expect(find.text('Route Map Navigation'), findsNothing);
      expect(find.byTooltip('Fullscreen Map'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
