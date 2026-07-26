part of '../travs_api_service.dart';

class TravSDriverDashboard {
  const TravSDriverDashboard({
    required this.jadwalAktif,
    required this.penumpangTerdaftar,
    required this.upcomingTrips,
    this.assignedVehicle,
    this.assignedVehicleMessage,
  });

  final int jadwalAktif;
  final int penumpangTerdaftar;
  final List<TravSDriverTripItem> upcomingTrips;
  final TravSDriverAssignedVehicle? assignedVehicle;
  final String? assignedVehicleMessage;

  factory TravSDriverDashboard.fromJson(Map<String, dynamic> json) {
    final summary = _asMap(json['summary']);
    final assignedVehicleJson = _asMap(
      json['kendaraan_ditugaskan'] ?? json['assigned_vehicle'],
    );
    return TravSDriverDashboard(
      jadwalAktif: _readInt(summary['jadwal_aktif']),
      penumpangTerdaftar: _readInt(summary['penumpang_terdaftar']),
      upcomingTrips: (json['upcoming_trips'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) =>
                TravSDriverTripItem.fromJson(item as Map<String, dynamic>),
          )
          .toList(),
      assignedVehicle: assignedVehicleJson.isEmpty
          ? null
          : TravSDriverAssignedVehicle.fromJson(assignedVehicleJson),
      assignedVehicleMessage: json['kendaraan_ditugaskan_message'] as String?,
    );
  }
}

class TravSDriverAssignedVehicle {
  const TravSDriverAssignedVehicle({
    required this.scheduleId,
    required this.route,
    required this.departureAt,
    required this.estimatedArrivalAt,
    required this.plateNumber,
    required this.brand,
    required this.type,
    required this.color,
    required this.capacity,
    required this.photoUrl,
    required this.vehicleStatus,
    required this.scheduleStatus,
  });

  final int scheduleId;
  final String route;
  final String departureAt;
  final String estimatedArrivalAt;
  final String plateNumber;
  final String brand;
  final String type;
  final String color;
  final int capacity;
  final String? photoUrl;
  final String vehicleStatus;
  final String scheduleStatus;

  String get displayName {
    final parts = [
      brand,
      type,
      plateNumber,
    ].where((value) => value.trim().isNotEmpty && value != '-').toList();
    return parts.isEmpty ? 'Armada belum ditugaskan' : parts.join(' ');
  }

  factory TravSDriverAssignedVehicle.fromJson(Map<String, dynamic> json) {
    // Kendaraan dibaca dari object khusus jadwal, bukan dari profil pengemudi.
    return TravSDriverAssignedVehicle(
      scheduleId: _readInt(json['id_jadwal']),
      route: json['rute'] as String? ?? '-',
      departureAt: json['waktu_berangkat'] as String? ?? '',
      estimatedArrivalAt: json['estimasi_tiba'] as String? ?? '',
      plateNumber: json['nomor_plat'] as String? ?? '-',
      brand:
          json['merek'] as String? ?? json['merk_kendaraan'] as String? ?? '-',
      type: json['tipe'] as String? ?? json['tipe_kendaraan'] as String? ?? '-',
      color: json['warna'] as String? ?? '-',
      capacity: _readInt(json['kapasitas'] ?? json['kapasitas_kursi']),
      photoUrl:
          json['foto_url'] as String? ?? json['foto_kendaraan_url'] as String?,
      vehicleStatus: json['status_kendaraan'] as String? ?? '-',
      scheduleStatus: json['status_jadwal'] as String? ?? '-',
    );
  }
}

class TravSDriverTripItem {
  const TravSDriverTripItem({
    required this.id,
    required this.code,
    required this.rute,
    required this.waktuBerangkat,
    required this.kendaraan,
    required this.kendaraanNama,
    required this.nomorPlat,
    required this.kendaraanFotoUrl,
    required this.status,
    this.passengerCount = 0,
    this.revenue = 0,
    this.manifest = const [],
  });

  final int id;
  final String code;
  final String rute;
  final String waktuBerangkat;
  final String kendaraan;
  final String kendaraanNama;
  final String nomorPlat;
  final String? kendaraanFotoUrl;
  final String status;
  final int passengerCount;
  final double revenue;
  final List<TravSDriverManifestPassenger> manifest;

