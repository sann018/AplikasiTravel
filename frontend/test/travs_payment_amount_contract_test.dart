import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/models/travs_booking_contract.dart';

void main() {
  test('nominal pembayaran dibaca sebagai integer rupiah dari backend', () {
    final payment = TravSBookingPaymentInfo.fromJson({
      'status': 'menunggu',
      'method': 'qris',
      'amount': 400000,
      'total_payment': 400000,
    });

    expect(payment.amount, 400000);
    expect(payment.providerFee, isNull);
    expect(payment.totalPayment, 400000);
  });

  test('metadata total nominal plus fee dibaca dari backend', () {
    final payment = TravSBookingPaymentInfo.fromJson({
      'status': 'menunggu',
      'method': 'qris',
      'amount': 400000,
      'provider_fee': 4000,
      'total_payment': 404000,
    });

    expect(payment.amount, 400000);
    expect(payment.providerFee, 4000);
    expect(payment.totalPayment, 404000);
  });

  test('metode VA spesifik dan snapshot provider dibaca dari backend', () {
    final payment = TravSBookingPaymentInfo.fromJson({
      'status': 'menunggu',
      'method': 'bri_va',
      'method_label': 'Virtual Account BRI',
      'method_family': 'va',
      'uses_gateway': true,
      'amount': 400000,
      'provider_fee': 3500,
      'total_payment': 403500,
      'payment_number': '888801234567890',
      'checkout_url': 'https://app.pakasir.com/pay/travs/400000?order_id=TRX-1',
    });

    expect(payment.method, 'bri_va');
    expect(payment.methodLabel, 'Virtual Account BRI');
    expect(payment.methodFamily, 'va');
    expect(payment.providerFee, 3500);
    expect(payment.totalPayment, 403500);
    expect(payment.paymentNumber, '888801234567890');
    expect(payment.hasCompleteDigitalSnapshot, isTrue);
  });

  test('payload tanpa barang bawaan mengirim daftar kosong', () {
    final payload = TravSCreateBookingPayload(
      jadwalId: 1,
      seatNumbers: const [4],
      pickupLatitude: -0.02,
      pickupLongitude: 109.34,
      pickupAddress: 'Alamat jemput',
      pickupNote: 'Patokan jemput',
      dropoffLatitude: 0.12,
      dropoffLongitude: 111.49,
      dropoffAddress: 'Alamat tujuan',
      dropoffNote: 'Patokan turun',
      passengers: const [
        TravSPassengerPayload(
          seatNumber: 4,
          namaPenumpang: 'Penumpang',
          noHpPenumpang: '081234567890',
        ),
      ],
      baggageItems: const [],
    );

    expect(payload.toJson()['baggage_items'], isEmpty);
    expect(payload.toJson(), isNot(contains('payment_method')));
  });

  test('booking tanpa metode mempertahankan state can choose', () {
    final payment = TravSBookingPaymentInfo.fromJson({
      'status': null,
      'method': null,
      'can_choose': true,
      'selection_expires_at': '2026-07-25T15:00:00+07:00',
      'amount': 400000,
    });

    expect(payment.status, isEmpty);
    expect(payment.method, isEmpty);
    expect(payment.canChoose, isTrue);
    expect(payment.isAwaitingMethodSelection, isTrue);
    expect(payment.selectionExpiresAt, isNotEmpty);
  });

  test('kontrak Indonesia terbaru dibaca tanpa alias lama', () {
    final booking = TravSBookingResult.fromJson({
      'booking_id': 29,
      'order_id': 'TRAVS-20260725-QLGUP',
      'status_pemesanan': 'menunggu_pembayaran',
      'jumlah_kursi': 1,
      'batas_pembayaran': '2026-07-25T22:49:30+07:00',
      'subtotal_tiket': 400000,
      'total_harga': 400000,
      'rute': 'Pontianak - Kapuas Hulu',
      'waktu_berangkat': '2026-07-26T05:00:00+07:00',
      'payment': {
        'status_pembayaran': 'menunggu',
        'metode_pembayaran': 'bri_va',
        'method_label': 'Virtual Account BRI',
        'method_family': 'va',
        'nominal_transaksi': 400000,
        'biaya_admin_provider': 3500,
        'total_dibayar_penumpang': 403500,
        'payment_number': '888801234567890',
        'uses_gateway': true,
      },
    });

    expect(booking.id, 29);
    expect(booking.kodeBooking, 'TRAVS-20260725-QLGUP');
    expect(booking.status, 'menunggu_pembayaran');
    expect(booking.payment.status, 'menunggu');
    expect(booking.payment.method, 'bri_va');
    expect(booking.payment.amount, 400000);
    expect(booking.payment.providerFee, 3500);
    expect(booking.payment.totalPayment, 403500);
  });
}
