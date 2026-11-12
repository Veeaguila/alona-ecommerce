import 'package:flutter/material.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/presentation/buyer_main_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/auth/logistics_login_screen.dart';

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

    final isUnauthenticated = !widget.authState.isLogisticsAuthenticated ||
        (_message != null && _message!.toLowerCase().contains('unauthenticated'));

    return Scaffold(
      appBar: AppBar(title: const Text('Logistics account')),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              isUnauthenticated ? Icons.lock_outline_rounded : Icons.pending_actions_outlined,
              size: 54,
              color: isUnauthenticated ? const Color(0xFF64748B) : const Color(0xFFD97706),
            ),
            const SizedBox(height: 16),
            Text(
              isUnauthenticated
                  ? 'Sign in required to access Logistics Sorting Center tools.'
                  : (_message ?? 'Approval is required.'),
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 24),
            if (isUnauthenticated)
              FilledButton.icon(
                onPressed: () {
                  Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(
                      builder: (_) => LogisticsLoginScreen(authState: widget.authState),
                    ),
                  );
                },
                icon: const Icon(Icons.login),
                label: const Text('Sign in to Logistics'),
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFF087F8C),
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                ),
              )
            else
              FilledButton(onPressed: _check, child: const Text('Check again')),
            const SizedBox(height: 12),
            TextButton.icon(
              onPressed: () async {
                await widget.authState.switchModule('buyer');
                if (context.mounted) {
                  Navigator.pushAndRemoveUntil(
                    context,
                    MaterialPageRoute(
                      builder: (_) => BuyerMainScreen(authState: widget.authState),
                    ),
                    (route) => false,
                  );
                }
              },
              icon: const Icon(Icons.arrow_back),
              label: const Text('Back to Buyer Home'),
            ),
            if (widget.authState.isLogisticsAuthenticated)
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
