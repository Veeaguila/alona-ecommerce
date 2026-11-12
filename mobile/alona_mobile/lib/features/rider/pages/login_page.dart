import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../services/api_error.dart';
import '../services/api_config.dart';
import '../services/rider_session.dart';
import 'rider_shell_page.dart';
import 'registration_page.dart';
import 'rider_portal_page.dart';
import '../../../shared/widgets/alona_auth_scaffold.dart';

class LoginPage extends StatefulWidget {
  final String? initialMessage;
  final RiderSession? session;
  const LoginPage({super.key, this.initialMessage, this.session});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final formKey = GlobalKey<FormState>();
  final email = TextEditingController();
  final password = TextEditingController();
  late final riderSession = widget.session ?? RiderSession();
  bool submitting = false;
  bool showPassword = false;
  bool rememberMe = true;
  String? message;
  bool success = false;

  @override
  void initState() {
    super.initState();
    message = widget.initialMessage;
  }

  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (submitting) return;
    FocusScope.of(context).unfocus();
    setState(() {
      message = null;
      success = false;
    });
    if (!formKey.currentState!.validate()) return;
    setState(() => submitting = true);
    try {
      final response = await riderSession.post(
        Uri.parse(loginApiUrl),
        headers: {'Accept': 'application/json'},
        body: {'email': email.text.trim(), 'password': password.text},
      );
      if (!mounted) return;
      if (response.statusCode != 200) {
        setState(() {
          submitting = false;
          message = response.statusCode == 401
              ? 'Invalid email or password.'
              : response.statusCode == 403
              ? 'Your rider registration is awaiting approval.'
              : apiErrorMessage(response);
        });
        return;
      }
      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final token = payload['token'];
      if (token is! String || token.isEmpty) throw const FormatException();
      await riderSession.tokens.save(token);
      await riderSession.requireReadyToken();
      if (!mounted) return;
      setState(() {
        submitting = false;
        success = true;
        message = 'Login successful.';
      });
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => RiderShellPage(session: riderSession),
        ),
      );
    } catch (exception) {
      if (!mounted) return;
      setState(() {
        submitting = false;
        message = exception is SessionFailure
            ? exception.message
            : 'Could not sign in. Check your connection and retry.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlonaAuthScaffold(
      showBackButton: Navigator.canPop(context),
      onBack: () => Navigator.pop(context),
      trailing: Navigator.canPop(context)
          ? IconButton(
              icon: const Icon(Icons.close, color: Colors.white, size: 22),
              onPressed: () => Navigator.pop(context),
            )
          : null,
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
        child: Form(
          key: formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Role badge pill
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
                        const Icon(Icons.two_wheeler_rounded, color: AlonaAuthColors.tealAccent, size: 15),
                        const SizedBox(width: 6),
                        Text(
                          'Courier & Rider Portal',
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

              // Title & Subtitle matching Screenshot 2
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
                "See today's pickups and deliveries, and track your earnings.",
                style: GoogleFonts.figtree(
                  fontSize: 14,
                  color: Colors.white.withValues(alpha: 0.85),
                ),
              ),
              const SizedBox(height: 24),

              // Status / Error message
              if (message != null) ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: success ? const Color(0xFFECFDF5) : const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: success ? const Color(0xFFA7F3D0) : const Color(0xFFFCA5A5),
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(
                            success ? Icons.check_circle_outline : Icons.error_outline,
                            color: success ? const Color(0xFF059669) : const Color(0xFFDC2626),
                            size: 18,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              message!,
                              style: GoogleFonts.figtree(
                                color: success ? const Color(0xFF059669) : const Color(0xFFDC2626),
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                      if (!success && message!.contains('awaiting approval')) ...[
                        const SizedBox(height: 8),
                        GestureDetector(
                          onTap: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => RiderAccountPage(session: riderSession),
                            ),
                          ),
                          child: Text(
                            'View account status →',
                            style: GoogleFonts.figtree(
                              fontSize: 12.5,
                              fontWeight: FontWeight.w700,
                              color: AlonaAuthColors.tealAccent,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 18),
              ],

              // Email Address Field
              AlonaAuthTextField(
                label: 'Email address',
                controller: email,
                hintText: 'you@example.com',
                keyboardType: TextInputType.emailAddress,
              ),
              const SizedBox(height: 16),

              // Password Field
              AlonaAuthTextField(
                label: 'Password',
                controller: password,
                hintText: 'Enter your password',
                obscureText: !showPassword,
                rightLabelWidget: GestureDetector(
                  onTap: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Contact your Sorting Center supervisor to reset password.'),
                      ),
                    );
                  },
                  child: Text(
                    'Forgot password?',
                    style: GoogleFonts.figtree(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AlonaAuthColors.tealAccent,
                    ),
                  ),
                ),
                suffixIcon: IconButton(
                  icon: Icon(
                    showPassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                    size: 20,
                    color: AlonaAuthColors.hintText,
                  ),
                  onPressed: () => setState(() => showPassword = !showPassword),
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
                      value: rememberMe,
                      onChanged: (val) => setState(() => rememberMe = val ?? true),
                      activeColor: AlonaAuthColors.amber,
                      checkColor: AlonaAuthColors.darkText,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                      side: const BorderSide(color: Colors.white, width: 1.5),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Keep me signed in',
                    style: GoogleFonts.figtree(
                      fontSize: 13.5,
                      color: Colors.white.withValues(alpha: 0.9),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),

              // Golden Primary Button: Sign In
              AlonaPrimaryButton(
                label: 'Sign In',
                isLoading: submitting,
                onPressed: submit,
              ),
              const SizedBox(height: 28),

              // Secondary: Register as a courier
              Center(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'New courier?',
                      style: GoogleFonts.figtree(
                        fontSize: 14,
                        color: Colors.white.withValues(alpha: 0.85),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(width: 6),
                    GestureDetector(
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const RegistrationPage(),
                        ),
                      ),
                      child: Text(
                        'Register as a courier',
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
              const SizedBox(height: 14),

              // Sub-note
              Center(
                child: Text(
                  'New courier accounts are verified and approved by the Logistics Hub before first dispatch.',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.figtree(
                    fontSize: 12,
                    color: Colors.white.withValues(alpha: 0.65),
                    height: 1.4,
                  ),
                ),
              ),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }
}
