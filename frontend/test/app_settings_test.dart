import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/models/app_settings.dart';

void main() {
  test('AppSettings membaca nilai backend dan memakai fallback aman', () {
    final settings = AppSettings.fromJson(<String, dynamic>{
      'app_name': 'RuslyTravel Uji',
      'app_logo_url': '/storage/app-logos/logo-uji.png',
      'max_seat_per_booking': 2,
      'seat_lock_form_minutes': 15,
      'payment_cash_enabled': true,
      'operational_expense_categories': <String>['BBM', 'Cuci Kendaraan'],
      'baggage_free_weight_kg': 8,
      'realtime': <String, dynamic>{
        'enabled': true,
        'app_key': 'kunci-publik-uji',
        'host': '10.0.2.2',
        'port': 8080,
        'scheme': 'ws',
        'seat_channel_prefix': 'jadwal.',
        'seat_event': 'kursi.diperbarui',
      },
    });

    expect(settings.appName, 'RuslyTravel Uji');
    expect(settings.appLogoUrl, '/storage/app-logos/logo-uji.png');
    expect(settings.maxSeatPerBooking, 2);
    expect(settings.seatLockFormMinutes, 15);
    expect(settings.seatLockPaymentMinutes, 5);
    expect(settings.paymentCashEnabled, isTrue);
    expect(settings.operationalExpenseCategories, <String>[
      'BBM',
      'Cuci Kendaraan',
    ]);
    expect(settings.baggageFreeWeightKg, 8);
    expect(settings.baggagePhotoRequired, isTrue);
    expect(settings.realtime.isConfigured, isTrue);
    expect(settings.realtime.host, '10.0.2.2');
  });

  test('AppSettings default tetap aman saat endpoint tidak tersedia', () {
    expect(AppSettings.defaults.maxSeatPerBooking, 4);
    expect(AppSettings.defaults.seatLockFormMinutes, 30);
    expect(AppSettings.defaults.seatLockPaymentMinutes, 5);
    expect(AppSettings.defaults.paymentCashEnabled, isFalse);
    expect(
      AppSettings.defaults.operationalExpenseCategories,
      containsAll(<String>['BBM', 'Tol', 'Parkir']),
    );
    expect(AppSettings.defaults.realtime.isConfigured, isFalse);
  });
}
