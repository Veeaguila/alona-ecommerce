import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppColors {
  // Alona Brand Primary Colors
  // Alona Teal #087F8C
  static const Color primary = Color(0xFF087F8C);
  static const Color primaryTeal = Color(0xFF087F8C);
  static const Color alonaTeal = Color(0xFF087F8C);
  static const Color primaryDark = Color(0xFF065A63);

  // Ocean Teal #16A6A0
  static const Color oceanTeal = Color(0xFF16A6A0);

  // Light Teal #E8F7F6
  static const Color lightTeal = Color(0xFFE8F7F6);
  static const Color primaryLight = Color(0xFFE8F7F6);

  // Gold Accent #F4B942
  static const Color accent = Color(0xFFF4B942);
  static const Color accentGold = Color(0xFFF4B942);
  static const Color goldAccent = Color(0xFFF4B942);
  static const Color accentAmber = Color(0xFFF4B942);

  // Background #F8FAF9
  static const Color background = Color(0xFFF8FAF9);
  static const Color lightBg = Color(0xFFF8FAF9);

  // White #FFFFFF
  static const Color white = Color(0xFFFFFFFF);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceAlt = Color(0xFFF1F5F9);

  // Text #1F2937
  static const Color text = Color(0xFF1F2937);
  static const Color textPrimary = Color(0xFF1F2937);
  static const Color textSecondary = Color(0xFF4B5563);

  // Muted Text #64748B
  static const Color mutedText = Color(0xFF64748B);
  static const Color textMuted = Color(0xFF64748B);
  static const Color textLight = Color(0xFF94A3B8);

  // Border #E5E7EB
  static const Color border = Color(0xFFE5E7EB);
  static const Color borderLight = Color(0xFFF1F5F9);

  // Success #22A06B
  static const Color success = Color(0xFF22A06B);
  static const Color warning = Color(0xFFF59E0B);

  // Sale #E85D5D
  static const Color sale = Color(0xFFE85D5D);
  static const Color error = Color(0xFFE85D5D);

  // Logistics Specific Dark Palette
  static const Color logisticsHeader = Color(0xFF087F8C);
  static const Color logisticsSidebar = Color(0xFF044D55);
}

class AppTheme {
  // Compatibility aliases
  static const Color primaryTeal = AppColors.primaryTeal;
  static const Color accentAmber = AppColors.accentAmber;
  static const Color darkSlate = AppColors.textPrimary;
  static const Color bodyMuted = AppColors.textMuted;
  static const Color lightBg = AppColors.background;

  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      colorScheme: const ColorScheme.light(
        primary: AppColors.primary,
        secondary: AppColors.accentGold,
        surface: AppColors.surface,
        error: AppColors.error,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: AppColors.textPrimary,
        onError: Colors.white,
      ),
      scaffoldBackgroundColor: AppColors.background,
      fontFamily: GoogleFonts.figtree().fontFamily,
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        scrolledUnderElevation: 1,
        centerTitle: false,
        titleTextStyle: GoogleFonts.figtree(
          fontSize: 18,
          fontWeight: FontWeight.w700,
          color: AppColors.textPrimary,
        ),
        iconTheme: const IconThemeData(color: AppColors.textPrimary),
      ),
      cardTheme: CardThemeData(
        color: AppColors.surface,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: AppColors.border, width: 1),
        ),
        margin: EdgeInsets.zero,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 14,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.error),
        ),
        hintStyle: GoogleFonts.figtree(
          color: AppColors.textLight,
          fontSize: 14,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          textStyle: GoogleFonts.figtree(
            fontSize: 14,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          backgroundColor: AppColors.surfaceAlt,
          foregroundColor: const Color(0xFF334155),
          side: const BorderSide(color: Color(0xFF334155), width: 1.0),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          textStyle: GoogleFonts.figtree(
            fontSize: 13,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.surfaceAlt,
        selectedColor: AppColors.primary,
        secondarySelectedColor: AppColors.primary,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
          side: const BorderSide(color: Color(0xFF334155), width: 1.0),
        ),
        labelStyle: GoogleFonts.figtree(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          color: const Color(0xFF334155),
        ),
        secondaryLabelStyle: GoogleFonts.figtree(
          fontSize: 13,
          fontWeight: FontWeight.w700,
          color: Colors.white,
        ),
        checkmarkColor: Colors.white,
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: AppColors.primary,
        unselectedLabelColor: AppColors.textMuted,
        indicatorColor: AppColors.primary,
        indicatorSize: TabBarIndicatorSize.tab,
        labelStyle: GoogleFonts.figtree(
          fontWeight: FontWeight.w700,
          fontSize: 14,
        ),
        unselectedLabelStyle: GoogleFonts.figtree(
          fontWeight: FontWeight.w500,
          fontSize: 14,
        ),
      ),
    );
  }
}
