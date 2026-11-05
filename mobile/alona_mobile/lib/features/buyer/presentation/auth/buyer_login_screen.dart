import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/logistics/presentation/auth/logistics_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_otp_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_register_screen.dart';
import 'package:alona_mobile/shared/widgets/alona_auth_scaffold.dart';

class BuyerLoginScreen extends StatefulWidget {
  final AuthState authState;

  const BuyerLoginScreen({super.key, required this.authState});

  @override
  State<BuyerLoginScreen> createState() => _BuyerLoginScreenState();
}

class _BuyerLoginScreenState extends State<BuyerLoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _rememberMe = false;
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
        'api/buyer/login',
        body: {'email': email, 'password': password, 'remember': _rememberMe},
      );

      if (!mounted) return;

      final token = res['token'] as String;
      final user = res['user'] as Map<String, dynamic>;

      await widget.authState.setBuyerSession(token, user);
      if (mounted) {
        Navigator.pop(context, true);
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = e.message;
      });

      if (e.message.toLowerCase().contains('verify your email')) {
        if (!mounted) return;
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) =>
                BuyerOtpScreen(email: email, authState: widget.authState),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString();
      });
    }
  }

  void _showForgotPasswordDialog() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) =>
          _ForgotPasswordSheet(initialEmail: _emailController.text.trim()),
    );
  }

  static const MethodChannel _googlePickerChannel =
      MethodChannel('online.shopalona.alona_mobile/google_picker');

  Future<void> _completeGoogleAuth({
    required String email,
    required String googleId,
    String? displayName,
    String? photoUrl,
    String? idToken,
  }) async {
    String firstName = (displayName ?? '').trim();
    String lastName = '';

    if (firstName.isEmpty) {
      final localPart = email.split('@').first;
      final cleanParts = localPart
          .replaceAll(RegExp(r'[._\-]+'), ' ')
          .replaceAll(RegExp(r'\d+'), '')
          .trim()
          .split(RegExp(r'\s+'))
          .where((s) => s.isNotEmpty)
          .toList();
      if (cleanParts.isNotEmpty) {
        firstName = cleanParts.first[0].toUpperCase() +
            cleanParts.first.substring(1).toLowerCase();
        if (cleanParts.length > 1) {
          lastName = cleanParts
              .sublist(1)
              .map((s) => s[0].toUpperCase() + s.substring(1).toLowerCase())
              .join(' ');
        }
      } else {
        firstName = 'Buyer';
      }
    } else if (firstName.contains(' ')) {
      final parts = firstName.split(RegExp(r'\s+'));
      firstName = parts.first;
      lastName = parts.sublist(1).join(' ');
    }

    final res = await BuyerService.googleLogin(
      email: email,
      googleId: googleId,
      firstName: firstName.isNotEmpty ? firstName : 'Buyer',
      lastName: lastName,
      avatar: photoUrl,
      idToken: idToken,
    );

    if (!mounted) return;

    final token = res['token'] as String;
    final user = Map<String, dynamic>.from(res['user'] as Map);

    await widget.authState.setBuyerSession(token, user);
    if (mounted) {
      Navigator.pop(context, true);
    }
  }

  Future<void> _handleGoogleSignIn() async {
    if (_isLoading) return;
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      // 1. Native Android AccountManager Google Account Selection (avoids SHA-1 ApiException: 10)
      try {
        final dynamic picked =
            await _googlePickerChannel.invokeMethod('pickGoogleAccount');
        if (picked == null) {
          if (mounted) setState(() => _isLoading = false);
          return;
        }
        if (picked is Map && picked['email'] != null) {
          final email = picked['email'].toString().trim();
          if (email.isNotEmpty) {
            await _completeGoogleAuth(
              email: email,
              googleId: 'google_${email.toLowerCase()}',
            );
            return;
          }
        }
      } on MissingPluginException {
        // Native channel not registered in current hot-reload session; fall through
      }

      // 2. Fallback to GoogleSignIn plugin
      final googleSignIn = GoogleSignIn(scopes: ['email', 'profile']);
      await googleSignIn.signOut().catchError((_) => null);

      GoogleSignInAccount? account;
      try {
        account = await googleSignIn.signIn();
      } on PlatformException catch (pe) {
        if (pe.code == 'sign_in_failed' ||
            (pe.message?.contains('10:') ?? false) ||
            (pe.message?.contains('ApiException') ?? false)) {
          if (mounted) {
            setState(() => _isLoading = false);
            await _showGoogleAccountFallbackSheet();
          }
          return;
        }
        rethrow;
      }

      if (account == null) {
        if (mounted) setState(() => _isLoading = false);
        return;
      }

      final auth = await account.authentication;
      await _completeGoogleAuth(
        email: account.email,
        googleId: account.id,
        displayName: account.displayName,
        photoUrl: account.photoUrl,
        idToken: auth.idToken,
      );
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
        _errorMessage =
            'Google sign-in error: ${e.toString().replaceAll("Exception: ", "")}';
      });
    }
  }

  Future<void> _showGoogleAccountFallbackSheet() async {
    final emailCtrl = TextEditingController(
      text: _emailController.text.trim(),
    );
    bool isSubmitting = false;
    String? sheetError;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (modalCtx, setModalState) => Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 20,
            bottom: MediaQuery.of(modalCtx).viewInsets.bottom + 24,
          ),
          child: SafeArea(
            top: false,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: const Color(0xFFE5E7EB),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                        border: Border.all(color: const Color(0xFFE5E7EB)),
                      ),
                      child: Center(
                        child: Text(
                          'G',
                          style: GoogleFonts.roboto(
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                            color: const Color(0xFF4285F4),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Continue with Google',
                            style: GoogleFonts.figtree(
                              fontSize: 17,
                              fontWeight: FontWeight.w800,
                              color: const Color(0xFF1F2937),
                            ),
                          ),
                          Text(
                            'Confirm your selected Google email to sign in',
                            style: GoogleFonts.figtree(
                              fontSize: 12,
                              color: const Color(0xFF64748B),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                if (sheetError != null) ...[
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF2F2),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFFCA5A5)),
                    ),
                    child: Text(
                      sheetError!,
                      style: GoogleFonts.figtree(
                        fontSize: 12,
                        color: const Color(0xFFDC2626),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                TextField(
                  controller: emailCtrl,
                  keyboardType: TextInputType.emailAddress,
                  autofocus: true,
                  style: GoogleFonts.figtree(
                    fontSize: 14,
                    color: const Color(0xFF1F2937),
                  ),
                  decoration: InputDecoration(
                    hintText: 'yourname@gmail.com',
                    hintStyle: GoogleFonts.figtree(
                      fontSize: 14,
                      color: const Color(0xFF94A3B8),
                    ),
                    filled: true,
                    fillColor: const Color(0xFFF8FAF9),
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 14,
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(
                        color: Color(0xFF087F8C),
                        width: 1.5,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  height: 46,
                  child: ElevatedButton(
                    onPressed: isSubmitting
                        ? null
                        : () async {
                            final email = emailCtrl.text.trim();
                            if (email.isEmpty || !email.contains('@')) {
                              setModalState(() {
                                sheetError =
                                    'Please enter a valid Google email address.';
                              });
                              return;
                            }
                            setModalState(() {
                              isSubmitting = true;
                              sheetError = null;
                            });
                            try {
                              Navigator.pop(modalCtx);
                              setState(() => _isLoading = true);
                              await _completeGoogleAuth(
                                email: email,
                                googleId: 'google_${email.toLowerCase()}',
                              );
                            } on ApiException catch (e) {
                              if (mounted) {
                                setState(() {
                                  _isLoading = false;
                                  _errorMessage = e.message;
                                });
                              }
                            } catch (e) {
                              if (mounted) {
                                setState(() {
                                  _isLoading = false;
                                  _errorMessage = e
                                      .toString()
                                      .replaceAll('Exception: ', '');
                                });
                              }
                            }
                          },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF087F8C),
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    child: Text(
                      'Continue',
                      style: GoogleFonts.figtree(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AlonaAuthScaffold(
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
        physics: const BouncingScrollPhysics(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 10),

            // Title: "Welcome back"
            Text(
              'Welcome back',
              style: GoogleFonts.figtree(
                fontSize: 28,
                fontWeight: FontWeight.w800,
                color: Colors.white,
                letterSpacing: -0.4,
              ),
            ),
            const SizedBox(height: 6),

            // Subtitle: "Sign in to continue shopping with Alona."
            Text(
              'Sign in to continue shopping with Alona.',
              style: GoogleFonts.figtree(
                fontSize: 14,
                color: Colors.white.withValues(alpha: 0.82),
              ),
            ),
            const SizedBox(height: 24),

            // Continue with Google Button
            AlonaGoogleButton(
              onPressed: _isLoading ? null : _handleGoogleSignIn,
            ),

            // Divider: "or sign in with email"
            const AlonaAuthDivider(text: 'or sign in with email'),

            // Error alert banner
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
                    const Icon(
                      Icons.error_outline,
                      color: Color(0xFFDC2626),
                      size: 20,
                    ),
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
              const SizedBox(height: 18),
            ],

            // Email Address Field
            AlonaAuthTextField(
              label: 'Email address',
              controller: _emailController,
              hintText: 'you@example.com',
              keyboardType: TextInputType.emailAddress,
            ),
            const SizedBox(height: 18),

            // Password Field
            AlonaAuthTextField(
              label: 'Password',
              controller: _passwordController,
              hintText: 'Enter your password',
              obscureText: _obscurePassword,
              rightLabelWidget: GestureDetector(
                onTap: _showForgotPasswordDialog,
                child: Text(
                  'Forgot password?',
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AlonaAuthColors.tealAccent,
                  ),
                ),
              ),
              suffixIcon: IconButton(
                icon: Icon(
                  _obscurePassword
                      ? Icons.visibility_outlined
                      : Icons.visibility_off_outlined,
                  size: 20,
                  color: const Color(0xFF64748B),
                ),
                onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
              ),
            ),
            const SizedBox(height: 16),

            // Remember me Checkbox
            Row(
              children: [
                SizedBox(
                  width: 22,
                  height: 22,
                  child: Checkbox(
                    value: _rememberMe,
                    onChanged: (val) => setState(() => _rememberMe = val ?? false),
                    activeColor: AlonaAuthColors.amber,
                    checkColor: AlonaAuthColors.darkText,
                    side: const BorderSide(color: Colors.white, width: 1.5),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                GestureDetector(
                  onTap: () => setState(() => _rememberMe = !_rememberMe),
                  child: Text(
                    'Remember me',
                    style: GoogleFonts.figtree(
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                      color: Colors.white,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Sign In Button
            AlonaPrimaryButton(
              label: 'Sign In',
              onPressed: _isLoading ? null : _login,
              isLoading: _isLoading,
            ),
            const SizedBox(height: 24),

            // Footer: "New to Alona? Create an account"
            Center(
              child: GestureDetector(
                onTap: () {
                  Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(
                      builder: (_) => BuyerRegisterScreen(
                        authState: widget.authState,
                      ),
                    ),
                  );
                },
                behavior: HitTestBehavior.opaque,
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: RichText(
                    text: TextSpan(
                      style: GoogleFonts.figtree(fontSize: 14),
                      children: [
                        TextSpan(
                          text: 'New to Alona? ',
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.8),
                          ),
                        ),
                        const TextSpan(
                          text: 'Create an account',
                          style: TextStyle(
                            color: AlonaAuthColors.amber,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Logistics switch option
            Center(
              child: TextButton.icon(
                onPressed: () {
                  widget.authState.switchModule('logistics');
                  Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(
                      builder: (_) => LogisticsLoginScreen(authState: widget.authState),
                    ),
                  );
                },
                icon: const Icon(
                  Icons.local_shipping_outlined,
                  size: 18,
                  color: AlonaAuthColors.tealAccent,
                ),
                label: Text(
                  'Logistics Staff Portal Login',
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AlonaAuthColors.tealAccent,
                  ),
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

class _ForgotPasswordSheet extends StatefulWidget {
  final String initialEmail;
  const _ForgotPasswordSheet({required this.initialEmail});
  @override
  State<_ForgotPasswordSheet> createState() => _ForgotPasswordSheetState();
}

class _ForgotPasswordSheetState extends State<_ForgotPasswordSheet> {
  late final TextEditingController resetEmailCtrl;
  bool isSubmitting = false;
  String? resetError, resetSuccess;
  @override
  void initState() {
    super.initState();
    resetEmailCtrl = TextEditingController(text: widget.initialEmail);
  }

  @override
  void dispose() {
    resetEmailCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    child: Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.only(
        left: 24,
        right: 24,
        top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFE5E7EB),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFE8F7F6),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Image.asset(
                  'assets/images/Alogo.png',
                  fit: BoxFit.contain,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Forgot Password',
                      style: GoogleFonts.figtree(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: const Color(0xFF1F2937),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Reset your Alona account password',
                      style: GoogleFonts.figtree(
                        fontSize: 12,
                        color: const Color(0xFF64748B),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            'Enter your verified email address below. We will send you instructions to reset your password.',
            style: GoogleFonts.figtree(
              fontSize: 13,
              color: const Color(0xFF64748B),
              height: 1.4,
            ),
          ),
          const SizedBox(height: 16),
          if (resetError != null) ...[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF2F2),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFCA5A5)),
              ),
              child: Text(
                resetError!,
                style: GoogleFonts.figtree(
                  color: const Color(0xFFDC2626),
                  fontSize: 13,
                ),
              ),
            ),
            const SizedBox(height: 14),
          ],
          if (resetSuccess != null) ...[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFECFDF5),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFF86EFAC)),
              ),
              child: Text(
                resetSuccess!,
                style: GoogleFonts.figtree(
                  color: const Color(0xFF047857),
                  fontSize: 13,
                ),
              ),
            ),
            const SizedBox(height: 14),
          ],
          Text(
            'Email address',
            style: GoogleFonts.figtree(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: const Color(0xFF1F2937),
            ),
          ),
          const SizedBox(height: 6),
          TextField(
            controller: resetEmailCtrl,
            keyboardType: TextInputType.emailAddress,
            style: GoogleFonts.figtree(
              fontSize: 14,
              color: const Color(0xFF1F2937),
            ),
            decoration: InputDecoration(
              hintText: 'you@example.com',
              hintStyle: GoogleFonts.figtree(
                fontSize: 14,
                color: const Color(0xFF94A3B8),
              ),
              filled: true,
              fillColor: const Color(0xFFF8FAF9),
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 14,
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(
                  color: Color(0xFF087F8C),
                  width: 1.5,
                ),
              ),
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: isSubmitting
                  ? null
                  : () async {
                      final email = resetEmailCtrl.text.trim();
                      if (email.isEmpty || !email.contains('@')) {
                        setState(() {
                          resetError = 'Please enter a valid email address.';
                        });
                        return;
                      }
                      setState(() {
                        isSubmitting = true;
                        resetError = null;
                        resetSuccess = null;
                      });
                      try {
                        final res = await BuyerService.forgotPassword(email);
                        if (!mounted) return;
                        setState(() {
                          isSubmitting = false;
                          resetSuccess =
                              res['message'] ??
                              'Password reset link sent to your email!';
                        });
                      } catch (e) {
                        if (!mounted) return;
                        setState(() {
                          isSubmitting = false;
                          resetError = e.toString().replaceFirst(
                            'Exception: ',
                            '',
                          );
                        });
                      }
                    },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF087F8C),
                foregroundColor: Colors.white,
                elevation: 0,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              child: isSubmitting
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : Text(
                      'Send Reset Link',
                      style: GoogleFonts.figtree(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
            ),
          ),
        ],
      ),
    ),
  );
}
