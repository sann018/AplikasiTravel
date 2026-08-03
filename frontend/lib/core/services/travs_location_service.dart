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

class TravSLocationCoordinates {
  const TravSLocationCoordinates({
    required this.latitude,
    required this.longitude,
  });

  final double latitude;
  final double longitude;
}

class TravSLocationService {
  /// Membaca alamat, pasangan koordinat, atau link Google Maps.
  ///
  /// Hasil akhirnya tetap berupa koordinat dan alamat yang sama dengan pilihan
  /// peta, sehingga kontrak pemesanan ke backend tidak berubah.
  Future<TravSPickupPoint> resolvePickupFromInput(String input) async {
    final query = input.trim();
    if (query.isEmpty) {
      throw const TravSLocationException(
        'Masukkan alamat lengkap atau link Google Maps terlebih dahulu.',
      );
    }

    final directCoordinates = extractCoordinatesFromInput(query);
    if (directCoordinates != null) {
      return resolvePickupFromCoordinates(
        latitude: directCoordinates.latitude,
        longitude: directCoordinates.longitude,
      );
    }

    final inputUri = Uri.tryParse(query);
    if (inputUri != null && inputUri.hasScheme) {
      if (!_isSupportedGoogleMapsUri(inputUri)) {
        throw const TravSLocationException(
          'Link belum dikenali. Gunakan link Google Maps atau tulis alamat lengkap.',
        );
      }

      final resolvedUri = _isShortGoogleMapsUri(inputUri)
          ? await _expandShortGoogleMapsUri(inputUri)
          : inputUri;
      final linkCoordinates = extractCoordinatesFromInput(
        resolvedUri.toString(),
      );
      if (linkCoordinates != null) {
        return resolvePickupFromCoordinates(
          latitude: linkCoordinates.latitude,
          longitude: linkCoordinates.longitude,
        );
      }

      final locationQuery = _extractGoogleMapsTextQuery(resolvedUri);
      if (locationQuery != null) {
        return resolvePickupFromAddress(locationQuery);
      }

      throw const TravSLocationException(
        'Lokasi pada link Google Maps belum dapat dibaca. Buka link tersebut lalu salin alamat lengkap atau pilih titik melalui peta aplikasi.',
      );
    }

    return resolvePickupFromAddress(query);
  }

  /// Parser ini tidak mengakses jaringan dan dipakai untuk format koordinat
  /// yang umum terdapat pada link Google Maps.
  static TravSLocationCoordinates? extractCoordinatesFromInput(String input) {
    final normalized = _safeDecode(input.trim()).replaceAll('+', ' ');
    final uri = Uri.tryParse(input.trim());

    final explicitPatterns = <RegExp>[
      RegExp(
        r'!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)',
        caseSensitive: false,
      ),
      RegExp(
        r'@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)',
        caseSensitive: false,
      ),
    ];

    for (final pattern in explicitPatterns) {
      final match = pattern.firstMatch(normalized);
      final coordinates = _coordinatesFromMatch(match);
      if (coordinates != null) {
        return coordinates;
      }
    }

    if (uri != null) {
      for (final key in const [
        'query',
        'q',
        'destination',
        'origin',
        'center',
        'll',
      ]) {
        final coordinates = _coordinatesFromPlainText(
          uri.queryParameters[key] ?? '',
        );
        if (coordinates != null) {
          return coordinates;
        }
      }
    }

    return _coordinatesFromPlainText(normalized);
  }

  Future<TravSPickupPoint> resolvePickupFromAddress(String address) async {
    final query = address.trim();
    if (query.isEmpty) {
      throw const TravSLocationException(
        'Nama tujuan perjalanan belum tersedia.',
      );
    }

    try {
      final locations = await locationFromAddress(query);
      if (locations.isNotEmpty) {
        return resolvePickupFromCoordinates(
          latitude: locations.first.latitude,
          longitude: locations.first.longitude,
        );
      }
    } catch (_) {
      // Lanjutkan ke mekanisme OpenStreetMap yang juga dipakai reverse geocoding.
    }

    final openStreetMapPoint = await _geocodeWithOpenStreetMap(query);
    if (openStreetMapPoint != null) {
      return openStreetMapPoint;
    }

    throw TravSLocationException(
      'Koordinat tujuan $query belum dapat ditemukan.',
    );
  }

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

