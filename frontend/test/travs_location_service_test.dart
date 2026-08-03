import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/services/travs_location_service.dart';

void main() {
  group('parser lokasi Google Maps', () {
    test('membaca koordinat dari link dengan penanda at', () {
      final result = TravSLocationService.extractCoordinatesFromInput(
        'https://www.google.com/maps/place/Pontianak/@-0.0263,109.3425,17z',
      );

      expect(result, isNotNull);
      expect(result!.latitude, closeTo(-0.0263, 0.000001));
      expect(result.longitude, closeTo(109.3425, 0.000001));
    });

    test('membaca koordinat dari data lokasi Google Maps', () {
      final result = TravSLocationService.extractCoordinatesFromInput(
        'https://www.google.com/maps/place/Test/data=!4m6!3d0.119166!4d110.598164',
      );

      expect(result, isNotNull);
      expect(result!.latitude, closeTo(0.119166, 0.000001));
      expect(result.longitude, closeTo(110.598164, 0.000001));
    });

    test('membaca koordinat dari parameter query Google Maps', () {
      final result = TravSLocationService.extractCoordinatesFromInput(
        'https://www.google.com/maps/search/?api=1&query=-0.0263%2C109.3425',
      );

      expect(result, isNotNull);
      expect(result!.latitude, closeTo(-0.0263, 0.000001));
      expect(result.longitude, closeTo(109.3425, 0.000001));
    });

    test('membaca pasangan koordinat yang ditempel langsung', () {
      final result = TravSLocationService.extractCoordinatesFromInput(
        '-0.0263, 109.3425',
      );

      expect(result, isNotNull);
      expect(result!.latitude, closeTo(-0.0263, 0.000001));
      expect(result.longitude, closeTo(109.3425, 0.000001));
    });

    test('menolak koordinat di luar rentang bumi', () {
      final result = TravSLocationService.extractCoordinatesFromInput(
        '95.0000, 200.0000',
      );

      expect(result, isNull);
    });
  });
}
