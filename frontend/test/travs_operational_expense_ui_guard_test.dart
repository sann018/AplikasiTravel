import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('form biaya pengemudi memakai dropdown dan foto nota wajib', () {
    final detailSource = File(
      'lib/features/home/bagian_beranda/detail_perjalanan_pengemudi.dart',
    ).readAsStringSync();
    final apiSource = File(
      'lib/core/services/travs_api_service.dart',
    ).readAsStringSync();

    expect(detailSource, contains('DropdownButtonFormField<String>'));
    expect(detailSource, contains('expenseCategories'));
    expect(detailSource, contains('Foto bukti/nota'));
    expect(detailSource, contains('_fotoBuktiNota'));
    expect(detailSource, contains('ImageSource.camera'));
    expect(detailSource, contains('ImageSource.gallery'));
    expect(apiSource, contains('http.MultipartRequest'));
    expect(apiSource, contains("'foto_bukti_nota'"));
  });
}
