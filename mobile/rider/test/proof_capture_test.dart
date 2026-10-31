import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rider_app/pages/proof_capture_page.dart';
import 'package:rider_app/services/rider_session.dart';

import 'rider_session_test.dart' show MemoryTokens, readyProfile;

void main() {
  RiderSession session({void Function(http.Request)? upload}) => RiderSession(
    tokens: MemoryTokens(),
    client: MockClient((request) async {
      if (request.url.path.endsWith('/partner/profile')) {
        return http.Response('{"data":${_profile()}}', 200);
      }
      upload?.call(request);
      return http.Response('{"data":{"id":1}}', 201);
    }),
  );

  testWidgets(
    'native proof entry opens camera once and cancelled capture cannot upload',
    (tester) async {
      var calls = 0;
      await tester.pumpWidget(
        MaterialApp(
          home: ProofCapturePage(
            session: session(),
            parcelId: 52,
            deliveryAddress: 'Recipient address',
            openCameraOnStart: true,
            capturePhoto: () async {
              calls++;
              return null;
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(calls, 1);
      expect(
        tester
            .widget<ElevatedButton>(
              find.widgetWithText(ElevatedButton, 'Upload Proof'),
            )
            .onPressed,
        isNull,
      );
    },
  );

  testWidgets(
    'camera permission denial is actionable and capture can be retried',
    (tester) async {
      var calls = 0;
      await tester.pumpWidget(
        MaterialApp(
          home: ProofCapturePage(
            session: session(),
            parcelId: 52,
            deliveryAddress: 'Recipient address',
            capturePhoto: () async {
              calls++;
              throw PlatformException(code: 'camera_access_denied');
            },
          ),
        ),
      );
      await tester.tap(find.text('Capture Photo'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Allow camera access in your device settings'),
        findsOneWidget,
      );
      await tester.tap(find.text('Capture Photo'));
      await tester.pumpAndSettle();
      expect(calls, 2);
    },
  );

  testWidgets(
    'unsupported desktop explains camera limits and selects real photos instead',
    (tester) async {
      debugDefaultTargetPlatformOverride = TargetPlatform.windows;
      try {
        var cameraCalls = 0;
        await tester.pumpWidget(
          MaterialApp(
            home: ProofCapturePage(
              session: session(),
              parcelId: 52,
              deliveryAddress: 'Recipient address',
              openCameraOnStart: true,
              capturePhoto: () async {
                cameraCalls++;
                return null;
              },
              choosePhoto: () async =>
                  throw PlatformException(code: 'photo_access_denied'),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(cameraCalls, 0);
        expect(
          find.textContaining('Live camera capture is available'),
          findsOneWidget,
        );
        await tester.ensureVisible(find.text('Choose proof photo'));
        await tester.tap(find.text('Choose proof photo'));
        await tester.pumpAndSettle();
        expect(find.textContaining('Allow photo access'), findsOneWidget);
      } finally {
        debugDefaultTargetPlatformOverride = null;
      }
    },
  );

  testWidgets('capture loading prevents duplicate camera requests', (
    tester,
  ) async {
    final pending = Completer<XFile?>();
    var calls = 0;
    await tester.pumpWidget(
      MaterialApp(
        home: ProofCapturePage(
          session: session(),
          parcelId: 52,
          deliveryAddress: 'Recipient address',
          capturePhoto: () {
            calls++;
            return pending.future;
          },
        ),
      ),
    );
    await tester.tap(find.text('Capture Photo'));
    await tester.pump();
    expect(calls, 1);
    expect(
      tester
          .widget<ElevatedButton>(
            find.widgetWithText(ElevatedButton, 'Opening photo…'),
          )
          .onPressed,
      isNull,
    );
    pending.complete(null);
    await tester.pumpAndSettle();
  });

  testWidgets(
    'real image bytes can be previewed, retaken and uploaded through existing API',
    (tester) async {
      final data = await rootBundle.load('assets/images/alona/alonalogo2.png');
      final bytes = data.buffer.asUint8List(
        data.offsetInBytes,
        data.lengthInBytes,
      );
      var captures = 0, uploads = 0;
      Future<XFile?> chooseProof() async {
        captures++;
        return XFile.fromData(bytes, name: 'proof.png', path: 'proof.png');
      }

      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => ProofCapturePage(
                      session: session(
                        upload: (r) {
                          uploads++;
                          expect(
                            r.url.path,
                            '/api/rider/deliveries/52/upload-proof',
                          );
                          expect(
                            r.headers['Authorization'],
                            'Bearer test-token',
                          );
                          expect(
                            latin1.decode(r.bodyBytes),
                            contains('filename="proof.png"'),
                          );
                        },
                      ),
                      parcelId: 52,
                      deliveryAddress: 'Recipient address',
                      capturePhoto: chooseProof,
                      choosePhoto: chooseProof,
                    ),
                  ),
                ),
                child: const Text('Open proof'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Open proof'));
      await tester.pumpAndSettle();
      Future<void> take(String label) async {
        await tester.ensureVisible(find.text(label));
        await tester.runAsync(() async {
          final callback = tester
              .widget<ElevatedButton>(
                find.widgetWithText(ElevatedButton, label),
              )
              .onPressed!;
          final result = Function.apply(callback, const []);
          if (result is Future) await result;
        });
        await tester.pumpAndSettle();
      }

      await take(kIsWeb ? 'Choose proof photo' : 'Capture Photo');
      final retake = kIsWeb ? 'Choose another photo' : 'Retake Photo';
      expect(find.text(retake), findsOneWidget);
      expect(find.byType(Image), findsOneWidget);
      await take(retake);
      expect(captures, 2);
      await tester.ensureVisible(find.text('Upload Proof'));
      await tester.tap(find.text('Upload Proof'));
      await tester.pumpAndSettle();
      expect(uploads, 1);
      expect(find.text('Open proof'), findsOneWidget);
    },
  );

  testWidgets('gallery rejects oversized intrinsic dimensions before upload', (
    tester,
  ) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.windows;
    try {
      var uploads = 0;
      await tester.pumpWidget(
        MaterialApp(
          home: ProofCapturePage(
            session: session(upload: (_) => uploads++),
            parcelId: 52,
            deliveryAddress: 'Recipient address',
            choosePhoto: () async => XFile.fromData(
              base64Decode(
                'iVBORw0KGgoAAAANSUhEUgAAF3EAAAABCAYAAABukZBZAAAALklEQVR4nO3BMQEAAADCoPVP7WcKoAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAuAFdxQAByLoM/AAAAABJRU5ErkJggg==',
              ),
              name: 'wide.png',
            ),
          ),
        ),
      );
      await tester.ensureVisible(find.text('Choose proof photo'));
      await tester.runAsync(() async {
        final button = tester.widget<ElevatedButton>(
          find.widgetWithText(ElevatedButton, 'Choose proof photo'),
        );
        final result = Function.apply(button.onPressed!, const []);
        if (result is Future) await result;
      });
      await tester.pumpAndSettle();
      expect(find.textContaining('6000 × 6000'), findsOneWidget);
      expect(
        tester
            .widget<ElevatedButton>(
              find.widgetWithText(ElevatedButton, 'Upload Proof'),
            )
            .onPressed,
        isNull,
      );
      expect(uploads, 0);
    } finally {
      debugDefaultTargetPlatformOverride = null;
    }
  });

  testWidgets('empty image never enables proof upload', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: ProofCapturePage(
          session: session(),
          parcelId: 52,
          deliveryAddress: 'Recipient address',
          capturePhoto: () async =>
              XFile.fromData(Uint8List(0), name: 'empty.png'),
        ),
      ),
    );
    await tester.tap(find.text('Capture Photo'));
    await tester.pumpAndSettle();
    expect(find.textContaining('photo is empty'), findsOneWidget);
    expect(
      tester
          .widget<ElevatedButton>(
            find.widgetWithText(ElevatedButton, 'Upload Proof'),
          )
          .onPressed,
      isNull,
    );
  });
}

String _profile() {
  // Keep this fixture local to tests, never in runtime application code.
  return const JsonEncoder().convert(readyProfile());
}
