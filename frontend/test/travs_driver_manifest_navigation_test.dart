import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('tombol manifes detail membuka perjalanan pengemudi yang sama', () {
    final scheduleSource = File(
      'lib/features/schedule/travs_schedule_detail_page.dart',
    ).readAsStringSync();
    final homeSource = File(
      'lib/features/home/travs_home_page.dart',
    ).readAsStringSync();

    // Hasil navigasi membawa ID jadwal ke manifes yang sudah tersedia.
    expect(scheduleSource, contains("pop('open_driver_manifest')"));
    expect(
      scheduleSource,
      isNot(contains('Fitur manifes detail akan segera tersedia.')),
    );
    expect(homeSource, contains("result == 'open_driver_manifest'"));
    expect(homeSource, contains('_openDriverJourneyById(schedule.id)'));
  });
}
