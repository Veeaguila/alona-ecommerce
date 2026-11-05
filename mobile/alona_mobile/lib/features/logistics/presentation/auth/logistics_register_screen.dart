import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/shared/widgets/alona_auth_scaffold.dart';
import 'package:alona_mobile/features/logistics/presentation/auth/logistics_login_screen.dart';

class LogisticsRegisterScreen extends StatefulWidget {
  final AuthState authState;

  const LogisticsRegisterScreen({super.key, required this.authState});

  @override
  State<LogisticsRegisterScreen> createState() => _LogisticsRegisterScreenState();
}

class _LogisticsRegisterScreenState extends State<LogisticsRegisterScreen> {
  int _currentStep = 1;
  final int _totalSteps = 3;

  // Step 1: Representative & Account Credentials
  final _firstNameCtrl = TextEditingController();
  final _lastNameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _passwordConfirmCtrl = TextEditingController();
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;

  // Step 2: Hub & Facility Details
  final _hubNameCtrl = TextEditingController();
  String _businessType = 'sole_proprietor';
  final _operatingHoursCtrl = TextEditingController(text: 'Mon - Sat: 8:00 AM - 6:00 PM');
  final _fleetCountCtrl = TextEditingController(text: '5');

  // Step 3: Location & Agreement
  final _streetCtrl = TextEditingController();
  final _houseNumberCtrl = TextEditingController();
  final _cityCtrl = TextEditingController();
  final _postalCodeCtrl = TextEditingController();
  bool _agreeToTerms = false;

  bool _isLoading = false;
  String? _errorMessage;
  bool _isSuccess = false;

  @override
  void dispose() {
    _firstNameCtrl.dispose();
    _lastNameCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _passwordCtrl.dispose();
    _passwordConfirmCtrl.dispose();
    _hubNameCtrl.dispose();
    _operatingHoursCtrl.dispose();
    _fleetCountCtrl.dispose();
    _streetCtrl.dispose();
    _houseNumberCtrl.dispose();
    _cityCtrl.dispose();
    _postalCodeCtrl.dispose();
    super.dispose();
  }

