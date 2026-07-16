import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/services/travs_validation_message.dart';

void main() {
  group('travsFriendlyValidationMessage', () {
    test('mengubah jadwal yang tidak tersedia menjadi pesan yang ramah', () {
      expect(
        travsFriendlyValidationMessage('jadwal_id', 'validation.exists'),
        'Jadwal yang dipilih sudah tidak tersedia. Silakan pilih jadwal lain.',
      );
    });

    test('tidak pernah menampilkan key validasi yang belum dikenal', () {
      final message = travsFriendlyValidationMessage(
        'data_lain',
        'validation.some_future_rule',
      );

      expect(
        message,
        'Data yang dikirim belum valid. Periksa kembali isian Anda.',
      );
      expect(message, isNot(contains('validation.')));
    });

    test('mempertahankan pesan bisnis bahasa Indonesia dari backend', () {
      const serverMessage = 'Kursi yang dipilih sudah tidak tersedia.';

      expect(
        travsFriendlyValidationMessage('seat_numbers.0', serverMessage),
        serverMessage,
      );
    });
  });
}
