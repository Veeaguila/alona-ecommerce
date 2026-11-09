import "dart:ui" as ui;

import "package:flutter/foundation.dart";
import "package:flutter/material.dart";

import '../widgets/rider_content.dart';

import "package:flutter/services.dart";
import "package:image_picker/image_picker.dart";
import "package:http/http.dart" as http;

import "../services/api_error.dart";
import "../services/api_config.dart";
import "../services/rider_session.dart";
import "../services/rider_session_guard.dart";

class ProofCapturePage extends StatefulWidget {
  final RiderSession? session;
  final Future<XFile?> Function()? capturePhoto;
  final Future<XFile?> Function()? choosePhoto;
  final bool openCameraOnStart;
  final int parcelId;
  final String deliveryAddress;

  const ProofCapturePage({
    super.key,
    this.session,
    this.capturePhoto,
    this.choosePhoto,
    this.openCameraOnStart = false,
    required this.parcelId,
    required this.deliveryAddress,
  });

  @override
  State<ProofCapturePage> createState() => _ProofCapturePageState();
}

class _ProofCapturePageState extends State<ProofCapturePage>
    with RiderSessionGuard<ProofCapturePage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  final ImagePicker _imagePicker = ImagePicker();
  XFile? _capturedImage;
  Uint8List? _capturedBytes;
  final TextEditingController _notesController = TextEditingController();
  bool _isUploading = false;
  bool _isCapturing = false;
  String? _error;
  bool get _nativeCamera =>
      !kIsWeb &&
      [
        TargetPlatform.android,
        TargetPlatform.iOS,
      ].contains(defaultTargetPlatform);
  bool get _busy => _isCapturing || _isUploading;

  @override
  void initState() {
    super.initState();
    if (widget.openCameraOnStart && _nativeCamera) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _capturePhoto();
      });
    }
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _capturePhoto({bool fromGallery = false}) async {
    if (_busy) return;
    setState(() {
      _isCapturing = true;
      _error = null;
    });
    try {
      // Browser file selection must start in the user's click, before network awaits.
      // Selection never mutates a parcel; readiness is checked before using/uploading it.
      if (!fromGallery) await readyToken();
      final injected = fromGallery ? widget.choosePhoto : widget.capturePhoto;
      final XFile? photo = injected != null
          ? await injected()
          : await _imagePicker.pickImage(
              source: fromGallery ? ImageSource.gallery : ImageSource.camera,
              maxWidth: 1200,
              maxHeight: 1200,
              imageQuality: 85,
            );
      if (photo != null) {
        if (fromGallery) await readyToken();
        if (await photo.length() > 20 * 1024 * 1024) {
          throw const SessionFailure("Proof must be at most 20 MB.");
        }
        final bytes = await photo.readAsBytes();
        if (bytes.isEmpty) {
          throw const SessionFailure("The photo is empty. Take another photo.");
        }
        final buffer = await ui.ImmutableBuffer.fromUint8List(bytes);
        // The codec callback exposes intrinsic dimensions on both native and web.
        // Encoded ImageDescriptor.width/height are unsupported in Flutter web.
        final codec = await ui.instantiateImageCodecWithSize(
          buffer,
          getTargetSize: (width, height) {
            if (width > 6000 || height > 6000) {
              throw const SessionFailure(
                "Proof images must be no larger than 6000 × 6000 pixels.",
              );
            }
            return ui.TargetImageSize(width: width > 1200 ? 1200 : width);
          },
        );
        try {
          final frame = await codec.getNextFrame();
          frame.image.dispose();
        } finally {
          codec.dispose();
        }
        if (bytes.length > 20 * 1024 * 1024) {
          throw const SessionFailure("Proof must be at most 20 MB.");
        }
        if (!mounted) return;
        setState(() {
          _capturedImage = photo;
          _capturedBytes = bytes;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = switch (e) {
          SessionFailure() => e.message,
          PlatformException(
            code: 'camera_access_denied' ||
                'camera_access_denied_without_prompt' ||
                'camera_access_restricted',
          ) =>
            'Camera access is blocked. Allow camera access in your device settings, then try again.',
          PlatformException(
            code: 'photo_access_denied' ||
                'photo_access_denied_without_prompt' ||
                'photo_access_restricted',
          ) =>
            'Photo access is blocked. Allow photo access in your device settings, then try again.',
          MissingPluginException() || UnsupportedError() => 'Camera capture is unavailable on this device. Use the Android or iOS Rider app, or select a real proof photo.',
          _ => 'Could not read a valid photo. Take another photo and retry.',
        },
      );
    } finally {
      if (mounted) setState(() => _isCapturing = false);
    }
  }

  Future<void> _uploadProof() async {
    if (_busy) return;
    if (_capturedImage == null || _capturedBytes == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Please capture a photo')));
      return;
    }
    if (_capturedBytes!.length > 20 * 1024 * 1024 ||
        _notesController.text.length > 500) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Proof must be at most 20 MB and notes at most 500 characters.',
          ),
        ),
      );
      return;
    }
    setState(() {
      _isUploading = true;
      _error = null;
    });
    try {
      final token = await readyToken();
      final request =
          http.MultipartRequest(
              'POST',
              Uri.parse('$deliveriesUrl/${widget.parcelId}/upload-proof'),
            )
            ..headers['Authorization'] = 'Bearer $token'
            ..headers['Accept'] = 'application/json'
            ..fields['type'] = 'PHOTO'
            ..fields['notes'] = _notesController.text
            ..files.add(
              http.MultipartFile.fromBytes(
                'file',
                _capturedBytes!,
                filename: _capturedImage!.name,
              ),
            );
      final response = await riderSession.sendMultipart(request);
      await checkAuthorization(response.statusCode);
      if (response.statusCode != 201) {
        throw SessionFailure(apiErrorMessage(response));
      }
      if (!mounted) return;
      setState(() => _isUploading = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Proof uploaded!')));
      Navigator.of(context).pop(true);
    } catch (failure) {
      if (mounted) {
        setState(
          () => _error = failure is SessionFailure
              ? failure.message
              : 'Proof upload could not be confirmed. Refresh before retrying.',
        );
      }
    } finally {
      if (mounted) setState(() => _isUploading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          "Delivery Photo Proof",
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
        ),
        backgroundColor: const Color(0xFF0F766E),
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        child: Padding(
          padding: riderPagePadding(context, maxWidth: 640),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Address Card
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.03),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEE2E2),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(
                        Icons.location_on_rounded,
                        color: Color(0xFFDC2626),
                        size: 20,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            "Delivery Destination",
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 12,
                              color: Color(0xFF64748B),
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            widget.deliveryAddress,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                              color: Color(0xFF1E293B),
                              height: 1.3,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // Photography Guidelines Pill
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFF0FDFA),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFCCFBF1)),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(
                      Icons.tips_and_updates_outlined,
                      color: Color(0xFF0F766E),
                      size: 20,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: const [
                          Text(
                            "Gabay sa Magandang Proof of Delivery:",
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 13,
                              color: Color(0xFF0F766E),
                            ),
                          ),
                          SizedBox(height: 4),
                          Text(
                            "• Isama sa kuha ang parcel at ang pinto/gate ng bahay.\n• Kung maaari, ipakita ang house number o landmark.",
                            style: TextStyle(
                              fontSize: 12,
                              color: Color(0xFF134E4A),
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // Camera Viewport & Capture Card
              Container(
                width: double.infinity,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: _capturedImage != null
                        ? const Color(0xFF10B981)
                        : const Color(0xFFCBD5E1),
                    width: _capturedImage != null ? 2 : 1,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.04),
                      blurRadius: 10,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(19),
                  child: Column(
                    children: [
                      if (_capturedImage != null) ...[
                        Stack(
                          children: [
                            Image.memory(
                              _capturedBytes!,
                              height: 280,
                              width: double.infinity,
                              fit: BoxFit.cover,
                              cacheWidth: 1200,
                            ),
                            Positioned(
                              top: 12,
                              left: 12,
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 5,
                                ),
                                decoration: BoxDecoration(
                                  color: const Color(0xFF059669),
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: const [
                                    Icon(
                                      Icons.check_circle_rounded,
                                      color: Colors.white,
                                      size: 16,
                                    ),
                                    SizedBox(width: 5),
                                    Text(
                                      "Photo Attached",
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ] else ...[
                        Container(
                          height: 220,
                          width: double.infinity,
                          color: const Color(0xFFF1F5F9),
                          child: Center(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(16),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    shape: BoxShape.circle,
                                    boxShadow: [
                                      BoxShadow(
                                        color: Colors.black.withValues(alpha: 0.05),
                                        blurRadius: 8,
                                        offset: const Offset(0, 2),
                                      ),
                                    ],
                                  ),
                                  child: const Icon(
                                    Icons.add_a_photo_outlined,
                                    size: 38,
                                    color: Color(0xFF0F766E),
                                  ),
                                ),
                                const SizedBox(height: 12),
                                const Text(
                                  "Wala pang delivery photo",
                                  style: TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 15,
                                    color: Color(0xFF334155),
                                  ),
                                ),
                                const SizedBox(height: 3),
                                const Text(
                                  "Pindutin ang button sa ibaba para kumuha ng litrato",
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: Color(0xFF64748B),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                      Padding(
                        padding: const EdgeInsets.all(14),
                        child: Row(
                          children: [
                            Expanded(
                              child: ElevatedButton.icon(
                                onPressed: _busy
                                    ? null
                                    : () => _capturePhoto(fromGallery: false),
                                icon: _isCapturing
                                    ? const SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                          valueColor: AlwaysStoppedAnimation(
                                            Colors.white,
                                          ),
                                        ),
                                      )
                                    : Icon(
                                        _capturedImage == null
                                            ? Icons.camera_alt_rounded
                                            : Icons.refresh_rounded,
                                        size: 18,
                                      ),
                                label: Text(
                                  _isCapturing
                                      ? 'Inihahanda...'
                                      : _capturedImage == null
                                      ? 'Buksan ang Camera'
                                      : 'Kumuha Muli ng Photo',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 14,
                                  ),
                                ),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF0F766E),
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(
                                    vertical: 14,
                                  ),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(14),
                                  ),
                                ),
                              ),
                            ),
                            if (!_nativeCamera) ...[
                              const SizedBox(width: 10),
                              IconButton.filledTonal(
                                tooltip: 'Pumili sa Gallery',
                                onPressed: _busy
                                    ? null
                                    : () => _capturePhoto(fromGallery: true),
                                icon: const Icon(Icons.photo_library_outlined),
                                style: IconButton.styleFrom(
                                  padding: const EdgeInsets.all(14),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(14),
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              if (_error != null) ...[
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEE2E2),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFFECACA)),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.error_outline_rounded,
                        color: Color(0xFFDC2626),
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _error!,
                          style: const TextStyle(
                            color: Color(0xFF991B1B),
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 20),

              // Notes Input
              const Text(
                "Karagdagang Tala (Optional)",
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 14,
                  color: Color(0xFF1E293B),
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _notesController,
                maxLines: 2,
                maxLength: 500,
                decoration: InputDecoration(
                  hintText: "Hal. Iniwan kay Nanay / Natanggap ng security guard...",
                  prefixIcon: const Icon(
                    Icons.edit_note_rounded,
                    color: Color(0xFF64748B),
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: const BorderSide(color: Color(0xFFCBD5E1)),
                  ),
                  filled: true,
                  fillColor: Colors.white,
                ),
              ),

              const SizedBox(height: 20),

              // Giant Action Button: Submit Proof
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: _busy || _capturedBytes == null ? null : _uploadProof,
                  icon: _isUploading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation(Colors.white),
                          ),
                        )
                      : const Icon(Icons.cloud_upload_rounded, size: 22),
                  label: Text(
                    _isUploading
                        ? "Ipinapasa ang Litrato..."
                        : "I-SUBMIT ANG DELIVERY PROOF",
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 15,
                      letterSpacing: 0.5,
                    ),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF059669),
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: const Color(0xFF94A3B8),
                    disabledForegroundColor: Colors.white70,
                    elevation: _capturedBytes != null ? 3 : 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 12),

              Center(
                child: TextButton(
                  onPressed: _busy ? null : () => Navigator.pop(context),
                  child: const Text(
                    "Kanselahin at Bumalik",
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF64748B),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}

