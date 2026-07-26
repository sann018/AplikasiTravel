part of '../travs_booking_contract.dart';

class TravSBookingPassengerInfo {
  const TravSBookingPassengerInfo({
    required this.seatNumber,
    required this.namaPenumpang,
    required this.noHpPenumpang,
    required this.statusKehadiran,
    this.ticket,
  });

  final int seatNumber;
  final String namaPenumpang;
  final String noHpPenumpang;
  final String statusKehadiran;
  final TravSBookingTicketInfo? ticket;

  bool get hasTicket => ticket != null;

  factory TravSBookingPassengerInfo.fromJson(Map<String, dynamic> json) {
    final ticketJson = _asMapOrNull(json['ticket']);

    return TravSBookingPassengerInfo(
      seatNumber: _readInt(json['seat_number']),
      namaPenumpang: json['nama_penumpang'] as String? ?? '-',
      noHpPenumpang: json['no_hp_penumpang'] as String? ?? '-',
      statusKehadiran: json['status_kehadiran'] as String? ?? '-',
      ticket: ticketJson == null
          ? null
          : TravSBookingTicketInfo.fromJson(ticketJson),
    );
  }
}

class TravSBookingBaggageInfo {
  const TravSBookingBaggageInfo({
    required this.deskripsiItem,
    required this.metodeHitung,
    required this.posisiBarang,
    required this.kategoriUkuran,
    required this.jenisBarangBesar,
    required this.beratKg,
    required this.ukuranCm,
    required this.panjangCm,
    required this.lebarCm,
    required this.jumlahItem,
    required this.fotoUrl,
    required this.fotoUrls,
  });

  final String deskripsiItem;
  final String? metodeHitung;
  final String posisiBarang;
  final String kategoriUkuran;
  final String? jenisBarangBesar;
  final double? beratKg;
  final int? ukuranCm;
  final int? panjangCm;
  final int? lebarCm;
  final int jumlahItem;
  final String? fotoUrl;
  final List<String> fotoUrls;

  factory TravSBookingBaggageInfo.fromJson(Map<String, dynamic> json) {
    final rawPhotoUrls = json['foto_urls'] as List<dynamic>? ?? <dynamic>[];
    final fotoUrls = rawPhotoUrls
        .whereType<String>()
        .where((url) => url.trim().isNotEmpty)
        .toList();
    final legacyPhotoUrl = json['foto_url'] as String?;
    if (legacyPhotoUrl != null &&
        legacyPhotoUrl.trim().isNotEmpty &&
        !fotoUrls.contains(legacyPhotoUrl)) {
      fotoUrls.insert(0, legacyPhotoUrl);
    }

    return TravSBookingBaggageInfo(
      deskripsiItem: json['deskripsi_item'] as String? ?? '-',
      metodeHitung: json['metode_hitung'] as String?,
      posisiBarang: json['posisi_barang'] as String? ?? 'masuk_bagasi',
      kategoriUkuran: json['kategori_ukuran'] as String? ?? 'besar',
      jenisBarangBesar: json['jenis_barang_besar'] as String?,
      beratKg: json['berat_kg'] == null ? null : _readDouble(json['berat_kg']),
      ukuranCm: json['ukuran_cm'] == null ? null : _readInt(json['ukuran_cm']),
      panjangCm: json['panjang_cm'] == null
          ? null
          : _readInt(json['panjang_cm']),
      lebarCm: json['lebar_cm'] == null ? null : _readInt(json['lebar_cm']),
      jumlahItem: _readInt(json['jumlah_item'], fallback: 1),
      fotoUrl: fotoUrls.isNotEmpty ? fotoUrls.first : legacyPhotoUrl,
      fotoUrls: fotoUrls.take(3).toList(),
    );
  }
}

class TravSBookingRequesterInfo {
  const TravSBookingRequesterInfo({
    required this.nama,
    required this.email,
    required this.noHp,
    this.fotoUrl,
  });

  final String nama;
  final String email;
  final String noHp;
  final String? fotoUrl;

  factory TravSBookingRequesterInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingRequesterInfo(
      nama: json['nama'] as String? ?? '-',
      email: json['email'] as String? ?? '-',
      noHp: json['no_hp'] as String? ?? '-',
      fotoUrl: json['foto_url'] as String?,
    );
  }
}
