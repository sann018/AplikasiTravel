import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../models/travs_booking_contract.dart';
import '../models/travs_user_session.dart';

class TravSApiException implements Exception {
  const TravSApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

class TravSApiService {
  static const Duration _requestTimeout = Duration(seconds: 10);
  static String? _lastSuccessfulBaseUrl;

  static List<String> get baseUrls {
    const configured = String.fromEnvironment('API_BASE_URL');
    if (configured.isNotEmpty) {
      return [_normalizeBaseUrl(configured)];
    }

    if (kIsWeb) {
      return const ['http://127.0.0.1:8000'];
    }

    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return const ['http://127.0.0.1:8000', 'http://10.0.2.2:8000'];
      default:
        return const ['http://127.0.0.1:8000'];
    }
  }

  static String get baseUrl {
    return _lastSuccessfulBaseUrl ?? baseUrls.first;
  }

  static String get backendTargetsLabel => _orderedBaseUrls().join(' atau ');

  static String? resolveMediaUrl(String? value) {
    final raw = value?.trim();
    if (raw == null || raw.isEmpty) {
      return null;
    }

    if (raw.startsWith('http://') || raw.startsWith('https://')) {
      final uri = Uri.tryParse(raw);
      final baseUri = Uri.parse(baseUrl);
      if (uri != null && uri.path.startsWith('/storage/')) {
        // Session lama pernah menyimpan URL ngrok. Untuk file storage backend,
        // pakai host API yang aktif agar gambar tetap muncul di lokal/mobile.
        return baseUri.replace(path: uri.path, query: uri.query).toString();
      }

      if (uri != null && (uri.host == '127.0.0.1' || uri.host == 'localhost')) {
        return baseUri.replace(path: uri.path, query: uri.query).toString();
      }

      return raw;
    }

    if (raw.startsWith('/')) {
      return '$baseUrl$raw';
    }

    if (raw.startsWith('storage/')) {
      return '$baseUrl/$raw';
    }

    return '$baseUrl/storage/$raw';
  }

  static Future<http.Response> getWithFallback(
    String path, {
    Map<String, String>? headers,
  }) {
    return _sendWithFallback(
      (baseUrl) => http.get(Uri.parse('$baseUrl$path'), headers: headers),
    );
  }

  static Future<http.Response> postWithFallback(
    String path, {
    Map<String, String>? headers,
    Object? body,
  }) {
    return _sendWithFallback(
      (baseUrl) =>
          http.post(Uri.parse('$baseUrl$path'), headers: headers, body: body),
    );
  }

  static Future<http.Response> putWithFallback(
    String path, {
    Map<String, String>? headers,
    Object? body,
  }) {
    return _sendWithFallback(
      (baseUrl) =>
          http.put(Uri.parse('$baseUrl$path'), headers: headers, body: body),
    );
  }

