import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'package:mobile_scanner/mobile_scanner.dart';

import '../services/rider_parcel_api.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import '../theme/rider_theme.dart';
import '../widgets/rider_content.dart';
import 'delivery_dashboard_page.dart';
import 'delivery_history_page.dart';

class ParcelScannerPage extends StatefulWidget {
  final RiderSession? session;
  final Widget Function(ValueChanged<String> onDetected)? scannerBuilder;
  const ParcelScannerPage({super.key, this.session, this.scannerBuilder});
  @override
  State<ParcelScannerPage> createState() => _ParcelScannerPageState();
}

class _ParcelScannerPageState extends State<ParcelScannerPage>
    with WidgetsBindingObserver, RiderSessionGuard<ParcelScannerPage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  late final api = RiderParcelApi(riderSession);
  final reference = TextEditingController();
  MobileScannerController? camera;
  bool busy = false, scanLocked = false;
  String? error, cameraError;
  bool get nativeCamera =>
      !kIsWeb &&
      [
        TargetPlatform.android,
        TargetPlatform.iOS,
      ].contains(defaultTargetPlatform);
  @override
  void initState() {
    super.initState();
    if (nativeCamera && widget.scannerBuilder == null) {
      camera = MobileScannerController(
        detectionTimeoutMs: 600,
        returnImage: false,
      );
    }
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (camera?.value.hasCameraPermission != true) return;
    if (state == AppLifecycleState.resumed &&
        !busy &&
        !scanLocked &&
        ModalRoute.of(context)?.isCurrent == true) {
      unawaited(startCamera());
    } else if (state != AppLifecycleState.resumed) {
      unawaited(stopCamera());
    }
  }

  Future<void> stopCamera() async {
    try {
      await camera?.stop();
    } catch (_) {}
  }

  Future<void> startCamera() async {
    try {
      await camera?.start();
    } catch (_) {
      if (mounted) {
        setState(
          () => cameraError =
              'Camera could not start. Allow camera access in device Settings, then retry or enter the reference.',
        );
      }
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    reference.dispose();
    unawaited(camera?.dispose());
    super.dispose();
  }

  void detected(String value) {
    if (busy || scanLocked) return;
    reference.text = value.trim();
    unawaited(lookup());
  }

  Future<void> lookup() async {
    if (busy) return;
    FocusScope.of(context).unfocus();
    setState(() {
      busy = true;
      scanLocked = true;
      error = null;
    });
    await stopCamera();
    try {
      final parcel = await sessionAction(() => api.lookup(reference.text));
      if (!mounted) return;
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => DeliveryDashboardPage(
            session: riderSession,
            parcelId: parcel['id'] as int,
          ),
        ),
      );
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure
              ? failure.message
              : 'Parcel lookup could not be confirmed. Retry or enter the reference.',
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> scanAgain() async {
    setState(() {
      scanLocked = false;
      error = null;
      cameraError = null;
    });
    await startCamera();
  }

  Widget _buildScannerIllustration() {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          // Corner Guides
          Padding(
            padding: const EdgeInsets.all(32),
            child: SizedBox(
              width: 240,
              height: 140,
              child: Stack(
                children: [
                  // Top-left corner
                  Align(
                    alignment: Alignment.topLeft,
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: const BoxDecoration(
                        border: Border(
                          top: BorderSide(color: Colors.white, width: 4),
                          left: BorderSide(color: Colors.white, width: 4),
                        ),
                        borderRadius: BorderRadius.only(
                          topLeft: Radius.circular(12),
                        ),
                      ),
                    ),
                  ),
                  // Top-right corner
                  Align(
                    alignment: Alignment.topRight,
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: const BoxDecoration(
                        border: Border(
                          top: BorderSide(color: Colors.white, width: 4),
                          right: BorderSide(color: Colors.white, width: 4),
                        ),
                        borderRadius: BorderRadius.only(
                          topRight: Radius.circular(12),
                        ),
                      ),
                    ),
                  ),
                  // Bottom-left corner
                  Align(
                    alignment: Alignment.bottomLeft,
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: const BoxDecoration(
                        border: Border(
                          bottom: BorderSide(color: Colors.white, width: 4),
                          left: BorderSide(color: Colors.white, width: 4),
                        ),
                        borderRadius: BorderRadius.only(
                          bottomLeft: Radius.circular(12),
                        ),
                      ),
                    ),
                  ),
                  // Bottom-right corner
                  Align(
                    alignment: Alignment.bottomRight,
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: const BoxDecoration(
                        border: Border(
                          bottom: BorderSide(color: Colors.white, width: 4),
                          right: BorderSide(color: Colors.white, width: 4),
                        ),
                        borderRadius: BorderRadius.only(
                          bottomRight: Radius.circular(12),
                        ),
                      ),
                    ),
                  ),
                  // Barcode vertical lines
                  Center(
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(24, (i) {
                        final isThick = i % 3 == 0;
                        return Container(
                          margin: const EdgeInsets.symmetric(horizontal: 2),
                          width: isThick ? 4 : 2,
                          height: 60,
                          color: const Color(0xFF64748B),
                        );
                      }),
                    ),
                  ),
                  // Horizontal Yellow / Amber Laser Scan Line
                  Center(
                    child: Container(
                      width: 210,
                      height: 3,
                      decoration: BoxDecoration(
                        color: RiderColors.amber,
                        borderRadius: BorderRadius.circular(2),
                        boxShadow: [
                          BoxShadow(
                            color: RiderColors.amber.withValues(alpha: 0.6),
                            blurRadius: 6,
                            spreadRadius: 1,
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          // Caption at bottom
          Positioned(
            bottom: 16,
            child: Text(
              'Camera scanning is available in the Android and iOS app.',
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.9),
                fontSize: 12.5,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: RiderColors.background,
    appBar: AppBar(title: const Text('Scan parcel')),
    body: ListView(
      padding: riderPagePadding(context, maxWidth: 640),
      children: [
        const SizedBox(height: 8),
        const Text(
          'Find your assigned delivery',
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w800,
            color: RiderColors.text,
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Place the parcel barcode inside the guide. Only deliveries assigned to you can be opened.',
          style: TextStyle(
            fontSize: 14,
            color: RiderColors.textSecondary,
            height: 1.35,
          ),
        ),
        const SizedBox(height: 20),
        ClipRRect(
          borderRadius: BorderRadius.circular(24),
          child: SizedBox(
            height: 280,
            child: widget.scannerBuilder != null
                ? widget.scannerBuilder!(detected)
                : !nativeCamera
                ? _buildScannerIllustration()
                : Stack(
                    fit: StackFit.expand,
                    children: [
                      MobileScanner(
                        controller: camera,
                        useAppLifecycleState: false,
                        onDetect: (capture) {
                          for (final barcode in capture.barcodes) {
                            final value = barcode.rawValue;
                            if (value != null && value.trim().isNotEmpty) {
                              detected(value);
                              break;
                            }
                          }
                        },
                        errorBuilder: (_, failure) =>
                            _buildScannerIllustration(),
                      ),
                      IgnorePointer(
                        child: Center(
                          child: FractionallySizedBox(
                            widthFactor: 0.78,
                            heightFactor: 0.55,
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                border: Border.all(
                                  color: RiderColors.amber,
                                  width: 3,
                                ),
                                borderRadius: BorderRadius.circular(16),
                              ),
                            ),
                          ),
                        ),
                      ),
                      if (busy)
                        const ColoredBox(
                          color: Color(0x88000000),
                          child: Center(
                            child: CircularProgressIndicator(
                              color: Colors.white,
                            ),
                          ),
                        ),
                    ],
                  ),
          ),
        ),
        if (busy)
          const Padding(
            padding: EdgeInsets.only(top: 12),
            child: Center(
              child: Text(
                'Checking your assignment...',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  color: RiderColors.teal,
                ),
              ),
            ),
          ),
        if (cameraError != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(
              cameraError!,
              style: const TextStyle(color: RiderColors.danger),
            ),
          ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(
              error!,
              style: const TextStyle(
                color: RiderColors.danger,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        if (nativeCamera || widget.scannerBuilder != null)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: TextButton.icon(
              onPressed: busy ? null : scanAgain,
              icon: const Icon(Icons.qr_code_scanner),
              label: Text(scanLocked ? 'Scan another parcel' : 'Retry camera'),
            ),
          ),
        const SizedBox(height: 20),
        // Divider "or enter it here"
        Row(
          children: const [
            Expanded(child: Divider(color: RiderColors.border, thickness: 1)),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16),
              child: Text(
                'or enter it here',
                style: TextStyle(
                  color: RiderColors.textSecondary,
                  fontWeight: FontWeight.w500,
                  fontSize: 13,
                ),
              ),
            ),
            Expanded(child: Divider(color: RiderColors.border, thickness: 1)),
          ],
        ),
        const SizedBox(height: 20),
        const Text(
          'Parcel reference',
          style: TextStyle(
            fontWeight: FontWeight.w700,
            fontSize: 14,
            color: RiderColors.text,
          ),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: reference,
          enabled: !busy,
          textCapitalization: TextCapitalization.characters,
          maxLength: 50,
          decoration: InputDecoration(
            hintText: 'ITEM-123',
            prefixIcon: const Icon(
              Icons.inventory_2_outlined,
              color: RiderColors.textSecondary,
            ),
            filled: true,
            fillColor: Colors.white,
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 14,
            ),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: const BorderSide(color: RiderColors.border),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: const BorderSide(color: RiderColors.border),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: const BorderSide(
                color: RiderColors.teal,
                width: 1.5,
              ),
            ),
          ),
          onSubmitted: busy ? null : (_) => lookup(),
        ),
        const SizedBox(height: 16),
        SizedBox(
          width: double.infinity,
          height: 52,
          child: FilledButton.icon(
            onPressed: busy ? null : lookup,
            icon: const Icon(Icons.search, size: 20),
            label: const Text(
              'Open delivery details',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
            ),
            style: FilledButton.styleFrom(
              backgroundColor: RiderColors.teal,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
            ),
          ),
        ),
        const SizedBox(height: 12),
        Center(
          child: TextButton(
            onPressed: busy
                ? null
                : () async {
                    await stopCamera();
                    if (mounted) setState(() => scanLocked = true);
                    if (!context.mounted) return;
                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) =>
                            DeliveryHistoryPage(session: riderSession),
                      ),
                    );
                    if (mounted) setState(() => scanLocked = true);
                  },
            child: const Text(
              'Delivery history',
              style: TextStyle(
                color: RiderColors.teal,
                fontWeight: FontWeight.w700,
                fontSize: 15,
              ),
            ),
          ),
        ),
        const SizedBox(height: 24),
      ],
    ),
  );
}
