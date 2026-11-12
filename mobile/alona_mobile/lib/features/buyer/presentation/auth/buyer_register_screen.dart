import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_otp_screen.dart';
import 'package:alona_mobile/shared/widgets/alona_auth_scaffold.dart';

class BuyerRegisterScreen extends StatefulWidget {
  final AuthState authState;

  const BuyerRegisterScreen({super.key, required this.authState});

  @override
  State<BuyerRegisterScreen> createState() => _BuyerRegisterScreenState();
}

class _BuyerRegisterScreenState extends State<BuyerRegisterScreen> {
  int _currentStep = 1;
  final int _totalSteps = 3;

  // Step 1 Controllers (Account Information)
  final _firstNameCtrl = TextEditingController();
  final _lastNameCtrl = TextEditingController();
  final _middleNameCtrl = TextEditingController();
  final _suffixCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _passwordConfirmCtrl = TextEditingController();
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;
  bool _showMiddleAndSuffix = false;

  // Step 2 Controllers (Personal Information)
  DateTime? _selectedBirthday;
  final _birthdayCtrl = TextEditingController();
  final _ageCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();

  // Step 3 (Review & Agreements)
  bool _agreeToTerms = false;

  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    final defaultDate = DateTime.now().subtract(const Duration(days: 365 * 20));
    _setBirthday(defaultDate);
  }

  @override
  void dispose() {
    _firstNameCtrl.dispose();
    _lastNameCtrl.dispose();
    _middleNameCtrl.dispose();
    _suffixCtrl.dispose();
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    _passwordConfirmCtrl.dispose();
    _birthdayCtrl.dispose();
    _ageCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  void _setBirthday(DateTime date) {
    _selectedBirthday = date;
    final year = date.year.toString().padLeft(4, '0');
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    _birthdayCtrl.text = '$year-$month-$day';

    final now = DateTime.now();
    int age = now.year - date.year;
    if (now.month < date.month || (now.month == date.month && now.day < date.day)) {
      age--;
    }
    _ageCtrl.text = age.toString();
  }

  Future<void> _pickBirthday() async {
    final now = DateTime.now();
    final maxDate = DateTime(now.year - 18, now.month, now.day);
    final initialDate = _selectedBirthday ?? maxDate;

    final picked = await showDatePicker(
      context: context,
      initialDate: initialDate.isAfter(maxDate) ? maxDate : initialDate,
      firstDate: DateTime(now.year - 100),
      lastDate: maxDate,
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: AlonaAuthColors.backgroundTeal,
              onPrimary: Colors.white,
              onSurface: AlonaAuthColors.darkText,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      setState(() => _setBirthday(picked));
    }
  }

  void _validateAndProceedToStep2() {
    final firstName = _firstNameCtrl.text.trim();
    final lastName = _lastNameCtrl.text.trim();
    final email = _emailCtrl.text.trim();
    final password = _passwordCtrl.text;
    final confirmPassword = _passwordConfirmCtrl.text;

    if (firstName.isEmpty || firstName.length < 2) {
      setState(() => _errorMessage = 'Please enter a valid first name (at least 2 characters).');
      return;
    }
    if (lastName.isEmpty || lastName.length < 2) {
      setState(() => _errorMessage = 'Please enter a valid last name (at least 2 characters).');
      return;
    }
    if (email.isEmpty || !email.contains('@')) {
      setState(() => _errorMessage = 'Please enter a valid email address.');
      return;
    }
    if (password.length < 8) {
      setState(() => _errorMessage = 'Password must be at least 8 characters long.');
      return;
    }
    if (password != confirmPassword) {
      setState(() => _errorMessage = 'Passwords do not match.');
      return;
    }

    setState(() {
      _errorMessage = null;
      _currentStep = 2;
    });
  }

  void _validateAndProceedToStep3() {
    var phone = _phoneCtrl.text.trim();
    if (phone.isEmpty) {
      setState(() => _errorMessage = 'Please enter your contact number.');
      return;
    }

    if (phone.startsWith('+63')) {
      phone = '0${phone.substring(3)}';
    } else if (phone.startsWith('63')) {
      phone = '0${phone.substring(2)}';
    }

    if (!RegExp(r'^09\d{9}$').hasMatch(phone)) {
      setState(() => _errorMessage = 'Please enter a valid Philippine mobile number (09XXXXXXXXX).');
      return;
    }

    if (_birthdayCtrl.text.isEmpty) {
      setState(() => _errorMessage = 'Please select your birthday.');
      return;
    }

    final age = int.tryParse(_ageCtrl.text) ?? 0;
    if (age < 18) {
      setState(() => _errorMessage = 'You must be at least 18 years old to register.');
      return;
    }

    setState(() {
      _errorMessage = null;
      _currentStep = 3;
    });
  }

  Future<void> _submitRegister() async {
    if (!_agreeToTerms) {
      setState(() => _errorMessage = 'You must agree to the Terms and Conditions and Privacy Policy.');
      return;
    }

    var phone = _phoneCtrl.text.trim();
    if (phone.startsWith('+63')) {
      phone = '0${phone.substring(3)}';
    } else if (phone.startsWith('63')) {
      phone = '0${phone.substring(2)}';
    }

    final age = int.tryParse(_ageCtrl.text) ?? 0;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final email = _emailCtrl.text.trim().toLowerCase();

    try {
      await ApiClient.post('api/buyer/register', body: {
        'first_name': _firstNameCtrl.text.trim(),
        'last_name': _lastNameCtrl.text.trim(),
        'middle_name': _middleNameCtrl.text.trim(),
        'suffix': _suffixCtrl.text.trim(),
        'email': email,
        'contact_no': phone,
        'birthday': _birthdayCtrl.text.trim(),
        'age': age,
        'password': _passwordCtrl.text,
        'password_confirmation': _passwordConfirmCtrl.text,
      });

      if (!mounted) return;
      setState(() => _isLoading = false);

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => BuyerOtpScreen(email: email, authState: widget.authState),
        ),
      );
    } on ApiException catch (e) {
      setState(() {
        _isLoading = false;
        _errorMessage = e.message;
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString();
      });
    }
  }

  void _showPoliciesDialog(String title) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text(
          title,
          style: GoogleFonts.figtree(
            fontWeight: FontWeight.w700,
            fontSize: 18,
            color: AlonaAuthColors.darkText,
          ),
        ),
        content: SizedBox(
          width: double.maxFinite,
          child: SingleChildScrollView(
            child: Text(
              'Welcome to Alona Marketplace. By using our platform, you agree to comply with our community standards, fair transaction policies, and data privacy safeguards under Philippine Republic Act No. 10173 (Data Privacy Act of 2012).\n\nYour personal details and delivery addresses are encrypted and processed strictly for order fulfillment, logistics handling, and marketplace communication.',
              style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF4B5563), height: 1.5),
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(
              'Understood',
              style: GoogleFonts.figtree(
                fontWeight: FontWeight.w700,
                color: AlonaAuthColors.backgroundTeal,
              ),
            ),
          ),
        ],
      ),
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

  Future<void> _handleGoogleSignUp() async {
    if (_isLoading) return;
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
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
        // Native channel not registered
      }

      final googleSignIn = GoogleSignIn(
        serverClientId: ApiConfig.googleServerClientId,
        scopes: ['email', 'profile'],
      );
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
            'Google sign-up failed: ${e.toString().replaceAll("Exception: ", "")}';
      });
    }
  }

  Future<void> _showGoogleAccountFallbackSheet() async {
    final emailCtrl = TextEditingController(
      text: _emailCtrl.text.trim(),
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
                            'Confirm your selected Google email to sign up',
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
                        color: AlonaAuthColors.backgroundTeal,
                        width: 1.5,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  height: 48,
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
                      backgroundColor: AlonaAuthColors.amber,
                      foregroundColor: AlonaAuthColors.darkText,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: Text(
                      'Continue',
                      style: GoogleFonts.figtree(
                        fontSize: 15,
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

  void _handleBackNavigation() {
    if (_currentStep > 1) {
      setState(() {
        _errorMessage = null;
        _currentStep--;
      });
    } else {
      Navigator.pop(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlonaAuthScaffold(
      onBack: _handleBackNavigation,
      trailing: IconButton(
        icon: const Icon(Icons.close, color: Colors.white, size: 22),
        onPressed: () => Navigator.pop(context),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Segmented Step Indicator (Step X of 3 [Buyer registration])
            AlonaStepProgressIndicator(
              currentStep: _currentStep,
              totalSteps: _totalSteps,
              roleBadge: 'Buyer registration',
            ),
            const SizedBox(height: 20),

            // Heading & Subtitle matching Screenshot 3
            Text(
              _currentStep == 1
                  ? 'Account information'
                  : _currentStep == 2
                      ? 'Personal information'
                      : 'Review & agreement',
              style: GoogleFonts.figtree(
                fontSize: 26,
                fontWeight: FontWeight.w800,
                color: Colors.white,
                letterSpacing: -0.5,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              _currentStep == 1
                  ? 'Create your Alona account credentials.'
                  : _currentStep == 2
                      ? 'Tell us a little about yourself.'
                      : 'Review your details and agree to our policies.',
              style: GoogleFonts.figtree(
                fontSize: 14,
                color: Colors.white.withValues(alpha: 0.85),
              ),
            ),
            const SizedBox(height: 22),

            // Error Alert Banner
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
              const SizedBox(height: 18),
            ],

            // ==========================================
            // STEP 1: ACCOUNT INFORMATION
            // ==========================================
            if (_currentStep == 1) ...[
              // Continue with Google Button
              AlonaGoogleButton(
                onPressed: _isLoading ? null : _handleGoogleSignUp,
              ),

              // Divider "or use your email"
              const AlonaAuthDivider(text: 'or use your email'),

              // First Name & Last Name (Side by side)
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: AlonaAuthTextField(
                      label: 'First name',
                      controller: _firstNameCtrl,
                      hintText: 'First name',
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: AlonaAuthTextField(
                      label: 'Last name',
                      controller: _lastNameCtrl,
                      hintText: 'Last name',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),

              // + Add middle name or suffix toggle
              GestureDetector(
                onTap: () {
                  setState(() => _showMiddleAndSuffix = !_showMiddleAndSuffix);
                },
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    children: [
                      Icon(
                        _showMiddleAndSuffix ? Icons.remove : Icons.add,
                        size: 16,
                        color: AlonaAuthColors.tealAccent,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        _showMiddleAndSuffix
                            ? 'Hide middle name or suffix'
                            : '+ Add middle name or suffix',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AlonaAuthColors.tealAccent,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              if (_showMiddleAndSuffix) ...[
                const SizedBox(height: 12),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      flex: 2,
                      child: AlonaAuthTextField(
                        label: 'Middle name (optional)',
                        controller: _middleNameCtrl,
                        hintText: 'Middle name',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 1,
                      child: AlonaAuthTextField(
                        label: 'Suffix (opt)',
                        controller: _suffixCtrl,
                        hintText: 'Jr., Sr.',
                      ),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 16),

              // Email Address
              AlonaAuthTextField(
                label: 'Email address',
                controller: _emailCtrl,
                hintText: 'you@example.com',
                keyboardType: TextInputType.emailAddress,
              ),
              const SizedBox(height: 16),

              // Password
              AlonaAuthTextField(
                label: 'Password',
                controller: _passwordCtrl,
                hintText: 'At least 8 characters',
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
              const SizedBox(height: 16),

              // Confirm Password
              AlonaAuthTextField(
                label: 'Confirm password',
                controller: _passwordConfirmCtrl,
                hintText: 'Repeat password',
                obscureText: _obscureConfirmPassword,
                suffixIcon: IconButton(
                  icon: Icon(
                    _obscureConfirmPassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                    size: 20,
                    color: AlonaAuthColors.hintText,
                  ),
                  onPressed: () => setState(() => _obscureConfirmPassword = !_obscureConfirmPassword),
                ),
              ),
              const SizedBox(height: 24),

              // Next Button (Amber)
              AlonaPrimaryButton(
                label: 'Next',
                onPressed: _validateAndProceedToStep2,
              ),
            ],

            // ==========================================
            // STEP 2: PERSONAL INFORMATION
            // ==========================================
            if (_currentStep == 2) ...[
              // Birthday Field
              AlonaAuthTextField(
                label: 'Birthday',
                controller: _birthdayCtrl,
                hintText: 'YYYY-MM-DD',
                readOnly: true,
                onTap: _pickBirthday,
                suffixIcon: IconButton(
                  icon: const Icon(Icons.calendar_today_outlined, size: 20, color: AlonaAuthColors.darkText),
                  onPressed: _pickBirthday,
                ),
              ),
              const SizedBox(height: 16),

              // Age (Auto-calculated)
              AlonaAuthTextField(
                label: 'Age',
                controller: _ageCtrl,
                hintText: 'Automatically calculated',
                readOnly: true,
              ),
              const SizedBox(height: 4),
              Text(
                'Age is automatically calculated from your birthday.',
                style: GoogleFonts.figtree(
                  fontSize: 12,
                  color: Colors.white.withValues(alpha: 0.7),
                ),
              ),
              const SizedBox(height: 16),

              // Contact Number
              AlonaAuthTextField(
                label: 'Contact number',
                controller: _phoneCtrl,
                hintText: '09XXXXXXXXX',
                keyboardType: TextInputType.phone,
              ),
              const SizedBox(height: 24),

              // Step 2 Buttons: Back and Next
              Row(
                children: [
                  Expanded(
                    child: AlonaSecondaryButton(
                      label: 'Back',
                      onPressed: () => setState(() => _currentStep = 1),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: AlonaPrimaryButton(
                      label: 'Next',
                      onPressed: _validateAndProceedToStep3,
                    ),
                  ),
                ],
              ),
            ],

            // ==========================================
            // STEP 3: REVIEW & AGREEMENT
            // ==========================================
            if (_currentStep == 3) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Account Summary',
                      style: GoogleFonts.figtree(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AlonaAuthColors.darkText,
                      ),
                    ),
                    const SizedBox(height: 12),
                    _buildSummaryRow('Name', '${_firstNameCtrl.text} ${_lastNameCtrl.text}'),
                    _buildSummaryRow('Email', _emailCtrl.text),
                    _buildSummaryRow('Phone', _phoneCtrl.text),
                    _buildSummaryRow('Birthday', '${_birthdayCtrl.text} (${_ageCtrl.text} y/o)'),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // Terms & Privacy Checkbox
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 22,
                    height: 22,
                    child: Checkbox(
                      value: _agreeToTerms,
                      onChanged: (val) => setState(() => _agreeToTerms = val ?? false),
                      activeColor: AlonaAuthColors.amber,
                      checkColor: AlonaAuthColors.darkText,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
                      side: const BorderSide(color: Colors.white, width: 1.5),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: RichText(
                      text: TextSpan(
                        text: 'I agree to the ',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          color: Colors.white.withValues(alpha: 0.9),
                          height: 1.4,
                        ),
                        children: [
                          WidgetSpan(
                            alignment: PlaceholderAlignment.baseline,
                            baseline: TextBaseline.alphabetic,
                            child: GestureDetector(
                              onTap: () => _showPoliciesDialog('Terms and Conditions'),
                              child: Text(
                                'Terms and Conditions',
                                style: GoogleFonts.figtree(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  color: AlonaAuthColors.tealAccent,
                                  decoration: TextDecoration.underline,
                                ),
                              ),
                            ),
                          ),
                          const TextSpan(text: ' and '),
                          WidgetSpan(
                            alignment: PlaceholderAlignment.baseline,
                            baseline: TextBaseline.alphabetic,
                            child: GestureDetector(
                              onTap: () => _showPoliciesDialog('Privacy Policy'),
                              child: Text(
                                'Privacy Policy',
                                style: GoogleFonts.figtree(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  color: AlonaAuthColors.tealAccent,
                                  decoration: TextDecoration.underline,
                                ),
                              ),
                            ),
                          ),
                          const TextSpan(
                            text: ' *',
                            style: TextStyle(
                              color: Color(0xFFF87171),
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),

              // Step 3 Buttons: Back and Submit
              Row(
                children: [
                  Expanded(
                    child: AlonaSecondaryButton(
                      label: 'Back',
                      onPressed: _isLoading ? null : () => setState(() => _currentStep = 2),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: AlonaPrimaryButton(
                      label: 'Create Account',
                      isLoading: _isLoading,
                      onPressed: _submitRegister,
                    ),
                  ),
                ],
              ),
            ],

            const SizedBox(height: 28),

            // Already have an account? Sign In Footer matching Screenshot 3
            Center(
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'Already have an account?',
                    style: GoogleFonts.figtree(
                      fontSize: 14,
                      color: Colors.white.withValues(alpha: 0.85),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(width: 6),
                  GestureDetector(
                    onTap: () {
                      Navigator.pushReplacement(
                        context,
                        MaterialPageRoute(
                          builder: (_) => BuyerLoginScreen(authState: widget.authState),
                        ),
                      );
                    },
                    child: Text(
                      'Log in',
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
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildSummaryRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 80,
            child: Text(
              label,
              style: GoogleFonts.figtree(
                fontSize: 13,
                fontWeight: FontWeight.w500,
                color: AlonaAuthColors.mutedText,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value.isEmpty ? '—' : value,
              style: GoogleFonts.figtree(
                fontSize: 13.5,
                fontWeight: FontWeight.w700,
                color: AlonaAuthColors.darkText,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
