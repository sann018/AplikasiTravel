part of '../travs_api_service.dart';

class TravSPaymentMethod {
  const TravSPaymentMethod({
    required this.code,
    required this.label,
    required this.family,
  });

  final String code;
  final String label;
  final String family;

  factory TravSPaymentMethod.fromJson(Map<String, dynamic> json) {
    return TravSPaymentMethod(
      code: json['code']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      family: json['family']?.toString() ?? '',
    );
  }
}

class TravSPaymentMethods {
  const TravSPaymentMethods({
    required this.transferEnabled,
    required this.qrisEnabled,
    required this.cashEnabled,
    this.options = const [],
  });

  final bool transferEnabled;
  final bool qrisEnabled;
  final bool cashEnabled;
  final List<TravSPaymentMethod> options;

  List<TravSPaymentMethod> get activeOptions {
    if (options.isNotEmpty) {
      return options;
    }

    return [
      if (qrisEnabled)
        const TravSPaymentMethod(code: 'qris', label: 'QRIS', family: 'qris'),
      if (transferEnabled)
        const TravSPaymentMethod(
          code: 'transfer',
          label: 'Transfer Bank',
          family: 'va',
        ),
      if (cashEnabled)
        const TravSPaymentMethod(
          code: 'cash',
          label: 'Bayar Tunai',
          family: 'cash',
        ),
    ];
  }

  List<String> get activeLabels {
    return activeOptions.map((method) => method.label).toList();
  }

  String get conciseSummary {
    final banks = activeOptions
        .where((method) => method.family == 'va')
        .map((method) => method.label.replaceFirst('Virtual Account ', ''))
        .toList();
    final labels = <String>[
      if (activeOptions.any((method) => method.family == 'qris')) 'QRIS',
      if (banks.isNotEmpty) 'Virtual Account (${_joinIndonesian(banks)})',
      if (activeOptions.any((method) => method.family == 'cash')) 'Tunai',
    ];

    return _joinIndonesian(labels);
  }

  factory TravSPaymentMethods.fromJson(Map<String, dynamic> json) {
    return TravSPaymentMethods(
      transferEnabled: _readBool(json['transfer']),
      qrisEnabled: _readBool(json['qris']),
      cashEnabled: _readBool(json['cash']),
      options: (json['options'] as List? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                TravSPaymentMethod.fromJson(Map<String, dynamic>.from(item)),
          )
          .where((item) => item.code.isNotEmpty)
          .toList(),
    );
  }
}

class TravSBaggageRules {
  const TravSBaggageRules({
    required this.weightEnabled,
    required this.freeWeightKg,
    required this.maxWeightPerPersonKg,
    required this.extraFeePerKg,
    required this.sizeEnabled,
    required this.freeSizeCm,
    required this.maxSizePerPersonCm,
    required this.extraFeePerCm,
    required this.itemEnabled,
    required this.freeItemCount,
    required this.maxItemPerPerson,
    required this.extraFeePerItem,
    required this.smallItemFee,
    required this.largeItemFee,
    required this.largeVehicleEnabled,
    required this.bicycleFee,
    required this.motorcycleFee,
    required this.heavyItemFee,
    required this.otherLargeItemFee,
    required this.photoRequired,
  });

  final bool weightEnabled;
  final int freeWeightKg;
  final int maxWeightPerPersonKg;
  final double extraFeePerKg;
  final bool sizeEnabled;
  final int freeSizeCm;
  final int maxSizePerPersonCm;
  final double extraFeePerCm;
  final bool itemEnabled;
  final int freeItemCount;
  final int maxItemPerPerson;
  final double extraFeePerItem;
  final double smallItemFee;
  final double largeItemFee;
  final bool largeVehicleEnabled;
  final double bicycleFee;
  final double motorcycleFee;
  final double heavyItemFee;
  final double otherLargeItemFee;
  final bool photoRequired;

  // Form mobile versi TA hanya memakai aturan berat dan jumlah item.
  // Ukuran/cm tetap dibaca untuk kompatibilitas API, tapi tidak dirender di form sederhana.
  bool get mobileBaggageInputEnabled => weightEnabled || itemEnabled;

  factory TravSBaggageRules.fromJson(Map<String, dynamic> json) {
    final fallbackItemFee = _readDouble(json['extra_fee_per_item']);

    return TravSBaggageRules(
      weightEnabled: _readBool(json['weight_enabled']),
      freeWeightKg: _readInt(json['free_weight_kg']),
      maxWeightPerPersonKg: _readInt(json['max_weight_per_person_kg']),
      extraFeePerKg: _readDouble(json['extra_fee_per_kg']),
      sizeEnabled: _readBool(json['size_enabled']),
      freeSizeCm: _readInt(json['free_size_cm']),
      maxSizePerPersonCm: _readInt(json['max_size_per_person_cm']),
      extraFeePerCm: _readDouble(json['extra_fee_per_cm']),
      itemEnabled: _readBool(json['item_enabled']),
      freeItemCount: _readInt(json['free_item_count']),
      maxItemPerPerson: _readInt(json['max_item_per_person']),
      extraFeePerItem: fallbackItemFee,
      smallItemFee: _readDouble(
        json['small_item_fee'],
        fallback: fallbackItemFee,
      ),
      largeItemFee: _readDouble(
        json['large_item_fee'],
        fallback: fallbackItemFee,
      ),
      largeVehicleEnabled: _readBool(json['large_vehicle_enabled']),
      bicycleFee: _readDouble(json['bicycle_fee']),
      motorcycleFee: _readDouble(json['motorcycle_fee']),
      heavyItemFee: _readDouble(json['heavy_item_fee']),
      otherLargeItemFee: _readDouble(json['other_large_item_fee']),
      photoRequired: _readBool(json['photo_required']),
    );
  }
}
