import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';

class BuyerOtpScreen extends StatefulWidget {
  final String email;
  final AuthState authState;

  const BuyerOtpScreen({
    super.key,
    required this.email,
    required this.authState,
  });

  @override
  State<BuyerOtpScreen> createState() => _BuyerOtpScreenState();
}

class _BuyerOtpScreenState extends State<BuyerOtpScreen> {
  final List<TextEditingController> _digitControllers = List.generate(
    6,
    (_) => TextEditingController(),
  );
  final List<FocusNode> _focusNodes = List.generate(6, (_) => FocusNode());

  bool _isLoading = false;
  bool _isResending = false;
  int _resendCooldown = 60;
  Timer? _countdownTimer;

  String? _errorMessage;
  String? _successMessage;

  @override
  void initState() {
    super.initState();
    _startCooldownTimer();
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    for (final c in _digitControllers) {
      c.dispose();
    }
    for (final f in _focusNodes) {
      f.dispose();
    }
    super.dispose();
  }

  void _startCooldownTimer() {
    _countdownTimer?.cancel();
    setState(() => _resendCooldown = 60);
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_resendCooldown > 0) {
        setState(() => _resendCooldown--);
      } else {
        timer.cancel();
      }
    });
  }

  String get _currentCode => _digitControllers.map((c) => c.text).join();

  void _onDigitChanged(int index, String value) {
    setState(() {
      _errorMessage = null;
    });

    if (value.length > 1) {
      // Pasted multi-character string
      final digits = value.replaceAll(RegExp(r'\D'), '').split('');
      for (int i = 0; i < 6; i++) {
        if (i < digits.length) {
          _digitControllers[i].text = digits[i];
        }
      }
      final nextIndex = digits.length < 6 ? digits.length : 5;
      _focusNodes[nextIndex].requestFocus();

      if (_currentCode.length == 6) {
        _verify();
      }
      return;
    }

    if (value.isNotEmpty) {
      if (index < 5) {
        _focusNodes[index + 1].requestFocus();
      } else {
        _focusNodes[index].unfocus();
        if (_currentCode.length == 6) {
          _verify();
        }
      }
    }
  }

  Future<void> _verify() async {
    if (_isLoading || _isResending) return;
    final otp = _currentCode;
    if (otp.length != 6) {
      setState(
        () => _errorMessage =
            'Please enter the complete 6-digit verification code.',
      );
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final res = await ApiClient.post(
        'api/buyer/verify-otp',
        body: {'email': widget.email, 'otp': otp},
      );

      if (!mounted) return;

      final token = res['token'] as String;
      final user = res['user'] as Map<String, dynamic>;

      await widget.authState.setBuyerSession(token, user);
      if (mounted) {
        Navigator.popUntil(context, (route) => route.isFirst);
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

  Future<void> _resend() async {
    if (_resendCooldown > 0 || _isResending || _isLoading) return;

    setState(() {
      _isResending = true;
      _errorMessage = null;
      _successMessage = null;
    });

    try {
      final res = await ApiClient.post(
        'api/buyer/resend-otp',
        body: {'email': widget.email},
      );
      if (!mounted) return;
      _startCooldownTimer();
      setState(() {
        _isResending = false;
        _successMessage =
            res['message'] ?? 'A new 6-digit code has been sent to your email.';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isResending = false;
        _errorMessage = e.toString().replaceFirst('Exception: ', '');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isCodeComplete = _currentCode.length == 6;

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFF1F2937)),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header logo container: h-14 w-14 rounded-2xl bg-[#E8F7F6] p-2
              Center(
                child: Container(
                  width: 56,
                  height: 56,
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE8F7F6),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Image.asset(
                    'assets/images/Alogo.png',
                    fit: BoxFit.contain,
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Title matching AuthModal.vue: "Verify your email"
              Center(
                child: Text(
                  'Verify your email',
                  style: GoogleFonts.figtree(
                    fontSize: 24,
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF1F2937),
                    letterSpacing: -0.5,
                  ),
                ),
              ),
              const SizedBox(height: 6),

              // Subtitle matching AuthModal.vue: "Enter the 6-digit code sent to [email]. It expires in 5 minutes."
              Center(
                child: Text(
                  'Enter the 6-digit code sent to ${widget.email}. It expires in 5 minutes.',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.figtree(
                    fontSize: 14,
                    color: const Color(0xFF64748B),
                    height: 1.4,
                  ),
                ),
              ),
              const SizedBox(height: 24),

              // Account Created Success Banner matching AuthModal.vue (emerald-50)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFECFDF5),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFF86EFAC)),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        color: const Color(0xFFD1FAE5),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(
                        Icons.check_circle_outline,
                        color: Color(0xFF047857),
                        size: 20,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Account Created Successfully!',
                            style: GoogleFonts.figtree(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: const Color(0xFF064E3B),
                            ),
                          ),
                          const SizedBox(height: 4),
                          RichText(
                            text: TextSpan(
                              text: 'A 6-digit verification code has been sent to ',
                              style: GoogleFonts.figtree(
                                fontSize: 12,
                                color: const Color(0xFF065F46),
                                height: 1.4,
                              ),
                              children: [
                                TextSpan(
                                  text: widget.email,
                                  style: GoogleFonts.figtree(
                                    fontWeight: FontWeight.bold,
                                    decoration: TextDecoration.underline,
                                  ),
                                ),
                                const TextSpan(
                                  text: '. Please enter it below to activate your account.',
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),

              // Resend Success Notice
              if (_successMessage != null) ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 12,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE8F7F6),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFF99F6E4)),
                  ),
                  child: Text(
                    _successMessage!,
                    style: GoogleFonts.figtree(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: const Color(0xFF087F8C),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // Error banner if any
              if (_errorMessage != null) ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFFCA5A5)),
                  ),
                  child: Row(
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
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // 6-Digit Code Label
              Text(
                '6-Digit Verification Code',
                style: GoogleFonts.figtree(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                  color: const Color(0xFF1F2937),
                ),
              ),
              const SizedBox(height: 12),

              // 6 Individual Stylized Digit Input Boxes
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: List.generate(6, (index) {
                  return SizedBox(
                    width: 46,
                    height: 54,
                    child: KeyboardListener(
                      focusNode: FocusNode(),
                      onKeyEvent: (event) {
                        if (event is KeyDownEvent &&
                            event.logicalKey == LogicalKeyboardKey.backspace &&
                            _digitControllers[index].text.isEmpty &&
                            index > 0) {
                          _focusNodes[index - 1].requestFocus();
                        }
                      },
                      child: TextField(
                        controller: _digitControllers[index],
                        focusNode: _focusNodes[index],
                        keyboardType: TextInputType.number,
                        textAlign: TextAlign.center,
                        maxLength: 1,
                        style: GoogleFonts.figtree(
                          fontSize: 22,
                          fontWeight: FontWeight.w700,
                          color: const Color(0xFF1F2937),
                        ),
                        decoration: InputDecoration(
                          counterText: '',
                          filled: true,
                          fillColor: const Color(0xFFF8FAF9),
                          contentPadding: EdgeInsets.zero,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(
                              color: Color(0xFFE5E7EB),
                            ),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(
                              color: Color(0xFFE5E7EB),
                            ),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(
                              color: Color(0xFF087F8C),
                              width: 2,
                            ),
                          ),
                        ),
                        onChanged: (val) => _onDigitChanged(index, val),
                      ),
                    ),
                  );
                }),
              ),
              const SizedBox(height: 10),
              Text(
                'Please check your inbox (or spam folder) for the 6-digit code.',
                style: GoogleFonts.figtree(
                  fontSize: 12,
                  color: const Color(0xFF94A3B8),
                ),
              ),
              const SizedBox(height: 24),

              // Verify Account Button
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton(
                  onPressed: (_isLoading || !isCodeComplete) ? null : _verify,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF087F8C),
                    disabledBackgroundColor: const Color(0x80087F8C),
                    foregroundColor: Colors.white,
                    disabledForegroundColor: const Color(0xB3FFFFFF),
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  child: _isLoading
                      ? Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                color: Colors.white,
                                strokeWidth: 2,
                              ),
                            ),
                            const SizedBox(width: 10),
                            Text(
                              'Verifying...',
                              style: GoogleFonts.figtree(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        )
                      : Text(
                          'Verify Account',
                          style: GoogleFonts.figtree(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                ),
              ),
              const SizedBox(height: 24),

              // Footer row: Resend Code and Change details
              Container(
                padding: const EdgeInsets.only(top: 18),
                decoration: const BoxDecoration(
                  border: Border(top: BorderSide(color: Color(0xFFE5E7EB))),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    GestureDetector(
                      onTap: (_resendCooldown > 0 || _isResending)
                          ? null
                          : _resend,
                      child: Text(
                        _isResending
                            ? 'Sending...'
                            : _resendCooldown > 0
                            ? 'Resend code in ${_resendCooldown}s'
                            : 'Resend Code',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: (_resendCooldown > 0 || _isResending)
                              ? const Color(0xFF94A3B8)
                              : const Color(0xFF087F8C),
                        ),
                      ),
                    ),
                    GestureDetector(
                      onTap: () => Navigator.pop(context),
                      child: Text(
                        'Change details',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                          color: const Color(0xFF64748B),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }
}
