import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class TravSTheme {
  static const Color surface = Color(0xFFFFFFFF);
  static const Color background = Color(0xFFF4F7FB);
  static const Color surfaceSoft = Color(0xFFF4F7FB);
  static const Color surfaceMuted = Color(0xFFEAF0F7);
  static const Color line = Color(0xFFD8E1EC);
  static const Color navy = Color(0xFF10233F);
  static const Color navySoft = Color(0xFF18365F);
  static const Color navyMuted = Color(0xFF5D7090);
  static const Color orange = Color(0xFFF58A2A);
  static const Color orangeSoft = Color(0xFFFFF1E4);
  static const Color success = Color(0xFF1F9D67);
  static const Color warning = Color(0xFFF8B34B);
  static const Color danger = Color(0xFFD94F4F);

  static const LinearGradient shellGradient = LinearGradient(
    colors: [Color(0xFFF8FBFF), Color(0xFFF1F5FA)],
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
  );

  static const LinearGradient headerGradient = LinearGradient(
    colors: [Color(0xFF163966), Color(0xFF0B2342)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static ThemeData light() {
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: navy,
        brightness: Brightness.light,
        primary: navy,
        secondary: orange,
        surface: surface,
      ),
    );

    final textTheme = GoogleFonts.plusJakartaSansTextTheme(base.textTheme)
        .copyWith(
          displayLarge: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w800,
          ),
          displayMedium: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w800,
          ),
          displaySmall: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w800,
          ),
          headlineLarge: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w800,
          ),
          headlineMedium: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w800,
          ),
          headlineSmall: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w700,
          ),
          titleLarge: GoogleFonts.plusJakartaSans(
            color: navy,
            fontWeight: FontWeight.w700,
          ),
          titleMedium: GoogleFonts.plusJakartaSans(
            color: navy,
            fontWeight: FontWeight.w700,
          ),
          titleSmall: GoogleFonts.plusJakartaSans(
            color: navy,
            fontWeight: FontWeight.w700,
          ),
          bodyLarge: GoogleFonts.plusJakartaSans(color: navy, height: 1.45),
          bodyMedium: GoogleFonts.plusJakartaSans(color: navy, height: 1.45),
          bodySmall: GoogleFonts.plusJakartaSans(
            color: navyMuted,
            height: 1.45,
          ),
          labelLarge: GoogleFonts.plusJakartaSans(color: navy),
          labelMedium: GoogleFonts.plusJakartaSans(color: navy),
          labelSmall: GoogleFonts.plusJakartaSans(color: navyMuted),
        );

    return base.copyWith(
      scaffoldBackgroundColor: background,
      textTheme: textTheme,
      textSelectionTheme: const TextSelectionThemeData(
        cursorColor: navy,
        selectionColor: orangeSoft,
        selectionHandleColor: navy,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: navy,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        titleTextStyle: textTheme.titleLarge?.copyWith(color: navy),
      ),
      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(28),
          side: const BorderSide(color: line),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          elevation: 0,
          backgroundColor: orange,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          textStyle: textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: navy,
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
          side: const BorderSide(color: Color(0xFFD7E0EA)),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          textStyle: textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surface,
        prefixIconColor: navyMuted,
        suffixIconColor: navyMuted,
        iconColor: navyMuted,
        hintStyle: textTheme.bodyMedium?.copyWith(color: navyMuted),
        labelStyle: textTheme.bodyMedium?.copyWith(color: navy),
        floatingLabelStyle: textTheme.bodyMedium?.copyWith(
          color: orange,
          fontWeight: FontWeight.w700,
        ),
        errorStyle: textTheme.bodySmall?.copyWith(
          color: danger,
          fontWeight: FontWeight.w700,
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 16,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(20),
          borderSide: const BorderSide(color: line),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(20),
          borderSide: const BorderSide(color: line),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(20),
          borderSide: const BorderSide(color: orange, width: 1.4),
        ),
      ),
      chipTheme: base.chipTheme.copyWith(
        backgroundColor: surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(999),
          side: const BorderSide(color: line),
        ),
        selectedColor: navy,
        secondaryLabelStyle: textTheme.labelLarge?.copyWith(
          color: Colors.white,
        ),
        labelStyle: textTheme.labelLarge?.copyWith(color: navy),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: surface,
        indicatorColor: orangeSoft,
        height: 74,
        labelTextStyle: WidgetStatePropertyAll(
          textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
    );
  }
}
