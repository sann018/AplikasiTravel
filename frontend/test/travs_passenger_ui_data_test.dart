import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/models/travs_booking_contract.dart';
import 'package:frontend/core/services/travs_api_service.dart';

void main() {
  test('banner beranda membaca judul dan gambar dari API', () {
    final banner = TravSHomeBanner.fromJson({
      'id': 7,
      'judul': 'Promo Sambas',
      'subjudul': 'Berangkat nyaman',
      'gambar_url': '/storage/home-banners/promo.webp',
    });

    expect(banner.title, 'Promo Sambas');
    expect(banner.imageUrl, '/storage/home-banners/promo.webp');
  });

  test('lokasi pemesanan membaca catatan patokan secara terpisah', () {
    final pickup = TravSBookingPickupInfo.fromJson({
      'latitude': -0.02,
      'longitude': 109.34,
      'address': 'Jalan Ampera, Pontianak',
      'note': 'Depan toko biru',
    });

    expect(pickup.address, 'Jalan Ampera, Pontianak');
    expect(pickup.note, 'Depan toko biru');
  });
}
