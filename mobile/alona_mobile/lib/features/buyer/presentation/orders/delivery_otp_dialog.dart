import 'dart:async';

import 'package:flutter/material.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';

/// Private, temporary display. The code never enters order DTOs, logs or storage.
class DeliveryOtpDialog extends StatefulWidget {
  final int orderId, parcelId;
  const DeliveryOtpDialog({
    super.key,
    required this.orderId,
    required this.parcelId,
  });
  @override
  State<DeliveryOtpDialog> createState() => _DeliveryOtpDialogState();
}

class _DeliveryOtpDialogState extends State<DeliveryOtpDialog>
    with WidgetsBindingObserver {
  String? _code, _error;
  bool _loading = true;
  Timer? _expiry;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
  }

  @override
  void dispose() {
    _expiry?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed && mounted) Navigator.pop(context);
  }

  Future<void> _load({bool renew = false}) async {
    setState(() {
      _loading = true;
      _error = null;
      _code = null;
    });
    _expiry?.cancel();
    try {
      final data = await BuyerService.getDeliveryOtp(
        widget.orderId,
        widget.parcelId,
        renew: renew,
      );
      if (!mounted) return;
      final expires = DateTime.parse(data['expires_at']).toUtc();
      final remaining = expires.difference(DateTime.now().toUtc());
      if (remaining <= Duration.zero) {
        throw Exception('Delivery OTP expired. Request a new code.');
      }
      setState(() {
        _code = data['otp'].toString();
        _loading = false;
      });
      _expiry = Timer(remaining, () {
        if (mounted) {
          setState(() {
            _code = null;
            _error = 'Delivery OTP expired. Request a new code.';
          });
        }
      });
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = error.toString();
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Delivery OTP'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Text(
          'Share this code with your assigned Rider only when your parcel is physically delivered.',
        ),
        const SizedBox(height: 16),
        if (_loading)
          const CircularProgressIndicator()
        else if (_code != null)
          Semantics(
            label: 'Delivery OTP code',
            child: Text(
              _code!,
              style: const TextStyle(
                fontSize: 30,
                fontWeight: FontWeight.bold,
                letterSpacing: 4,
              ),
            ),
          )
        else
          Text(_error ?? 'Code unavailable.'),
      ],
    ),
    actions: [
      if (!_loading && _code == null)
        TextButton(
          onPressed: () => _load(renew: true),
          child: const Text('Request new code'),
        ),
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Close'),
      ),
    ],
  );
}
