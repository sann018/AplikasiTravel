class AppSettings {
  const AppSettings({
    required this.appName,
    required this.appLogoUrl,
    required this.serviceWhatsapp,
    required this.maxSeatPerBooking,
    required this.seatLockFormMinutes,
    required this.seatLockPaymentMinutes,
    required this.paymentCashEnabled,
    required this.paymentCashNote,
    required this.baggageWeightEnabled,
    required this.baggageSizeEnabled,
    required this.baggageItemEnabled,
    required this.baggagePhotoRequired,
    required this.baggageFreeWeightKg,
    required this.baggageMaxWeightPerPersonKg,
    required this.baggageExtraFeePerKg,
    required this.baggageFreeSizeCm,
    required this.baggageMaxSizePerPersonCm,
    required this.baggageExtraFeePerCm,
    required this.baggageFreeItemCount,
    required this.baggageMaxItemPerPerson,
    required this.baggageExtraFeePerItem,
    required this.baggageSmallItemFee,
    required this.baggageLargeItemFee,
    required this.baggageLargeVehicleEnabled,
    required this.baggageBicycleFee,
    required this.baggageMotorcycleFee,
    required this.baggageHeavyItemFee,
    required this.baggageOtherLargeItemFee,
    required this.baggageMaxTotalWeightPerVehicle,
  });

  static const AppSettings defaults = AppSettings(
    appName: 'RuslyTravel',
    appLogoUrl: '',
    serviceWhatsapp: '',
    maxSeatPerBooking: 4,
    seatLockFormMinutes: 30,
    seatLockPaymentMinutes: 5,
    paymentCashEnabled: false,
    paymentCashNote: 'Pembayaran tunai dilakukan kepada pengemudi.',
    baggageWeightEnabled: true,
    baggageSizeEnabled: true,
    baggageItemEnabled: true,
    baggagePhotoRequired: true,
    baggageFreeWeightKg: 10,
    baggageMaxWeightPerPersonKg: 20,
    baggageExtraFeePerKg: 3000,
    baggageFreeSizeCm: 160,
    baggageMaxSizePerPersonCm: 160,
    baggageExtraFeePerCm: 500,
    baggageFreeItemCount: 2,
    baggageMaxItemPerPerson: 10,
    baggageExtraFeePerItem: 2500,
    baggageSmallItemFee: 5000,
    baggageLargeItemFee: 10000,
    baggageLargeVehicleEnabled: false,
    baggageBicycleFee: 0,
    baggageMotorcycleFee: 0,
    baggageHeavyItemFee: 0,
    baggageOtherLargeItemFee: 0,
    baggageMaxTotalWeightPerVehicle: 50,
  );

  final String appName;
  final String appLogoUrl;
  final String serviceWhatsapp;
  final int maxSeatPerBooking;
  final int seatLockFormMinutes;
  final int seatLockPaymentMinutes;
  final bool paymentCashEnabled;
  final String paymentCashNote;
  final bool baggageWeightEnabled;
  final bool baggageSizeEnabled;
  final bool baggageItemEnabled;
  final bool baggagePhotoRequired;
  final double baggageFreeWeightKg;
  final double baggageMaxWeightPerPersonKg;
  final double baggageExtraFeePerKg;
  final double baggageFreeSizeCm;
  final double baggageMaxSizePerPersonCm;
  final double baggageExtraFeePerCm;
  final int baggageFreeItemCount;
  final int baggageMaxItemPerPerson;
  final double baggageExtraFeePerItem;
  final double baggageSmallItemFee;
  final double baggageLargeItemFee;
  final bool baggageLargeVehicleEnabled;
  final double baggageBicycleFee;
  final double baggageMotorcycleFee;
  final double baggageHeavyItemFee;
  final double baggageOtherLargeItemFee;
  final double baggageMaxTotalWeightPerVehicle;

  factory AppSettings.fromJson(Map<String, dynamic> json) {
    return AppSettings(
      appName: _readString(json['app_name'], defaults.appName),
      appLogoUrl: _readString(json['app_logo_url'], defaults.appLogoUrl),
      serviceWhatsapp: _readString(
        json['service_whatsapp'],
        defaults.serviceWhatsapp,
      ),
      maxSeatPerBooking: _readInt(
        json['max_seat_per_booking'],
        defaults.maxSeatPerBooking,
      ),
      seatLockFormMinutes: _readInt(
        json['seat_lock_form_minutes'],
        defaults.seatLockFormMinutes,
      ),
      seatLockPaymentMinutes: _readInt(
        json['seat_lock_payment_minutes'],
        defaults.seatLockPaymentMinutes,
      ),
      paymentCashEnabled: _readBool(
        json['payment_cash_enabled'],
        defaults.paymentCashEnabled,
      ),
      paymentCashNote: _readString(
        json['payment_cash_note'],
        defaults.paymentCashNote,
      ),
      baggageWeightEnabled: _readBool(
        json['baggage_weight_enabled'],
        defaults.baggageWeightEnabled,
      ),
      baggageSizeEnabled: _readBool(
        json['baggage_size_enabled'],
        defaults.baggageSizeEnabled,
      ),
      baggageItemEnabled: _readBool(
        json['baggage_item_enabled'],
        defaults.baggageItemEnabled,
      ),
      baggagePhotoRequired: _readBool(
        json['baggage_photo_required'],
        defaults.baggagePhotoRequired,
      ),
      baggageFreeWeightKg: _readDouble(
        json['baggage_free_weight_kg'],
        defaults.baggageFreeWeightKg,
      ),
      baggageMaxWeightPerPersonKg: _readDouble(
        json['baggage_max_weight_per_person_kg'],
        defaults.baggageMaxWeightPerPersonKg,
      ),
      baggageExtraFeePerKg: _readDouble(
        json['baggage_extra_fee_per_kg'],
        defaults.baggageExtraFeePerKg,
      ),
      baggageFreeSizeCm: _readDouble(
        json['baggage_free_size_cm'],
        defaults.baggageFreeSizeCm,
      ),
      baggageMaxSizePerPersonCm: _readDouble(
        json['baggage_max_size_per_person_cm'],
        defaults.baggageMaxSizePerPersonCm,
      ),
      baggageExtraFeePerCm: _readDouble(
        json['baggage_extra_fee_per_cm'],
        defaults.baggageExtraFeePerCm,
      ),
      baggageFreeItemCount: _readInt(
        json['baggage_free_item_count'],
        defaults.baggageFreeItemCount,
      ),
      baggageMaxItemPerPerson: _readInt(
        json['baggage_max_item_per_person'],
        defaults.baggageMaxItemPerPerson,
      ),
      baggageExtraFeePerItem: _readDouble(
        json['baggage_extra_fee_per_item'],
        defaults.baggageExtraFeePerItem,
      ),
      baggageSmallItemFee: _readDouble(
        json['baggage_small_item_fee'],
        defaults.baggageSmallItemFee,
      ),
      baggageLargeItemFee: _readDouble(
        json['baggage_large_item_fee'],
        defaults.baggageLargeItemFee,
      ),
      baggageLargeVehicleEnabled: _readBool(
        json['baggage_large_vehicle_enabled'],
        defaults.baggageLargeVehicleEnabled,
      ),
      baggageBicycleFee: _readDouble(
        json['baggage_bicycle_fee'],
        defaults.baggageBicycleFee,
      ),
      baggageMotorcycleFee: _readDouble(
        json['baggage_motorcycle_fee'],
        defaults.baggageMotorcycleFee,
      ),
      baggageHeavyItemFee: _readDouble(
        json['baggage_heavy_item_fee'],
        defaults.baggageHeavyItemFee,
      ),
      baggageOtherLargeItemFee: _readDouble(
        json['baggage_other_large_item_fee'],
        defaults.baggageOtherLargeItemFee,
      ),
      baggageMaxTotalWeightPerVehicle: _readDouble(
        json['baggage_max_total_weight_per_vehicle'],
        defaults.baggageMaxTotalWeightPerVehicle,
      ),
    );
  }

  Map<String, dynamic> toJson() => <String, dynamic>{
    'app_name': appName,
    'app_logo_url': appLogoUrl,
    'service_whatsapp': serviceWhatsapp,
    'max_seat_per_booking': maxSeatPerBooking,
    'seat_lock_form_minutes': seatLockFormMinutes,
    'seat_lock_payment_minutes': seatLockPaymentMinutes,
    'payment_cash_enabled': paymentCashEnabled,
    'payment_cash_note': paymentCashNote,
    'baggage_weight_enabled': baggageWeightEnabled,
    'baggage_size_enabled': baggageSizeEnabled,
    'baggage_item_enabled': baggageItemEnabled,
    'baggage_photo_required': baggagePhotoRequired,
    'baggage_free_weight_kg': baggageFreeWeightKg,
    'baggage_max_weight_per_person_kg': baggageMaxWeightPerPersonKg,
    'baggage_extra_fee_per_kg': baggageExtraFeePerKg,
    'baggage_free_size_cm': baggageFreeSizeCm,
    'baggage_max_size_per_person_cm': baggageMaxSizePerPersonCm,
    'baggage_extra_fee_per_cm': baggageExtraFeePerCm,
    'baggage_free_item_count': baggageFreeItemCount,
    'baggage_max_item_per_person': baggageMaxItemPerPerson,
    'baggage_extra_fee_per_item': baggageExtraFeePerItem,
    'baggage_small_item_fee': baggageSmallItemFee,
    'baggage_large_item_fee': baggageLargeItemFee,
    'baggage_large_vehicle_enabled': baggageLargeVehicleEnabled,
    'baggage_bicycle_fee': baggageBicycleFee,
    'baggage_motorcycle_fee': baggageMotorcycleFee,
    'baggage_heavy_item_fee': baggageHeavyItemFee,
    'baggage_other_large_item_fee': baggageOtherLargeItemFee,
    'baggage_max_total_weight_per_vehicle': baggageMaxTotalWeightPerVehicle,
  };
}

String _readString(dynamic value, String fallback) {
  final text = value?.toString().trim();
  return text == null || text.isEmpty ? fallback : text;
}

int _readInt(dynamic value, int fallback) {
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

double _readDouble(dynamic value, double fallback) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? fallback;
}

bool _readBool(dynamic value, bool fallback) {
  if (value is bool) return value;
  if (value == null) return fallback;
  final text = value.toString().toLowerCase();
  if (text == 'true' || text == '1') return true;
  if (text == 'false' || text == '0') return false;
  return fallback;
}
