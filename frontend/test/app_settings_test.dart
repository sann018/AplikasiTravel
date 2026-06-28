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
      'baggage_free_weight_kg': 8,
    });

    expect(settings.appName, 'RuslyTravel Uji');
    expect(settings.appLogoUrl, '/storage/app-logos/logo-uji.png');
    expect(settings.maxSeatPerBooking, 2);
    expect(settings.seatLockFormMinutes, 15);
    expect(settings.seatLockPaymentMinutes, 5);
    expect(settings.paymentCashEnabled, isTrue);
    expect(settings.baggageFreeWeightKg, 8);
    expect(settings.baggagePhotoRequired, isTrue);
  });

  test('AppSettings default tetap aman saat endpoint tidak tersedia', () {
    expect(AppSettings.defaults.maxSeatPerBooking, 4);
    expect(AppSettings.defaults.seatLockFormMinutes, 30);
    expect(AppSettings.defaults.seatLockPaymentMinutes, 5);
    expect(AppSettings.defaults.paymentCashEnabled, isFalse);
  });
}
