import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/shared/widgets/alona_auth_scaffold.dart';
import 'package:alona_mobile/features/buyer/presentation/buyer_main_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/logistics_main_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/auth/logistics_register_screen.dart';

class LogisticsLoginScreen extends StatefulWidget {
  final AuthState authState;

  const LogisticsLoginScreen({super.key, required this.authState});

  @override
  State<LogisticsLoginScreen> createState() => _LogisticsLoginScreenState();
}

class _LogisticsLoginScreenState extends State<LogisticsLoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _rememberMe = true;
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

  void _returnToBuyer() {
    widget.authState.switchModule('buyer');
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (_) => BuyerMainScreen(authState: widget.authState),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AlonaAuthScaffold(
      onBack: _returnToBuyer,
      trailing: IconButton(
        icon: const Icon(Icons.close, color: Colors.white, size: 22),
        onPressed: _returnToBuyer,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Module badge pill
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.25)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.local_shipping_rounded, color: AlonaAuthColors.tealAccent, size: 14),
                      const SizedBox(width: 6),
                      Text(
                        'Logistics Hub Portal',
                        style: GoogleFonts.figtree(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),

            // Heading & Subtitle
            Text(
              'Welcome back',
              style: GoogleFonts.figtree(
                fontSize: 28,
                fontWeight: FontWeight.w800,
                color: Colors.white,
                letterSpacing: -0.5,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Sign in to access sorting hub and dispatch operations.',
              style: GoogleFonts.figtree(
                fontSize: 14,
                color: Colors.white.withValues(alpha: 0.85),
              ),
            ),
            const SizedBox(height: 24),

            // Error banner if any
            if (_errorMessage != null) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF2F2),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFFCA5A5)),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.error_outline, color: Color(0xFFDC2626), size: 20),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        _errorMessage!,
                        style: GoogleFonts.figtree(
                          color: const Color(0xFFDC2626),
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
            ],

            // Email Address Input (Crisp white card)
            AlonaAuthTextField(
              label: 'Staff / Center Email',
              controller: _emailController,
              hintText: 'sc.hub@shopalona.online',
              keyboardType: TextInputType.emailAddress,
            ),
            const SizedBox(height: 16),

            // Password Input
            AlonaAuthTextField(
              label: 'Password',
              controller: _passwordController,
              hintText: 'Enter your password',
              obscureText: _obscurePassword,
              suffixIcon: IconButton(
                icon: Icon(
                  _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                  size: 20,
                  color: AlonaAuthColors.hintText,
                ),
                onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
              ),
            ),
            const SizedBox(height: 14),

            // Remember me checkbox
            Row(
              children: [
                SizedBox(
                  width: 20,
                  height: 20,
                  child: Checkbox(
                    value: _rememberMe,
                    onChanged: (val) => setState(() => _rememberMe = val ?? true),
                    activeColor: AlonaAuthColors.amber,
                    checkColor: AlonaAuthColors.darkText,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                    side: const BorderSide(color: Colors.white, width: 1.5),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  'Remember me',
                  style: GoogleFonts.figtree(
                    fontSize: 13.5,
                    color: Colors.white.withValues(alpha: 0.9),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Golden Primary Button: Log in
            AlonaPrimaryButton(
              label: 'Log in',
              isLoading: _isLoading,
              onPressed: _login,
            ),
            const SizedBox(height: 24),

            // Register as Sorting Center link
            Center(
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'New sorting center partner? ',
                    style: GoogleFonts.figtree(
                      fontSize: 14,
                      color: Colors.white.withValues(alpha: 0.85),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  GestureDetector(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => LogisticsRegisterScreen(authState: widget.authState),
                        ),
                      );
                    },
                    child: Text(
                      'Apply as Hub',
                      style: GoogleFonts.figtree(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: AlonaAuthColors.amber,
                        decoration: TextDecoration.underline,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 28),

            // Return to Buyer Marketplace Link
            Center(
              child: GestureDetector(
                onTap: _returnToBuyer,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.storefront_outlined,
                      color: Colors.white70,
                      size: 18,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Return to Buyer Marketplace',
                      style: GoogleFonts.figtree(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                        decoration: TextDecoration.underline,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}