  static Map<String, String> jsonHeaders({String? token}) {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  /// Memuat data beranda dari backend (bootstrap + jadwal).
  /// Melempar TravSApiException jika koneksi gagal atau response tidak valid,
  /// agar UI bisa menampilkan error state yang jujur ke pengguna.
  Future<TravSHomePayload> loadHomePayload() async {
    final responses = await Future.wait([
      getWithFallback('/api/mobile/bootstrap', headers: jsonHeaders()),
      getWithFallback('/api/guest/jadwal', headers: jsonHeaders()),
    ]);
    final bootstrapResponse = responses[0];
    final scheduleResponse = responses[1];

    if (bootstrapResponse.statusCode != 200) {
      throw TravSApiException(
        _extractErrorMessage(_tryDecodeMap(bootstrapResponse.body)),
      );
    }

    if (scheduleResponse.statusCode != 200) {
      throw TravSApiException(
        _extractErrorMessage(_tryDecodeMap(scheduleResponse.body)),
      );
    }

    final bootstrapJson =
        _decodeBody(bootstrapResponse.body) as Map<String, dynamic>;
    final scheduleJson =
        _decodeBody(scheduleResponse.body) as Map<String, dynamic>;

    final bootstrapData =
        bootstrapJson['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
    final scheduleItems = scheduleJson['data'] as List<dynamic>? ?? <dynamic>[];
    final featuredItems =
        bootstrapData['featured_schedules'] as List<dynamic>? ?? <dynamic>[];

    final schedulesById = <int, TravSSchedule>{};
    for (final item in [...featuredItems, ...scheduleItems]) {
      final schedule = TravSSchedule.fromJson(item as Map<String, dynamic>);
      schedulesById[schedule.id] = schedule;
    }

    final branding =
        bootstrapData['branding'] as Map<String, dynamic>? ??
        <String, dynamic>{};

    return TravSHomePayload(
      appName: branding['app_name'] as String? ?? 'TravS',
      featuredSchedules: schedulesById.values.toList(),
      sourceLabel: 'Live dari Laravel API',
      serviceWhatsapp: branding['service_whatsapp'] as String?,
      adminContact: branding['contact'] as String?,
    );
  }

  /// Memuat detail jadwal dari backend.
  /// Melempar TravSApiException jika gagal, agar UI menampilkan error yang jujur.
  Future<TravSScheduleDetail> loadScheduleDetail(TravSSchedule schedule) async {
    final response = await getWithFallback(
      '/api/guest/jadwal/${schedule.id}',
      headers: jsonHeaders(),
    );

    if (response.statusCode != 200) {
      throw TravSApiException(
        _extractErrorMessage(_tryDecodeMap(response.body)),
      );
    }

    final decoded = _decodeBody(response.body) as Map<String, dynamic>;
    final data =
        decoded['data'] as Map<String, dynamic>? ?? <String, dynamic>{};

    return TravSScheduleDetail.fromJson(data);
  }

  /// Memuat dashboard penumpang dari backend.
  /// Melempar TravSApiException jika gagal — tidak lagi fallback ke data palsu.
  Future<TravSPassengerDashboard> loadPassengerDashboard(String token) async {
    final response = await getWithFallback(
      '/api/penumpang/dashboard',
      headers: jsonHeaders(token: token),
    );

    if (response.statusCode != 200) {
      throw TravSApiException(
        _extractErrorMessage(_tryDecodeMap(response.body)),
      );
    }

    final decoded = _decodeBody(response.body) as Map<String, dynamic>;
    final data =
        decoded['data'] as Map<String, dynamic>? ?? <String, dynamic>{};

    return TravSPassengerDashboard.fromJson(data);
  }

  /// Memuat dashboard pengemudi dari backend.
  /// Melempar TravSApiException jika gagal — tidak lagi fallback ke data palsu.
  Future<TravSDriverDashboard> loadDriverDashboard(String token) async {
    final response = await getWithFallback(
      '/api/pengemudi/dashboard',
      headers: jsonHeaders(token: token),
    );

    if (response.statusCode != 200) {
      throw TravSApiException(
        _extractErrorMessage(_tryDecodeMap(response.body)),
      );
    }

    final decoded = _decodeBody(response.body) as Map<String, dynamic>;
    final data =
        decoded['data'] as Map<String, dynamic>? ?? <String, dynamic>{};

    return TravSDriverDashboard.fromJson(data);
  }

  Future<List<TravSDriverTripItem>> loadDriverTrips(String token) async {
    final response = await getWithFallback(
      '/api/pengemudi/trips',
      headers: jsonHeaders(token: token),
    );

    return _decodeDataList(
      response,
      (item) => TravSDriverTripItem.fromJson(item),
    );
  }

  /// Memuat daftar pemesanan penumpang dari backend.
  /// Melempar TravSApiException jika gagal — tidak lagi fallback ke data palsu.
  Future<List<TravSBookingResult>> loadPassengerBookings(String token) async {
    final response = await getWithFallback(
      '/api/penumpang/bookings',
      headers: jsonHeaders(token: token),
    );

    return _decodeDataList(
      response,
      (item) => TravSBookingResult.fromJson(item),
    );
  }

  Future<TravSBookingResult> loadPassengerBookingDetail({
    required String token,
    required int bookingId,
  }) async {
    final response = await getWithFallback(
      '/api/penumpang/bookings/$bookingId',
      headers: jsonHeaders(token: token),
    );

    return _decodeDataObject(
      response,
      (data) => TravSBookingResult.fromJson(data),
    );
  }

  dynamic _decodeBody(String body) {
    return jsonDecode(body);
  }

  Future<TravSSeatLockResult> lockSeatsForCheckout({
    required String token,
    required int scheduleId,
    required List<int> seatNumbers,
  }) async {
    final response = await postWithFallback(
      '/api/penumpang/bookings/lock-form',
      headers: jsonHeaders(token: token),
      body: jsonEncode({'jadwal_id': scheduleId, 'seat_numbers': seatNumbers}),
    );

    return _decodeDataObject(
      response,
      (data) => TravSSeatLockResult.fromJson(data),
    );
  }

  Future<TravSBookingResult> createBooking({
    required String token,
    required TravSCreateBookingPayload payload,
  }) async {
    final response = await postWithFallback(
      '/api/penumpang/bookings',
      headers: jsonHeaders(token: token),
      body: jsonEncode(payload.toJson()),
    );

    return _decodeDataObject(
      response,
      (data) => TravSBookingResult.fromJson(data),
    );
  }

  Future<TravSUploadedBaggagePhoto> uploadBaggagePhoto({
    required String token,
    required XFile photo,
  }) async {
    final bytes = await photo.readAsBytes();
    Object? lastError;

    for (final candidate in _orderedBaseUrls()) {
      try {
        final request = http.MultipartRequest(
          'POST',
          Uri.parse('$candidate/api/penumpang/bookings/baggage-photo'),
        );
        request.headers.addAll({
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        });
        request.files.add(
          http.MultipartFile.fromBytes('foto', bytes, filename: photo.name),
        );

        final streamed = await request.send().timeout(
          const Duration(seconds: 20),
        );
        final response = await http.Response.fromStream(streamed);
        _lastSuccessfulBaseUrl = candidate;

        return _decodeDataObject(
          response,
          (data) => TravSUploadedBaggagePhoto.fromJson(data),
        );
      } catch (error) {
        lastError = error;
      }
    }

    if (lastError != null) {
      throw lastError;
    }

    throw const TravSApiException('Foto barang bawaan gagal diunggah.');
  }

  Future<TravSUserProfile> updateProfile({
    required String token,
    required String nama,
    required String email,
    required String noHp,
  }) async {
    final response = await putWithFallback(
      '/api/auth/profile',
      headers: jsonHeaders(token: token),
      body: jsonEncode({
        'nama': nama.trim(),
        'email': email.trim(),
        'no_hp': noHp.trim(),
      }),
    );

    return _decodeDataObject(
      response,
      (data) => TravSUserProfile.fromJson(_profilePayload(data)),
    );
  }

  Future<TravSUserProfile> loadCurrentUserProfile({
    required String token,
  }) async {
    final response = await getWithFallback(
      '/api/auth/me',
      headers: jsonHeaders(token: token),
    );

    return _decodeDataObject(
      response,
      (data) => TravSUserProfile.fromJson(_profilePayload(data)),
    );
  }

  Future<void> changePassword({
    required String token,
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await postWithFallback(
      '/api/auth/change-password',
      headers: jsonHeaders(token: token),
      body: jsonEncode({
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': passwordConfirmation,
      }),
    );

    final body = _tryDecodeMap(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw TravSApiException(_extractErrorMessage(body));
    }
  }

  Future<TravSUploadedProfilePhoto> uploadProfilePhoto({
    required String token,
    required XFile photo,
  }) async {
    final bytes = await photo.readAsBytes();
    Object? lastError;

    for (final candidate in _orderedBaseUrls()) {
      try {
        final request = http.MultipartRequest(
          'POST',
          Uri.parse('$candidate/api/auth/upload-photo'),
        );
        request.headers.addAll({
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        });
        request.files.add(
          http.MultipartFile.fromBytes(
            'foto_profil',
            bytes,
            filename: photo.name,
          ),
        );

        final streamed = await request.send().timeout(
          const Duration(seconds: 20),
        );
        final response = await http.Response.fromStream(streamed);
        _lastSuccessfulBaseUrl = candidate;

        return _decodeDataObject(
          response,
          (data) => TravSUploadedProfilePhoto.fromJson(data),
        );
      } catch (error) {
        lastError = error;
      }
    }

    if (lastError != null) {
      throw lastError;
    }

    throw const TravSApiException('Foto profil gagal diunggah.');
  }

  Future<TravSBookingResult> simulatePayment({
    required String token,
    required int bookingId,
  }) async {
    final response = await postWithFallback(
      '/api/penumpang/bookings/$bookingId/simulate-payment',
      headers: jsonHeaders(token: token),
    );

    return _decodeDataObject(
      response,
      (data) => TravSBookingResult.fromJson(data),
    );
  }

  Future<TravSBookingResult> refreshPaymentStatus({
    required String token,
    required int bookingId,
  }) async {
    final response = await postWithFallback(
      '/api/penumpang/bookings/$bookingId/refresh-payment-status',
      headers: jsonHeaders(token: token),
    );

    return _decodeDataObject(
      response,
      (data) => TravSBookingResult.fromJson(data),
    );
  }

  Future<TravSScanTicketResult> scanDriverTicket({
    required String token,
    required String ticketToken,
  }) async {
    final response = await postWithFallback(
      '/api/pengemudi/tickets/scan',
      headers: jsonHeaders(token: token),
      body: jsonEncode({'token': ticketToken}),
    );

    return _decodeDataObject(
      response,
      (data) => TravSScanTicketResult.fromJson(data),
    );
  }

  Future<TravSDriverManifestPassenger> markDriverPassengerDropoff({
    required String token,
    required int tripId,
    required int detailId,
  }) async {
    final response = await postWithFallback(
      '/api/pengemudi/trips/$tripId/passengers/$detailId/dropoff',
      headers: jsonHeaders(token: token),
    );

    return _decodeDataObject(
      response,
      (data) => TravSDriverManifestPassenger.fromJson(data),
    );
  }

  /// Mengirim request forgot password ke backend.
  /// Mengembalikan token reset jika email terdaftar.
  Future<String?> forgotPassword({required String email}) async {
    final response = await postWithFallback(
      '/api/auth/forgot-password',
      headers: jsonHeaders(),
      body: jsonEncode({'email': email}),
    );

    final body = _tryDecodeMap(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw TravSApiException(_extractErrorMessage(body));
    }

    final data = body['data'] as Map<String, dynamic>?;
    return data?['reset_token'] as String?;
  }

  /// Mengirim request reset password ke backend.
  /// Membutuhkan email, token, dan password baru.
  Future<void> resetPassword({
    required String email,
    required String token,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await postWithFallback(
      '/api/auth/reset-password',
      headers: jsonHeaders(),
      body: jsonEncode({
        'email': email,
        'token': token,
        'password': password,
        'password_confirmation': passwordConfirmation,
      }),
    );

    final body = _tryDecodeMap(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw TravSApiException(_extractErrorMessage(body));
    }
  }

  T _decodeDataObject<T>(
    http.Response response,
    T Function(Map<String, dynamic> data) parser,
  ) {
    final body = _tryDecodeMap(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw TravSApiException(_extractErrorMessage(body));
    }

    final data = body['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
    return parser(data);
  }

  Map<String, dynamic> _profilePayload(Map<String, dynamic> data) {
    final nestedUser = data['user'];
    if (nestedUser is Map<String, dynamic>) {
      return nestedUser;
    }

    return data;
  }

  List<T> _decodeDataList<T>(
    http.Response response,
    T Function(Map<String, dynamic> data) parser,
  ) {
    final body = _tryDecodeMap(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw TravSApiException(_extractErrorMessage(body));
    }

    final data = body['data'] as List<dynamic>? ?? <dynamic>[];
    return data.map((item) => parser(item as Map<String, dynamic>)).toList();
  }

  Map<String, dynamic> _tryDecodeMap(String body) {
    try {
      final decoded = jsonDecode(body);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
    } catch (_) {
      // Fallback ke map kosong di bawah.
    }

    return <String, dynamic>{};
  }

  String _extractErrorMessage(Map<String, dynamic> body) {
    final errors = body['errors'];
    if (errors is Map) {
      for (final entry in errors.entries) {
        final fieldName = entry.key.toString();
        final value = entry.value;
        if (value is List && value.isNotEmpty) {
          final message = value.first?.toString();
          if (message != null && message.isNotEmpty) {
            return _friendlyValidationMessage(fieldName, message);
          }
        }
      }
    }

    final message = body['message']?.toString();
    if (message != null && message.isNotEmpty) {
      return _friendlyValidationMessage('', message);
    }

    return 'Permintaan tidak dapat diproses. Silakan coba lagi.';
  }

  String _friendlyValidationMessage(String fieldName, String message) {
    final normalizedMessage = message.toLowerCase();

    if (normalizedMessage == 'validation.unique') {
      if (fieldName == 'email') {
        return 'Email sudah terdaftar. Silakan masuk atau gunakan email lain.';
      }
      if (fieldName == 'no_hp') {
        return 'Nomor HP sudah terdaftar. Silakan masuk atau gunakan nomor lain.';
      }

      return 'Data tersebut sudah terdaftar. Silakan gunakan data lain.';
    }

    if (normalizedMessage == 'validation.confirmed') {
      return 'Konfirmasi kata sandi belum sama.';
    }

    if (normalizedMessage == 'validation.email') {
      return 'Format email belum valid.';
    }

    if (normalizedMessage == 'validation.required') {
      return 'Data wajib masih ada yang belum diisi.';
    }

    if (normalizedMessage == 'validation.max.file') {
      return 'Ukuran file terlalu besar. Pilih foto maksimal 5 MB.';
    }

    if (normalizedMessage == 'validation.image') {
      return 'File harus berupa gambar.';
    }

    if (normalizedMessage == 'validation.mimes') {
      return 'Format file belum didukung. Gunakan JPG, JPEG, PNG, atau WEBP.';
    }

    return message;
  }

  static Future<http.Response> _sendWithFallback(
    Future<http.Response> Function(String baseUrl) request,
  ) async {
    Object? lastError;
    final candidates = _orderedBaseUrls();
    for (final candidate in candidates) {
      try {
        final response = await request(candidate).timeout(_requestTimeout);
        _lastSuccessfulBaseUrl = candidate;
        return response;
      } catch (error) {
        lastError = error;
      }
    }

    if (lastError != null) {
      throw TravSApiException(
        'Backend lokal belum terhubung ke ${candidates.join(' atau ')}. '
        'Pastikan Laravel berjalan di port 8000 dan adb reverse aktif jika memakai HP fisik.',
      );
    }

    throw const TravSApiException(
      'Backend lokal belum dapat dijangkau. Pastikan Laravel berjalan dan alamat API sesuai perangkat.',
    );
  }

  static String _normalizeBaseUrl(String value) {
    return value.endsWith('/') ? value.substring(0, value.length - 1) : value;
  }

  static List<String> _orderedBaseUrls() {
    final urls = baseUrls;
    final activeUrl = _lastSuccessfulBaseUrl;
    if (activeUrl == null || !urls.contains(activeUrl)) {
      return urls;
    }

    return [activeUrl, ...urls.where((url) => url != activeUrl)];
  }
}

class TravSUploadedBaggagePhoto {
  const TravSUploadedBaggagePhoto({
    required this.path,
    required this.url,
    required this.originalName,
  });

  final String path;
  final String url;
  final String originalName;

  factory TravSUploadedBaggagePhoto.fromJson(Map<String, dynamic> json) {
    return TravSUploadedBaggagePhoto(
      path: json['path'] as String? ?? '',
      url: json['url'] as String? ?? '',
      originalName: json['original_name'] as String? ?? '',
    );
  }
}

class TravSUploadedProfilePhoto {
  const TravSUploadedProfilePhoto({required this.path, required this.url});

  final String path;
  final String url;

  factory TravSUploadedProfilePhoto.fromJson(Map<String, dynamic> json) {
    return TravSUploadedProfilePhoto(
      path: json['foto_profil'] as String? ?? '',
      url: json['foto_profil_url'] as String? ?? '',
    );
  }
}

class TravSHomePayload {
  const TravSHomePayload({
    required this.appName,
    required this.featuredSchedules,
    required this.sourceLabel,
    this.serviceWhatsapp,
    this.adminContact,
  });

  final String appName;
  final List<TravSSchedule> featuredSchedules;
  final String sourceLabel;
  final String? serviceWhatsapp;
  final String? adminContact;
}

class TravSSchedule {
  const TravSSchedule({
    required this.id,
    required this.asal,
    required this.tujuan,
    required this.jalur,
    required this.pengemudi,
    required this.kendaraan,
    required this.waktuBerangkat,
    required this.estimasiTiba,
    required this.hargaTiket,
    required this.sisaKursi,
    required this.kapasitas,
    required this.durasiMenit,
  });

  final int id;
  final String asal;
  final String tujuan;
  final String jalur;
  final String pengemudi;
  final String kendaraan;
  final String waktuBerangkat;
  final String estimasiTiba;
  final double hargaTiket;
  final int sisaKursi;
  final int kapasitas;
  final int durasiMenit;

  factory TravSSchedule.fromJson(Map<String, dynamic> json) {
    final route = _asMap(json['rute']);
    final kendaraanData = json['kendaraan'];
    final pengemudiData = json['pengemudi'];
    final seatMap = json['seat_map'] as List<dynamic>? ?? <dynamic>[];

    String kendaraan = '-';
    if (kendaraanData is String) {
      kendaraan = kendaraanData;
    } else if (kendaraanData is Map) {
      final kendaraanMap = _asMap(kendaraanData);
      kendaraan =
          '${kendaraanMap['merk'] ?? '-'} - ${kendaraanMap['plat'] ?? '-'}';
    }

    String pengemudi = '-';
    if (pengemudiData is String) {
      pengemudi = pengemudiData;
    } else if (pengemudiData is Map) {
      pengemudi = _asMap(pengemudiData)['nama'] as String? ?? '-';
    }

    return TravSSchedule(
      id: _readInt(json['id']),
      asal: route['asal'] as String? ?? '-',
      tujuan: route['tujuan'] as String? ?? '-',
      jalur: route['jalur'] as String? ?? '-',
      pengemudi: pengemudi,
      kendaraan: kendaraan,
      waktuBerangkat: json['waktu_berangkat'] as String? ?? '',
      estimasiTiba: json['estimasi_tiba'] as String? ?? '',
      hargaTiket: _readDouble(json['harga_tiket'] ?? route['harga_tiket']),
      sisaKursi: json['sisa_kursi'] != null
          ? _readInt(json['sisa_kursi'])
          : seatMap
                .map((item) => _asMap(item)['status'])
                .where((status) => status == 'tersedia')
                .length,
      kapasitas: json['kapasitas'] != null
          ? _readInt(json['kapasitas'])
          : (_asMap(json['kendaraan'])['kapasitas'] != null
                ? _readInt(_asMap(json['kendaraan'])['kapasitas'])
                : seatMap.length),
      durasiMenit: json['estimasi_durasi_menit'] != null
          ? _readInt(json['estimasi_durasi_menit'])
          : _readInt(route['durasi_menit']),
    );
  }
}

class TravSScheduleDetail {
  const TravSScheduleDetail({
    required this.schedule,
    required this.seatMap,
    required this.seatLayout,
    required this.bookingRules,
    required this.paymentMethods,
    required this.baggageRules,
    required this.vehicleFacilities,
    required this.vehicleType,
    required this.driverLicenseNumber,
    required this.sourceLabel,
    required this.isLive,
  });

  final TravSSchedule schedule;
  final List<TravSSeatItem> seatMap;

  /// Konfigurasi baris kursi dari backend (per kendaraan).
  /// Format: [1, 3, 2] = baris 1 ada 1 kursi, baris 2 ada 3 kursi, baris 3 ada 2 kursi.
  /// Jika kosong, frontend akan generate default berdasarkan jumlah kursi.
  final List<int> seatLayout;

  final TravSBookingRules bookingRules;
  final TravSPaymentMethods paymentMethods;
  final TravSBaggageRules baggageRules;
  final String vehicleFacilities;
  final String vehicleType;
  final String driverLicenseNumber;
  final String sourceLabel;
  final bool isLive;

  int get bookedSeatCount =>
      seatMap.where((seat) => seat.status == 'dipesan').length;

  int get lockedSeatCount =>
      seatMap.where((seat) => seat.status == 'terkunci').length;

  factory TravSScheduleDetail.fromJson(Map<String, dynamic> json) {
    final kendaraan = _asMap(json['kendaraan']);
    final pengemudi = _asMap(json['pengemudi']);

    // Parse seat_layout dari response API kendaraan
    final rawLayout = kendaraan['seat_layout'];
    final seatLayout = rawLayout is List
        ? rawLayout.map((e) => _readInt(e)).toList()
        : <int>[];

    return TravSScheduleDetail(
      schedule: TravSSchedule.fromJson(json),
      seatMap: (json['seat_map'] as List<dynamic>? ?? <dynamic>[])
          .map((item) => TravSSeatItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      seatLayout: seatLayout,
      bookingRules: TravSBookingRules.fromJson(_asMap(json['booking_rules'])),
      paymentMethods: TravSPaymentMethods.fromJson(
        _asMap(json['payment_methods']),
      ),
      baggageRules: TravSBaggageRules.fromJson(_asMap(json['bagasi'])),
      vehicleFacilities: kendaraan['fasilitas'] as String? ?? '-',
      vehicleType: kendaraan['tipe'] as String? ?? '-',
      driverLicenseNumber: pengemudi['nomor_sim'] as String? ?? '-',
      sourceLabel: 'Live dari Laravel API',
      isLive: true,
    );
  }
}

class TravSSeatItem {
  const TravSSeatItem({
    required this.id,
    required this.seatNumber,
    required this.status,
    this.lockExpiresAt,
  });

  final int id;
  final int seatNumber;
  final String status;
  final String? lockExpiresAt;

  factory TravSSeatItem.fromJson(Map<String, dynamic> json) {
    return TravSSeatItem(
      id: _readInt(json['id']),
      seatNumber: _readInt(json['nomor_kursi']),
      status: json['status'] as String? ?? 'tersedia',
      lockExpiresAt: json['lock_expires_at'] as String?,
    );
  }
}

class TravSBookingRules {
  const TravSBookingRules({
    required this.maxSeatPerBooking,
    required this.seatLockFormMinutes,
    required this.seatLockPaymentMinutes,
    required this.adminFee,
  });

  final int maxSeatPerBooking;
  final int seatLockFormMinutes;
  final int seatLockPaymentMinutes;
  final double adminFee;

  factory TravSBookingRules.fromJson(Map<String, dynamic> json) {
    return TravSBookingRules(
      maxSeatPerBooking: _readInt(json['max_seat_per_booking'], fallback: 4),
      seatLockFormMinutes: _readInt(
        json['seat_lock_form_minutes'],
        fallback: 30,
      ),
      seatLockPaymentMinutes: _readInt(
        json['seat_lock_payment_minutes'],
        fallback: 5,
      ),
      adminFee: _readDouble(json['admin_fee']),
    );
  }
}

class TravSPaymentMethods {
  const TravSPaymentMethods({
    required this.transferEnabled,
    required this.qrisEnabled,
    required this.cashEnabled,
  });

  final bool transferEnabled;
  final bool qrisEnabled;
  final bool cashEnabled;

  List<String> get activeLabels {
    final labels = <String>[];
    if (transferEnabled) {
      labels.add('Transfer Bank');
    }
    if (qrisEnabled) {
      labels.add('QRIS');
    }
    if (cashEnabled) {
      labels.add('Bayar Tunai');
    }
    return labels;
  }

  factory TravSPaymentMethods.fromJson(Map<String, dynamic> json) {
    return TravSPaymentMethods(
      transferEnabled: _readBool(json['transfer']),
      qrisEnabled: _readBool(json['qris']),
      cashEnabled: _readBool(json['cash']),
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
  final bool photoRequired;

  factory TravSBaggageRules.fromJson(Map<String, dynamic> json) {
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
      extraFeePerItem: _readDouble(json['extra_fee_per_item']),
      photoRequired: _readBool(json['photo_required']),
    );
  }
}

class TravSPassengerDashboard {
  const TravSPassengerDashboard({
    required this.totalBooking,
    required this.bookingAktif,
    required this.tiketAktif,
    required this.maxSeatPerBooking,
    required this.recentBookings,
  });

  final int totalBooking;
  final int bookingAktif;
  final int tiketAktif;
  final int maxSeatPerBooking;
  final List<TravSPassengerBookingItem> recentBookings;

  factory TravSPassengerDashboard.fromJson(Map<String, dynamic> json) {
    final summary = _asMap(json['summary']);
    return TravSPassengerDashboard(
      totalBooking: _readInt(summary['total_booking']),
      bookingAktif: _readInt(summary['booking_aktif']),
      tiketAktif: _readInt(summary['tiket_aktif']),
      maxSeatPerBooking: _readInt(summary['max_seat_per_booking'], fallback: 4),
      recentBookings: (json['recent_bookings'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) => TravSPassengerBookingItem.fromJson(
              item as Map<String, dynamic>,
            ),
          )
          .toList(),
    );
  }
}

class TravSPassengerBookingItem {
  const TravSPassengerBookingItem({
    required this.id,
    required this.kodeBooking,
    required this.status,
    required this.paymentStatus,
    required this.totalHarga,
    required this.rute,
    required this.waktuBerangkat,
    required this.jumlahTiket,
  });

  final int id;
  final String kodeBooking;
  final String status;
  final String paymentStatus;
  final double totalHarga;
  final String rute;
  final String waktuBerangkat;
  final int jumlahTiket;

  factory TravSPassengerBookingItem.fromJson(Map<String, dynamic> json) {
    return TravSPassengerBookingItem(
      id: _readInt(json['id']),
      kodeBooking: json['kode_booking'] as String? ?? '-',
      status: json['status'] as String? ?? '-',
      paymentStatus: json['payment_status'] as String? ?? '-',
      totalHarga: _readDouble(json['total_harga']),
      rute: json['rute'] as String? ?? '-',
      waktuBerangkat: json['waktu_berangkat'] as String? ?? '',
      jumlahTiket: _readInt(json['jumlah_tiket']),
    );
  }
}

class TravSDriverDashboard {
  const TravSDriverDashboard({
    required this.jadwalAktif,
    required this.penumpangTerdaftar,
    required this.upcomingTrips,
  });

  final int jadwalAktif;
  final int penumpangTerdaftar;
  final List<TravSDriverTripItem> upcomingTrips;

  factory TravSDriverDashboard.fromJson(Map<String, dynamic> json) {
    final summary = _asMap(json['summary']);
    return TravSDriverDashboard(
      jadwalAktif: _readInt(summary['jadwal_aktif']),
      penumpangTerdaftar: _readInt(summary['penumpang_terdaftar']),
      upcomingTrips: (json['upcoming_trips'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) =>
                TravSDriverTripItem.fromJson(item as Map<String, dynamic>),
          )
          .toList(),
    );
  }
}

class TravSDriverTripItem {
  const TravSDriverTripItem({
    required this.id,
    required this.code,
    required this.rute,
    required this.waktuBerangkat,
    required this.kendaraan,
    required this.status,
    this.passengerCount = 0,
    this.revenue = 0,
    this.manifest = const [],
  });

  final int id;
  final String code;
  final String rute;
  final String waktuBerangkat;
  final String kendaraan;
  final String status;
  final int passengerCount;
  final double revenue;
  final List<TravSDriverManifestPassenger> manifest;

  factory TravSDriverTripItem.fromJson(Map<String, dynamic> json) {
    return TravSDriverTripItem(
      id: _readInt(json['id']),
      code: json['code'] as String? ?? 'DRV-${_readInt(json['id'])}',
      rute: json['rute'] as String? ?? '-',
      waktuBerangkat: json['waktu_berangkat'] as String? ?? '',
      kendaraan: json['kendaraan'] as String? ?? '-',
      status: json['status'] as String? ?? '-',
      passengerCount: _readInt(json['passenger_count']),
      revenue: _readDouble(json['revenue']),
      manifest: (json['manifest'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) => TravSDriverManifestPassenger.fromJson(
              item as Map<String, dynamic>,
            ),
          )
          .toList(),
    );
  }
}

class TravSDriverManifestPassenger {
  const TravSDriverManifestPassenger({
    required this.bookingId,
    required this.detailId,
    required this.bookingCode,
    required this.name,
    required this.seatNumber,
    required this.phone,
    required this.requesterName,
    required this.requesterEmail,
    required this.requesterPhone,
    this.requesterPhotoUrl,
    required this.pickupAddress,
    required this.baggageLabel,
    required this.fareLabel,
    required this.paymentStatus,
    required this.paymentMethod,
    required this.paymentChannel,
    required this.paymentLabel,
    required this.attendanceStatus,
    required this.isPickedUp,
    required this.isDroppedOff,
    this.droppedOffAt,
    required this.ticketCode,
    required this.ticketToken,
    required this.ticketScanned,
  });

  final int bookingId;
  final int detailId;
  final String bookingCode;
  final String name;
  final int seatNumber;
  final String phone;
  final String requesterName;
  final String requesterEmail;
  final String requesterPhone;
  final String? requesterPhotoUrl;
  final String pickupAddress;
  final String baggageLabel;
  final String fareLabel;
  final String paymentStatus;
  final String paymentMethod;
  final String paymentChannel;
  final String paymentLabel;
  final String attendanceStatus;
  final bool isPickedUp;
  final bool isDroppedOff;
  final String? droppedOffAt;
  final String ticketCode;
  final String ticketToken;
  final bool ticketScanned;

  factory TravSDriverManifestPassenger.fromJson(Map<String, dynamic> json) {
    final requester = _asMap(json['akun_pemesan']);
    final pickup = _asMap(json['pickup']);
    final dropoff = _asMap(json['dropoff']);
    final baggage = _asMap(json['baggage']);
    final fare = _asMap(json['fare']);
    final payment = _asMap(json['pembayaran']);
    final ticket = _asMap(json['ticket']);
    final paymentLabel =
        payment['label'] as String? ?? fare['label'] as String? ?? '-';

    return TravSDriverManifestPassenger(
      bookingId: _readInt(json['booking_id']),
      detailId: _readInt(json['detail_id']),
      bookingCode: json['kode_booking'] as String? ?? '-',
      name: json['nama_penumpang'] as String? ?? '-',
      seatNumber: _readInt(json['nomor_kursi']),
      phone: json['no_hp_penumpang'] as String? ?? '-',
      requesterName: requester['nama'] as String? ?? '-',
      requesterEmail: requester['email'] as String? ?? '-',
      requesterPhone: requester['no_hp'] as String? ?? '-',
      requesterPhotoUrl: requester['foto_url'] as String?,
      pickupAddress: pickup['address'] as String? ?? '-',
      baggageLabel: baggage['label'] as String? ?? '-',
      fareLabel: fare['label'] as String? ?? '-',
      paymentStatus: payment['status'] as String? ?? '-',
      paymentMethod: payment['metode'] as String? ?? '-',
      paymentChannel: payment['channel'] as String? ?? '-',
      paymentLabel: paymentLabel,
      attendanceStatus: json['status_kehadiran'] as String? ?? 'menunggu',
      isPickedUp: _readBool(pickup['is_picked_up']),
      isDroppedOff: _readBool(dropoff['is_dropped_off']),
      droppedOffAt: dropoff['dropped_off_at'] as String?,
      ticketCode: ticket['kode_qr'] as String? ?? '-',
      ticketToken: ticket['token'] as String? ?? '',
      ticketScanned: _readBool(ticket['sudah_dipindai']),
    );
  }
}

class TravSScanTicketResult {
  const TravSScanTicketResult({
    required this.valid,
    required this.scannedAt,
    required this.tripRoute,
    required this.passengerName,
    required this.seatNumber,
    required this.bookingCode,
  });

  final bool valid;
  final String scannedAt;
  final String tripRoute;
  final String passengerName;
  final int seatNumber;
  final String bookingCode;

  factory TravSScanTicketResult.fromJson(Map<String, dynamic> json) {
    final trip = _asMap(json['trip']);
    final passenger = _asMap(json['passenger']);

    return TravSScanTicketResult(
      valid: _readBool(json['valid']),
      scannedAt: json['scanned_at'] as String? ?? '',
      tripRoute: trip['rute'] as String? ?? '-',
      passengerName: passenger['nama_penumpang'] as String? ?? '-',
      seatNumber: _readInt(passenger['nomor_kursi']),
      bookingCode: passenger['kode_booking'] as String? ?? '-',
    );
  }

  List<String> get detailLines => [
    'Nama: $passengerName',
    'Rute: $tripRoute',
    'Kursi: No. ${seatNumber.toString().padLeft(2, '0')}',
    'Kode: $bookingCode',
  ];
}

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map<String, dynamic>) {
    return value;
  }

  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }

  return <String, dynamic>{};
}

int _readInt(dynamic value, {int fallback = 0}) {
  if (value == null) {
    return fallback;
  }

  if (value is int) {
    return value;
  }

  if (value is num) {
    return value.toInt();
  }

  return int.tryParse(value.toString()) ?? fallback;
}

double _readDouble(dynamic value, {double fallback = 0}) {
  if (value == null) {
    return fallback;
  }

  if (value is double) {
    return value;
  }

  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value.toString()) ?? fallback;
}

bool _readBool(dynamic value) {
  if (value is bool) {
    return value;
  }

  final raw = value?.toString().toLowerCase();
  return raw == 'true' || raw == '1';
}
