import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AlonaAuthColors {
  static const Color backgroundTeal = Color(0xFF05505A);
  static const Color accentTealCircle = Color(0xFF067A87);
  static const Color amber = Color(0xFFF5A524);
  static const Color amberHover = Color(0xFFE0941D);
  static const Color darkText = Color(0xFF0F172A);
  static const Color mutedText = Color(0xFF64748B);
  static const Color hintText = Color(0xFF94A3B8);
  static const Color white = Colors.white;
  static const Color tealAccent = Color(0xFF5EEAD4);
}

/// A shared scaffold with the branded deep teal background (#05505A)
/// and ambient decorative circles (amber on bottom-left, soft teal on top-right)
class AlonaAuthScaffold extends StatelessWidget {
  final Widget body;
  final bool showBackButton;
  final VoidCallback? onBack;
  final Widget? trailing;

  const AlonaAuthScaffold({
    super.key,
    required this.body,
    this.showBackButton = true,
    this.onBack,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AlonaAuthColors.backgroundTeal,
      body: Stack(
        children: [
          // Background ambient circles matching design
          Positioned(
            top: -30,
            right: -40,
            child: Container(
              width: 230,
              height: 230,
              decoration: const BoxDecoration(
                color: AlonaAuthColors.accentTealCircle,
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            bottom: -35,
            left: -35,
            child: Container(
              width: 140,
              height: 140,
              decoration: const BoxDecoration(
                color: AlonaAuthColors.amber,
                shape: BoxShape.circle,
              ),
            ),
          ),

          // Main content
          SafeArea(
            child: Column(
              children: [
                // Top navigation bar
                if (showBackButton || trailing != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        if (showBackButton)
                          IconButton(
                            icon: const Icon(Icons.arrow_back_ios_new, color: Colors.white, size: 20),
                            onPressed: onBack ?? () => Navigator.pop(context),
                          )
                        else
                          const SizedBox(width: 48),
                        ?trailing,
                      ],
                    ),
                  ),

                // Expanded body
                Expanded(child: body),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Input field styled with crisp white card, rounded 14px, dark text, and top white label
class AlonaAuthTextField extends StatelessWidget {
  final String label;
  final TextEditingController controller;
  final String hintText;
  final bool obscureText;
  final Widget? suffixIcon;
  final Widget? rightLabelWidget;
  final TextInputType keyboardType;
  final ValueChanged<String>? onChanged;
  final bool readOnly;
  final VoidCallback? onTap;

  const AlonaAuthTextField({
    super.key,
    required this.label,
    required this.controller,
    required this.hintText,
    this.obscureText = false,
    this.suffixIcon,
    this.rightLabelWidget,
    this.keyboardType = TextInputType.text,
    this.onChanged,
    this.readOnly = false,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              label,
              style: GoogleFonts.figtree(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: Colors.white,
              ),
            ),
            ?rightLabelWidget,
          ],
        ),
        const SizedBox(height: 8),
        TextField(
          controller: controller,
          obscureText: obscureText,
          keyboardType: keyboardType,
          onChanged: onChanged,
          readOnly: readOnly,
          onTap: onTap,
          style: GoogleFonts.figtree(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: AlonaAuthColors.darkText,
          ),
          decoration: InputDecoration(
            hintText: hintText,
            hintStyle: GoogleFonts.figtree(
              fontSize: 14,
              color: AlonaAuthColors.hintText,
              fontWeight: FontWeight.w400,
            ),
            filled: true,
            fillColor: Colors.white,
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: BorderSide.none,
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: AlonaAuthColors.amber, width: 2),
            ),
            suffixIcon: suffixIcon,
          ),
        ),
      ],
    );
  }
}

/// Primary button in golden amber (#F5A524) with dark text
class AlonaPrimaryButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final bool isLoading;

  const AlonaPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.isLoading = false,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton(
        onPressed: isLoading ? null : onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: AlonaAuthColors.amber,
          foregroundColor: AlonaAuthColors.darkText,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          disabledBackgroundColor: AlonaAuthColors.amber.withValues(alpha: 0.6),
        ),
        child: isLoading
            ? const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                  color: AlonaAuthColors.darkText,
                  strokeWidth: 2.4,
                ),
              )
            : Text(
                label,
                style: GoogleFonts.figtree(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: AlonaAuthColors.darkText,
                  letterSpacing: -0.2,
                ),
              ),
      ),
    );
  }
}

/// Secondary white button with dark text
class AlonaSecondaryButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;

  const AlonaSecondaryButton({
    super.key,
    required this.label,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: Colors.white,
          foregroundColor: AlonaAuthColors.darkText,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
        child: Text(
          label,
          style: GoogleFonts.figtree(
            fontSize: 16,
            fontWeight: FontWeight.w800,
            color: AlonaAuthColors.darkText,
          ),
        ),
      ),
    );
  }
}

/// Google Sign-In white card button
class AlonaGoogleButton extends StatelessWidget {
  final VoidCallback? onPressed;
  final String label;

  const AlonaGoogleButton({
    super.key,
    required this.onPressed,
    this.label = 'Continue with Google',
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: Colors.white,
          foregroundColor: const Color(0xFF1F2937),
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 22,
              height: 22,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
              ),
              child: Center(
                child: Text(
                  'G',
                  style: GoogleFonts.roboto(
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                    color: const Color(0xFF4285F4),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Text(
              label,
              style: GoogleFonts.figtree(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: const Color(0xFF1F2937),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Auth divider: "or sign in with email" / "or use your email"
class AlonaAuthDivider extends StatelessWidget {
  final String text;

  const AlonaAuthDivider({super.key, this.text = 'or sign in with email'});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 20),
      child: Row(
        children: [
          Expanded(child: Divider(color: Colors.white.withValues(alpha: 0.18), height: 1)),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Text(
              text,
              style: GoogleFonts.figtree(
                fontSize: 12.5,
                color: Colors.white.withValues(alpha: 0.70),
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          Expanded(child: Divider(color: Colors.white.withValues(alpha: 0.18), height: 1)),
        ],
      ),
    );
  }
}

/// Segmented progress bar: e.g. Step 1 of 3 [Buyer registration]
class AlonaStepProgressIndicator extends StatelessWidget {
  final int currentStep;
  final int totalSteps;
  final String roleBadge;

  const AlonaStepProgressIndicator({
    super.key,
    required this.currentStep,
    required this.totalSteps,
    required this.roleBadge,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              'Step $currentStep of $totalSteps',
              style: GoogleFonts.figtree(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: AlonaAuthColors.tealAccent,
              ),
            ),
            const SizedBox(width: 10),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3.5),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: Colors.white.withValues(alpha: 0.20)),
              ),
              child: Text(
                roleBadge,
                style: GoogleFonts.figtree(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: Colors.white,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: List.generate(totalSteps, (index) {
            final isDone = index + 1 <= currentStep;
            return Expanded(
              child: Container(
                height: 3.5,
                margin: EdgeInsets.only(right: index == totalSteps - 1 ? 0 : 6),
                decoration: BoxDecoration(
                  color: isDone ? AlonaAuthColors.amber : Colors.white.withValues(alpha: 0.22),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            );
          }),
        ),
      ],
    );
  }
}

