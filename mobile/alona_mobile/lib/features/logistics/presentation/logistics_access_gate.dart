import 'package:flutter/material.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';

/// Server approval remains authoritative. No background timer keeps tokens alive.
class LogisticsAccessGate extends StatefulWidget {
  const LogisticsAccessGate({
    super.key,
    required this.authState,
    required this.owner,
    required this.staff,
  });
  final AuthState authState;
  final Widget owner;
  final Widget staff;
  @override
  State<LogisticsAccessGate> createState() => _LogisticsAccessGateState();
}

class _LogisticsAccessGateState extends State<LogisticsAccessGate>
    with WidgetsBindingObserver {
  bool _loading = true, _approved = false, _staff = false;
  String? _message;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _check();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _check();
  }

  Future<void> _check() async {
    setState(() {
      _loading = true;
      _approved = false;
    });
    try {
      final response = await ApiClient.get('api/partner/profile');
      final data = response['data'];
      if (!mounted) return;
      setState(() {
        _approved =
            data['role'] == 'sorting_center' &&
            data['status'] == 'approved' &&
            data['approvalStatus'] == 'approved';
        _staff = data['operator']?['role'] == 'hub_staff';
        _message =
            data['reason']?.toString() ??
            'Your account is ${data['status']}. Complete account verification and approval in the web portal.';
        _loading = false;
      });
    } catch (error) {
      if (mounted) {
        setState(() {
          _message = error.toString();
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_approved) return _staff ? widget.staff : widget.owner;
    return Scaffold(
      appBar: AppBar(title: const Text('Logistics account')),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_message ?? 'Approval is required.'),
            const SizedBox(height: 16),
            FilledButton(onPressed: _check, child: const Text('Check again')),
            TextButton(
              onPressed: () => widget.authState.switchModule('buyer'),
              child: const Text('Back to Buyer'),
            ),
            TextButton(
              onPressed: () => widget.authState.clearLogisticsSession(),
              child: const Text('Sign out'),
            ),
          ],
        ),
      ),
    );
  }
}
