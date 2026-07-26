part of '../travs_home_page.dart';

class _DriverJourneyRecord {
  const _DriverJourneyRecord({
    required this.id,
    required this.code,
    required this.route,
    required this.departureAt,
    required this.vehicle,
    required this.vehiclePhotoUrl,
    required this.status,
    required this.revenue,
    required this.manifest,
  });

  final int id;
  final String code;
  final String route;
  final String departureAt;
  final String vehicle;
  final String? vehiclePhotoUrl;
  final String status;
  final double revenue;
  final List<_ManifestPassengerRecord> manifest;

  String get statusLabel => _humanizeStatus(status);
  int get passengerCount => manifest.length;

  _DriverJourneyRecord copyWith({List<_ManifestPassengerRecord>? manifest}) {
    return _DriverJourneyRecord(
      id: id,
      code: code,
      route: route,
      departureAt: departureAt,
      vehicle: vehicle,
      vehiclePhotoUrl: vehiclePhotoUrl,
      status: status,
      revenue: revenue,
      manifest: manifest ?? this.manifest,
    );
  }

  factory _DriverJourneyRecord.fromTrip(TravSDriverTripItem trip) {
    return _DriverJourneyRecord(
      id: trip.id,
      code: trip.code,
      route: trip.rute,
      departureAt: trip.waktuBerangkat,
      vehicle: trip.kendaraan,
      vehiclePhotoUrl: trip.kendaraanFotoUrl,
      status: trip.status,
      revenue: trip.revenue,
      manifest: trip.manifest
          .map((passenger) => _ManifestPassengerRecord.fromApi(passenger))
          .toList(),
    );
  }

  static List<_DriverJourneyRecord> fromTrips(List<TravSDriverTripItem> trips) {
    final records = <_DriverJourneyRecord>[];
    for (final trip in trips) {
      records.add(_DriverJourneyRecord.fromTrip(trip));
    }

    return records;
  }
}

class _ManifestPassengerRecord {
  const _ManifestPassengerRecord({
    required this.detailId,
    required this.name,
    required this.accountName,
    required this.accountEmail,
    required this.accountPhone,
    required this.profilePhotoUrl,
    required this.seatLabel,
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
    required this.paymentStatusLabel,
    required this.canConfirmCash,
    required this.canDropoff,
    required this.isDroppedOff,
  });

  final int detailId;
  final String name;
  final String accountName;
  final String accountEmail;
  final String accountPhone;
  final String? profilePhotoUrl;
  final String seatLabel;
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
  final String paymentStatusLabel;
  final bool canConfirmCash;
  final bool canDropoff;
  final bool isDroppedOff;

  String get journeyStatusLabel {
    if (isDroppedOff) {
      return 'Turun';
    }

    return attendanceStatus.toLowerCase() == 'hadir' ? 'Hadir' : 'Belum Hadir';
  }

  factory _ManifestPassengerRecord.fromApi(
    TravSDriverManifestPassenger passenger,
  ) {
    final isCashPending = passenger.canConfirmCash;
    final paymentStatusLabel = isCashPending
        ? 'Belum bayar tunai'
        : passenger.paymentStatus == 'berhasil'
        ? 'Lunas'
        : _humanizeStatus(passenger.paymentStatus);

    return _ManifestPassengerRecord(
      detailId: passenger.detailId,
      name: passenger.name,
      accountName: passenger.accountName,
      accountEmail: passenger.accountEmail,
      accountPhone: passenger.accountPhone,
      profilePhotoUrl: passenger.profilePhotoUrl,
      seatLabel: 'No. ${passenger.seatNumber.toString().padLeft(2, '0')}',
      phone: passenger.phone,
      pickupAddress: passenger.pickupLandmark,
      pickupLatitude: passenger.pickupLatitude,
      pickupLongitude: passenger.pickupLongitude,
      dropoffAddress: passenger.dropoffLandmark,
      dropoffLatitude: passenger.dropoffLatitude,
      dropoffLongitude: passenger.dropoffLongitude,
      baggageLabel: passenger.baggageLabel,
      baggagePhotoUrl: passenger.baggagePhotoUrl,
      baggagePhotoUrls: passenger.baggagePhotoUrls,
      fareLabel: passenger.fareLabel,
      attendanceStatus: passenger.attendanceStatus,
      paymentStatusLabel: paymentStatusLabel,
      canConfirmCash: passenger.canConfirmCash,
      canDropoff:
          passenger.attendanceStatus == 'hadir' && !passenger.isDroppedOff,
      isDroppedOff: passenger.isDroppedOff,
    );
  }
}

enum _NotificationTone { info, success, warning }

class _NotificationEntry {
  const _NotificationEntry({
    required this.notificationType,
    required this.referenceId,
    required this.title,
    required this.message,
    required this.timeLabel,
    required this.category,
    required this.tone,
    required this.isUnread,
  });

  final String notificationType;
  final int? referenceId;
  final String title;
  final String message;
  final String timeLabel;
  final String category;
  final _NotificationTone tone;
  final bool isUnread;
}