  static TravSLocationCoordinates? _coordinatesFromMatch(RegExpMatch? match) {
    if (match == null) {
      return null;
    }

    return _validCoordinates(
      double.tryParse(match.group(1) ?? ''),
      double.tryParse(match.group(2) ?? ''),
    );
  }

  static TravSLocationCoordinates? _coordinatesFromPlainText(String value) {
    final match = RegExp(
      r'^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,;]\s*(-?\d{1,3}(?:\.\d+)?)\s*$',
    ).firstMatch(value);
    return _coordinatesFromMatch(match);
  }

  static TravSLocationCoordinates? _validCoordinates(
    double? latitude,
    double? longitude,
  ) {
    if (latitude == null || longitude == null) {
      return null;
    }
    if (latitude < -90 ||
        latitude > 90 ||
        longitude < -180 ||
        longitude > 180) {
      return null;
    }

    return TravSLocationCoordinates(latitude: latitude, longitude: longitude);
  }

  static String _safeDecode(String value) {
    try {
      return Uri.decodeFull(value);
    } catch (_) {
      return value;
    }
  }

  static bool _isSupportedGoogleMapsUri(Uri uri) {
    final host = uri.host.toLowerCase();
    if (_isShortGoogleMapsUri(uri)) {
      return true;
    }

    final isGoogleHost =
        host == 'google.com' ||
        host.endsWith('.google.com') ||
        RegExp(r'(^|\.)google\.[a-z.]+$').hasMatch(host);
    return isGoogleHost &&
        (uri.path.toLowerCase().startsWith('/maps') ||
            host.startsWith('maps.'));
  }

  static bool _isShortGoogleMapsUri(Uri uri) {
    final host = uri.host.toLowerCase();
    return host == 'maps.app.goo.gl' ||
        (host == 'goo.gl' && uri.path.toLowerCase().startsWith('/maps'));
  }

  static String? _extractGoogleMapsTextQuery(Uri uri) {
    for (final key in const ['query', 'q', 'destination', 'origin']) {
      final value = uri.queryParameters[key]?.trim();
      if (value != null &&
          value.isNotEmpty &&
          _coordinatesFromPlainText(value) == null) {
        return value;
      }
    }

    final segments = uri.pathSegments;
    final placeIndex = segments.indexWhere(
      (segment) => segment.toLowerCase() == 'place',
    );
    if (placeIndex >= 0 && placeIndex + 1 < segments.length) {
      final place = _safeDecode(
        segments[placeIndex + 1],
      ).replaceAll('+', ' ').trim();
      if (place.isNotEmpty) {
        return place;
      }
    }

    return null;
  }

  Future<Uri> _expandShortGoogleMapsUri(Uri uri) async {
    final client = http.Client();
    try {
      final request = http.Request('GET', uri)
        ..followRedirects = true
        ..maxRedirects = 6
        ..headers.addAll(const {
          'Accept': 'text/html,application/xhtml+xml',
          'User-Agent': 'RuslyTravel Mobile App',
        });
      final response = await client
          .send(request)
          .timeout(const Duration(seconds: 10));

      final resolvedUri = response is http.BaseResponseWithUrl
          ? (response as http.BaseResponseWithUrl).url
          : uri;
      await response.stream.drain<void>();
      return resolvedUri;
    } catch (_) {
      throw const TravSLocationException(
        'Link pendek Google Maps belum dapat dibuka. Periksa koneksi internet atau tempel alamat lengkap.',
      );
    } finally {
      client.close();
    }
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

  Future<TravSPickupPoint?> _geocodeWithOpenStreetMap(String query) async {
    final uri = Uri.https('nominatim.openstreetmap.org', '/search', {
      'format': 'jsonv2',
      'q': query,
      'limit': '1',
      'countrycodes': 'id',
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
      if (decoded is! List || decoded.isEmpty || decoded.first is! Map) {
        return null;
      }

      final result = Map<String, dynamic>.from(decoded.first as Map);
      final latitude = double.tryParse(result['lat']?.toString() ?? '');
      final longitude = double.tryParse(result['lon']?.toString() ?? '');
      if (latitude == null || longitude == null) {
        return null;
      }

      final displayName = result['display_name']?.toString().trim();
      return TravSPickupPoint(
        latitude: latitude,
        longitude: longitude,
        address: displayName == null || displayName.isEmpty
            ? query
            : displayName,
      );
    } catch (_) {
      return null;
    }
  }

  String _fallbackAddress() {
    return 'Alamat belum ditemukan. Silakan isi nama jalan atau patokan jemput secara manual.';
  }
}
