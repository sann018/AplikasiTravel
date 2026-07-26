part of '../travs_booking_contract.dart';

class TravSSeatLockResult {
  const TravSSeatLockResult({
    required this.jadwalId,
    required this.seatNumbers,
    required this.lockExpiresAt,
    required this.lockMinutes,
  });

  final int jadwalId;
  final List<int> seatNumbers;
  final String lockExpiresAt;
  final int lockMinutes;

  factory TravSSeatLockResult.fromJson(Map<String, dynamic> json) {
    return TravSSeatLockResult(
      jadwalId: _readInt(json['jadwal_id']),
      seatNumbers: (json['seat_numbers'] as List<dynamic>? ?? <dynamic>[])
          .map(_readInt)
          .toList(),
      lockExpiresAt: json['lock_expires_at'] as String? ?? '',
      lockMinutes: _readInt(json['lock_minutes']),
    );
  }
}

class TravSPassengerPayload {
  const TravSPassengerPayload({
    required this.seatNumber,
    required this.namaPenumpang,
    required this.noHpPenumpang,
  });

  final int seatNumber;
  final String namaPenumpang;
  final String noHpPenumpang;

  Map<String, dynamic> toJson() {
    return {
      'seat_number': seatNumber,
      'nama_penumpang': namaPenumpang,
      'no_hp_penumpang': noHpPenumpang,
    };
  }
}

class TravSBaggagePayload {
  const TravSBaggagePayload({
    required this.deskripsiItem,
    this.metodeHitung,
    this.posisiBarang,
    this.kategoriUkuran,
    this.jenisBarangBesar,
    this.beratKg,
    this.ukuranCm,
    this.panjangCm,
    this.lebarCm,
    this.jumlahItem,
    this.fotoUrl,
    this.fotoUrls = const <String>[],
  });

  final String deskripsiItem;
  final String? metodeHitung;
  final String? posisiBarang;
  final String? kategoriUkuran;
  final String? jenisBarangBesar;
  final double? beratKg;
  final int? ukuranCm;
  final int? panjangCm;
  final int? lebarCm;
  final int? jumlahItem;
  final String? fotoUrl;
  final List<String> fotoUrls;

  Map<String, dynamic> toJson() {
    return {
      'deskripsi_item': deskripsiItem,
      'metode_hitung': metodeHitung,
      if (posisiBarang != null) 'posisi_barang': posisiBarang,
      if (kategoriUkuran != null) 'kategori_ukuran': kategoriUkuran,
      if (jenisBarangBesar != null) 'jenis_barang_besar': jenisBarangBesar,
      'berat_kg': beratKg,
      'ukuran_cm': ukuranCm,
      'panjang_cm': panjangCm,
      'lebar_cm': lebarCm,
      if (jumlahItem != null) 'jumlah_item': jumlahItem,
      'foto_url': fotoUrl,
      if (fotoUrls.isNotEmpty) 'foto_urls': fotoUrls,
    };
  }
}

class TravSCreateBookingPayload {
  const TravSCreateBookingPayload({
    required this.jadwalId,
    required this.seatNumbers,
    required this.pickupLatitude,
    required this.pickupLongitude,
    required this.pickupAddress,
    this.pickupNote,
    required this.dropoffLatitude,
    required this.dropoffLongitude,
    required this.dropoffAddress,
    this.dropoffNote,
    required this.passengers,
    required this.baggageItems,
  });

  final int jadwalId;
  final List<int> seatNumbers;
  final double pickupLatitude;
  final double pickupLongitude;
  final String pickupAddress;
  final String? pickupNote;
  final double dropoffLatitude;
  final double dropoffLongitude;
  final String dropoffAddress;
  final String? dropoffNote;
  final List<TravSPassengerPayload> passengers;
  final List<TravSBaggagePayload> baggageItems;

  Map<String, dynamic> toJson() {
    return {
      'jadwal_id': jadwalId,
      'seat_numbers': seatNumbers,
      'pickup': {
        'latitude': pickupLatitude,
        'longitude': pickupLongitude,
        'address': pickupAddress,
        'note': pickupNote,
      },
      'dropoff': {
        'latitude': dropoffLatitude,
        'longitude': dropoffLongitude,
        'address': dropoffAddress,
        'note': dropoffNote,
      },
      'passengers': passengers.map((item) => item.toJson()).toList(),
      'baggage_items': baggageItems.map((item) => item.toJson()).toList(),
    };
  }
}
