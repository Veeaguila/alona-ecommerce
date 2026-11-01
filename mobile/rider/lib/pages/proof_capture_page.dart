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
      appBar: AppBar(
        title: const Text("Capture Delivery Proof"),
        backgroundColor: const Color(0xFF1f3a70),
      ),
      body: SingleChildScrollView(
        child: Padding(
          padding: riderPagePadding(context, maxWidth: 640),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        "Delivery Address",
                        style: TextStyle(fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        widget.deliveryAddress,
                        style: const TextStyle(color: Colors.grey),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                "Delivery photo",
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const Text(
                'Capture a clear photo of this delivery. A signature alone cannot confirm delivery.',
              ),
              const SizedBox(height: 20),
              _capturedImage != null
                  ? Image.memory(
                      _capturedBytes!,
                      height: 300,
                      width: double.infinity,
                      fit: BoxFit.cover,
                      cacheWidth: 1200,
                    )
                  : Container(
                      height: 200,
                      width: double.infinity,
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.grey),
                      ),
                      child: const Center(
                        child: Text(
                          "No image captured",
                          style: TextStyle(color: Colors.grey),
                        ),
                      ),
                    ),
              const SizedBox(height: 12),
              if (!_nativeCamera) ...[
                const Text(
                  'Live camera capture is available in the Android and iOS Rider app. Here, select a real photo to submit as proof.',
                ),
                const SizedBox(height: 12),
              ],
              if (_error != null) ...[
                Text(_error!, style: const TextStyle(color: Color(0xff9b3125))),
                const SizedBox(height: 12),
              ],
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _busy
                      ? null
                      : () => _capturePhoto(fromGallery: !_nativeCamera),
                  icon: _isCapturing
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Icon(
                          _nativeCamera
                              ? Icons.camera_alt
                              : Icons.photo_library_outlined,
                        ),
                  label: Text(
                    _isCapturing
                        ? 'Opening photo…'
                        : _nativeCamera
                        ? (_capturedImage == null
                              ? 'Capture Photo'
                              : 'Retake Photo')
                        : (_capturedImage == null
                              ? 'Choose proof photo'
                              : 'Choose another photo'),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF1f3a70),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                "Notes",
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _notesController,
                maxLines: 3,
                maxLength: 500,
                decoration: InputDecoration(
                  hintText: "Add notes about delivery...",
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _busy || _capturedBytes == null
                      ? null
                      : _uploadProof,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  child: _isUploading
                      ? const CircularProgressIndicator(
                          valueColor: AlwaysStoppedAnimation(Colors.white),
                        )
                      : const Text("Upload Proof"),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: _busy ? null : () => Navigator.pop(context),
                  child: const Text("Cancel"),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
