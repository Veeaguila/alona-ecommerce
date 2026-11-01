import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/presentation/buyer_main_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/auth/logistics_login_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/logistics_main_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  GoogleFonts.config.allowRuntimeFetching = false;
  await ApiConfig.init();
  final authState = AuthState();
  await authState.init();

  runApp(AlonaApp(authState: authState));
}

class AlonaApp extends StatelessWidget {
  final AuthState authState;

  const AlonaApp({super.key, required this.authState});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: authState,
      builder: (context, _) {
        return MaterialApp(
          key: ValueKey(
            '${authState.activeModule}:${authState.sessionRevision}',
          ),
          title: 'Alona E-Commerce',
          theme: AppTheme.lightTheme,
          debugShowCheckedModeBanner: false,
          home: _buildHome(),
        );
      },
    );
  }

  Widget _buildHome() {
    if (authState.isLogisticsModule) {
      if (!authState.isLogisticsAuthenticated) {
        return LogisticsLoginScreen(authState: authState);
      }
      return LogisticsMainScreen(authState: authState);
    }

    // Default: Buyer Module
    return BuyerMainScreen(authState: authState);
  }
}
