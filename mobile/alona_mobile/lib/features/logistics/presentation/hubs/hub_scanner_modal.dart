import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

class HubScannerResult {
  final String? singleCode;
  final List<String> batchCodes;
  final bool isBatch;

  const HubScannerResult.single(this.singleCode)
      : batchCodes = const [],
        isBatch = false;

  const HubScannerResult.batch(this.batchCodes)
      : singleCode = null,
        isBatch = true;
}

class HubScannerModal extends StatefulWidget {
  final bool initialBatchMode;

  const HubScannerModal({
    super.key,
    this.initialBatchMode = false,
  });

  static Future<HubScannerResult?> show(
    BuildContext context, {
    bool initialBatchMode = false,
  }) {
    return Navigator.push<HubScannerResult>(
      context,
      MaterialPageRoute(
        builder: (_) => HubScannerModal(initialBatchMode: initialBatchMode),
        fullscreenDialog: true,
      ),
    );
  }

  @override
  State<HubScannerModal> createState() => _HubScannerModalState();
}

class _HubScannerModalState extends State<HubScannerModal>
    with WidgetsBindingObserver {
  MobileScannerController? _camera;
  late bool _batchMode;
  final Set<String> _scannedCodes = {};
  final TextEditingController _manualController = TextEditingController();
  bool _torchOn = false;
  String? _lastScanned;
  DateTime? _lastScannedTime;

  bool get _nativeCamera =>
      !kIsWeb &&
      [
        TargetPlatform.android,
        TargetPlatform.iOS,
      ].contains(defaultTargetPlatform);

  @override
  void initState() {
    super.initState();
    _batchMode = widget.initialBatchMode;
    if (_nativeCamera) {
      _camera = MobileScannerController(
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
    if (_camera?.value.hasCameraPermission != true) return;
    if (state == AppLifecycleState.resumed &&
        ModalRoute.of(context)?.isCurrent == true) {
      _camera?.start();
    } else if (state != AppLifecycleState.resumed) {
      _camera?.stop();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _manualController.dispose();
    _camera?.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    final now = DateTime.now();
    for (final barcode in capture.barcodes) {
      final code = barcode.rawValue?.trim();
      if (code != null && code.isNotEmpty) {
        // Prevent rapid duplicate scans within 1.5 seconds
        if (_lastScanned == code &&
            _lastScannedTime != null &&
            now.difference(_lastScannedTime!).inMilliseconds < 1500) {
          continue;
        }
        _processCode(code);
        break;
      }
    }
  }

  void _processCode(String rawCode) {
    final code = rawCode.trim();
    if (code.isEmpty) return;

    _lastScanned = code;
    _lastScannedTime = DateTime.now();

    if (!_batchMode) {
      HapticFeedback.mediumImpact();
      Navigator.pop(context, HubScannerResult.single(code));
      return;
    }

    // Batch mode:
    HapticFeedback.lightImpact();
    final isNew = _scannedCodes.add(code);
    setState(() {});

    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          isNew
              ? 'Added: $code (${_scannedCodes.length} in batch)'
              : 'Already scanned: $code',
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
        duration: const Duration(seconds: 1),
        backgroundColor: isNew ? const Color(0xFF0F766E) : const Color(0xFF64748B),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  void _finishBatch() {
    Navigator.pop(context, HubScannerResult.batch(_scannedCodes.toList()));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(_batchMode ? 'Batch Hub Scanner' : 'Scan Parcel'),
        actions: [
          if (_nativeCamera)
            IconButton(
              icon: Icon(_torchOn ? Icons.flash_on : Icons.flash_off),
              tooltip: 'Toggle Flash',
              onPressed: () async {
                await _camera?.toggleTorch();
                setState(() => _torchOn = !_torchOn);
              },
            ),
        ],
      ),
      body: Column(
        children: [
          // Mode switch bar
          Container(
            color: const Color(0xFF1E293B),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Icon(
                      _batchMode ? Icons.checklist_rtl : Icons.crop_free,
                      color: _batchMode ? Colors.tealAccent : Colors.amberAccent,
                      size: 20,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      _batchMode ? 'Batch Multi-Scan' : 'Single Parcel Mode',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                    ),
                  ],
                ),
                Row(
                  children: [
                    const Text('Batch', style: TextStyle(color: Colors.white70, fontSize: 13)),
                    Switch(
                      value: _batchMode,
                      activeTrackColor: Colors.teal.shade800,
                      activeThumbColor: Colors.tealAccent,
                      onChanged: (val) {
                        setState(() => _batchMode = val);
                      },
                    ),
                  ],
                ),
              ],
            ),
          ),

          // Camera Viewfinder Area
          Expanded(
            child: Stack(
              fit: StackFit.expand,
              children: [
                if (_nativeCamera && _camera != null)
                  MobileScanner(
                    controller: _camera,
                    onDetect: _onDetect,
                    errorBuilder: (ctx, error) => _buildErrorFallback(error.toString()),
                  )
                else
                  _buildWebFallback(),

                // Viewfinder Box Overlay
                Center(
                  child: Container(
                    width: 260,
                    height: 180,
                    decoration: BoxDecoration(
                      border: Border.all(
                        color: _batchMode ? Colors.tealAccent : Colors.amberAccent,
                        width: 3,
                      ),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(
                          color: (_batchMode ? Colors.tealAccent : Colors.amberAccent)
                              .withValues(alpha: 0.3),
                          blurRadius: 12,
                          spreadRadius: 2,
                        ),
                      ],
                    ),
                    child: Center(
                      child: Container(
                        height: 2,
                        width: 230,
                        color: _batchMode ? Colors.tealAccent : Colors.amberAccent,
                      ),
                    ),
                  ),
                ),

                // Batch Counter Pill
                if (_batchMode)
                  Positioned(
                    top: 16,
                    left: 20,
                    right: 20,
                    child: Center(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        decoration: BoxDecoration(
                          color: const Color(0xFF0F766E).withValues(alpha: 0.9),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: Colors.tealAccent, width: 1.5),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.inventory_2_outlined, color: Colors.white, size: 16),
                            const SizedBox(width: 6),
                            Text(
                              '${_scannedCodes.length} Parcel(s) Scanned',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),

          // Bottom Control Panel & Manual Input Fallback
          Container(
            color: const Color(0xFF0F172A),
            padding: const EdgeInsets.all(16),
            child: SafeArea(
              top: false,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _manualController,
                          style: const TextStyle(color: Colors.white),
                          decoration: InputDecoration(
                            isDense: true,
                            hintText: 'Enter barcode manually (e.g. ITEM-123)',
                            hintStyle: const TextStyle(color: Colors.white54, fontSize: 13),
                            filled: true,
                            fillColor: const Color(0xFF1E293B),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(10),
                              borderSide: BorderSide.none,
                            ),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                          ),
                          onSubmitted: (val) {
                            _processCode(val);
                            _manualController.clear();
                          },
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton.filled(
                        style: IconButton.styleFrom(
                          backgroundColor: _batchMode ? const Color(0xFF0F766E) : const Color(0xFFD97706),
                        ),
                        icon: const Icon(Icons.add, color: Colors.white),
                        onPressed: () {
                          _processCode(_manualController.text);
                          _manualController.clear();
                        },
                      ),
                    ],
                  ),
                  if (_batchMode) ...[
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        style: FilledButton.styleFrom(
                          backgroundColor: const Color(0xFF0D9488),
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        icon: const Icon(Icons.check_circle_outline),
                        label: Text(
                          'Done Scanning (${_scannedCodes.length} Selected)',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                        ),
                        onPressed: _finishBatch,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildWebFallback() {
    return Container(
      color: const Color(0xFF1E293B),
      child: const Center(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.qr_code_scanner, size: 64, color: Colors.white54),
              SizedBox(height: 16),
              Text(
                'Camera scanner is available on mobile devices (Android/iOS). Use the manual entry below to look up parcels.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white70),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildErrorFallback(String error) {
    return Container(
      color: Colors.black87,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.no_photography_outlined, size: 48, color: Colors.amberAccent),
              const SizedBox(height: 12),
              const Text(
                'Camera could not start. Check app camera permissions.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white),
              ),
              const SizedBox(height: 8),
              Text(
                error,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white54, fontSize: 11),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
