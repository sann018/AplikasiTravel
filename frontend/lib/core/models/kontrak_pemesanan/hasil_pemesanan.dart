part of '../travs_booking_contract.dart';

class TravSBookingResult {
  const TravSBookingResult({
    required this.id,
    this.jadwalId = 0,
    required this.kodeBooking,
    required this.status,
    required this.jumlahKursi,
    required this.batasPembayaran,
    required this.subtotalTiket,
    required this.totalHarga,
    required this.rute,
    required this.waktuBerangkat,
    required this.payment,
    this.sumberPemesanan = 'aplikasi_penumpang',
    this.dibuatOlehNama,
    this.pemesan,
    this.pickup,
    this.dropoff,
    this.kendaraan,
    this.pengemudi,
    this.passengers = const <TravSBookingPassengerInfo>[],
    this.baggageItems = const <TravSBookingBaggageInfo>[],
  });

  final int id;
  final int jadwalId;
  final String kodeBooking;
  final String status;
  final int jumlahKursi;
  final String batasPembayaran;
  final int subtotalTiket;
  final int totalHarga;
  final String rute;
  final String waktuBerangkat;
  final TravSBookingPaymentInfo payment;
  final String sumberPemesanan;
  final String? dibuatOlehNama;
  final TravSBookingRequesterInfo? pemesan;
  final TravSBookingPickupInfo? pickup;
  final TravSBookingPickupInfo? dropoff;
  final TravSBookingVehicleInfo? kendaraan;
  final TravSBookingDriverInfo? pengemudi;
  final List<TravSBookingPassengerInfo> passengers;
  final List<TravSBookingBaggageInfo> baggageItems;

  bool get isPaid => status == 'terbayar' || payment.status == 'berhasil';
  bool get isStafPelayananBooking => sumberPemesanan == 'loket';
  int get activeTicketCount =>
      passengers.where((item) => item.hasTicket).length;

  factory TravSBookingResult.fromJson(Map<String, dynamic> json) {
    final pickupJson = _asMapOrNull(json['pickup']);
    final dropoffJson = _asMapOrNull(json['dropoff']);
    final vehicleJson = _asMapOrNull(json['kendaraan']);
    final driverJson = _asMapOrNull(json['pengemudi']);
    final requesterJson = _asMapOrNull(json['pemesan']);
    final creatorJson = _asMapOrNull(json['dibuat_oleh']);

    return TravSBookingResult(
      id: _readInt(json['booking_id'] ?? json['id']),
      jadwalId: _readInt(json['jadwal_id']),
      kodeBooking:
          json['order_id'] as String? ?? json['kode_booking'] as String? ?? '-',
      status:
          json['status_pemesanan'] as String? ??
          json['status'] as String? ??
          '-',
      jumlahKursi: _readInt(json['jumlah_kursi']),
      batasPembayaran: json['batas_pembayaran'] as String? ?? '',
      subtotalTiket: _readInt(json['subtotal_tiket']),
      totalHarga: _readInt(json['total_harga']),
      rute: json['rute'] as String? ?? '-',
      waktuBerangkat: json['waktu_berangkat'] as String? ?? '',
      payment: TravSBookingPaymentInfo.fromJson(_asMap(json['payment'])),
      sumberPemesanan:
          json['sumber_pemesanan'] as String? ?? 'aplikasi_penumpang',
      dibuatOlehNama: creatorJson?['nama'] as String?,
      pemesan: requesterJson == null
          ? null
          : TravSBookingRequesterInfo.fromJson(requesterJson),
      pickup: pickupJson == null
          ? null
          : TravSBookingPickupInfo.fromJson(pickupJson),
      dropoff: dropoffJson == null
          ? null
          : TravSBookingPickupInfo.fromJson(dropoffJson),
      kendaraan: vehicleJson == null
          ? null
          : TravSBookingVehicleInfo.fromJson(vehicleJson),
      pengemudi: driverJson == null
          ? null
          : TravSBookingDriverInfo.fromJson(driverJson),
      passengers: (json['passengers'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) => TravSBookingPassengerInfo.fromJson(
              item as Map<String, dynamic>,
            ),
          )
          .toList(),
      baggageItems: (json['baggage_items'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) =>
                TravSBookingBaggageInfo.fromJson(item as Map<String, dynamic>),
          )
          .toList(),
    );
  }
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

Map<String, dynamic>? _asMapOrNull(dynamic value) {
  if (value == null) {
    return null;
  }

  if (value is Map<String, dynamic>) {
    return value;
  }

  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }

  return null;
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

int? _readNullableInt(dynamic value) {
  if (value == null) {
    return null;
  }

  if (value is int) {
    return value;
  }

  if (value is num) {
    return value.toInt();
  }

  return int.tryParse(value.toString());
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
