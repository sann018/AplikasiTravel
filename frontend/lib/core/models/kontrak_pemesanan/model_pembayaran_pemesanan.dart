part of '../travs_booking_contract.dart';

class TravSBookingPaymentInfo {
  const TravSBookingPaymentInfo({
    required this.status,
    required this.method,
    required this.methodLabel,
    required this.methodFamily,
    required this.canChoose,
    required this.selectionExpiresAt,
    required this.expiredAt,
    this.paidAt = '',
    required this.canSimulate,
    required this.usesGateway,
    required this.provider,
    required this.project,
    required this.amount,
    required this.providerFee,
    required this.totalPayment,
    required this.paymentNumber,
    required this.checkoutUrl,
    required this.gatewayStatus,
  });

  final String status;
  final String method;
  final String methodLabel;
  final String methodFamily;
  final bool canChoose;
  final String selectionExpiresAt;
  final String expiredAt;
  final String paidAt;
  final bool canSimulate;
  final bool usesGateway;
  final String provider;
  final String project;
  final int amount;
  final int? providerFee;
  final int? totalPayment;
  final String paymentNumber;

  /// Tautan hosted checkout dipakai untuk membuka halaman pembayaran Pakasir.
  final String checkoutUrl;

  final String gatewayStatus;

  bool get hasPaymentNumber => paymentNumber.trim().isNotEmpty;

  /// Menandai apakah tautan pembayaran dari Pakasir tersedia dan bisa dibuka.
  bool get hasCheckoutUrl => checkoutUrl.trim().isNotEmpty;

  bool get hasSelectedMethod => method.trim().isNotEmpty;

  bool get isAwaitingMethodSelection => canChoose && !hasSelectedMethod;

  bool get hasCompleteDigitalSnapshot =>
      amount > 0 &&
      providerFee != null &&
      totalPayment != null &&
      hasCheckoutUrl;

  factory TravSBookingPaymentInfo.fromJson(Map<String, dynamic> json) {
    // Nama Indonesia adalah kontrak baru; nama lama tetap dibaca agar transaksi
    // yang sudah tersimpan masih dapat dibuka.
    return TravSBookingPaymentInfo(
      status:
          json['status_pembayaran'] as String? ??
          json['status'] as String? ??
          '',
      method:
          json['metode_pembayaran'] as String? ??
          json['method'] as String? ??
          '',
      methodLabel:
          json['method_label'] as String? ??
          json['metode_pembayaran'] as String? ??
          json['method'] as String? ??
          '',
      methodFamily: json['method_family'] as String? ?? '',
      canChoose: json['can_choose'] as bool? ?? false,
      selectionExpiresAt: json['selection_expires_at'] as String? ?? '',
      expiredAt: json['expired_at'] as String? ?? '',
      paidAt: json['paid_at'] as String? ?? '',
      canSimulate: json['can_simulate'] as bool? ?? false,
      usesGateway: json['uses_gateway'] as bool? ?? false,
      provider: json['provider'] as String? ?? '',
      project: json['project'] as String? ?? '',
      amount: _readInt(json['nominal_transaksi'] ?? json['amount']),
      providerFee: _readNullableInt(
        json['biaya_admin_provider'] ?? json['provider_fee'],
      ),
      totalPayment: _readNullableInt(
        json['total_dibayar_penumpang'] ?? json['total_payment'],
      ),
      paymentNumber: json['payment_number'] as String? ?? '',
      checkoutUrl: json['checkout_url'] as String? ?? '',
      gatewayStatus: json['gateway_status'] as String? ?? '',
    );
  }
}