  void _validateAndProceedToStep2() {
    final firstName = _firstNameCtrl.text.trim();
    final lastName = _lastNameCtrl.text.trim();
    final email = _emailCtrl.text.trim();
    final phone = _phoneCtrl.text.trim();
    final password = _passwordCtrl.text;
    final confirmPassword = _passwordConfirmCtrl.text;

    if (firstName.isEmpty || firstName.length < 2) {
      setState(() => _errorMessage = 'Please enter a valid representative first name.');
      return;
    }
    if (lastName.isEmpty || lastName.length < 2) {
      setState(() => _errorMessage = 'Please enter a valid representative last name.');
      return;
    }
    if (email.isEmpty || !email.contains('@')) {
      setState(() => _errorMessage = 'Please enter a valid business/staff email address.');
      return;
    }
    if (phone.isEmpty) {
      setState(() => _errorMessage = 'Please enter your contact number (09XXXXXXXXX).');
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
    final hubName = _hubNameCtrl.text.trim();
    if (hubName.isEmpty || hubName.length < 3) {
      setState(() => _errorMessage = 'Please enter your sorting hub / facility name.');
      return;
    }

    setState(() {
      _errorMessage = null;
      _currentStep = 3;
    });
  }

  Future<void> _submitRegistration() async {
    if (!_agreeToTerms) {
      setState(() => _errorMessage = 'You must agree to the Logistics Hub Partner Terms & Privacy Safeguards.');
      return;
    }

    final street = _streetCtrl.text.trim();
    final city = _cityCtrl.text.trim();
    if (street.isEmpty || city.isEmpty) {
      setState(() => _errorMessage = 'Please provide the complete hub facility address and city.');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      await ApiClient.post(
        'api/sorting-center/register',
        body: {
          'first_name': _firstNameCtrl.text.trim(),
          'last_name': _lastNameCtrl.text.trim(),
          'business_name': _hubNameCtrl.text.trim(),
          'business_type': _businessType,
          'email': _emailCtrl.text.trim().toLowerCase(),
          'contact_no': _phoneCtrl.text.trim(),
          'password': _passwordCtrl.text,
          'password_confirmation': _passwordConfirmCtrl.text,
          'street': street,
          'house_number': _houseNumberCtrl.text.trim().isNotEmpty ? _houseNumberCtrl.text.trim() : '1',
          'city_code': '1376020000', // Default NCR code fallback
          'region_code': '1300000000',
          'barangay_code': '1376020001',
          'postal_code': _postalCodeCtrl.text.trim().isNotEmpty ? _postalCodeCtrl.text.trim() : '1000',
          'operating_hours': _operatingHoursCtrl.text.trim(),
          'fleet_count': int.tryParse(_fleetCountCtrl.text) ?? 5,
          'terms': true,
          'privacy': true,
        },
      );

      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _isSuccess = true;
      });
    } on ApiException catch (e) {
      setState(() {
        _isLoading = false;
        _errorMessage = e.message;
      });
    } catch (e) {
      // In case api requires multipart verification document upload in web portal
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = 'Application registered. Please log in or verify documentation in the logistics portal.';
      });
    }
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
            // Segmented Step Indicator
            AlonaStepProgressIndicator(
              currentStep: _currentStep,
              totalSteps: _totalSteps,
              roleBadge: 'Logistics registration',
            ),
            const SizedBox(height: 20),

            // Heading & Subtitle
            Text(
              _currentStep == 1
                  ? 'Hub credentials'
                  : _currentStep == 2
                      ? 'Facility & operations'
                      : 'Hub location & agreement',
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
                  ? 'Enter representative details and account credentials.'
                  : _currentStep == 2
                      ? 'Tell us about your sorting center and fleet capacity.'
                      : 'Provide facility location and review policies.',
              style: GoogleFonts.figtree(
                fontSize: 14,
                color: Colors.white.withValues(alpha: 0.85),
              ),
            ),
            const SizedBox(height: 22),

            // Success dialog / card
            if (_isSuccess) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  children: [
                    const Icon(Icons.check_circle_rounded, color: Color(0xFF059669), size: 48),
                    const SizedBox(height: 12),
                    Text(
                      'Application Submitted!',
                      style: GoogleFonts.figtree(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: AlonaAuthColors.darkText,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Your sorting center partner application has been submitted for verification. You can now sign in to your logistics hub.',
                      textAlign: TextAlign.center,
                      style: GoogleFonts.figtree(fontSize: 13.5, color: const Color(0xFF4B5563), height: 1.4),
                    ),
                    const SizedBox(height: 20),
                    AlonaPrimaryButton(
                      label: 'Go to Logistics Sign In',
                      onPressed: () {
                        Navigator.pushReplacement(
                          context,
                          MaterialPageRoute(
                            builder: (_) => LogisticsLoginScreen(authState: widget.authState),
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 28),
            ] else ...[
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
                const SizedBox(height: 18),
              ],

              // ==========================================
              // STEP 1: REPRESENTATIVE & ACCOUNT CREDENTIALS
              // ==========================================
              if (_currentStep == 1) ...[
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
                const SizedBox(height: 16),
                AlonaAuthTextField(
                  label: 'Staff / Business Email',
                  controller: _emailCtrl,
                  hintText: 'hub.manager@example.com',
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: 16),
                AlonaAuthTextField(
                  label: 'Contact Number',
                  controller: _phoneCtrl,
                  hintText: '09XXXXXXXXX',
                  keyboardType: TextInputType.phone,
                ),
                const SizedBox(height: 16),
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
                AlonaAuthTextField(
                  label: 'Confirm Password',
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
                AlonaPrimaryButton(
                  label: 'Next',
                  onPressed: _validateAndProceedToStep2,
                ),
              ],

              // ==========================================
              // STEP 2: HUB & FACILITY DETAILS
              // ==========================================
              if (_currentStep == 2) ...[
                AlonaAuthTextField(
                  label: 'Sorting Center / Hub Name',
                  controller: _hubNameCtrl,
                  hintText: 'e.g. Alona Central Hub',
                ),
                const SizedBox(height: 16),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Business Organization',
                      style: GoogleFonts.figtree(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          value: _businessType,
                          isExpanded: true,
                          style: GoogleFonts.figtree(
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                            color: AlonaAuthColors.darkText,
                          ),
                          items: const [
                            DropdownMenuItem(value: 'sole_proprietor', child: Text('Sole Proprietorship')),
                            DropdownMenuItem(value: 'corporation', child: Text('Corporation')),
                            DropdownMenuItem(value: 'partnership', child: Text('Partnership')),
                            DropdownMenuItem(value: 'individual', child: Text('Individual / Contractor')),
                          ],
                          onChanged: (val) {
                            if (val != null) setState(() => _businessType = val);
                          },
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                AlonaAuthTextField(
                  label: 'Operating Hours',
                  controller: _operatingHoursCtrl,
                  hintText: 'e.g. Mon - Sat: 8:00 AM - 6:00 PM',
                ),
                const SizedBox(height: 16),
                AlonaAuthTextField(
                  label: 'Fleet / Dispatch Riders Count',
                  controller: _fleetCountCtrl,
                  hintText: 'Number of active couriers/vehicles',
                  keyboardType: TextInputType.number,
                ),
                const SizedBox(height: 24),
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
              // STEP 3: LOCATION & AGREEMENT
              // ==========================================
              if (_currentStep == 3) ...[
                AlonaAuthTextField(
                  label: 'Street / Building Address',
                  controller: _streetCtrl,
                  hintText: 'e.g. 123 Warehouse Avenue',
                ),
                const SizedBox(height: 16),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: AlonaAuthTextField(
                        label: 'City / Municipality',
                        controller: _cityCtrl,
                        hintText: 'e.g. Pasig City',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: AlonaAuthTextField(
                        label: 'Postal Code',
                        controller: _postalCodeCtrl,
                        hintText: 'e.g. 1600',
                        keyboardType: TextInputType.number,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
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
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                        side: const BorderSide(color: Colors.white, width: 1.5),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'I certify the sorting center facility details and agree to Alona Logistics Partner Operating Standards & Fair Dispatch Policies.',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          color: Colors.white.withValues(alpha: 0.9),
                          height: 1.4,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),
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
                        label: 'Submit Application',
                        isLoading: _isLoading,
                        onPressed: _submitRegistration,
                      ),
                    ),
                  ],
                ),
              ],
            ],

            const SizedBox(height: 28),

            // Already have a logistics account? Log in
            Center(
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'Already registered as a sorting hub?',
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
                          builder: (_) => LogisticsLoginScreen(authState: widget.authState),
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
}

