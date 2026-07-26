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
    expect(find.text('Lanjut Sebagai Tamu'), findsOneWidget);
    expect(find.text('Penumpang'), findsNothing);
    expect(find.text('Pengemudi'), findsNothing);

    final registerButton = find.text('Daftar Akun Penumpang');
    await tester.ensureVisible(registerButton);
    await tester.tap(registerButton);
    await tester.pumpAndSettle();

    expect(find.text('Daftar Akun Penumpang'), findsOneWidget);
    expect(find.text('Lanjut Sebagai Tamu'), findsNothing);
    expect(find.textContaining('Akun pengemudi dibuat admin'), findsNothing);
  });

  testWidgets('menolak format email lupa kata sandi yang tidak lengkap', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const TravSApp());
    await tester.pumpAndSettle();

    await tester.tap(find.text('Lupa Password?'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField), 'alamat@');
    await tester.tap(find.text('Kirim Tautan Reset'));
    await tester.pump();

    expect(find.text('Format email tidak valid.'), findsOneWidget);
  });
}
