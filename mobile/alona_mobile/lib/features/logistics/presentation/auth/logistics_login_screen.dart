import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/shared/widgets/alona_text_field.dart';
import 'package:alona_mobile/features/buyer/presentation/buyer_main_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/logistics_main_screen.dart';

class LogisticsLoginScreen extends StatefulWidget {
  final AuthState authState;

  const LogisticsLoginScreen({super.key, required this.authState});

  @override
  State<LogisticsLoginScreen> createState() => _LogisticsLoginScreenState();
}

class _LogisticsLoginScreenState extends State<LogisticsLoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (_isLoading) return;
    final email = _emailController.text.trim();
    final password = _passwordController.text;

    if (email.isEmpty || password.isEmpty) {
      setState(() => _errorMessage = 'Please enter both email and password.');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final res = await ApiClient.post(
        'api/sorting-center/login',
        body: {'email': email, 'password': password},
      );

      final token = res['token'] as String;
      final user = res['user'] as Map<String, dynamic>;
      final sc = res['sorting_center'] as Map<String, dynamic>?;

      await widget.authState.setLogisticsSession(
        token,
        user,
        sc,
        operator: res['operator'] == null
            ? null
            : Map<String, dynamic>.from(res['operator']),
        accountStatus: res['account_status'] as String?,
      );

      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => LogisticsMainScreen(authState: widget.authState),
          ),
        );
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = e.message;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0B3C49),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconButton(
                icon: const Icon(Icons.arrow_back, color: Colors.white),
                onPressed: () {
                  widget.authState.switchModule('buyer');
                  Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          BuyerMainScreen(authState: widget.authState),
                    ),
                  );
                },
              ),
              const SizedBox(height: 20),
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Center(
                  child: Icon(
                    Icons.local_shipping_rounded,
                    color: Colors.white,
                    size: 32,
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Text(
                'Logistics Hub Portal',
                style: GoogleFonts.figtree(
                  fontSize: 26,
                  fontWeight: FontWeight.w800,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'Authorized access for sorting center supervisors, managers, and fulfillment operations.',
                style: GoogleFonts.figtree(
                  fontSize: 13,
                  color: Colors.white70,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 32),

              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (_errorMessage != null) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEE2E2),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: const Color(0xFFFCA5A5)),
                        ),
                        child: Text(
                          _errorMessage!,
                          style: GoogleFonts.figtree(
                            color: AppColors.error,
                            fontSize: 13,
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],

                    AlonaTextField(
                      label: 'Staff / Center Email',
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      hint: 'sc.hub@shopalona.online',
                      prefixIcon: const Icon(
                        Icons.email_outlined,
                        color: AppColors.textMuted,
                      ),
                    ),
                    const SizedBox(height: 16),
                    AlonaTextField(
                      label: 'Password',
                      controller: _passwordController,
                      isPassword: true,
                      hint: '••••••••',
                      prefixIcon: const Icon(
                        Icons.lock_outline,
                        color: AppColors.textMuted,
                      ),
                    ),
                    const SizedBox(height: 24),

                    AlonaButton(
                      text: 'Log In to Logistics',
                      isLoading: _isLoading,
                      onPressed: _login,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 30),

              Center(
                child: TextButton.icon(
                  onPressed: () {
                    widget.authState.switchModule('buyer');
                    Navigator.pushReplacement(
                      context,
                      MaterialPageRoute(
                        builder: (_) =>
                            BuyerMainScreen(authState: widget.authState),
                      ),
                    );
                  },
                  icon: const Icon(
                    Icons.storefront_outlined,
                    color: Colors.white70,
                    size: 18,
                  ),
                  label: Text(
                    'Return to Buyer Marketplace',
                    style: GoogleFonts.figtree(
                      color: Colors.white,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
