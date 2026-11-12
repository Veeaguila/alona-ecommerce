import 'package:flutter/material.dart';
import 'pages/login_page.dart';
import 'pages/rider_shell_page.dart';
import 'services/rider_session.dart';

class RiderEntryGate extends StatefulWidget {
  const RiderEntryGate({super.key});

  @override
  State<RiderEntryGate> createState() => _RiderEntryGateState();
}

class _RiderEntryGateState extends State<RiderEntryGate> {
  final _session = RiderSession();
  bool _checking = true;
  bool _hasToken = false;

  @override
  void initState() {
    super.initState();
    _check();
  }

  Future<void> _check() async {
    final token = await _session.tokens.read();
    if (!mounted) return;
    setState(() {
      _hasToken = token != null && token.isNotEmpty;
      _checking = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_checking) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(color: Color(0xFF087F8C)),
        ),
      );
    }
    if (_hasToken) {
      return RiderShellPage(session: _session);
    }
    return LoginPage(session: _session);
  }
}

