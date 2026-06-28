import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/services/travs_api_service.dart';

void main() {
  group('Data perjalanan pengemudi', () {
    test('membaca identitas kendaraan yang dikirim backend', () {
      final trip = TravSDriverTripItem.fromJson({
        'id': 12,
        'code': 'DRV-12',
        'rute': 'Pontianak - Sambas',
        'waktu_berangkat': '2026-06-23T08:00:00+07:00',
        'kendaraan': 'Toyota Innova KB 1234 TS',
        'kendaraan_nama': 'Toyota Innova',
        'nomor_plat': 'KB 1234 TS',
        'kendaraan_foto_url': '/storage/vehicles/innova.webp',
        'status': 'dibuka',
      });

      expect(trip.kendaraanNama, 'Toyota Innova');
      expect(trip.nomorPlat, 'KB 1234 TS');
      expect(trip.kendaraanFotoUrl, '/storage/vehicles/innova.webp');
    });

    test('alamat kosong memakai keterangan yang mudah dipahami', () {
      final passenger = TravSDriverManifestPassenger.fromJson({
        'booking_id': 1,
        'detail_id': 2,
        'nama_penumpang': 'Ihsan',
        'nomor_kursi': 3,
        'pickup': {'address': ''},
        'dropoff': {'address': null},
        'baggage': <String, dynamic>{},
        'fare': <String, dynamic>{},
        'pembayaran': <String, dynamic>{},
        'ticket': <String, dynamic>{},
        'akun_pemesan': <String, dynamic>{},
      });

      expect(passenger.pickupLandmark, 'Belum diisi penumpang');
      expect(passenger.dropoffLandmark, 'Belum diisi penumpang');
    });
  });
}
