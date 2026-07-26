import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  String source(String relativePath) {
    final mainFile = File(relativePath);
    final mainSource = mainFile.readAsStringSync();
    final combinedSource = StringBuffer(mainSource);

    // Test guard ikut membaca widget yang dipisah melalui directive part.
    final partPattern = RegExp(r"^part\s+'([^']+)';", multiLine: true);
    for (final match in partPattern.allMatches(mainSource)) {
      final partPath = match.group(1);
      if (partPath == null) {
        continue;
      }

      final partFile = File.fromUri(mainFile.parent.uri.resolve(partPath));
      combinedSource
        ..writeln()
        ..write(partFile.readAsStringSync());
    }

    return combinedSource.toString();
  }

  test('halaman pembayaran membuka QRIS melalui hosted Pakasir', () {
    final paymentSource = source(
      'lib/features/payment/travs_payment_preview_page.dart',
    );

    expect(paymentSource, isNot(contains('QrImageView(')));
    expect(paymentSource, isNot(contains('data: payment.paymentNumber')));
    expect(paymentSource, contains("const Text('Lanjutkan Pembayaran')"));
    expect(
      paymentSource,
      contains('_openPaymentCheckout(payment.checkoutUrl)'),
    );
    expect(paymentSource, isNot(contains('Scan QRIS di bawah')));
    expect(paymentSource, contains('payment.hasCheckoutUrl'));
    expect(paymentSource, isNot(contains("label: 'Biaya layanan'")));
    expect(paymentSource, contains("label: 'Biaya admin'"));
    expect(
      paymentSource,
      isNot(contains('Biaya admin ditentukan oleh penyedia pembayaran.')),
    );
    expect(paymentSource, contains('payment.providerFee != null'));
    expect(paymentSource, contains("label: 'Metode pembayaran'"));
    expect(paymentSource, contains("const Text('Lihat Tiket Penumpang')"));
    expect(paymentSource, contains('DropdownButtonFormField<String>'));
    expect(paymentSource, contains("const Text('Buat Pembayaran')"));
    expect(paymentSource, contains('_selectedVirtualAccount = value'));
    expect(paymentSource, contains('chooseBookingPaymentMethod('));
    expect(
      paymentSource,
      isNot(
        contains(
          'Ringkasan pemesanan ini dipakai juga oleh daftar pemesanan, riwayat, dan tiket penumpang.',
        ),
      ),
    );
  });

  test('pemilih metode kosong tidak membuat halaman pembayaran gagal', () {
    final paymentSource = source(
      'lib/features/payment/travs_payment_preview_page.dart',
    );
    final detailSource = source(
      'lib/features/booking/travs_booking_detail_page.dart',
    );

    expect(paymentSource, contains('if (families.isEmpty)'));
    expect(paymentSource, contains('selection.isEmpty'));
    expect(detailSource, contains('selection.isEmpty'));
  });

  test(
    'barang bawaan memakai segmented control dan payload kosong saat mati',
    () {
      final paymentSource = source(
        'lib/features/payment/travs_payment_preview_page.dart',
      );

      expect(paymentSource, contains('SegmentedButton<bool>'));
      expect(paymentSource, contains("'Tidak membawa barang'"));
      expect(paymentSource, contains("'Membawa barang'"));
      expect(paymentSource, contains('return const <TravSBaggagePayload>[];'));
      expect(paymentSource, isNot(contains('ChoiceChip(')));
    },
  );

  test('detail pembayaran ringkas tidak memakai mini card berulang', () {
    final paymentSource = source(
      'lib/features/payment/travs_payment_preview_page.dart',
    );

    expect(paymentSource, contains("title: 'Detail Perjalanan'"));
    expect(paymentSource, contains("title: 'Rincian Pembayaran'"));
    expect(paymentSource, contains('_CompactTravelRow('));
    expect(paymentSource, isNot(contains("title: 'Detail Pemesanan'")));
    expect(paymentSource, isNot(contains("label: 'Status pemesanan'")));
    expect(paymentSource, isNot(contains("label: 'Status pembayaran'")));
  });

  test('konfirmasi memakai banner ringkas tanpa label akun penumpang', () {
    final paymentSource = source(
      'lib/features/payment/travs_payment_preview_page.dart',
    );

    expect(paymentSource, contains('_CheckoutStatusBanner('));
    expect(paymentSource, contains("title: 'Detail Perjalanan'"));
    expect(paymentSource, isNot(contains("'Akun Penumpang'")));
    expect(paymentSource, isNot(contains('_HeaderPill(')));
    expect(paymentSource, isNot(contains('_TravelInfoGrid(')));
  });

  test('detail pemesanan selalu terbuka tanpa expand collapse', () {
    final bookingSource = source(
      'lib/features/booking/travs_booking_detail_page.dart',
    );

    expect(bookingSource, contains("title: 'Informasi Pemesanan'"));
    expect(bookingSource, contains("title: 'Lokasi Perjalanan'"));
    expect(bookingSource, contains('SegmentedButton<_BookingLocationTab>'));
    expect(bookingSource, isNot(contains('AnimatedCrossFade')));
    expect(bookingSource, isNot(contains('initiallyExpanded')));
    expect(bookingSource, isNot(contains('keyboard_arrow_up')));
    expect(bookingSource, isNot(contains('keyboard_arrow_down')));
  });

  test('konfirmasi mempertahankan kedua form lokasi dan validasinya', () {
    final paymentSource = source(
      'lib/features/payment/travs_payment_preview_page.dart',
    );

    expect(paymentSource, contains("title: 'Lokasi Perjalanan'"));
    expect(paymentSource, contains('SegmentedButton<_CheckoutLocationTab>'));
    expect(paymentSource, contains('_buildPickupFields()'));
    expect(paymentSource, contains('_buildDropoffFields(context)'));
    expect(paymentSource, contains('Offstage('));
    expect(paymentSource, contains('final isFormValid = form.validate();'));
    expect(paymentSource, contains("'Lengkapi data pada tab Titik Jemput.'"));
    expect(
      paymentSource,
      contains("'Lengkapi data pada tab Titik Tujuan / Turun.'"),
    );
  });

  test('card informasi perjalanan tetap satu card dengan empat bagian', () {
    final scheduleSource = source(
      'lib/features/schedule/travs_schedule_detail_page.dart',
    );

    expect(scheduleSource, contains("'Informasi Perjalanan'"));
    expect(scheduleSource, contains("title: 'Harga Tiket'"));
    expect(scheduleSource, contains("title: 'Kendaraan'"));
    expect(scheduleSource, contains("title: 'Pengemudi'"));
    expect(scheduleSource, contains("title: 'Metode Pembayaran'"));
    expect(scheduleSource, contains('detail.paymentMethods.conciseSummary'));
    expect(scheduleSource, contains('if (index > 0) const Divider'));
    expect(scheduleSource, contains('return Row('));
  });

  test('card pilihan kursi selebar card subtotal tiket', () {
    final scheduleSource = source(
      'lib/features/schedule/travs_schedule_detail_page.dart',
    );

    expect(scheduleSource, contains("key: const Key('seat-choice-summary')"));
    expect(scheduleSource, contains("key: const Key('ticket-price-summary')"));
    expect(
      RegExp(
        r"key: const Key\('(seat-choice-summary|ticket-price-summary)'\),\s+width: double\.infinity",
      ).allMatches(scheduleSource),
      hasLength(2),
    );
  });

  test('peringatan keluar memverifikasi status lunas dari server', () {
    final paymentSource = source(
      'lib/features/payment/travs_payment_preview_page.dart',
    );

    expect(paymentSource, contains('await _refreshCreatedBookingStatus()'));
    expect(paymentSource, contains('if (refreshedBooking == null)'));
    expect(paymentSource, contains('if (booking?.isPaid ?? false)'));
    expect(paymentSource, contains('Navigator.of(context).pop(true)'));
    expect(paymentSource, contains("? 'Keluar dari pembayaran?'"));
  });
}
