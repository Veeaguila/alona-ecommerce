import 'package:flutter/material.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import '../services/rider_session.dart';

class LoginPage extends StatelessWidget {
  final String? initialMessage;
  final RiderSession? session;

  const LoginPage({super.key, this.initialMessage, this.session});

  @override
  Widget build(BuildContext context) {
    return BuyerLoginScreen(
      authState: AuthState.instance,
      initialMessage: initialMessage,
      riderSession: session,
    );
  }
}