  factory TravSDriverTripItem.fromJson(Map<String, dynamic> json) {
    return TravSDriverTripItem(
      id: _readInt(json['id']),
      code: json['code'] as String? ?? 'DRV-${_readInt(json['id'])}',
      rute: json['rute'] as String? ?? '-',
      waktuBerangkat: json['waktu_berangkat'] as String? ?? '',
      kendaraan: json['kendaraan'] as String? ?? '-',
      kendaraanNama:
          json['kendaraan_nama'] as String? ??
          json['kendaraan'] as String? ??
          '-',
      nomorPlat: json['nomor_plat'] as String? ?? '-',
      kendaraanFotoUrl: json['kendaraan_foto_url'] as String?,
      status: json['status'] as String? ?? '-',
      passengerCount: _readInt(json['passenger_count']),
      revenue: _readDouble(json['revenue']),
      manifest: (json['manifest'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) => TravSDriverManifestPassenger.fromJson(
              item as Map<String, dynamic>,
            ),
          )
          .toList(),
    );
  }
}

class TravSDriverManifestPassenger {
  const TravSDriverManifestPassenger({
    required this.bookingId,
    required this.detailId,
    required this.name,
    required this.accountName,
    required this.accountEmail,
    required this.accountPhone,
    required this.profilePhotoUrl,
    required this.seatNumber,
    required this.phone,
    required this.pickupAddress,
    required this.pickupLatitude,
    required this.pickupLongitude,
    required this.dropoffAddress,
    required this.dropoffLatitude,
    required this.dropoffLongitude,
    required this.baggageLabel,
    required this.baggagePhotoUrl,
    required this.baggagePhotoUrls,
    required this.fareLabel,
    required this.attendanceStatus,
    required this.bookingStatus,
    required this.paymentStatus,
    required this.paymentMethod,
    required this.paymentLabel,
    required this.canConfirmCash,
    required this.hasTicket,
    required this.ticketScanned,
    required this.isDroppedOff,
  });

  final int bookingId;
  final int detailId;
  final String name;
  final String accountName;
  final String accountEmail;
  final String accountPhone;
  final String? profilePhotoUrl;
  final int seatNumber;
  final String phone;
  final String pickupAddress;
  final double? pickupLatitude;
  final double? pickupLongitude;
  final String dropoffAddress;
  final double? dropoffLatitude;
  final double? dropoffLongitude;
  final String baggageLabel;
  final String? baggagePhotoUrl;
  final List<String> baggagePhotoUrls;
  final String fareLabel;
  final String attendanceStatus;
  final String bookingStatus;
  final String paymentStatus;
  final String paymentMethod;
  final String paymentLabel;
  final bool canConfirmCash;
  final bool hasTicket;
  final bool ticketScanned;
  final bool isDroppedOff;

  String get pickupLandmark => _friendlyLandmark(pickupAddress);
  String get dropoffLandmark => _friendlyLandmark(dropoffAddress);

  static String _friendlyLandmark(String value) {
    final landmark = value.trim();
    return landmark.isEmpty || landmark == '-'
        ? 'Belum diisi penumpang'
        : landmark;
  }

