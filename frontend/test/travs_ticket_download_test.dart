import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/models/travs_booking_contract.dart';
import 'package:frontend/core/models/travs_user_session.dart';
import 'package:frontend/core/services/travs_api_service.dart';
import 'package:frontend/features/ticket/travs_ticket_detail_page.dart';
import 'package:intl/date_symbol_data_local.dart';

void main() {
  setUpAll(() async => initializeDateFormatting('id_ID'));

  testWidgets(
    'tiket multi penumpang memakai pemilih nama dan dapat disimpan sebagai PNG',
    (tester) async {
      tester.view.physicalSize = const Size(390, 1800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      Uint8List? savedBytes;
      String? savedFileName;
      await tester.pumpWidget(
        MaterialApp(
          home: TravSTicketDetailPage(
            session: _session,
            bookingId: 88,
            apiService: _FakeTicketApi(),
            saveTicketImage: (bytes, fileName) async {
              savedBytes = bytes;
              savedFileName = fileName;
            },
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Nama Pemilik Tiket'), findsOneWidget);
      expect(find.byKey(const Key('ticket-owner-selector')), findsOneWidget);
      expect(find.byKey(const Key('active-ticket-card')), findsOneWidget);
      expect(find.text('Kursi A1 - Pontianak - Sanggau'), findsOneWidget);
      expect(find.text('Bagikan'), findsOneWidget);

      await tester.drag(
        find.byKey(const Key('ticket-owner-selector')),
        const Offset(-140, 0),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ChoiceChip, 'Budi Santoso'));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('active-ticket-card')), findsOneWidget);
      expect(find.text('Kursi A1 - Pontianak - Sanggau'), findsNothing);
      expect(find.text('Kursi A2 - Pontianak - Sanggau'), findsOneWidget);

      await tester.ensureVisible(find.text('Unduh Tiket'));
      await tester.pump();
      await tester.tap(find.text('Unduh Tiket'));
      await tester.pump();
      await tester.runAsync(
        () => Future<void>.delayed(const Duration(milliseconds: 500)),
      );
      await tester.pump();

      expect(savedBytes, isNotNull);
      expect(savedBytes, isNotEmpty);
      expect(savedFileName, contains('budi-santoso'));
      late int imageWidth;
      late int imageHeight;
      await tester.runAsync(() async {
        final codec = await ui.instantiateImageCodec(savedBytes!);
        final frame = await codec.getNextFrame();
        imageWidth = frame.image.width;
        imageHeight = frame.image.height;
        frame.image.dispose();
        codec.dispose();
      });
      expect(imageWidth, greaterThan(imageHeight));
      expect(find.text('Tiket berhasil disimpan ke galeri.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'download tiket menampilkan pengaturan ketika izin galeri ditolak',
    (tester) async {
      tester.view.physicalSize = const Size(390, 1800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      var settingsOpened = false;
      var imageSaved = false;
      await tester.pumpWidget(
        MaterialApp(
          home: TravSTicketDetailPage(
            session: _session,
            bookingId: 88,
            apiService: _FakeTicketApi(),
            requestGalleryAccess: () async => false,
            openGallerySettings: () async {
              settingsOpened = true;
              return true;
            },
            saveTicketImage: (bytes, fileName) async {
              imageSaved = true;
            },
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.text('Unduh Tiket'));
      await tester.tap(find.text('Unduh Tiket'));
      await tester.pump(const Duration(milliseconds: 300));

      expect(find.text('Izin Galeri Diperlukan'), findsOneWidget);
      expect(find.text('Buka Pengaturan'), findsOneWidget);
      expect(imageSaved, isFalse);

      await tester.tap(find.text('Buka Pengaturan'));
      await tester.pumpAndSettle();

      expect(settingsOpened, isTrue);
    },
  );
}

const _session = TravSUserSession(
  token: 'ticket-token',
  user: TravSUserProfile(
    id: 1,
    nama: 'Pemesan',
    email: 'pemesan@gmail.com',
    noHp: '081234567890',
    peran: 'penumpang',
    statusAktif: true,
  ),
);

class _FakeTicketApi extends TravSApiService {
  @override
  Future<TravSBookingResult> loadPassengerBookingDetail({
    required String token,
    required int bookingId,
  }) async => _ticketBooking;
}

const _ticketInfo = TravSBookingTicketInfo(
  kodeQr: 'QR-TICKET',
  token: 'token-tiket-rusly-travel',
  sudahDipindai: false,
);

const _ticketBooking = TravSBookingResult(
  id: 88,
  kodeBooking: 'TRAVS-TICKET-88',
  status: 'terbayar',
  jumlahKursi: 3,
  batasPembayaran: '',
  subtotalTiket: 750000,
  totalHarga: 750000,
  rute: 'Pontianak - Sanggau',
  waktuBerangkat: '2099-07-28T23:00:00+07:00',
  payment: TravSBookingPaymentInfo(
    status: 'berhasil',
    method: 'qris',
    methodLabel: 'QRIS',
    methodFamily: 'qris',
    canChoose: false,
    selectionExpiresAt: '',
    expiredAt: '',
    paidAt: '2099-07-26T08:43:00+07:00',
    canSimulate: false,
    usesGateway: true,
    provider: 'pakasir',
    project: 'ruslytravel',
    amount: 750000,
    providerFee: 5000,
    totalPayment: 755000,
    paymentNumber: '000201010212',
    checkoutUrl: '',
    gatewayStatus: 'completed',
  ),
  kendaraan: TravSBookingVehicleInfo(
    merk: 'Innova Reborn MVP',
    plat: 'KB 1234 AB',
    warna: 'Hitam',
    tipe: 'MPV',
    kapasitas: 6,
    fasilitas: 'AC',
  ),
  pengemudi: TravSBookingDriverInfo(
    nama: 'Rusli Driver',
    email: 'rusli@gmail.com',
    noHp: '087567889302',
    nomorSim: 'SIM-001',
    status: 'aktif',
  ),
  passengers: [
    TravSBookingPassengerInfo(
      seatNumber: 1,
      namaPenumpang: 'Ihsan Al Husna',
      noHpPenumpang: '081111111111',
      statusKehadiran: 'menunggu',
      ticket: _ticketInfo,
    ),
    TravSBookingPassengerInfo(
      seatNumber: 2,
      namaPenumpang: 'Budi Santoso',
      noHpPenumpang: '082222222222',
      statusKehadiran: 'menunggu',
      ticket: _ticketInfo,
    ),
    TravSBookingPassengerInfo(
      seatNumber: 3,
      namaPenumpang: 'Siti Aminah',
      noHpPenumpang: '083333333333',
      statusKehadiran: 'menunggu',
      ticket: _ticketInfo,
    ),
  ],
);
