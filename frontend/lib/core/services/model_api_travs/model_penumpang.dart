part of '../travs_api_service.dart';

class TravSPassengerDashboard {
  const TravSPassengerDashboard({
    required this.totalBooking,
    required this.bookingAktif,
    required this.tiketAktif,
    required this.maxSeatPerBooking,
    required this.recentBookings,
  });

  final int totalBooking;
  final int bookingAktif;
  final int tiketAktif;
  final int maxSeatPerBooking;
  final List<TravSPassengerBookingItem> recentBookings;

  factory TravSPassengerDashboard.fromJson(Map<String, dynamic> json) {
    final summary = _asMap(json['summary']);
    return TravSPassengerDashboard(
      totalBooking: _readInt(summary['total_booking']),
      bookingAktif: _readInt(summary['booking_aktif']),
      tiketAktif: _readInt(summary['tiket_aktif']),
      maxSeatPerBooking: _readInt(summary['max_seat_per_booking'], fallback: 4),
      recentBookings: (json['recent_bookings'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) => TravSPassengerBookingItem.fromJson(
              item as Map<String, dynamic>,
            ),
          )
          .toList(),
    );
  }
}

class TravSPassengerBookingItem {
  const TravSPassengerBookingItem({
    required this.id,
    required this.kodeBooking,
    required this.status,
    required this.paymentStatus,
    required this.totalHarga,
    required this.rute,
    required this.waktuBerangkat,
    required this.jumlahTiket,
  });

  final int id;
  final String kodeBooking;
  final String status;
  final String paymentStatus;
  final double totalHarga;
  final String rute;
  final String waktuBerangkat;
  final int jumlahTiket;

  factory TravSPassengerBookingItem.fromJson(Map<String, dynamic> json) {
    return TravSPassengerBookingItem(
      id: _readInt(json['id']),
      kodeBooking: json['kode_booking'] as String? ?? '-',
      status: json['status'] as String? ?? '-',
      paymentStatus: json['payment_status'] as String? ?? '-',
      totalHarga: _readDouble(json['total_harga']),
      rute: json['rute'] as String? ?? '-',
      waktuBerangkat: json['waktu_berangkat'] as String? ?? '',
      jumlahTiket: _readInt(json['jumlah_tiket']),
    );
  }
}
