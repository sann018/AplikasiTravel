import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/models/app_role_preview.dart';
import 'package:frontend/core/models/app_settings.dart';
import 'package:frontend/core/models/travs_booking_contract.dart';
import 'package:frontend/core/models/travs_user_session.dart';
import 'package:frontend/core/services/travs_api_service.dart';
import 'package:frontend/features/booking/travs_booking_detail_page.dart';
import 'package:frontend/features/payment/travs_payment_preview_page.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUpAll(() async => initializeDateFormatting('id_ID'));
  setUp(() => SharedPreferences.setMockInitialValues(<String, Object>{}));

  testWidgets('konfirmasi tampil ringkas tanpa informasi jenis akun', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        home: TravSPaymentPreviewPage(
          detail: _scheduleDetail,
          appSettings: AppSettings.defaults,
          checkoutDraft: _checkoutDraft,
          selectedRole: AppRolePreview.passenger,
          session: _session,
          selectedSeatNumbers: const [1],
          baggagePlacement: 'masuk_bagasi',
          baggageWeight: 0,
          baggageItems: 0,
          lockExpiresAt: '2099-07-25T23:39:50+07:00',
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Lengkapi Data Pemesanan'), findsOneWidget);
    expect(find.text('Detail Perjalanan'), findsOneWidget);
    expect(find.text('Akun Penumpang'), findsNothing);
    expect(find.textContaining('Lengkapi sebelum'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('halaman pembayaran tetap tampil saat metode belum dipilih', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final booking = _activeBooking();

    await tester.pumpWidget(
      MaterialApp(
        home: TravSPaymentPreviewPage(
          detail: _scheduleDetail,
          appSettings: AppSettings.defaults,
          checkoutDraft: _checkoutDraft,
          selectedRole: AppRolePreview.passenger,
          session: _session,
          selectedSeatNumbers: const [1],
          baggagePlacement: 'masuk_bagasi',
          baggageWeight: 0,
          baggageItems: 0,
          lockExpiresAt: '2099-07-25T23:39:50+07:00',
          initialBooking: booking,
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Pembayaran'), findsOneWidget);
    expect(find.text('Menunggu Pembayaran'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Pilih Metode Pembayaran'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Pilih Metode Pembayaran'), findsOneWidget);

    await tester.ensureVisible(find.text('QRIS'));
    await tester.pump();
    await tester.tap(find.text('QRIS'));
    await tester.pump();
    await tester.ensureVisible(find.text('QRIS'));
    await tester.pump();
    await tester.tap(find.text('QRIS'));
    await tester.pump();

    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('validasi pembayaran tampil dekat pemilih metode', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        home: TravSPaymentPreviewPage(
          detail: _scheduleDetail,
          appSettings: AppSettings.defaults,
          checkoutDraft: _checkoutDraft,
          selectedRole: AppRolePreview.passenger,
          session: _session,
          selectedSeatNumbers: const [1],
          baggagePlacement: 'masuk_bagasi',
          baggageWeight: 0,
          baggageItems: 0,
          lockExpiresAt: '2099-07-25T23:39:50+07:00',
          initialBooking: _activeBooking(),
        ),
      ),
    );
    await tester.pump();
    final scrollable = tester.state<ScrollableState>(
      find.byType(Scrollable).first,
    );
    scrollable.position.jumpTo(600);
    await tester.pump();
    await tester.tap(find.text('Buat Pembayaran'));
    await tester.pump();

    final validation = find.text('Pilih metode pembayaran terlebih dahulu.');
    expect(validation, findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('detail pemesanan merender pemilih pembayaran di layar sempit', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        home: TravSBookingDetailPage(
          session: _session,
          bookingId: 31,
          apiService: _FakeBookingApi(_activeBooking()),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Pilih Metode Pembayaran'),
      300,
      scrollable: find.byType(Scrollable).first,
    );

    expect(find.text('Pilih Metode Pembayaran'), findsOneWidget);
    expect(find.text('VA'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('nilai pada card pembayaran detail rata kanan', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: TravSBookingDetailPage(
          session: _session,
          bookingId: 33,
          apiService: _FakeBookingApi(_paidQrisBooking()),
        ),
      ),
    );
    await tester.pumpAndSettle();

    for (final value in [
      'Terbayar',
      'Berhasil',
      'QRIS',
      'Rp400.000',
      'Rp4.000',
      'Rp404.000',
    ]) {
      final rightAligned = find
          .text(value)
          .evaluate()
          .map((element) => element.widget)
          .whereType<Text>()
          .where((text) => text.textAlign == TextAlign.right);
      expect(rightAligned, isNotEmpty, reason: value);
    }
  });

  testWidgets('pending tampil ringkas dengan countdown dan total satu kali', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        home: TravSPaymentPreviewPage(
          detail: _scheduleDetail,
          appSettings: AppSettings.defaults,
          checkoutDraft: _checkoutDraft,
          selectedRole: AppRolePreview.passenger,
          session: _session,
          selectedSeatNumbers: const [1],
          baggagePlacement: 'masuk_bagasi',
          baggageWeight: 0,
          baggageItems: 0,
          lockExpiresAt: '2099-07-25T23:39:50+07:00',
          initialBooking: _activeQrisBooking(),
        ),
      ),
    );
    await tester.pump();

    // Banner berada paling atas, jadi periksa sebelum daftar digulir ke tombol.
    expect(find.text('Menunggu Pembayaran'), findsOneWidget);
    expect(find.textContaining('Bayar sebelum'), findsWidgets);

    await tester.scrollUntilVisible(
      find.text('Lanjutkan Pembayaran'),
      300,
      scrollable: find.byType(Scrollable).first,
    );

    expect(find.text('Detail Perjalanan'), findsOneWidget);
    expect(find.text('Rincian Pembayaran'), findsOneWidget);
    expect(find.text('Lanjutkan Pembayaran'), findsOneWidget);
    expect(find.text('Rp404.000'), findsOneWidget);
    expect(find.text('QRIS'), findsOneWidget);
    expect(find.text('Status pembayaran'), findsNothing);
    expect(find.text('Nominal transaksi'), findsNothing);
    expect(find.byType(Image), findsNothing);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('pembayaran berhasil tanpa countdown dan informasi berulang', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        home: TravSPaymentPreviewPage(
          detail: _scheduleDetail,
          appSettings: AppSettings.defaults,
          checkoutDraft: _checkoutDraft,
          selectedRole: AppRolePreview.passenger,
          session: _session,
          selectedSeatNumbers: const [1],
          baggagePlacement: 'masuk_bagasi',
          baggageWeight: 0,
          baggageItems: 0,
          lockExpiresAt: '2099-07-25T23:39:50+07:00',
          initialBooking: _paidQrisBooking(),
        ),
      ),
    );
    await tester.pump();

    // Status berhasil hanya tampil satu kali pada banner paling atas.
    expect(find.text('Pembayaran Berhasil'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.text('Lihat Tiket Penumpang'),
      300,
      scrollable: find.byType(Scrollable).first,
    );

    expect(find.text('Total dibayar'), findsOneWidget);
    expect(find.text('Rp404.000'), findsOneWidget);
    expect(find.text('Lihat Tiket Penumpang'), findsOneWidget);
    expect(find.textContaining('Bayar sebelum'), findsNothing);
    expect(find.text('Status pembayaran'), findsNothing);
    expect(find.text('Status pemesanan'), findsNothing);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets(
    'pembayaran kedaluwarsa menghentikan countdown tanpa aksi palsu',
    (tester) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await tester.pumpWidget(
        MaterialApp(
          home: TravSPaymentPreviewPage(
            detail: _scheduleDetail,
            appSettings: AppSettings.defaults,
            checkoutDraft: _checkoutDraft,
            selectedRole: AppRolePreview.passenger,
            session: _session,
            selectedSeatNumbers: const [1],
            baggagePlacement: 'masuk_bagasi',
            baggageWeight: 0,
            baggageItems: 0,
            lockExpiresAt: '2026-07-25T23:39:50+07:00',
            initialBooking: _expiredQrisBooking(),
          ),
        ),
      );
      await tester.pump();

      expect(find.text('Pembayaran Kedaluwarsa'), findsOneWidget);
      expect(find.text('Buat Pembayaran Baru'), findsNothing);
      expect(find.text('Lanjutkan Pembayaran'), findsNothing);
      expect(find.textContaining('Bayar sebelum'), findsNothing);
      expect(tester.takeException(), isNull);

      await tester.pumpWidget(const SizedBox.shrink());
    },
  );

  testWidgets('pembayaran tunai tidak menampilkan countdown pembayaran', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        home: TravSPaymentPreviewPage(
          detail: _scheduleDetail,
          appSettings: AppSettings.defaults,
          checkoutDraft: _checkoutDraft,
          selectedRole: AppRolePreview.passenger,
          session: _session,
          selectedSeatNumbers: const [1],
          baggagePlacement: 'masuk_bagasi',
          baggageWeight: 0,
          baggageItems: 0,
          lockExpiresAt: '2099-07-25T23:39:50+07:00',
          initialBooking: _cashBooking(),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Pembayaran Tunai'), findsOneWidget);
    expect(
      find.text(
        'Bayarkan kepada pengemudi setelah dijemput dan sebelum naik kendaraan.',
      ),
      findsOneWidget,
    );
    expect(find.textContaining('Bayar sebelum'), findsNothing);
    expect(find.text('Lanjutkan Pembayaran'), findsNothing);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox.shrink());
  });
}

TravSBookingResult _activeBooking() {
  return TravSBookingResult.fromJson({
    'booking_id': 31,
    'order_id': 'TRAVS-UJI-31',
    'status_pemesanan': 'menunggu_pembayaran',
    'jumlah_kursi': 1,
    'batas_pembayaran': '2099-07-25T23:39:50+07:00',
    'subtotal_tiket': 400000,
    'total_harga': 400000,
    'rute': 'Pontianak - Kapuas Hulu',
    'waktu_berangkat': '2099-07-26T05:00:00+07:00',
    'kendaraan': {'merk': 'Toyota Avanza', 'tipe': 'MPV'},
    'payment': {
      'status_pembayaran': null,
      'metode_pembayaran': null,
      'can_choose': true,
      'selection_expires_at': '2099-07-25T23:39:50+07:00',
      'nominal_transaksi': 400000,
    },
  });
}

TravSBookingResult _activeQrisBooking() {
  return TravSBookingResult.fromJson({
    'booking_id': 32,
    'order_id': 'TRAVS-UJI-32',
    'status_pemesanan': 'menunggu_pembayaran',
    'jumlah_kursi': 1,
    'batas_pembayaran': '2099-07-25T23:39:50+07:00',
    'subtotal_tiket': 400000,
    'total_harga': 400000,
    'rute': 'Pontianak - Kapuas Hulu',
    'waktu_berangkat': '2099-07-26T05:00:00+07:00',
    'kendaraan': {'merk': 'Toyota Avanza', 'tipe': 'MPV'},
    'payment': {
      'status_pembayaran': 'menunggu',
      'metode_pembayaran': 'qris',
      'method_label': 'QRIS',
      'method_family': 'qris',
      'uses_gateway': true,
      'provider': 'pakasir',
      'nominal_transaksi': 400000,
      'biaya_admin_provider': 4000,
      'total_dibayar_penumpang': 404000,
      'payment_number': 'QRIS-RAW-DARI-PAKASIR',
      'checkout_url':
          'https://app.pakasir.com/pay/travs/400000?order_id=TRX-UJI-32&qris_only=1',
      'expired_at': '2099-07-25T23:39:50+07:00',
    },
  });
}

TravSBookingResult _paidQrisBooking() {
  return TravSBookingResult.fromJson({
    'booking_id': 33,
    'order_id': 'TRAVS-UJI-33',
    'status_pemesanan': 'terbayar',
    'jumlah_kursi': 1,
    'batas_pembayaran': '2099-07-25T23:39:50+07:00',
    'subtotal_tiket': 400000,
    'total_harga': 400000,
    'rute': 'Pontianak - Kapuas Hulu',
    'waktu_berangkat': '2099-07-26T05:00:00+07:00',
    'kendaraan': {'merk': 'Toyota Avanza', 'tipe': 'MPV'},
    'passengers': [
      {'seat_number': 1, 'nama_penumpang': 'Penumpang Uji'},
    ],
    'payment': {
      'status_pembayaran': 'berhasil',
      'metode_pembayaran': 'qris',
      'method_label': 'QRIS',
      'method_family': 'qris',
      'uses_gateway': true,
      'nominal_transaksi': 400000,
      'biaya_admin_provider': 4000,
      'total_dibayar_penumpang': 404000,
      'paid_at': '2026-07-26T08:43:00+07:00',
    },
  });
}

TravSBookingResult _expiredQrisBooking() {
  return TravSBookingResult.fromJson({
    'booking_id': 34,
    'order_id': 'TRAVS-UJI-34',
    'status_pemesanan': 'dibatalkan',
    'jumlah_kursi': 1,
    'batas_pembayaran': '2026-07-25T23:39:50+07:00',
    'subtotal_tiket': 400000,
    'total_harga': 400000,
    'rute': 'Pontianak - Kapuas Hulu',
    'waktu_berangkat': '2099-07-26T05:00:00+07:00',
    'kendaraan': {'merk': 'Toyota Avanza', 'tipe': 'MPV'},
    'payment': {
      'status_pembayaran': 'kedaluwarsa',
      'metode_pembayaran': 'qris',
      'method_label': 'QRIS',
      'method_family': 'qris',
      'uses_gateway': true,
      'nominal_transaksi': 400000,
      'biaya_admin_provider': 4000,
      'total_dibayar_penumpang': 404000,
      'expired_at': '2026-07-25T23:39:50+07:00',
    },
  });
}

TravSBookingResult _cashBooking() {
  return TravSBookingResult.fromJson({
    'booking_id': 35,
    'order_id': 'TRAVS-UJI-35',
    'status_pemesanan': 'menunggu_pembayaran',
    'jumlah_kursi': 1,
    // Data legacy sengaja masih mempunyai deadline untuk memastikan mobile
    // tetap tidak menampilkan countdown setelah metode tunai dipilih.
    'batas_pembayaran': '2099-07-25T23:39:50+07:00',
    'subtotal_tiket': 250000,
    'total_harga': 250000,
    'rute': 'Pontianak - Sanggau',
    'waktu_berangkat': '2099-07-29T16:00:00+07:00',
    'kendaraan': {'merk': 'Toyota Avanza', 'tipe': 'MPV'},
    'passengers': [
      {'seat_number': 1, 'nama_penumpang': 'Penumpang Uji'},
    ],
    'payment': {
      'status_pembayaran': 'menunggu_konfirmasi_tunai',
      'metode_pembayaran': 'cash',
      'method_label': 'Bayar Tunai',
      'method_family': 'cash',
      'uses_gateway': false,
      'nominal_transaksi': 250000,
      'biaya_admin_provider': 0,
      'total_dibayar_penumpang': 250000,
      'expired_at': null,
    },
  });
}

const _session = TravSUserSession(
  token: 'token-uji',
  user: TravSUserProfile(
    id: 6,
    nama: 'Penumpang Uji',
    email: 'penumpang@gmail.com',
    noHp: '081234567890',
    peran: 'penumpang',
    statusAktif: true,
  ),
);

const _checkoutDraft = TravSCheckoutDraft(
  seatCount: 1,
  passengerCount: 1,
  baggageMethod: '',
  baggagePlacement: 'masuk_bagasi',
  baggageSizeCategory: '',
  largeBaggageType: '',
  baggageWeight: 0,
  baggageSize: 0,
  baggageLength: 0,
  baggageWidth: 0,
  baggageItems: 0,
  ticketSubtotal: 400000,
  weightFee: 0,
  sizeFee: 0,
  itemFee: 0,
);

final _scheduleDetail = TravSScheduleDetail.fromJson({
  'id': 11,
  'rute': {
    'asal': 'Pontianak',
    'tujuan': 'Kapuas Hulu',
    'jalur': 'Pontianak - Kapuas Hulu',
    'harga_tiket': 400000,
  },
  'waktu_berangkat': '2099-07-26T05:00:00+07:00',
  'estimasi_tiba': '2099-07-26T15:00:00+07:00',
  'kendaraan': {
    'merk': 'Toyota Avanza',
    'plat': 'KB 4443 XYZ',
    'tipe': 'MPV',
    'kapasitas': 6,
    'seat_layout': [1, 3, 2],
  },
  'pengemudi': {'nama': 'Pengemudi Uji'},
  'seat_map': [
    {'id': 1, 'nomor_kursi': 1, 'status': 'terkunci'},
  ],
  'booking_rules': {
    'max_seat_per_booking': 3,
    'seat_lock_form_minutes': 10,
    'seat_lock_payment_minutes': 10,
  },
  'payment_methods': {
    'qris': true,
    'transfer': true,
    'cash': false,
    'options': [
      {'code': 'qris', 'label': 'QRIS', 'family': 'qris'},
      {'code': 'bri_va', 'label': 'Virtual Account BRI', 'family': 'va'},
    ],
  },
  'bagasi': <String, Object>{},
});

class _FakeBookingApi extends TravSApiService {
  _FakeBookingApi(this.booking);

  final TravSBookingResult booking;

  @override
  Future<TravSBookingResult> loadPassengerBookingDetail({
    required String token,
    required int bookingId,
  }) async => booking;

  @override
  Future<TravSPaymentMethods> loadPaymentMethods() async =>
      _scheduleDetail.paymentMethods;
}
