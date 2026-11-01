import 'package:flutter/material.dart';

class RiderColors {
  static const Color teal = Color(0xFF007A87);
  static const Color tealDark = Color(0xFF005C66);
  static const Color amber = Color(0xFFFDB813);
  static const Color background = Color(0xFFF4F6F8);
  static const Color card = Colors.white;
  static const Color border = Color(0xFFE5E7EB);
  static const Color inputBorder = Color(0xFFD1D5DB);
  static const Color text = Color(0xFF111827);
  static const Color textSecondary = Color(0xFF6B7280);

  // Status and Pill Colors
  static const Color mintBanner = Color(0xFFE6F7F7);
  static const Color mintBadge = Color(0xFFD1FAE5);
  static const Color mintBadgeText = Color(0xFF065F46);

  static const Color amberBadge = Color(0xFFFEF3C7);
  static const Color amberBadgeText = Color(0xFF92400E);

  static const Color blueBadge = Color(0xFFE0F2FE);
  static const Color blueBadgeText = Color(0xFF0369A1);

  static const Color redBadge = Color(0xFFFEE2E2);
  static const Color redBadgeText = Color(0xFF991B1B);

  // Standard semantic colors
  static const Color success = Color(0xFF059669);
  static const Color danger = Color(0xFFDC2626);
}

class RiderTheme {
  static ThemeData get theme {
    return ThemeData(
      useMaterial3: true,
      fontFamily: 'Roboto',
      scaffoldBackgroundColor: RiderColors.background,
      colorScheme: ColorScheme.fromSeed(
        seedColor: RiderColors.teal,
        primary: RiderColors.teal,
        secondary: RiderColors.amber,
        surface: RiderColors.card,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: RiderColors.teal,
        foregroundColor: Colors.white,
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        toolbarHeight: 60,
        titleTextStyle: TextStyle(
          color: Colors.white,
          fontFamily: 'Roboto',
          fontSize: 20,
          fontWeight: FontWeight.w700,
        ),
      ),
      cardTheme: CardThemeData(
        color: RiderColors.card,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: RiderColors.border),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        floatingLabelBehavior: FloatingLabelBehavior.always,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        errorMaxLines: 3,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: RiderColors.inputBorder),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: RiderColors.inputBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: RiderColors.teal, width: 2),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: RiderColors.teal,
          foregroundColor: Colors.white,
          minimumSize: const Size(48, 52),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          textStyle: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w700,
            fontFamily: 'Roboto',
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: RiderColors.text,
          minimumSize: const Size(48, 50),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          side: const BorderSide(color: Color(0xFF64748B)),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            fontFamily: 'Roboto',
          ),
        ),
      ),
    );
  }
}
