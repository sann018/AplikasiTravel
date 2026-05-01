import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';

class TravSLocationException implements Exception {
  const TravSLocationException(this.message);

  final String message;

  @override
  String toString() => message;
}

class TravSPickupPoint {
  const TravSPickupPoint({
    required this.latitude,
    required this.longitude,
    required this.address,
  });

  final double latitude;
  final double longitude;
  final String address;
}

class TravSLocationService {
  Future<TravSPickupPoint> resolveCurrentPickup() async {
    final hasPermission = await _ensurePermission();
    if (!hasPermission) {
      throw const TravSLocationException(
        'Izin lokasi belum diberikan. Aktifkan izin lokasi lalu coba lagi.',
      );
    }

    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
    );

    return resolvePickupFromCoordinates(
      latitude: position.latitude,
      longitude: position.longitude,
    );
  }

  Future<TravSPickupPoint> resolvePickupFromCoordinates({
    required double latitude,
    required double longitude,
  }) async {
    final address = await _reverseGeocode(
      latitude: latitude,
      longitude: longitude,
    );

    return TravSPickupPoint(
      latitude: latitude,
      longitude: longitude,
      address: address,
    );
  }

  Future<bool> _ensurePermission() async {
    final isServiceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!isServiceEnabled) {
      throw const TravSLocationException(
        'Layanan lokasi di perangkat sedang nonaktif.',
      );
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied) {
      return false;
    }

    if (permission == LocationPermission.deniedForever) {
      throw const TravSLocationException(
        'Izin lokasi ditolak permanen. Buka pengaturan perangkat untuk mengaktifkannya.',
      );
    }

    return true;
  }

  Future<String> _reverseGeocode({
    required double latitude,
    required double longitude,
  }) async {
    try {
      final placemarks = await placemarkFromCoordinates(latitude, longitude);

      if (placemarks.isEmpty) {
        return _fallbackAddress(latitude, longitude);
      }

      final place = placemarks.first;
      final parts =
          <String?>[
            place.street,
            place.subLocality,
            place.locality,
            place.administrativeArea,
          ].whereType<String>().map((part) => part.trim()).where((part) {
            return part.isNotEmpty;
          }).toList();

      if (parts.isEmpty) {
        return _fallbackAddress(latitude, longitude);
      }

      return parts.join(', ');
    } catch (_) {
      return _fallbackAddress(latitude, longitude);
    }
  }

  String _fallbackAddress(double latitude, double longitude) {
    return 'Koordinat pickup (${latitude.toStringAsFixed(6)}, ${longitude.toStringAsFixed(6)})';
  }
}
