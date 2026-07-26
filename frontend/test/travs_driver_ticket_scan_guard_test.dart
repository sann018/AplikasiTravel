import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/features/home/travs_home_page.dart';

void main() {
  Widget buildHarness({
    required String scheduleStatus,
    required VoidCallback onAllowed,
  }) {
    return MaterialApp(
      home: Scaffold(
        body: Builder(
          builder: (context) => ElevatedButton(
            onPressed: () => handleDriverTicketScanRequest(
              context: context,
              scheduleStatus: scheduleStatus,
              onAllowed: onAllowed,
            ),
            child: const Text('Pindai Tiket'),
          ),
        ),
      ),
    );
  }

  testWidgets('jadwal aktif tetap meneruskan pembukaan pemindai', (
    tester,
  ) async {
    var scannerOpened = false;

    await tester.pumpWidget(
      buildHarness(
        scheduleStatus: 'berangkat',
        onAllowed: () => scannerOpened = true,
      ),
    );
    await tester.tap(find.text('Pindai Tiket'));
    await tester.pump();

    expect(scannerOpened, isTrue);
    expect(
      find.text(
        'Maaf, pemindaian tidak dapat dilakukan karena jadwal perjalanan ini sudah selesai.',
      ),
      findsNothing,
    );
  });

  testWidgets('jadwal selesai menampilkan pesan dan tidak membuka pemindai', (
    tester,
  ) async {
    var scannerOpened = false;

    await tester.pumpWidget(
      buildHarness(
        scheduleStatus: 'selesai',
        onAllowed: () => scannerOpened = true,
      ),
    );
    await tester.tap(find.text('Pindai Tiket'));
    await tester.pump();

    expect(scannerOpened, isFalse);
    expect(
      find.text(
        'Maaf, pemindaian tidak dapat dilakukan karena jadwal perjalanan ini sudah selesai.',
      ),
      findsOneWidget,
    );
  });
}