  factory TravSDriverManifestPassenger.fromJson(Map<String, dynamic> json) {
    final pickup = _asMap(json['pickup']);
    final baggage = _asMap(json['baggage']);
    final fare = _asMap(json['fare']);
    final payment = _asMap(json['pembayaran']);
    final ticket = _asMap(json['ticket']);
    final account = _asMap(json['akun_pemesan']);
    final dropoff = _asMap(json['dropoff']);
    final baggagePhotoUrls =
        (baggage['foto_urls'] as List<dynamic>? ?? <dynamic>[])
            .whereType<String>()
            .where((url) => url.trim().isNotEmpty)
            .toList();
    final legacyBaggagePhoto = baggage['foto_url'] as String?;
    if (legacyBaggagePhoto != null &&
        legacyBaggagePhoto.trim().isNotEmpty &&
        !baggagePhotoUrls.contains(legacyBaggagePhoto)) {
      baggagePhotoUrls.insert(0, legacyBaggagePhoto);
    }

    return TravSDriverManifestPassenger(
      bookingId: _readInt(json['booking_id']),
      detailId: _readInt(json['detail_id']),
      name: json['nama_penumpang'] as String? ?? '-',
      accountName: account['nama'] as String? ?? '-',
      accountEmail: account['email'] as String? ?? '-',
      accountPhone: account['no_hp'] as String? ?? '-',
      profilePhotoUrl: account['foto_url'] as String?,
      seatNumber: _readInt(json['nomor_kursi']),
      phone: json['no_hp_penumpang'] as String? ?? '-',
      pickupAddress: pickup['address'] as String? ?? '',
      pickupLatitude: _readNullableDouble(pickup['latitude']),
      pickupLongitude: _readNullableDouble(pickup['longitude']),
      dropoffAddress: dropoff['address'] as String? ?? '',
      dropoffLatitude: _readNullableDouble(dropoff['latitude']),
      dropoffLongitude: _readNullableDouble(dropoff['longitude']),
      baggageLabel: baggage['label'] as String? ?? '-',
      baggagePhotoUrl: baggagePhotoUrls.isNotEmpty
          ? baggagePhotoUrls.first
          : legacyBaggagePhoto,
      baggagePhotoUrls: baggagePhotoUrls.take(3).toList(),
      fareLabel: fare['label'] as String? ?? '-',
      attendanceStatus: json['status_kehadiran'] as String? ?? 'menunggu',
      bookingStatus: json['status_pemesanan'] as String? ?? '-',
      paymentStatus: payment['status'] as String? ?? '-',
      paymentMethod: payment['metode'] as String? ?? '-',
      paymentLabel:
          (payment['label'] as String?) ?? (fare['label'] as String?) ?? '-',
      canConfirmCash: _readBool(payment['can_confirm_cash']),
      hasTicket: ticket.isNotEmpty,
      ticketScanned: _readBool(ticket['sudah_dipindai']),
      isDroppedOff: _readBool(dropoff['is_dropped_off']),
    );
  }
}

class TravSScanTicketResult {
  const TravSScanTicketResult({
    required this.valid,
    required this.scannedAt,
    required this.tripRoute,
    required this.passengerName,
    required this.seatNumber,
    required this.bookingCode,
  });

  final bool valid;
  final String scannedAt;
  final String tripRoute;
  final String passengerName;
  final int seatNumber;
  final String bookingCode;

  factory TravSScanTicketResult.fromJson(Map<String, dynamic> json) {
    final trip = _asMap(json['trip']);
    final passenger = _asMap(json['passenger']);

    return TravSScanTicketResult(
      valid: _readBool(json['valid']),
      scannedAt: json['scanned_at'] as String? ?? '',
      tripRoute: trip['rute'] as String? ?? '-',
      passengerName: passenger['nama_penumpang'] as String? ?? '-',
      seatNumber: _readInt(passenger['nomor_kursi']),
      bookingCode: passenger['kode_booking'] as String? ?? '-',
    );
  }

  List<String> get detailLines => [
    'Nama: $passengerName',
    'Rute: $tripRoute',
    'Kursi: No. ${seatNumber.toString().padLeft(2, '0')}',
    'Kode: $bookingCode',
  ];
}

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map<String, dynamic>) {
    return value;
  }

  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }

  return <String, dynamic>{};
}

String _joinIndonesian(List<String> values) {
  if (values.isEmpty) return '';
  if (values.length == 1) return values.first;
  if (values.length == 2) return '${values.first} dan ${values.last}';

  return '${values.sublist(0, values.length - 1).join(', ')}, dan ${values.last}';
}

int _readInt(dynamic value, {int fallback = 0}) {
  if (value == null) {
    return fallback;
  }

  if (value is int) {
    return value;
  }

  if (value is num) {
    return value.toInt();
  }

  return int.tryParse(value.toString()) ?? fallback;
}

double _readDouble(dynamic value, {double fallback = 0}) {
  if (value == null) {
    return fallback;
  }

  if (value is double) {
    return value;
  }

  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value.toString()) ?? fallback;
}

double? _readNullableDouble(dynamic value) {
  if (value == null || value.toString().trim().isEmpty) {
    return null;
  }

  return double.tryParse(value.toString());
}

bool _readBool(dynamic value) {
  if (value is bool) {
    return value;
  }

  final raw = value?.toString().toLowerCase();
  return raw == 'true' || raw == '1';
}
