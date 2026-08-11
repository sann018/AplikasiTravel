import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/models/travs_booking_contract.dart';

void main() {
  test('model pemesanan membaca sumber dan nama Petugas Loket', () {
    final booking = TravSBookingResult.fromJson({
      'id': 10,
      'kode_booking': 'TRAVS-LOKET-UJI',
      'status_pemesanan': 'menunggu_pembayaran',
      'jumlah_kursi': 1,
      'subtotal_tiket': 250000,
      'total_harga': 250000,
      'rute': 'Pontianak - Sanggau',
      'waktu_berangkat': '2026-08-12T08:00:00+07:00',
      'sumber_pemesanan': 'loket',
      'dibuat_oleh': {'id': 7, 'nama': 'Petugas Satu'},
      'payment': <String, dynamic>{},
    });

    expect(booking.isCounterBooking, isTrue);
    expect(booking.dibuatOlehNama, 'Petugas Satu');
  });
}
