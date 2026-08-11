import 'package:intl/date_symbol_data_local.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:frontend/app.dart';

void main() {
  setUpAll(() async {
    await initializeDateFormatting('id_ID');
  });

  setUp(() {
    SharedPreferences.setMockInitialValues(<String, Object>{});
  });

  testWidgets('renders auth flow when no saved session', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const TravSApp());
    await tester.pumpAndSettle();

    expect(find.text('Masuk ke Aplikasi Rusly Travel'), findsOneWidget);
    expect(find.text('Tamu'), findsOneWidget);
    expect(find.byIcon(Icons.visibility_outlined), findsNothing);
    expect(
      find.text('Gunakan email atau nomor HP 11–12 digit yang terdaftar.'),
      findsOneWidget,
    );
    expect(find.text('Penumpang'), findsNothing);
    expect(find.text('Pengemudi'), findsNothing);

    final registerButton = find.text('Daftar Sekarang');
    await tester.ensureVisible(registerButton);
    await tester.tap(registerButton);
    await tester.pumpAndSettle();

    expect(find.text('Daftar Akun Penumpang'), findsOneWidget);
    expect(find.text('Tamu'), findsNothing);
    expect(find.textContaining('Akun pengemudi dibuat admin'), findsNothing);

    final nameFinder = find.widgetWithText(TextFormField, 'Nama lengkap');
    final emailFinder = find.widgetWithText(TextFormField, 'Email');
    final passwordFinder = find.widgetWithText(TextFormField, 'Kata sandi');
    final nameInput = tester.widget<TextField>(
      find.descendant(of: nameFinder, matching: find.byType(TextField)),
    );
    final emailInput = tester.widget<TextField>(
      find.descendant(of: emailFinder, matching: find.byType(TextField)),
    );
    final passwordInput = tester.widget<TextField>(
      find.descendant(of: passwordFinder, matching: find.byType(TextField)),
    );
    final nameField = tester.widget<TextFormField>(nameFinder);
    final emailField = tester.widget<TextFormField>(emailFinder);
    final passwordField = tester.widget<TextFormField>(passwordFinder);

    expect(nameField.validator?.call('Ihsan Al Husna'), isNull);
    expect(nameField.validator?.call('Ihsan123'), isNotNull);
    expect(emailField.validator?.call('penumpang@yahoo.com'), isNull);
    expect(emailField.validator?.call('mahasiswa@polnep.ac.id'), isNull);
    expect(emailField.validator?.call('alamat@'), isNotNull);
    expect(passwordField.validator?.call('Abc12!'), isNull);
    expect(passwordField.validator?.call('Abcdefghijklmnopqrstu1!'), isNotNull);
    expect(nameInput.decoration?.helperMaxLines, 3);
    expect(emailInput.decoration?.helperMaxLines, 3);
    expect(passwordInput.decoration?.helperMaxLines, 3);
    expect(passwordInput.decoration?.errorMaxLines, 3);

    await tester.enterText(nameFinder, 'Ihsan123 Al Husna');
    expect(find.text('Ihsan Al Husna'), findsOneWidget);
  });

  testWidgets('menolak format email lupa kata sandi yang tidak lengkap', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const TravSApp());
    await tester.pumpAndSettle();

    await tester.tap(find.text('Lupa Kata Sandi?'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField), 'alamat@');
    await tester.tap(find.text('Kirim Tautan Reset'));
    await tester.pump();

    expect(find.text('Format email tidak valid.'), findsOneWidget);
  });
}
