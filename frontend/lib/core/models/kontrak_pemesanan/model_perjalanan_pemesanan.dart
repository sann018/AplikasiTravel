part of '../travs_booking_contract.dart';

class TravSBookingPickupInfo {
  const TravSBookingPickupInfo({
    required this.latitude,
    required this.longitude,
    required this.address,
    this.note,
  });

  final double latitude;
  final double longitude;
  final String address;
  final String? note;

  factory TravSBookingPickupInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingPickupInfo(
      latitude: _readDouble(json['latitude']),
      longitude: _readDouble(json['longitude']),
      address: json['address'] as String? ?? '-',
      note: json['note'] as String?,
    );
  }
}

class TravSBookingVehicleInfo {
  const TravSBookingVehicleInfo({
    required this.merk,
    required this.plat,
    required this.warna,
    required this.tipe,
    required this.kapasitas,
    required this.fasilitas,
    this.fotoUrl,
  });

  final String merk;
  final String plat;
  final String warna;
  final String tipe;
  final int kapasitas;
  final String fasilitas;
  final String? fotoUrl;

  factory TravSBookingVehicleInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingVehicleInfo(
      merk: json['merk'] as String? ?? '-',
      plat: json['plat'] as String? ?? '-',
      warna: json['warna'] as String? ?? '-',
      tipe: json['tipe'] as String? ?? '-',
      kapasitas: _readInt(json['kapasitas']),
      fasilitas: json['fasilitas'] as String? ?? '-',
      fotoUrl: json['foto_url'] as String?,
    );
  }
}

class TravSBookingDriverInfo {
  const TravSBookingDriverInfo({
    required this.nama,
    required this.email,
    required this.noHp,
    required this.nomorSim,
    required this.status,
    this.fotoUrl,
  });

  final String nama;
  final String email;
  final String noHp;
  final String nomorSim;
  final String status;
  final String? fotoUrl;

  factory TravSBookingDriverInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingDriverInfo(
      nama: json['nama'] as String? ?? '-',
      email: json['email'] as String? ?? '-',
      noHp: json['no_hp'] as String? ?? '-',
      nomorSim: json['nomor_sim'] as String? ?? '-',
      status: json['status'] as String? ?? '-',
      fotoUrl: json['foto_url'] as String?,
    );
  }
}

class TravSBookingTicketInfo {
  const TravSBookingTicketInfo({
    required this.kodeQr,
    required this.token,
    required this.sudahDipindai,
  });

  final String kodeQr;
  final String token;
  final bool sudahDipindai;

  factory TravSBookingTicketInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingTicketInfo(
      kodeQr: json['kode_qr'] as String? ?? '-',
      token: json['token'] as String? ?? '',
      sudahDipindai: json['sudah_dipindai'] as bool? ?? false,
    );
  }
}
