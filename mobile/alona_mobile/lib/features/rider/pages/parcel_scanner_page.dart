import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:mobile_scanner/mobile_scanner.dart';

import '../services/rider_parcel_api.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import '../theme/rider_theme.dart';
import 'delivery_dashboard_page.dart';
import 'delivery_history_page.dart';

enum ScannerMode {
  continuousBatch,
  singleLookup,
}

enum BatchItemStatus {
  verifying,
  success,
  error,
}

class ScannedBatchItem {
  final String code;
  BatchItemStatus status;
  String? message;
  String? buyerName;
  String? deliveryAddress;
  bool isCod;
  double codAmount;
  final DateTime scannedAt;

  ScannedBatchItem({
    required this.code,
    this.status = BatchItemStatus.verifying,
    this.message,
    this.buyerName,
    this.deliveryAddress,
    this.isCod = false,
    this.codAmount = 0.0,
    DateTime? scannedAt,
  }) : scannedAt = scannedAt ?? DateTime.now();
}

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

  ScannerMode mode = ScannerMode.continuousBatch;
  final reference = TextEditingController();
  MobileScannerController? camera;
  bool busy = false;
  bool scanLocked = false;
  bool isTorchOn = false;
  String? error;
  String? cameraError;
  String? lastScannedToast;
  bool lastScannedToastIsError = false;
  Timer? toastTimer;

  // Continuous Batch State
  final List<ScannedBatchItem> batchItems = [];
  String? _lastCode;
  DateTime? _lastScanTime;

  bool get nativeCamera =>
      !kIsWeb &&
      [
        TargetPlatform.android,
        TargetPlatform.iOS,
      ].contains(defaultTargetPlatform);

  int get successfulCount =>
      batchItems.where((i) => i.status == BatchItemStatus.success).length;

  int get errorCount =>
      batchItems.where((i) => i.status == BatchItemStatus.error).length;

  double get totalBatchCod => batchItems
      .where((i) => i.status == BatchItemStatus.success && i.isCod)
      .fold(0.0, (acc, item) => acc + item.codAmount);

  @override
  void initState() {
    super.initState();
    if (nativeCamera && widget.scannerBuilder == null) {
      camera = MobileScannerController(
        detectionTimeoutMs: 200,
        detectionSpeed: DetectionSpeed.normal,
        cameraResolution: const Size(1280, 720),
        formats: const [
          BarcodeFormat.code128,
          BarcodeFormat.code39,
          BarcodeFormat.qrCode,
          BarcodeFormat.ean13,
          BarcodeFormat.ean8,
          BarcodeFormat.upcA,
        ],
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
              'Camera could not start. Allow camera access in Settings, then retry.',
        );
      }
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    reference.dispose();
    toastTimer?.cancel();
    unawaited(camera?.dispose());
    super.dispose();
  }

  void _showScanNotice(String message, {bool isError = false}) {
    toastTimer?.cancel();
    setState(() {
      lastScannedToast = message;
      lastScannedToastIsError = isError;
    });
    toastTimer = Timer(const Duration(milliseconds: 2500), () {
      if (mounted) {
        setState(() {
          lastScannedToast = null;
          lastScannedToastIsError = false;
        });
      }
    });
  }

  // -------------------------------------------------------------
  // SCAN DETECTION ROUTING
  // -------------------------------------------------------------
  void detected(String rawValue) {
    final clean = rawValue.trim().toUpperCase();
    if (clean.isEmpty) return;

    if (mode == ScannerMode.singleLookup) {
      if (busy || scanLocked) return;
      reference.text = clean;
      unawaited(singleLookup());
      return;
    }

    // Continuous Batch Mode
    final now = DateTime.now();
    if (_lastCode == clean &&
        _lastScanTime != null &&
        now.difference(_lastScanTime!).inMilliseconds < 1400) {
      // Debounce rapid repeat read of the same exact barcode
      return;
    }

    _lastCode = clean;
    _lastScanTime = now;

    // Check if already in current batch
    final existingIndex = batchItems.indexWhere((i) => i.code == clean);
    if (existingIndex >= 0 &&
        batchItems[existingIndex].status == BatchItemStatus.success) {
      SystemSound.play(SystemSoundType.alert);
      HapticFeedback.heavyImpact();
      _showScanNotice('Nai-scan na ito: $clean (Duplicate)', isError: true);
      return;
    }

    // Process new scan in batch
    SystemSound.play(SystemSoundType.click);
    HapticFeedback.lightImpact();
    final item = ScannedBatchItem(code: clean);
    setState(() {
      if (existingIndex >= 0) {
        batchItems.removeAt(existingIndex);
      }
      batchItems.insert(0, item);
      _showScanNotice('Na-scan: $clean (Inaalam ang detalye...)');
    });

    _processBatchScan(item);
  }

  Future<void> _processBatchScan(ScannedBatchItem item) async {
    try {
      final res = await sessionAction(() => api.smartScan(item.code));
      if (!mounted) return;

      final parcel = res['parcel'] as Map<String, dynamic>?;
      final isCod = parcel?['is_cod'] == true;
      final codAmt = parcel?['cod_amount'] != null
          ? double.tryParse('${parcel!['cod_amount']}'.replaceAll(',', '')) ?? 0.0
          : 0.0;

      setState(() {
        item.status = BatchItemStatus.success;
        item.message = res['message']?.toString() ??
            res['title']?.toString() ??
            'Out for Delivery (Loaded)';
        item.buyerName = parcel?['buyer_name']?.toString() ??
            parcel?['recipient_name']?.toString() ??
            'Buyer';
        item.deliveryAddress = parcel?['delivery_address']?.toString() ?? '';
        item.isCod = isCod;
        item.codAmount = codAmt;
      });

      SystemSound.play(SystemSoundType.click);
      HapticFeedback.mediumImpact();
      _showScanNotice('Na-load: ${item.code} (${item.buyerName})');
    } catch (e) {
      if (!mounted) return;
      final msg = e is SessionFailure ? e.message : 'Hindi ma-load ang parcel.';
      setState(() {
        item.status = BatchItemStatus.error;
        item.message = msg;
      });
      SystemSound.play(SystemSoundType.alert);
      HapticFeedback.heavyImpact();
      _showScanNotice('May issue sa ${item.code}: $msg', isError: true);
      // Auto-clear _lastCode after 1s so rider can quickly retry without delay
      Future.delayed(const Duration(milliseconds: 1000), () {
        if (mounted && _lastCode == item.code) {
          _lastCode = null;
        }
      });
    }
  }

  // -------------------------------------------------------------
  // SINGLE LOOKUP MODE
  // -------------------------------------------------------------
  Future<void> singleLookup() async {
    if (busy) return;
    FocusScope.of(context).unfocus();
    setState(() {
      busy = true;
      scanLocked = true;
      error = null;
    });
    try {
      final result = await sessionAction(() => api.smartScan(reference.text));
      if (!mounted) return;

      final parcel = result['parcel'] as Map<String, dynamic>?;
      final parcelId = parcel?['id'] as int?;
      final actionMessage = result['message'] as String? ??
          result['title'] as String? ??
          'Scan processed';

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(actionMessage),
          backgroundColor: RiderColors.teal,
          duration: const Duration(seconds: 4),
        ),
      );

      if (parcelId != null && parcelId > 0) {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => DeliveryDashboardPage(
              session: riderSession,
              parcelId: parcelId,
            ),
          ),
        );
        if (mounted) {
          setState(() {
            scanLocked = false;
            reference.clear();
            error = null;
          });
        }
      }
    } catch (smartScanErr) {
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
        if (mounted) {
          setState(() {
            scanLocked = false;
            reference.clear();
            error = null;
          });
        }
      } catch (failure) {
        if (mounted) {
          final msg = failure is SessionFailure
              ? failure.message
              : (smartScanErr is SessionFailure
                  ? smartScanErr.message
                  : 'Parcel lookup could not be confirmed.');
          setState(() => error = msg);
          _showScanNotice(msg, isError: true);
          SystemSound.play(SystemSoundType.alert);
          HapticFeedback.heavyImpact();
        }
      }
    } finally {
      if (mounted) {
        setState(() => busy = false);
        // Automatically unlock scanning after 1.5 seconds so rider can try again smoothly without getting stuck
        Future.delayed(const Duration(milliseconds: 1500), () {
          if (mounted && !busy) {
            setState(() => scanLocked = false);
          }
        });
      }
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

  void _clearBatch() {
    HapticFeedback.mediumImpact();
    setState(() {
      batchItems.clear();
      _lastCode = null;
      _lastScanTime = null;
    });
    _showScanNotice('Nalinis na ang batch scanner.');
  }

  void _finishAndGoToDeliveries() {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (_) => DeliveryDashboardPage(session: riderSession),
      ),
    );
  }

  void _showManualInputDialog() {
    final inputCtrl = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('I-type ang Reference'),
        content: TextField(
          controller: inputCtrl,
          textCapitalization: TextCapitalization.characters,
          autofocus: true,
          decoration: const InputDecoration(
            hintText: 'Halimbawa: ITEM-12',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Kanselahin'),
          ),
          FilledButton(
            onPressed: () {
              final val = inputCtrl.text.trim();
              Navigator.pop(ctx);
              if (val.isNotEmpty) {
                detected(val);
              }
            },
            child: const Text('I-scan / Tanggapin'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderColors.background,
      appBar: AppBar(
        title: const Text('Rider Bodega Scanner'),
        actions: [
          if (nativeCamera)
            IconButton(
              icon: Icon(isTorchOn ? Icons.flash_on : Icons.flash_off),
              tooltip: 'Toggle Flashlight',
              onPressed: () async {
                try {
                  await camera?.toggleTorch();
                  setState(() => isTorchOn = !isTorchOn);
                } catch (_) {}
              },
            ),
          IconButton(
            icon: const Icon(Icons.keyboard_alt_outlined),
            tooltip: 'Type code manually',
            onPressed: _showManualInputDialog,
          ),
        ],
      ),
      body: Column(
        children: [
          // Mode Switcher Banner
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            color: Colors.white,
            child: Row(
              children: [
                Expanded(
                  child: SegmentedButton<ScannerMode>(
                    segments: const [
                      ButtonSegment(
                        value: ScannerMode.continuousBatch,
                        label: Text('Mabilisang Bodega (Batch)'),
                        icon: Icon(Icons.bolt_outlined, size: 18),
                      ),
                      ButtonSegment(
                        value: ScannerMode.singleLookup,
                        label: Text('Single Lookup'),
                        icon: Icon(Icons.search_outlined, size: 18),
                      ),
                    ],
                    selected: {mode},
                    onSelectionChanged: (val) {
                      setState(() {
                        mode = val.first;
                        if (mode == ScannerMode.continuousBatch) {
                          scanLocked = false;
                          startCamera();
                        }
                      });
                    },
                    style: SegmentedButton.styleFrom(
                      selectedBackgroundColor: const Color(0xFF0F766E),
                      selectedForegroundColor: Colors.white,
                      textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Camera Viewport Area
          SizedBox(
            height: mode == ScannerMode.continuousBatch ? 260 : 310,
            width: double.infinity,
            child: Stack(
              fit: StackFit.expand,
              children: [
                if (widget.scannerBuilder != null)
                  widget.scannerBuilder!(detected)
                else if (!nativeCamera)
                  _buildScannerIllustration()
                else
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
                    errorBuilder: (_, failure) => _buildScannerIllustration(),
                  ),

                // High-Tech Courier Reticle HUD with Corner Brackets & Laser
                IgnorePointer(
                  child: Center(
                    child: SizedBox(
                      width: 290,
                      height: 160,
                      child: Stack(
                        alignment: Alignment.center,
                        children: [
                          CustomPaint(
                            size: const Size(290, 160),
                            painter: ScannerReticlePainter(
                              color: lastScannedToast != null
                                  ? (lastScannedToastIsError
                                      ? RiderColors.danger
                                      : const Color(0xFF10B981))
                                  : const Color(0xFFFBBF24),
                            ),
                          ),
                          // Luminous Center Scanning Beam
                          Container(
                            height: 2,
                            width: 240,
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                colors: [
                                  Colors.transparent,
                                  (lastScannedToast != null &&
                                          lastScannedToastIsError)
                                      ? const Color(0xFFEF4444)
                                      : const Color(0xFFFBBF24),
                                  Colors.transparent,
                                ],
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: (lastScannedToast != null &&
                                          lastScannedToastIsError)
                                      ? const Color(0xFFEF4444)
                                          .withValues(alpha: 0.8)
                                      : const Color(0xFFFBBF24)
                                          .withValues(alpha: 0.8),
                                  blurRadius: 6,
                                  spreadRadius: 1,
                                ),
                              ],
                            ),
                          ),
                          // Helper Alignment Tag
                          Positioned(
                            bottom: 8,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 3,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.black.withValues(alpha: 0.65),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Text(
                                'Itapat ang barcode o QR code',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),

                // Floating Viewfinder Flashlight (Torch) Button
                if (nativeCamera)
                  Positioned(
                    top: 10,
                    right: 12,
                    child: Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: () async {
                          try {
                            await camera?.toggleTorch();
                            setState(() => isTorchOn = !isTorchOn);
                          } catch (_) {}
                        },
                        borderRadius: BorderRadius.circular(16),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: isTorchOn
                                ? const Color(0xFFF59E0B)
                                : Colors.black.withValues(alpha: 0.6),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: isTorchOn
                                  ? const Color(0xFFFDE68A)
                                  : Colors.white.withValues(alpha: 0.3),
                            ),
                            boxShadow: isTorchOn
                                ? [
                                    BoxShadow(
                                      color: const Color(0xFFF59E0B)
                                          .withValues(alpha: 0.5),
                                      blurRadius: 10,
                                      spreadRadius: 1,
                                    ),
                                  ]
                                : null,
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                isTorchOn
                                    ? Icons.flash_on_rounded
                                    : Icons.flash_off_rounded,
                                color: isTorchOn
                                    ? const Color(0xFF78350F)
                                    : Colors.white,
                                size: 18,
                              ),
                              const SizedBox(width: 5),
                              Text(
                                isTorchOn ? 'Flash ON' : 'Flash',
                                style: TextStyle(
                                  color: isTorchOn
                                      ? const Color(0xFF78350F)
                                      : Colors.white,
                                  fontWeight: FontWeight.w800,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),

                // Live Toast Banner inside Camera
                if (lastScannedToast != null)
                  Positioned(
                    top: 12,
                    left: 20,
                    right: 20,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      decoration: BoxDecoration(
                        color: lastScannedToastIsError
                            ? const Color(0xFF991B1B).withValues(alpha: 0.9)
                            : const Color(0xFF0F766E).withValues(alpha: 0.9),
                        borderRadius: BorderRadius.circular(20),
                        boxShadow: const [
                          BoxShadow(color: Colors.black26, blurRadius: 6, offset: Offset(0, 2)),
                        ],
                      ),
                      child: Text(
                        lastScannedToast!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                        ),
                      ),
                    ),
                  ),

                // Batch Stats Overlay on Camera
                if (mode == ScannerMode.continuousBatch)
                  Positioned(
                    bottom: 10,
                    left: 14,
                    right: 14,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(
                            color: Colors.black.withValues(alpha: 0.65),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.inventory_2_outlined, color: Colors.white, size: 14),
                              const SizedBox(width: 5),
                              Text(
                                'Na-load: $successfulCount${errorCount > 0 ? ' · $errorCount Issue' : ''}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (totalBatchCod > 0)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                            decoration: BoxDecoration(
                              color: const Color(0xFFD97706).withValues(alpha: 0.9),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Text(
                              'COD: ₱${totalBatchCod.toStringAsFixed(2)}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
              ],
            ),
          ),

          // Content Area (Batch List or Single Lookup Controls)
          Expanded(
            child: mode == ScannerMode.continuousBatch
                ? _buildContinuousBatchView()
                : _buildSingleLookupView(),
          ),
        ],
      ),
    );
  }

  // -------------------------------------------------------------
  // CONTINUOUS BATCH VIEW (List of scanned parcels)
  // -------------------------------------------------------------
  Widget _buildContinuousBatchView() {
    return Column(
      children: [
        // Action Bar for Batch
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(bottom: BorderSide(color: RiderColors.border)),
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  'Talaan ng Na-scan (${batchItems.length} kabuuan)',
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
                ),
              ),
              if (batchItems.isNotEmpty)
                TextButton.icon(
                  onPressed: _clearBatch,
                  icon: const Icon(Icons.delete_sweep, size: 16),
                  label: const Text('Linisin', style: TextStyle(fontSize: 12)),
                  style: TextButton.styleFrom(foregroundColor: RiderColors.danger),
                ),
            ],
          ),
        ),

        // List of scanned items
        Expanded(
          child: batchItems.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: const [
                        Icon(Icons.qr_code_scanner, size: 48, color: Colors.grey),
                        SizedBox(height: 12),
                        Text(
                          'Mabilisang Pag-scan sa Bodega',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                        SizedBox(height: 6),
                        Text(
                          'Itapat ang camera sa mga barcode ng parcel nang sunod-sunod. Awtomatiko itong maia-assign sa iyong delivery run.',
                          textAlign: TextAlign.center,
                          style: TextStyle(fontSize: 13, color: Colors.grey),
                        ),
                      ],
                    ),
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: batchItems.length,
                  itemBuilder: (ctx, index) {
                    final item = batchItems[index];
                    final isSuccess = item.status == BatchItemStatus.success;
                    final isVerifying = item.status == BatchItemStatus.verifying;

                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                          color: isSuccess
                              ? const Color(0xFF10B981)
                              : (isVerifying ? const Color(0xFFF59E0B) : const Color(0xFFEF4444)),
                          width: 1.2,
                        ),
                      ),
                      child: Row(
                        children: [
                          // Status Icon
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: isSuccess
                                  ? const Color(0xFFD1FAE5)
                                  : (isVerifying ? const Color(0xFFFEF3C7) : const Color(0xFFFEE2E2)),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: isVerifying
                                ? const SizedBox(
                                    width: 16,
                                    height: 16,
                                    child: CircularProgressIndicator(strokeWidth: 2),
                                  )
                                : Icon(
                                    isSuccess ? Icons.check_circle : Icons.error,
                                    color: isSuccess ? const Color(0xFF059669) : const Color(0xFFDC2626),
                                    size: 18,
                                  ),
                          ),
                          const SizedBox(width: 12),
                          // Details
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Text(
                                      item.code,
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w900,
                                        fontSize: 15,
                                        color: RiderColors.text,
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    if (item.isCod)
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFFEF3C7),
                                          borderRadius: BorderRadius.circular(6),
                                        ),
                                        child: Text(
                                          'COD ₱${item.codAmount.toStringAsFixed(2)}',
                                          style: const TextStyle(
                                            color: Color(0xFF92400E),
                                            fontSize: 10,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  isSuccess
                                      ? '${item.buyerName ?? "Buyer"} · ${item.deliveryAddress ?? ""}'
                                      : (item.message ?? 'Inaalam ang status...'),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: isSuccess ? RiderColors.textSecondary : RiderColors.danger,
                                    fontWeight: isSuccess ? FontWeight.normal : FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          // Remove button
                          IconButton(
                            icon: const Icon(Icons.close, size: 18, color: Colors.grey),
                            onPressed: () {
                              setState(() => batchItems.removeAt(index));
                            },
                          ),
                        ],
                      ),
                    );
                  },
                ),
        ),

        // Bottom Finish Button
        Container(
          padding: const EdgeInsets.all(16),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: RiderColors.border)),
          ),
          child: SafeArea(
            top: false,
            child: SizedBox(
              width: double.infinity,
              height: 50,
              child: FilledButton.icon(
                onPressed: successfulCount > 0 ? _finishAndGoToDeliveries : null,
                icon: const Icon(Icons.local_shipping, size: 20),
                label: Text(
                  successfulCount > 0
                      ? 'Tapusin at Simulan ang Delivery Run ($successfulCount Parcels)'
                      : 'Mag-scan muna ng mga parcel',
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
                ),
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFF0F766E),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  // -------------------------------------------------------------
  // SINGLE LOOKUP VIEW (Old mode)
  // -------------------------------------------------------------
  Widget _buildSingleLookupView() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (error != null)
          Container(
            padding: const EdgeInsets.all(12),
            margin: const EdgeInsets.only(bottom: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFFEE2E2),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(error!, style: const TextStyle(color: RiderColors.danger, fontWeight: FontWeight.bold)),
          ),
        TextField(
          controller: reference,
          textCapitalization: TextCapitalization.characters,
          decoration: InputDecoration(
            hintText: 'ITEM-123',
            prefixIcon: const Icon(Icons.inventory_2_outlined),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
            filled: true,
            fillColor: Colors.white,
          ),
          onSubmitted: (_) => singleLookup(),
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: busy ? null : singleLookup,
          icon: const Icon(Icons.search),
          label: const Text('Buksan ang Detalye ng Parcel'),
          style: FilledButton.styleFrom(
            backgroundColor: RiderColors.teal,
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          ),
        ),
        const SizedBox(height: 8),
        if (scanLocked)
          TextButton.icon(
            onPressed: scanAgain,
            icon: const Icon(Icons.qr_code_scanner),
            label: const Text('Muling i-scan ang camera'),
          ),
        const SizedBox(height: 16),
        Center(
          child: TextButton(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => DeliveryHistoryPage(session: riderSession)),
            ),
            child: const Text('Tingnan ang Delivery History'),
          ),
        ),
      ],
    );
  }

  Widget _buildScannerIllustration() {
    return Container(
      color: const Color(0xFF1E293B),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.camera_alt, color: Colors.white54, size: 40),
            const SizedBox(height: 8),
            Text(
              cameraError ?? 'Camera is not active on this environment.',
              style: const TextStyle(color: Colors.white70, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}

class ScannerReticlePainter extends CustomPainter {
  final Color color;
  final double cornerLength;
  final double strokeWidth;

  ScannerReticlePainter({
    required this.color,
    this.cornerLength = 26.0,
    this.strokeWidth = 3.5,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = strokeWidth
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    final w = size.width;
    final h = size.height;
    final l = cornerLength;

    // Top-Left corner
    canvas.drawLine(Offset(0, l), const Offset(0, 0), paint);
    canvas.drawLine(const Offset(0, 0), Offset(l, 0), paint);

    // Top-Right corner
    canvas.drawLine(Offset(w - l, 0), Offset(w, 0), paint);
    canvas.drawLine(Offset(w, 0), Offset(w, l), paint);

    // Bottom-Left corner
    canvas.drawLine(Offset(0, h - l), Offset(0, h), paint);
    canvas.drawLine(Offset(0, h), Offset(l, h), paint);

    // Bottom-Right corner
    canvas.drawLine(Offset(w - l, h), Offset(w, h), paint);
    canvas.drawLine(Offset(w, h), Offset(w, h - l), paint);
  }

  @override
  bool shouldRepaint(covariant ScannerReticlePainter oldDelegate) =>
      oldDelegate.color != color;
}
