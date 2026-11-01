import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_otp_screen.dart';

class BuyerRegisterScreen extends StatefulWidget {
  final AuthState authState;

  const BuyerRegisterScreen({super.key, required this.authState});

  @override
  State<BuyerRegisterScreen> createState() => _BuyerRegisterScreenState();
}

class _BuyerRegisterScreenState extends State<BuyerRegisterScreen> {
  int _currentStep = 1;
  final int _totalSteps = 2;

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

  // Step 2 Controllers (Personal Information)
  DateTime? _selectedBirthday;
  final _birthdayCtrl = TextEditingController();
  final _ageCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  bool _agreeToTerms = false;

  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    // Default birthday 20 years ago
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

    // Calculate age
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
              primary: Color(0xFF087F8C),
              onPrimary: Colors.white,
              onSurface: Color(0xFF1F2937),
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

  Future<void> _submitRegister() async {
    if (!_agreeToTerms) {
      setState(() => _errorMessage = 'You must agree to the Terms and Conditions and Privacy Policy.');
      return;
    }

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
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text(
          title,
          style: GoogleFonts.figtree(fontWeight: FontWeight.w700, fontSize: 18),
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
              style: GoogleFonts.figtree(fontWeight: FontWeight.w600, color: const Color(0xFF087F8C)),
            ),
          ),
        ],
      ),
    );
  }

  void _showGoogleSignUpModal() {
    final emailCtrl = TextEditingController(text: 'newbuyer@gmail.com');
    bool isSigningIn = false;
    String? googleError;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (modalContext, setModalState) => Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(modalContext).viewInsets.bottom + 24,
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
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      border: Border.all(color: const Color(0xFFE5E7EB)),
                      boxShadow: const [
                        BoxShadow(
                          color: Color(0x0A000000),
                          blurRadius: 6,
                          offset: Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Center(
                      child: Text(
                        'G',
                        style: GoogleFonts.roboto(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          color: const Color(0xFF4285F4),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Sign up with Google',
                          style: GoogleFonts.figtree(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: const Color(0xFF1F2937),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Join Alona Buyer instantly',
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
                'Create your Alona Buyer account instantly with your Google account. No manual password required.',
                style: GoogleFonts.figtree(
                  fontSize: 13,
                  color: const Color(0xFF64748B),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 16),
              if (googleError != null) ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFFCA5A5)),
                  ),
                  child: Text(
                    googleError!,
                    style: GoogleFonts.figtree(color: const Color(0xFFDC2626), fontSize: 13),
                  ),
                ),
                const SizedBox(height: 14),
              ],
              Text(
                'Google Account Email',
                style: GoogleFonts.figtree(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: const Color(0xFF1F2937),
                ),
              ),
              const SizedBox(height: 6),
              TextField(
                controller: emailCtrl,
                keyboardType: TextInputType.emailAddress,
                style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF1F2937)),
                decoration: InputDecoration(
                  hintText: 'username@gmail.com',
                  hintStyle: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF94A3B8)),
                  filled: true,
                  fillColor: const Color(0xFFF8FAF9),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
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
                    borderSide: const BorderSide(color: Color(0xFF087F8C), width: 1.5),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: isSigningIn
                      ? null
                      : () async {
                          final email = emailCtrl.text.trim();
                          if (email.isEmpty || !email.contains('@')) {
                            setModalState(() {
                              googleError = 'Please enter a valid Google email address.';
                            });
                            return;
                          }
                          setModalState(() {
                            isSigningIn = true;
                            googleError = null;
                          });

                          final rootNav = Navigator.of(context);
                          final modalNav = Navigator.of(modalContext);

                          try {
                            final googleId = 'gid_${email.replaceAll(RegExp(r'[^a-zA-Z0-9]'), '')}';
                            final namePart = email.split('@')[0];
                            final res = await BuyerService.googleLogin(
                              email: email,
                              googleId: googleId,
                              firstName: namePart,
                              lastName: 'Buyer',
                            );

                            final token = res['token'] as String;
                            final user = res['user'] as Map<String, dynamic>;

                            await widget.authState.setBuyerSession(token, user);

                            if (mounted) {
                              modalNav.pop();
                              rootNav.pop();
                            }
                          } catch (e) {
                            setModalState(() {
                              isSigningIn = false;
                              googleError = e.toString().replaceFirst('Exception: ', '');
                            });
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF087F8C),
                    foregroundColor: Colors.white,
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  child: isSigningIn
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                        )
                      : Text(
                          'Continue with Google',
                          style: GoogleFonts.figtree(fontSize: 14, fontWeight: FontWeight.w600),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  InputDecoration _fieldDecoration({required String hintText, Widget? suffixIcon}) {
    return InputDecoration(
      hintText: hintText,
      hintStyle: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF94A3B8)),
      filled: true,
      fillColor: const Color(0xFFF8FAF9),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
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
        borderSide: const BorderSide(color: Color(0xFF087F8C), width: 1.5),
      ),
      suffixIcon: suffixIcon,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFF1F2937)),
          onPressed: () {
            if (_currentStep == 2) {
              setState(() => _currentStep = 1);
            } else {
              Navigator.pop(context);
            }
          },
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

              // Title matching AuthModal.vue: "Account information" (Step 1) or "Personal information" (Step 2)
              Center(
                child: Text(
                  _currentStep == 1 ? 'Account information' : 'Personal information',
                  style: GoogleFonts.figtree(
                    fontSize: 24,
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF1F2937),
                    letterSpacing: -0.5,
                  ),
                ),
              ),
              const SizedBox(height: 6),

              // Subtitle matching AuthModal.vue
              Center(
                child: Text(
                  _currentStep == 1
                      ? 'Create your Alona account credentials.'
                      : 'Tell us a little about yourself.',
                  style: GoogleFonts.figtree(
                    fontSize: 14,
                    color: const Color(0xFF64748B),
                  ),
                ),
              ),
              const SizedBox(height: 24),

              // Step Progress Bar matching AuthModal.vue
              Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Step $_currentStep of $_totalSteps',
                        style: GoogleFonts.figtree(
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                          color: const Color(0xFF64748B),
                        ),
                      ),
                      Text(
                        'Buyer registration',
                        style: GoogleFonts.figtree(
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                          color: const Color(0xFF64748B),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: _currentStep / _totalSteps,
                      minHeight: 8,
                      backgroundColor: const Color(0xFFE5E7EB),
                      valueColor: const AlwaysStoppedAnimation<Color>(Color(0xFF087F8C)),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),

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
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.error_outline, color: Color(0xFFDC2626), size: 20),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          _errorMessage!,
                          style: GoogleFonts.figtree(color: const Color(0xFFDC2626), fontSize: 13),
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
                // First Name and Last Name Row
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'First name',
                            style: GoogleFonts.figtree(
                              fontSize: 14,
                              fontWeight: FontWeight.w500,
                              color: const Color(0xFF1F2937),
                            ),
                          ),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _firstNameCtrl,
                            style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF1F2937)),
                            decoration: _fieldDecoration(hintText: 'Enter your first name'),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Last name',
                            style: GoogleFonts.figtree(
                              fontSize: 14,
                              fontWeight: FontWeight.w500,
                              color: const Color(0xFF1F2937),
                            ),
                          ),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _lastNameCtrl,
                            style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF1F2937)),
                            decoration: _fieldDecoration(hintText: 'Enter your last name'),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                // Middle Name & Suffix Row
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      flex: 2,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          RichText(
                            text: TextSpan(
                              text: 'Middle name ',
                              style: GoogleFonts.figtree(
                                fontSize: 14,
                                fontWeight: FontWeight.w500,
                                color: const Color(0xFF1F2937),
                              ),
                              children: [
                                TextSpan(
                                  text: '(optional)',
                                  style: GoogleFonts.figtree(color: const Color(0xFF94A3B8), fontWeight: FontWeight.normal),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _middleNameCtrl,
                            style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF1F2937)),
                            decoration: _fieldDecoration(hintText: 'Enter middle name'),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 1,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          RichText(
                            text: TextSpan(
                              text: 'Suffix ',
                              style: GoogleFonts.figtree(
                                fontSize: 14,
                                fontWeight: FontWeight.w500,
                                color: const Color(0xFF1F2937),
                              ),
                              children: [
                                TextSpan(
                                  text: '(opt)',
                                  style: GoogleFonts.figtree(color: const Color(0xFF94A3B8), fontWeight: FontWeight.normal),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _suffixCtrl,
                            style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF1F2937)),
                            decoration: _fieldDecoration(hintText: 'Jr., Sr.'),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                // Email Address
                Text(
                  'Email address',
                  style: GoogleFonts.figtree(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: const Color(0xFF1F2937),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _emailCtrl,
                  keyboardType: TextInputType.emailAddress,
                  style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF1F2937)),
                  decoration: _fieldDecoration(hintText: 'you@example.com'),
                ),
                const SizedBox(height: 16),

                // Password with eye toggle
                Text(
                  'Password',
                  style: GoogleFonts.figtree(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: const Color(0xFF1F2937),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _passwordCtrl,
                  obscureText: _obscurePassword,
                  style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF1F2937)),
                  decoration: _fieldDecoration(
                    hintText: 'At least 8 characters',
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                        size: 20,
                        color: const Color(0xFF94A3B8),
                      ),
                      onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Confirm Password with eye toggle
                Text(
                  'Confirm password',
                  style: GoogleFonts.figtree(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: const Color(0xFF1F2937),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _passwordConfirmCtrl,
                  obscureText: _obscureConfirmPassword,
                  style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF1F2937)),
                  decoration: _fieldDecoration(
                    hintText: 'Repeat your password',
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscureConfirmPassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                        size: 20,
                        color: const Color(0xFF94A3B8),
                      ),
                      onPressed: () => setState(() => _obscureConfirmPassword = !_obscureConfirmPassword),
                    ),
                  ),
                ),
                const SizedBox(height: 24),

                // Step 1 Next Button
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: _validateAndProceedToStep2,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF087F8C),
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    child: Text(
                      'Next',
                      style: GoogleFonts.figtree(fontSize: 14, fontWeight: FontWeight.w600),
                    ),
                  ),
                ),
                const SizedBox(height: 20),

                // "or continue with" Divider
                Row(
                  children: [
                    const Expanded(child: Divider(color: Color(0xFFE5E7EB))),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 14),
                      child: Text(
                        'or continue with',
                        style: GoogleFonts.figtree(
                          fontSize: 12,
                          color: const Color(0xFF94A3B8),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                    const Expanded(child: Divider(color: Color(0xFFE5E7EB))),
                  ],
                ),
                const SizedBox(height: 18),

                // Continue with Google Button
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: OutlinedButton(
                    onPressed: _showGoogleSignUpModal,
                    style: OutlinedButton.styleFrom(
                      backgroundColor: Colors.white,
                      side: const BorderSide(color: Color(0xFFE5E7EB)),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      elevation: 0,
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          width: 24,
                          height: 24,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                            border: Border.all(color: const Color(0xFFE5E7EB)),
                          ),
                          child: Center(
                            child: Text(
                              'G',
                              style: GoogleFonts.roboto(
                                fontSize: 14,
                                fontWeight: FontWeight.w900,
                                color: const Color(0xFF4285F4),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          'Continue with Google',
                          style: GoogleFonts.figtree(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color: const Color(0xFF1F2937),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],

              // ==========================================
              // STEP 2: PERSONAL INFORMATION
              // ==========================================
              if (_currentStep == 2) ...[
                // Birthday
                Text(
                  'Birthday',
                  style: GoogleFonts.figtree(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: const Color(0xFF1F2937),
                  ),
                ),
                const SizedBox(height: 8),
                GestureDetector(
                  onTap: _pickBirthday,
                  child: AbsorbPointer(
                    child: TextField(
                      controller: _birthdayCtrl,
                      style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF1F2937)),
                      decoration: _fieldDecoration(
                        hintText: 'YYYY-MM-DD',
                        suffixIcon: const Icon(Icons.calendar_today_outlined, size: 20, color: Color(0xFF087F8C)),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Age (Readonly, auto-calculated)
                Text(
                  'Age',
                  style: GoogleFonts.figtree(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: const Color(0xFF1F2937),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _ageCtrl,
                  readOnly: true,
                  style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF64748B)),
                  decoration: InputDecoration(
                    hintText: 'Automatically calculated',
                    hintStyle: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF94A3B8)),
                    filled: true,
                    fillColor: const Color(0xFFF1F5F9),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                    ),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Age is automatically calculated from your birthday.',
                  style: GoogleFonts.figtree(fontSize: 12, color: const Color(0xFF94A3B8)),
                ),
                const SizedBox(height: 16),

                // Contact Number
                Text(
                  'Contact number',
                  style: GoogleFonts.figtree(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: const Color(0xFF1F2937),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _phoneCtrl,
                  keyboardType: TextInputType.phone,
                  style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF1F2937)),
                  decoration: _fieldDecoration(hintText: '09XXXXXXXXX'),
                ),
                const SizedBox(height: 20),

                // Terms & Privacy Policy Checkbox matching AuthModal.vue
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 22,
                      height: 22,
                      child: Checkbox(
                        value: _agreeToTerms,
                        onChanged: (val) => setState(() => _agreeToTerms = val ?? false),
                        activeColor: const Color(0xFF087F8C),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                        side: const BorderSide(color: Color(0xFFCBD5E1)),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: RichText(
                        text: TextSpan(
                          text: 'I agree to the ',
                          style: GoogleFonts.figtree(fontSize: 13, color: const Color(0xFF475569), height: 1.4),
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
                                    fontWeight: FontWeight.w600,
                                    color: const Color(0xFF087F8C),
                                    decoration: TextDecoration.underline,
                                  ),
                                ),
                              ),
                            ),
                            TextSpan(
                              text: ' and ',
                              style: GoogleFonts.figtree(fontSize: 13, color: const Color(0xFF475569)),
                            ),
                            WidgetSpan(
                              alignment: PlaceholderAlignment.baseline,
                              baseline: TextBaseline.alphabetic,
                              child: GestureDetector(
                                onTap: () => _showPoliciesDialog('Privacy Policy'),
                                child: Text(
                                  'Privacy Policy',
                                  style: GoogleFonts.figtree(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: const Color(0xFF087F8C),
                                    decoration: TextDecoration.underline,
                                  ),
                                ),
                              ),
                            ),
                            const TextSpan(
                              text: ' *',
                              style: TextStyle(color: Color(0xFFEF4444), fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),

                // Step 2 Action Buttons (Back & Submit)
                Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 48,
                        child: OutlinedButton(
                          onPressed: _isLoading ? null : () => setState(() => _currentStep = 1),
                          style: OutlinedButton.styleFrom(
                            backgroundColor: Colors.white,
                            side: const BorderSide(color: Color(0xFFE5E7EB)),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          ),
                          child: Text(
                            'Back',
                            style: GoogleFonts.figtree(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: const Color(0xFF1F2937),
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: SizedBox(
                        height: 48,
                        child: ElevatedButton(
                          onPressed: _isLoading ? null : _submitRegister,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF087F8C),
                            foregroundColor: Colors.white,
                            elevation: 0,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          ),
                          child: _isLoading
                              ? Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                                    ),
                                    const SizedBox(width: 8),
                                    Text(
                                      'Submitting...',
                                      style: GoogleFonts.figtree(fontSize: 13, fontWeight: FontWeight.w600),
                                    ),
                                  ],
                                )
                              : Text(
                                  'Create Account',
                                  style: GoogleFonts.figtree(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w600,
                                    color: Colors.white,
                                  ),
                                ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],

              const SizedBox(height: 28),

              // Switch to Login Footer
              Container(
                width: double.infinity,
                padding: const EdgeInsets.only(top: 20),
                decoration: const BoxDecoration(
                  border: Border(top: BorderSide(color: Color(0xFFE5E7EB))),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'Already have an Alona account?',
                      style: GoogleFonts.figtree(
                        fontSize: 14,
                        color: const Color(0xFF64748B),
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
                          fontWeight: FontWeight.w600,
                          color: const Color(0xFF087F8C),
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
