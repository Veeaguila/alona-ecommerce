import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/buyer_main_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_otp_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_register_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/logistics_main_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/auth/logistics_register_screen.dart';
import 'package:alona_mobile/features/rider/pages/registration_page.dart';
import 'package:alona_mobile/features/rider/pages/rider_portal_page.dart';
import 'package:alona_mobile/features/rider/pages/rider_shell_page.dart';
import 'package:alona_mobile/features/rider/services/api_config.dart' show loginApiUrl;
import 'package:alona_mobile/features/rider/services/api_error.dart';
import 'package:alona_mobile/features/rider/services/rider_session.dart';
import 'package:alona_mobile/shared/widgets/alona_auth_scaffold.dart';

class BuyerLoginScreen extends StatefulWidget {
  final AuthState authState;
  final String? initialMessage;
  final RiderSession? riderSession;
  final VoidCallback? onBackOverride;

  const BuyerLoginScreen({
    super.key,
    required this.authState,
    this.initialMessage,
    this.riderSession,
    this.onBackOverride,
  });

  @override
  State<BuyerLoginScreen> createState() => _BuyerLoginScreenState();
}

class _BuyerLoginScreenState extends State<BuyerLoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  late final RiderSession _riderSession = widget.riderSession ?? RiderSession();
  bool _obscurePassword = true;
  bool _rememberMe = false;
  bool _isLoading = false;
  String? _errorMessage;
  bool _showRiderStatusLink = false;

  @override
  void initState() {
    super.initState();
    _errorMessage = widget.initialMessage;
    _showRiderStatusLink =
        widget.initialMessage?.toLowerCase().contains('awaiting approval') ??
            false;
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (_isLoading) return;
    FocusScope.of(context).unfocus();
    final email = _emailController.text.trim();
    final password = _passwordController.text;

    if (email.isEmpty || password.isEmpty) {
      setState(() {
        _errorMessage = 'Please enter both email and password.';
        _showRiderStatusLink = false;
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _showRiderStatusLink = false;
    });

    // 1. Try Buyer Login first
    try {
      final res = await ApiClient.post(
        'api/buyer/login',
        body: {'email': email, 'password': password, 'remember': _rememberMe},
      );

      if (!mounted) return;

      final token = res['token'] as String;
      final user = Map<String, dynamic>.from(res['user'] as Map);

      await widget.authState.setBuyerSession(token, user);
      await widget.authState.switchModule('buyer');
      if (!mounted) return;

      if (Navigator.canPop(context)) {
        Navigator.pop(context, true);
      } else {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(
            builder: (_) => BuyerMainScreen(authState: widget.authState),
          ),
          (route) => false,
        );
      }
      return;
    } on ApiException catch (buyerErr) {
      if (!mounted) return;

      if (buyerErr.message.toLowerCase().contains('verify your email')) {
        setState(() {
          _isLoading = false;
          _errorMessage = buyerErr.message;
        });
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) =>
                BuyerOtpScreen(email: email, authState: widget.authState),
          ),
        );
        return;
      }

      // If it is a restricted/suspended Buyer account (not role mismatch), surface it immediately
      if (buyerErr.statusCode == 403 &&
          !buyerErr.message.toLowerCase().contains('only buyer accounts')) {
        setState(() {
          _isLoading = false;
          _errorMessage = buyerErr.message;
        });
        return;
      }
    } catch (_) {
      // Fall through to check Logistics and Rider accounts
    }

    // 2. Try Logistics / Sorting Center Login
    try {
      final scRes = await ApiClient.post(
        'api/sorting-center/login',
        body: {'email': email, 'password': password},
      );

      if (!mounted) return;

      final token = scRes['token'] as String;
      final user = Map<String, dynamic>.from(scRes['user'] as Map);
      final sc = scRes['sorting_center'] != null
          ? Map<String, dynamic>.from(scRes['sorting_center'] as Map)
          : null;

      await widget.authState.setLogisticsSession(
        token,
        user,
        sc,
        operator: scRes['operator'] == null
            ? null
            : Map<String, dynamic>.from(scRes['operator'] as Map),
        accountStatus: scRes['account_status'] as String?,
      );
      await widget.authState.switchModule('logistics');

      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (_) => LogisticsMainScreen(authState: widget.authState),
        ),
        (route) => false,
      );
      return;
    } on ApiException catch (scErr) {
      if (!mounted) return;
      if (scErr.statusCode == 403) {
        setState(() {
          _isLoading = false;
          _errorMessage = scErr.message;
        });
        return;
      }
    } catch (_) {
      // Fall through to Rider login
    }

    // 3. Try Rider Login
    try {
      final riderResponse = await _riderSession.post(
        Uri.parse(loginApiUrl),
        headers: {'Accept': 'application/json'},
        body: {'email': email, 'password': password},
      );

      if (!mounted) return;

      if (riderResponse.statusCode == 200) {
        final payload = jsonDecode(riderResponse.body) as Map<String, dynamic>;
        final token = payload['token'];
        if (token is! String || token.isEmpty) {
          throw const FormatException();
        }
        await _riderSession.tokens.save(token);
        await _riderSession.requireReadyToken();

        if (!mounted) return;
        setState(() => _isLoading = false);
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(
            builder: (_) => RiderShellPage(session: _riderSession),
          ),
          (route) => false,
        );
        return;
      }

      if (riderResponse.statusCode == 403) {
        final msg = apiErrorMessage(riderResponse);
        setState(() {
          _isLoading = false;
          _errorMessage = msg.isNotEmpty
              ? msg
              : 'Your rider registration is awaiting approval.';
          _showRiderStatusLink =
              _errorMessage!.toLowerCase().contains('awaiting approval');
        });
        return;
      }

      setState(() {
        _isLoading = false;
        _errorMessage = 'Invalid email or password.';
      });
    } on SessionFailure catch (failure) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = failure.message;
        _showRiderStatusLink =
            failure.message.toLowerCase().contains('awaiting approval');
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = 'Invalid email or password.';
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
    await widget.authState.switchModule('buyer');
    if (!mounted) return;
    if (Navigator.canPop(context)) {
      Navigator.pop(context, true);
    } else {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (_) => BuyerMainScreen(authState: widget.authState),
        ),
        (route) => false,
      );
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
      final googleSignIn = GoogleSignIn(
        serverClientId: ApiConfig.googleServerClientId,
        scopes: ['email', 'profile'],
      );
      try {
        await googleSignIn.signOut().catchError((_) => null);
      } on MissingPluginException {
        if (mounted) {
          setState(() => _isLoading = false);
          await _showGoogleAccountFallbackSheet();
        }
        return;
      }

      GoogleSignInAccount? account;
      try {
        account = await googleSignIn.signIn();
      } on MissingPluginException {
        if (mounted) {
          setState(() => _isLoading = false);
          await _showGoogleAccountFallbackSheet();
        }
        return;
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
                            'Google sign-in is not available through Google Play Services on this device. Confirm your Google email below to sign in.',
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
                const SizedBox(height: 8),
                Center(
                  child: TextButton(
                    onPressed: () => Navigator.pop(modalCtx),
                    child: Text(
                      'Close',
                      style: GoogleFonts.figtree(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: const Color(0xFF64748B),
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

  Future<void> _continueAsGuest() async {
    await widget.authState.switchModule('buyer');
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (_) => BuyerMainScreen(authState: widget.authState),
      ),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final canPop = Navigator.canPop(context);
    return AlonaAuthScaffold(
      showBackButton: true,
      onBack: widget.onBackOverride ??
          (canPop ? () => Navigator.pop(context) : _continueAsGuest),
      trailing: widget.onBackOverride != null
          ? IconButton(
              icon: const Icon(Icons.close, color: Colors.white, size: 22),
              onPressed: widget.onBackOverride,
            )
          : null,
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

            // Subtitle: Unified sign-in for Buyer, Rider, and Logistics
            Text(
              'Sign in with your Buyer, Rider, or Logistics Hub account.',
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
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
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
                    if (_showRiderStatusLink) ...[
                      const SizedBox(height: 8),
                      GestureDetector(
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                RiderAccountPage(session: _riderSession),
                          ),
                        ),
                        child: Text(
                          'View account status →',
                          style: GoogleFonts.figtree(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                            color: const Color(0xFF087F8C),
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

            // Separate Registration Options (Buyer, Rider, Logistics Hub)
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
                          text: 'Create Buyer Account',
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
            const SizedBox(height: 12),

            // Partner Registration Links (Rider & Logistics Hub)
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                GestureDetector(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const RegistrationPage(),
                      ),
                    );
                  },
                  behavior: HitTestBehavior.opaque,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.two_wheeler_rounded,
                          size: 16,
                          color: AlonaAuthColors.tealAccent,
                        ),
                        const SizedBox(width: 5),
                        Text(
                          'Register as Rider',
                          style: GoogleFonts.figtree(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                            color: AlonaAuthColors.tealAccent,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: Text(
                    '•',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.4),
                      fontSize: 14,
                    ),
                  ),
                ),
                GestureDetector(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => LogisticsRegisterScreen(
                          authState: widget.authState,
                        ),
                      ),
                    );
                  },
                  behavior: HitTestBehavior.opaque,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.local_shipping_outlined,
                          size: 16,
                          color: AlonaAuthColors.tealAccent,
                        ),
                        const SizedBox(width: 5),
                        Text(
                          'Apply as Logistics Hub',
                          style: GoogleFonts.figtree(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                            color: AlonaAuthColors.tealAccent,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),

            // Continue as guest button
            Center(
              child: GestureDetector(
                onTap: _continueAsGuest,
                behavior: HitTestBehavior.opaque,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 6,
                  ),
                  child: Text(
                    'Continue as guest',
                    style: GoogleFonts.figtree(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: Colors.white.withValues(alpha: 0.92),
                      decoration: TextDecoration.underline,
                      decorationColor: Colors.white,
                      decorationThickness: 1.4,
                    ),
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
