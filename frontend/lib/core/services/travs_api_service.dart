import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../models/travs_booking_contract.dart';
import '../models/app_settings.dart';
import '../models/travs_notification.dart';
import '../models/travs_user_session.dart';
import 'travs_validation_message.dart';

// Bagian besar dipisah per fungsi agar file utama lebih mudah dipelajari.
part 'model_api_travs/model_unggahan.dart';
part 'model_api_travs/model_beranda_dan_jadwal.dart';
part 'model_api_travs/model_pembayaran_dan_bagasi.dart';
part 'model_api_travs/model_penumpang.dart';
part 'model_api_travs/model_pengemudi.dart';

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
    required String keterangan,
    required XFile fotoBuktiNota,
  }) async {
    final proofBytes = await fotoBuktiNota.readAsBytes();
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
          'keterangan': keterangan.trim(),
        });
        request.files.add(
          http.MultipartFile.fromBytes(
            'foto_bukti_nota',
            proofBytes,
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

    throw const TravSApiException('Bukti biaya operasional gagal diunggah.');
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
      'Aplikasi belum terhubung ke layanan. Periksa koneksi, lalu coba lagi.',
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
