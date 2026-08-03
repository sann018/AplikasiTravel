import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'password mobile minimal enam dan setiap field memakai satu ikon hitam',
    () {
      final authSource = File(
        'lib/features/auth/travs_auth_page.dart',
      ).readAsStringSync();
      final profileSource = File(
        'lib/features/home/bagian_beranda/profil.dart',
      ).readAsStringSync();

      for (final source in [authSource, profileSource]) {
        expect(source, isNot(contains('LengthLimitingTextInputFormatter(8)')));
        expect(source, isNot(contains('length > 8')));
        expect(source, contains('LengthLimitingTextInputFormatter(72)'));
        expect(source, contains('Minimal 6 karakter'));
      }

      expect(authSource, contains('color: Colors.black'));
      expect(profileSource, contains('_currentPasswordHidden'));
      expect(profileSource, contains('_newPasswordHidden'));
      expect(profileSource, contains('_confirmPasswordHidden'));
      expect(profileSource, contains('color: Colors.black'));
    },
  );
}
