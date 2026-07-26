part of '../travs_api_service.dart';

class TravSHomePayload {
  const TravSHomePayload({
    required this.appName,
    required this.featuredSchedules,
    required this.sourceLabel,
    this.serviceWhatsapp,
    this.adminContact,
    this.importantInformation = const <TravSImportantInformation>[],
    this.banners = const <TravSHomeBanner>[],
  });

  final String appName;
  final List<TravSSchedule> featuredSchedules;
  final String sourceLabel;
  final String? serviceWhatsapp;
  final String? adminContact;
  final List<TravSImportantInformation> importantInformation;
  final List<TravSHomeBanner> banners;
}

class TravSImportantInformation {
  const TravSImportantInformation({
    required this.id,
    required this.title,
    required this.description,
  });

  final String id;
  final String title;
  final String description;

  factory TravSImportantInformation.fromJson(Map<String, dynamic> json) {
    return TravSImportantInformation(
      id: json['id'] as String? ?? '',
      title: (json['title'] as String? ?? '').trim(),
      description: (json['description'] as String? ?? '').trim(),
    );
  }
}

class TravSHomeBanner {
  const TravSHomeBanner({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.imageUrl,
  });

  final int id;
  final String title;
  final String subtitle;
  final String imageUrl;

  factory TravSHomeBanner.fromJson(Map<String, dynamic> json) {
    return TravSHomeBanner(
      id: _readInt(json['id']),
      title: json['judul'] as String? ?? '',
      subtitle: json['subjudul'] as String? ?? '',
      imageUrl: json['gambar_url'] as String? ?? '',
    );
  }
}

class TravSSchedule {
  const TravSSchedule({
    required this.id,
    required this.asal,
    required this.tujuan,
    required this.jalur,
    required this.pengemudi,
    required this.kendaraan,
    this.kendaraanFotoUrl,
    required this.waktuBerangkat,
    required this.estimasiTiba,
    required this.hargaTiket,
    required this.sisaKursi,
    required this.kapasitas,
    required this.durasiMenit,
  });

  final int id;
  final String asal;
  final String tujuan;
  final String jalur;
  final String pengemudi;
  final String kendaraan;
  final String? kendaraanFotoUrl;
  final String waktuBerangkat;
  final String estimasiTiba;
  final double hargaTiket;
  final int sisaKursi;
  final int kapasitas;
  final int durasiMenit;

  factory TravSSchedule.fromJson(Map<String, dynamic> json) {
    final route = _asMap(json['rute']);
    final kendaraanData = json['kendaraan'];
    final pengemudiData = json['pengemudi'];
    final seatMap = json['seat_map'] as List<dynamic>? ?? <dynamic>[];

    String kendaraan = '-';
    String? kendaraanFotoUrl;
    if (kendaraanData is String) {
      kendaraan = kendaraanData;
    } else if (kendaraanData is Map) {
      final kendaraanMap = _asMap(kendaraanData);
      kendaraan =
          '${kendaraanMap['merk'] ?? '-'} - ${kendaraanMap['plat'] ?? '-'}';
      kendaraanFotoUrl = kendaraanMap['foto_url'] as String?;
    }

    String pengemudi = '-';
    if (pengemudiData is String) {
      pengemudi = pengemudiData;
    } else if (pengemudiData is Map) {
      pengemudi = _asMap(pengemudiData)['nama'] as String? ?? '-';
    }

    return TravSSchedule(
      id: _readInt(json['id']),
      asal: route['asal'] as String? ?? '-',
      tujuan: route['tujuan'] as String? ?? '-',
      jalur: route['jalur'] as String? ?? '-',
      pengemudi: pengemudi,
      kendaraan: kendaraan,
      kendaraanFotoUrl: kendaraanFotoUrl,
      waktuBerangkat: json['waktu_berangkat'] as String? ?? '',
      estimasiTiba: json['estimasi_tiba'] as String? ?? '',
      hargaTiket: _readDouble(json['harga_tiket'] ?? route['harga_tiket']),
      sisaKursi: json['sisa_kursi'] != null
          ? _readInt(json['sisa_kursi'])
          : seatMap
                .map((item) => _asMap(item)['status'])
                .where((status) => status == 'tersedia')
                .length,
      kapasitas: json['kapasitas'] != null
          ? _readInt(json['kapasitas'])
          : (_asMap(json['kendaraan'])['kapasitas'] != null
                ? _readInt(_asMap(json['kendaraan'])['kapasitas'])
                : seatMap.length),
      durasiMenit: json['estimasi_durasi_menit'] != null
          ? _readInt(json['estimasi_durasi_menit'])
          : _readInt(route['durasi_menit']),
    );
  }
}

