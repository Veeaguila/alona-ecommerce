import 'package:flutter/material.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/presentation/buyer_main_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';

class LogisticsLoginScreen extends StatelessWidget {
  final AuthState authState;

  const LogisticsLoginScreen({super.key, required this.authState});

  void _returnToBuyer(BuildContext context) {
    authState.switchModule('buyer');
    if (Navigator.canPop(context)) {
      Navigator.pop(context);
    } else {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => BuyerMainScreen(authState: authState),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return BuyerLoginScreen(
      authState: authState,
      onBackOverride: () => _returnToBuyer(context),
    );
  }
}
