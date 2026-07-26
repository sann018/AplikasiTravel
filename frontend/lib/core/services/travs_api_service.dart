import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../models/travs_booking_contract.dart';
import '../models/app_settings.dart';
import '../models/travs_notification.dart';
import '../models/travs_user_session.dart';
import 'travs_validation_message.dart';

class TravSApiException implements Exception {
  const TravSApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

class TravSApiService {
  static const Duration _requestTimeout = Duration(seconds: 3);
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
        // Jika sesi lama menyimpan alamat server yang berbeda, pakai alamat API
        // yang sedang aktif supaya foto tetap terbaca di web dan HP.
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
    Duration timeout = _requestTimeout,
  }) {
    return _sendWithFallback(
      (baseUrl) =>
          http.post(Uri.parse('$baseUrl$path'), headers: headers, body: body),
      timeout: timeout,
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

  static Future<http.Response> deleteWithFallback(
    String path, {
    Map<String, String>? headers,
    Object? body,
  }) {
    return _sendWithFallback(
      (baseUrl) =>
          http.delete(Uri.parse('$baseUrl$path'), headers: headers, body: body),
    );
  }

  static Map<String, String> jsonHeaders({String? token}) {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  /// Mobile membaca aturan dari backend agar perubahan admin tidak perlu build ulang aplikasi.
  /// Nilai ini tetap hanya membantu UI; backend selalu melakukan validasi terakhir.
  Future<AppSettings> loadMobileSettings() async {
    final response = await getWithFallback(
      '/api/settings/mobile',
      headers: jsonHeaders(),
    );

    if (response.statusCode != 200) {
      throw TravSApiException(
        _extractErrorMessage(_tryDecodeMap(response.body)),
      );
    }

    final decoded = _decodeBody(response.body) as Map<String, dynamic>;
    final data = decoded['data'] as Map<String, dynamic>?;
    if (data == null) {
      throw const TravSApiException('Pengaturan aplikasi belum tersedia.');
    }

    return AppSettings.fromJson(data);
  }

  /// Memuat data awal beranda dan daftar jadwal dari backend.
  /// Jika gagal, kesalahan diteruskan agar halaman menampilkan pesan yang jelas.
  Future<TravSHomePayload> loadHomePayload() async {
    final responses = await Future.wait([
      getWithFallback('/api/mobile/bootstrap', headers: jsonHeaders()),
      getWithFallback('/api/guest/jadwal', headers: jsonHeaders()),
      getWithFallback('/api/home-banners', headers: jsonHeaders()),
    ]);
    final bootstrapResponse = responses[0];
    final scheduleResponse = responses[1];
    final bannerResponse = responses[2];

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
    final bannerJson = bannerResponse.statusCode == 200
        ? _decodeBody(bannerResponse.body) as Map<String, dynamic>
        : <String, dynamic>{};

    final bootstrapData =
        bootstrapJson['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
    final scheduleItems = scheduleJson['data'] as List<dynamic>? ?? <dynamic>[];
    final featuredItems =
        bootstrapData['featured_schedules'] as List<dynamic>? ?? <dynamic>[];
    final importantInformationItems =
        bootstrapData['important_information'] as List<dynamic>? ?? <dynamic>[];
    final bannerItems = bannerJson['data'] as List<dynamic>? ?? <dynamic>[];

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
      sourceLabel: 'Data tersedia',
      serviceWhatsapp: branding['service_whatsapp'] as String?,
      adminContact: branding['contact'] as String?,
      importantInformation: importantInformationItems
          .whereType<Map<String, dynamic>>()
          .map(TravSImportantInformation.fromJson)
          .where((item) => item.title.isNotEmpty && item.description.isNotEmpty)
          .toList(),
      banners: bannerItems
          .map((item) => TravSHomeBanner.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }

  Future<TravSPaymentMethods> loadPaymentMethods() async {
    final response = await getWithFallback(
      '/api/mobile/bootstrap',
      headers: jsonHeaders(),
    );

    return _decodeDataObject(
      response,
      (data) => TravSPaymentMethods.fromJson(_asMap(data['payment_methods'])),
    );
  }

  /// Memuat detail satu jadwal, termasuk kursi, bagasi, dan metode bayar.
  /// Kesalahan diteruskan agar halaman tidak diam saat data gagal dimuat.
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

  /// Memuat ringkasan beranda penumpang dari backend.
  /// Data palsu tidak dipakai agar status pemesanan tetap sesuai database.
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

  /// Memuat ringkasan tugas pengemudi dari backend.
  /// Kalau gagal, halaman menampilkan pesan daripada menebak data sendiri.
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

  /// Mengambil notifikasi milik akun yang sedang login.
  /// Status baca hanya dikirim saat halaman notifikasi benar-benar dibuka.
  Future<TravSNotificationFeed> loadNotifications({
    required String token,
    bool markAsRead = false,
  }) async {
    final suffix = markAsRead ? '?mark_as_read=1' : '';
    final response = await getWithFallback(
      '/api/notifications$suffix',
      headers: jsonHeaders(token: token),
    );

    return _decodeDataObject(
      response,
      (data) => TravSNotificationFeed.fromJson(data),
    );
  }

  /// Menghapus riwayat terbaca sesuai periode yang sudah divalidasi backend.
  Future<int> clearNotificationHistory({
    required String token,
    required String period,
  }) async {
    final response = await deleteWithFallback(
      '/api/notifications/history',
      headers: jsonHeaders(token: token),
      body: jsonEncode({'period': period}),
    );

    final data = _decodeDataObject<Map<String, dynamic>>(
      response,
      (value) => value,
    );
    return (data['deleted_count'] as num?)?.toInt() ?? 0;
  }

  Future<TravSDriverTripItem> loadDriverTripManifest({
    required String token,
    required int tripId,
  }) async {
    final response = await getWithFallback(
      '/api/pengemudi/trips/$tripId/manifest',
      headers: jsonHeaders(token: token),
    );

    return _decodeDataObject(
      response,
      (data) => TravSDriverTripItem.fromJson(data),
    );
  }

  Future<TravSDriverTripItem> updateDriverTripStatus({
    required String token,
    required int tripId,
    required String status,
  }) async {
    final response = await postWithFallback(
      '/api/pengemudi/trips/$tripId/status',
      headers: jsonHeaders(token: token),
      body: jsonEncode({'status': status}),
    );

    return _decodeDataObject(
      response,
      (data) => TravSDriverTripItem.fromJson(data),
    );
  }

  /// Memuat daftar pemesanan milik penumpang yang sedang login.
  /// Data harus dari backend supaya status bayar dan tiket selalu akurat.
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

  Future<void> releaseFormSeatLocks({
    required String token,
    required int scheduleId,
    required List<int> seatNumbers,
  }) async {
    await postWithFallback(
      '/api/penumpang/bookings/release-form-lock',
      headers: jsonHeaders(token: token),
      body: jsonEncode({'jadwal_id': scheduleId, 'seat_numbers': seatNumbers}),
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

  Future<TravSBookingResult> chooseBookingPaymentMethod({
    required String token,
    required int bookingId,
    required String paymentMethod,
  }) async {
    final response = await putWithFallback(
      '/api/penumpang/bookings/$bookingId/payment',
      headers: jsonHeaders(token: token),
      body: jsonEncode({'payment_method': paymentMethod}),
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
    String? nomorSim,
  }) async {
    final profileData = <String, String>{
      'nama': nama.trim(),
      'email': email.trim(),
      'no_hp': noHp.trim(),
    };
    if (nomorSim != null) {
      profileData['nomor_sim'] = nomorSim.trim();
    }

    final response = await putWithFallback(
      '/api/auth/profile',
      headers: jsonHeaders(token: token),
      body: jsonEncode(profileData),
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

  Future<TravSUploadedVehiclePhoto> uploadAssignedVehiclePhoto({
    required String token,
    required XFile photo,
  }) async {
    final bytes = await photo.readAsBytes();
    Object? lastError;

    for (final candidate in _orderedBaseUrls()) {
      try {
        final request = http.MultipartRequest(
          'POST',
          Uri.parse('$candidate/api/pengemudi/vehicle/photo'),
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
          (data) => TravSUploadedVehiclePhoto.fromJson(data),
        );
      } catch (error) {
        lastError = error;
      }
    }

    if (lastError != null) {
      throw lastError;
    }

    throw const TravSApiException('Foto kendaraan gagal diunggah.');
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

  Future<TravSDriverManifestPassenger> confirmDriverPassengerCash({
    required String token,
    required int tripId,
    required int detailId,
  }) async {
    final response = await postWithFallback(
      '/api/pengemudi/trips/$tripId/passengers/$detailId/confirm-cash',
      headers: jsonHeaders(token: token),
    );

    return _decodeDataObject(
      response,
      (data) => TravSDriverManifestPassenger.fromJson(data),
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

  Future<Map<String, dynamic>> submitDriverOperationalExpense({
    required String token,
    required int tripId,
    required String jenisBiaya,
    required num nominal,
    required DateTime tanggal,
    String? keterangan,
    required XFile fotoBuktiNota,
  }) async {
    Object? lastError;

    for (final candidate in _orderedBaseUrls()) {
      try {
        final request = http.MultipartRequest(
          'POST',
          Uri.parse('$candidate/api/pengemudi/trips/$tripId/expenses'),
        );
        request.headers.addAll({
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        });
        request.fields.addAll({
          'jenis_biaya': jenisBiaya.trim(),
          'nominal': nominal.toString(),
          'tanggal': tanggal.toIso8601String(),
          if (keterangan != null && keterangan.trim().isNotEmpty)
            'keterangan': keterangan.trim(),
        });

        final bytes = await fotoBuktiNota.readAsBytes();
        request.files.add(
          http.MultipartFile.fromBytes(
            'foto_bukti_nota',
            bytes,
            filename: fotoBuktiNota.name,
          ),
        );

        final streamed = await request.send().timeout(
          const Duration(seconds: 20),
        );
        final response = await http.Response.fromStream(streamed);
        _lastSuccessfulBaseUrl = candidate;

        return _decodeDataObject(response, (data) => data);
      } catch (error) {
        lastError = error;
      }
    }

    if (lastError != null) {
      throw lastError;
    }

    throw const TravSApiException(
      'Pencatatan biaya operasional gagal dikirim.',
    );
  }

  /// Meminta tautan reset tanpa membuka token sensitif ke aplikasi mobile.
  Future<String> forgotPassword({required String email}) async {
    final response = await postWithFallback(
      '/api/auth/forgot-password',
      headers: jsonHeaders(),
      timeout: const Duration(seconds: 20),
      body: jsonEncode({'email': email}),
    );

    final body = _tryDecodeMap(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw TravSApiException(_extractErrorMessage(body));
    }

    return body['message']?.toString() ??
        'Jika email terdaftar, tautan reset kata sandi telah dikirim.';
  }

  /// Mengirim token dan password baru untuk mengganti password akun.
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
      // Kalau isi balasan bukan JSON valid, pakai map kosong agar pesan tetap aman.
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
        if (value is String && value.isNotEmpty) {
          return _friendlyValidationMessage(fieldName, value);
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
    return travsFriendlyValidationMessage(fieldName, message);
  }

  static Future<http.Response> _sendWithFallback(
    Future<http.Response> Function(String baseUrl) request, {
    Duration timeout = _requestTimeout,
  }) async {
    Object? lastError;
    for (final candidate in _orderedBaseUrls()) {
      try {
        final response = await request(candidate).timeout(timeout);
        _lastSuccessfulBaseUrl = candidate;
        return response;
      } catch (error) {
        lastError = error;
      }
    }

    if (lastError != null) {
      throw lastError;
    }

    throw const TravSApiException(
      'Aplikasi belum terhubung ke server. Pastikan server aktif, lalu coba lagi.',
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

class TravSUploadedVehiclePhoto {
  const TravSUploadedVehiclePhoto({required this.path, required this.url});

  final String path;
  final String url;

  factory TravSUploadedVehiclePhoto.fromJson(Map<String, dynamic> json) {
    return TravSUploadedVehiclePhoto(
      path: json['foto_path'] as String? ?? '',
      url: json['foto_url'] as String? ?? '',
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
    this.importantInformation = const <TravSImportantInformation>[],
    this.banners = const <TravSHomeBanner>[],
  });

  final String appName;
  final List<TravSSchedule> featuredSchedules;
  final String sourceLabel;
  final String? serviceWhatsapp;
  final String? adminContact;
  final List<TravSImportantInformation> importantInformation;
  final List<TravSHomeBanner> banners;
}

class TravSImportantInformation {
  const TravSImportantInformation({
    required this.id,
    required this.title,
    required this.description,
  });

  final String id;
  final String title;
  final String description;

  factory TravSImportantInformation.fromJson(Map<String, dynamic> json) {
    return TravSImportantInformation(
      id: json['id'] as String? ?? '',
      title: (json['title'] as String? ?? '').trim(),
      description: (json['description'] as String? ?? '').trim(),
    );
  }
}

class TravSHomeBanner {
  const TravSHomeBanner({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.imageUrl,
  });

  final int id;
  final String title;
  final String subtitle;
  final String imageUrl;

  factory TravSHomeBanner.fromJson(Map<String, dynamic> json) {
    return TravSHomeBanner(
      id: _readInt(json['id']),
      title: json['judul'] as String? ?? '',
      subtitle: json['subjudul'] as String? ?? '',
      imageUrl: json['gambar_url'] as String? ?? '',
    );
  }
}

class TravSSchedule {
  const TravSSchedule({
    required this.id,
    required this.asal,
    required this.tujuan,
    required this.jalur,
    required this.pengemudi,
    required this.kendaraan,
    this.kendaraanFotoUrl,
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
  final String? kendaraanFotoUrl;
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
    String? kendaraanFotoUrl;
    if (kendaraanData is String) {
      kendaraan = kendaraanData;
    } else if (kendaraanData is Map) {
      final kendaraanMap = _asMap(kendaraanData);
      kendaraan =
          '${kendaraanMap['merk'] ?? '-'} - ${kendaraanMap['plat'] ?? '-'}';
      kendaraanFotoUrl = kendaraanMap['foto_url'] as String?;
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
      kendaraanFotoUrl: kendaraanFotoUrl,
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
    this.vehiclePhotoUrl,
    required this.driverLicenseNumber,
    this.driverPhotoUrl,
    required this.sourceLabel,
    required this.isLive,
  });

  final TravSSchedule schedule;
  final List<TravSSeatItem> seatMap;

  /// Susunan kursi dari admin untuk kendaraan ini.
  /// Contoh [1, 3, 2]: baris depan 1 kursi, tengah 3, belakang 2.
  /// Jika belum diatur, aplikasi membuat susunan sederhana dari jumlah kursi.
  final List<int> seatLayout;

  final TravSBookingRules bookingRules;
  final TravSPaymentMethods paymentMethods;
  final TravSBaggageRules baggageRules;
  final String vehicleFacilities;
  final String vehicleType;
  final String? vehiclePhotoUrl;
  final String driverLicenseNumber;
  final String? driverPhotoUrl;
  final String sourceLabel;
  final bool isLive;

  int get bookedSeatCount =>
      seatMap.where((seat) => seat.status == 'dipesan').length;

  int get lockedSeatCount =>
      seatMap.where((seat) => seat.status == 'terkunci').length;

  factory TravSScheduleDetail.fromJson(Map<String, dynamic> json) {
    final kendaraan = _asMap(json['kendaraan']);
    final pengemudi = _asMap(json['pengemudi']);

    // Ambil susunan kursi dari data kendaraan agar tampilan denah mengikuti armada.
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
      vehiclePhotoUrl: kendaraan['foto_url'] as String?,
      driverLicenseNumber: pengemudi['nomor_sim'] as String? ?? '-',
      driverPhotoUrl: pengemudi['foto_url'] as String?,
      sourceLabel: 'Data tersedia',
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
  });

  final int maxSeatPerBooking;
  final int seatLockFormMinutes;
  final int seatLockPaymentMinutes;

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
    );
  }
}

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
    this.assignedVehicle,
    this.assignedVehicleMessage,
  });

  final int jadwalAktif;
  final int penumpangTerdaftar;
  final List<TravSDriverTripItem> upcomingTrips;
  final TravSDriverAssignedVehicle? assignedVehicle;
  final String? assignedVehicleMessage;

  factory TravSDriverDashboard.fromJson(Map<String, dynamic> json) {
    final summary = _asMap(json['summary']);
    final assignedVehicleJson = _asMap(
      json['kendaraan_ditugaskan'] ?? json['assigned_vehicle'],
    );
    return TravSDriverDashboard(
      jadwalAktif: _readInt(summary['jadwal_aktif']),
      penumpangTerdaftar: _readInt(summary['penumpang_terdaftar']),
      upcomingTrips: (json['upcoming_trips'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) =>
                TravSDriverTripItem.fromJson(item as Map<String, dynamic>),
          )
          .toList(),
      assignedVehicle: assignedVehicleJson.isEmpty
          ? null
          : TravSDriverAssignedVehicle.fromJson(assignedVehicleJson),
      assignedVehicleMessage: json['kendaraan_ditugaskan_message'] as String?,
    );
  }
}

class TravSDriverAssignedVehicle {
  const TravSDriverAssignedVehicle({
    required this.scheduleId,
    required this.route,
    required this.departureAt,
    required this.estimatedArrivalAt,
    required this.plateNumber,
    required this.brand,
    required this.type,
    required this.color,
    required this.capacity,
    required this.photoUrl,
    required this.vehicleStatus,
    required this.scheduleStatus,
  });

  final int scheduleId;
  final String route;
  final String departureAt;
  final String estimatedArrivalAt;
  final String plateNumber;
  final String brand;
  final String type;
  final String color;
  final int capacity;
  final String? photoUrl;
  final String vehicleStatus;
  final String scheduleStatus;

  String get displayName {
    final parts = [
      brand,
      type,
      plateNumber,
    ].where((value) => value.trim().isNotEmpty && value != '-').toList();
    return parts.isEmpty ? 'Armada belum ditugaskan' : parts.join(' ');
  }

  factory TravSDriverAssignedVehicle.fromJson(Map<String, dynamic> json) {
    // Kendaraan dibaca dari object khusus jadwal, bukan dari profil pengemudi.
    return TravSDriverAssignedVehicle(
      scheduleId: _readInt(json['id_jadwal']),
      route: json['rute'] as String? ?? '-',
      departureAt: json['waktu_berangkat'] as String? ?? '',
      estimatedArrivalAt: json['estimasi_tiba'] as String? ?? '',
      plateNumber: json['nomor_plat'] as String? ?? '-',
      brand:
          json['merek'] as String? ?? json['merk_kendaraan'] as String? ?? '-',
      type: json['tipe'] as String? ?? json['tipe_kendaraan'] as String? ?? '-',
      color: json['warna'] as String? ?? '-',
      capacity: _readInt(json['kapasitas'] ?? json['kapasitas_kursi']),
      photoUrl:
          json['foto_url'] as String? ?? json['foto_kendaraan_url'] as String?,
      vehicleStatus: json['status_kendaraan'] as String? ?? '-',
      scheduleStatus: json['status_jadwal'] as String? ?? '-',
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
    required this.kendaraanNama,
    required this.nomorPlat,
    required this.kendaraanFotoUrl,
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
  final String kendaraanNama;
  final String nomorPlat;
  final String? kendaraanFotoUrl;
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
      kendaraanNama:
          json['kendaraan_nama'] as String? ??
          json['kendaraan'] as String? ??
          '-',
      nomorPlat: json['nomor_plat'] as String? ?? '-',
      kendaraanFotoUrl: json['kendaraan_foto_url'] as String?,
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
    required this.name,
    required this.accountName,
    required this.accountEmail,
    required this.accountPhone,
    required this.profilePhotoUrl,
    required this.seatNumber,
    required this.phone,
    required this.pickupAddress,
    required this.pickupLatitude,
    required this.pickupLongitude,
    required this.dropoffAddress,
    required this.dropoffLatitude,
    required this.dropoffLongitude,
    required this.baggageLabel,
    required this.baggagePhotoUrl,
    required this.baggagePhotoUrls,
    required this.fareLabel,
    required this.attendanceStatus,
    required this.bookingStatus,
    required this.paymentStatus,
    required this.paymentMethod,
    required this.paymentLabel,
    required this.canConfirmCash,
    required this.hasTicket,
    required this.ticketScanned,
    required this.isDroppedOff,
  });

  final int bookingId;
  final int detailId;
  final String name;
  final String accountName;
  final String accountEmail;
  final String accountPhone;
  final String? profilePhotoUrl;
  final int seatNumber;
  final String phone;
  final String pickupAddress;
  final double? pickupLatitude;
  final double? pickupLongitude;
  final String dropoffAddress;
  final double? dropoffLatitude;
  final double? dropoffLongitude;
  final String baggageLabel;
  final String? baggagePhotoUrl;
  final List<String> baggagePhotoUrls;
  final String fareLabel;
  final String attendanceStatus;
  final String bookingStatus;
  final String paymentStatus;
  final String paymentMethod;
  final String paymentLabel;
  final bool canConfirmCash;
  final bool hasTicket;
  final bool ticketScanned;
  final bool isDroppedOff;

  String get pickupLandmark => _friendlyLandmark(pickupAddress);
  String get dropoffLandmark => _friendlyLandmark(dropoffAddress);

  static String _friendlyLandmark(String value) {
    final landmark = value.trim();
    return landmark.isEmpty || landmark == '-'
        ? 'Belum diisi penumpang'
        : landmark;
  }

  factory TravSDriverManifestPassenger.fromJson(Map<String, dynamic> json) {
    final pickup = _asMap(json['pickup']);
    final baggage = _asMap(json['baggage']);
    final fare = _asMap(json['fare']);
    final payment = _asMap(json['pembayaran']);
    final ticket = _asMap(json['ticket']);
    final account = _asMap(json['akun_pemesan']);
    final dropoff = _asMap(json['dropoff']);
    final baggagePhotoUrls =
        (baggage['foto_urls'] as List<dynamic>? ?? <dynamic>[])
            .whereType<String>()
            .where((url) => url.trim().isNotEmpty)
            .toList();
    final legacyBaggagePhoto = baggage['foto_url'] as String?;
    if (legacyBaggagePhoto != null &&
        legacyBaggagePhoto.trim().isNotEmpty &&
        !baggagePhotoUrls.contains(legacyBaggagePhoto)) {
      baggagePhotoUrls.insert(0, legacyBaggagePhoto);
    }

    return TravSDriverManifestPassenger(
      bookingId: _readInt(json['booking_id']),
      detailId: _readInt(json['detail_id']),
      name: json['nama_penumpang'] as String? ?? '-',
      accountName: account['nama'] as String? ?? '-',
      accountEmail: account['email'] as String? ?? '-',
      accountPhone: account['no_hp'] as String? ?? '-',
      profilePhotoUrl: account['foto_url'] as String?,
      seatNumber: _readInt(json['nomor_kursi']),
      phone: json['no_hp_penumpang'] as String? ?? '-',
      pickupAddress: pickup['address'] as String? ?? '',
      pickupLatitude: _readNullableDouble(pickup['latitude']),
      pickupLongitude: _readNullableDouble(pickup['longitude']),
      dropoffAddress: dropoff['address'] as String? ?? '',
      dropoffLatitude: _readNullableDouble(dropoff['latitude']),
      dropoffLongitude: _readNullableDouble(dropoff['longitude']),
      baggageLabel: baggage['label'] as String? ?? '-',
      baggagePhotoUrl: baggagePhotoUrls.isNotEmpty
          ? baggagePhotoUrls.first
          : legacyBaggagePhoto,
      baggagePhotoUrls: baggagePhotoUrls.take(3).toList(),
      fareLabel: fare['label'] as String? ?? '-',
      attendanceStatus: json['status_kehadiran'] as String? ?? 'menunggu',
      bookingStatus: json['status_pemesanan'] as String? ?? '-',
      paymentStatus: payment['status'] as String? ?? '-',
      paymentMethod: payment['metode'] as String? ?? '-',
      paymentLabel:
          (payment['label'] as String?) ?? (fare['label'] as String?) ?? '-',
      canConfirmCash: _readBool(payment['can_confirm_cash']),
      hasTicket: ticket.isNotEmpty,
      ticketScanned: _readBool(ticket['sudah_dipindai']),
      isDroppedOff: _readBool(dropoff['is_dropped_off']),
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

String _joinIndonesian(List<String> values) {
  if (values.isEmpty) return '';
  if (values.length == 1) return values.first;
  if (values.length == 2) return '${values.first} dan ${values.last}';

  return '${values.sublist(0, values.length - 1).join(', ')}, dan ${values.last}';
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

double? _readNullableDouble(dynamic value) {
  if (value == null || value.toString().trim().isEmpty) {
    return null;
  }

  return double.tryParse(value.toString());
}

bool _readBool(dynamic value) {
  if (value is bool) {
    return value;
  }

  final raw = value?.toString().toLowerCase();
  return raw == 'true' || raw == '1';
}