class TravSScheduleDetail {
  const TravSScheduleDetail({
    required this.schedule,
    required this.seatMap,
    required this.seatLayout,
    required this.bookingRules,
    required this.paymentMethods,
    required this.baggageRules,
    required this.vehicleFacilities,
    required this.vehicleType,
    this.vehiclePhotoUrl,
    required this.driverLicenseNumber,
    this.driverPhotoUrl,
    required this.sourceLabel,
    required this.isLive,
  });

  final TravSSchedule schedule;
  final List<TravSSeatItem> seatMap;

  /// Susunan kursi dari admin untuk kendaraan ini.
  /// Contoh [1, 3, 2]: baris depan 1 kursi, tengah 3, belakang 2.
  /// Jika belum diatur, aplikasi membuat susunan sederhana dari jumlah kursi.
  final List<int> seatLayout;

  final TravSBookingRules bookingRules;
  final TravSPaymentMethods paymentMethods;
  final TravSBaggageRules baggageRules;
  final String vehicleFacilities;
  final String vehicleType;
  final String? vehiclePhotoUrl;
  final String driverLicenseNumber;
  final String? driverPhotoUrl;
  final String sourceLabel;
  final bool isLive;

  int get bookedSeatCount =>
      seatMap.where((seat) => seat.status == 'dipesan').length;

  int get lockedSeatCount =>
      seatMap.where((seat) => seat.status == 'terkunci').length;

  factory TravSScheduleDetail.fromJson(Map<String, dynamic> json) {
    final kendaraan = _asMap(json['kendaraan']);
    final pengemudi = _asMap(json['pengemudi']);

    // Ambil susunan kursi dari data kendaraan agar tampilan denah mengikuti armada.
    final rawLayout = kendaraan['seat_layout'];
    final seatLayout = rawLayout is List
        ? rawLayout.map((e) => _readInt(e)).toList()
        : <int>[];

    return TravSScheduleDetail(
      schedule: TravSSchedule.fromJson(json),
      seatMap: (json['seat_map'] as List<dynamic>? ?? <dynamic>[])
          .map((item) => TravSSeatItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      seatLayout: seatLayout,
      bookingRules: TravSBookingRules.fromJson(_asMap(json['booking_rules'])),
      paymentMethods: TravSPaymentMethods.fromJson(
        _asMap(json['payment_methods']),
      ),
      baggageRules: TravSBaggageRules.fromJson(_asMap(json['bagasi'])),
      vehicleFacilities: kendaraan['fasilitas'] as String? ?? '-',
      vehicleType: kendaraan['tipe'] as String? ?? '-',
      vehiclePhotoUrl: kendaraan['foto_url'] as String?,
      driverLicenseNumber: pengemudi['nomor_sim'] as String? ?? '-',
      driverPhotoUrl: pengemudi['foto_url'] as String?,
      sourceLabel: 'Data tersedia',
      isLive: true,
    );
  }
}

class TravSSeatItem {
  const TravSSeatItem({
    required this.id,
    required this.seatNumber,
    required this.status,
    this.lockExpiresAt,
  });

  final int id;
  final int seatNumber;
  final String status;
  final String? lockExpiresAt;

  factory TravSSeatItem.fromJson(Map<String, dynamic> json) {
    return TravSSeatItem(
      id: _readInt(json['id']),
      seatNumber: _readInt(json['nomor_kursi']),
      status: json['status'] as String? ?? 'tersedia',
      lockExpiresAt: json['lock_expires_at'] as String?,
    );
  }
}

class TravSBookingRules {
  const TravSBookingRules({
    required this.maxSeatPerBooking,
    required this.seatLockFormMinutes,
    required this.seatLockPaymentMinutes,
  });

  final int maxSeatPerBooking;
  final int seatLockFormMinutes;
  final int seatLockPaymentMinutes;

  factory TravSBookingRules.fromJson(Map<String, dynamic> json) {
    return TravSBookingRules(
      maxSeatPerBooking: _readInt(json['max_seat_per_booking'], fallback: 4),
      seatLockFormMinutes: _readInt(
        json['seat_lock_form_minutes'],
        fallback: 30,
      ),
      seatLockPaymentMinutes: _readInt(
        json['seat_lock_payment_minutes'],
        fallback: 5,
      ),
    );
  }
}
