import 'dart:convert';

import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;

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
        return await _reverseGeocodeWithOpenStreetMap(
              latitude: latitude,
              longitude: longitude,
            ) ??
            _fallbackAddress();
      }

      final place = placemarks.first;
      final parts =
          <String?>[
                place.street,
                place.thoroughfare,
                place.subThoroughfare,
                place.subLocality,
                place.locality,
                place.subAdministrativeArea,
                place.administrativeArea,
                place.postalCode,
                place.country,
              ]
              .whereType<String>()
              .map((part) => part.trim())
              .where((part) {
                return part.isNotEmpty;
              })
              .toSet()
              .toList();

      if (parts.isEmpty) {
        return await _reverseGeocodeWithOpenStreetMap(
              latitude: latitude,
              longitude: longitude,
            ) ??
            _fallbackAddress();
      }

      return parts.join(', ');
    } catch (_) {
      return await _reverseGeocodeWithOpenStreetMap(
            latitude: latitude,
            longitude: longitude,
          ) ??
          _fallbackAddress();
    }
  }

  Future<String?> _reverseGeocodeWithOpenStreetMap({
    required double latitude,
    required double longitude,
  }) async {
    final uri = Uri.https('nominatim.openstreetmap.org', '/reverse', {
      'format': 'jsonv2',
      'lat': latitude.toString(),
      'lon': longitude.toString(),
      'zoom': '18',
      'addressdetails': '1',
    });

    try {
      final response = await http
          .get(
            uri,
            headers: const {
              'Accept': 'application/json',
              'User-Agent': 'RuslyTravel Mobile App',
            },
          )
          .timeout(const Duration(seconds: 8));

      if (response.statusCode != 200) {
        return null;
      }

      final decoded = jsonDecode(response.body);
      if (decoded is! Map<String, dynamic>) {
        return null;
      }

      final address = decoded['address'];
      if (address is Map<String, dynamic>) {
        final roadName =
            address['road'] ??
            address['residential'] ??
            address['neighbourhood'] ??
            address['village'] ??
            address['suburb'];
        final parts =
            <String?>[
                  roadName?.toString(),
                  address['hamlet']?.toString(),
                  address['village']?.toString(),
                  address['suburb']?.toString(),
                  address['city']?.toString(),
                  address['county']?.toString(),
                  address['state']?.toString(),
                  address['postcode']?.toString(),
                  address['country']?.toString(),
                ]
                .whereType<String>()
                .map((part) => part.trim())
                .where((part) => part.isNotEmpty)
                .toSet()
                .toList();

        if (parts.isNotEmpty) {
          return parts.join(', ');
        }
      }

      final displayName = decoded['display_name']?.toString().trim();
      if (displayName != null && displayName.isNotEmpty) {
        return displayName;
      }
    } catch (_) {
      return null;
    }

    return null;
  }

  String _fallbackAddress() {
    return 'Alamat belum ditemukan. Silakan isi nama jalan atau patokan jemput secara manual.';
  }
}
