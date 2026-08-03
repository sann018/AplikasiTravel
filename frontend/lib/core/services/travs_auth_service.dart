import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../models/travs_user_session.dart';
import 'travs_api_service.dart';
import 'travs_validation_message.dart';

class TravSAuthException implements Exception {
  const TravSAuthException(
    this.message, {
    this.requiresEmailVerification = false,
    this.verificationEmail,
  });

  final String message;
  final bool requiresEmailVerification;
  final String? verificationEmail;

  @override
  String toString() => message;
}

class TravSRegistrationResult {
  const TravSRegistrationResult({required this.email, required this.message});

  final String email;
  final String message;
}

class TravSAuthService {
  // Pengiriman OTP melalui SMTP dapat lebih lama daripada request API biasa.
  static const Duration _emailRequestTimeout = Duration(seconds: 20);

  static const String _sessionKey = 'travs_user_session';

  Future<TravSUserSession?> restoreSession() async {
    final prefs = await SharedPreferences.getInstance();
    final encoded = prefs.getString(_sessionKey);
    if (encoded == null || encoded.isEmpty) {
      return null;
    }

    try {
      final decoded = jsonDecode(encoded) as Map<String, dynamic>;
      final session = TravSUserSession.fromJson(decoded);
      if (session.token.isEmpty || session.user.isAdmin) {
        await clearSession();
        return null;
      }

      return await _refreshStoredSession(session);
    } catch (_) {
      await clearSession();
      return null;
    }
  }

  Future<TravSUserSession> login({
    required String login,
    required String password,
  }) async {
    final response = await TravSApiService.postWithFallback(
      '/api/auth/login',
      headers: TravSApiService.jsonHeaders(),
      body: jsonEncode({
        'login': login.trim(),
        'password': password,
        'device_name': 'travelku-mobile',
      }),
    );

    return _handleAuthResponse(response);
  }

  Future<TravSRegistrationResult> register({
    required String nama,
    required String email,
    required String noHp,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await TravSApiService.postWithFallback(
      '/api/auth/register',
      headers: TravSApiService.jsonHeaders(),
      timeout: _emailRequestTimeout,
      body: jsonEncode({
        'nama': nama.trim(),
        'email': email.trim(),
        'no_hp': noHp.trim(),
        'password': password,
        'password_confirmation': passwordConfirmation,
        'device_name': 'travelku-mobile',
      }),
    );

    final decoded = _decodeBody(response.body);
    final data =
        decoded['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
    final registeredEmail = data['email']?.toString().trim() ?? '';
    final requiresVerification =
        data['requires_email_verification'] == true &&
        registeredEmail.isNotEmpty;

    // Akun bisa sudah tersimpan walaupun layanan email sedang gagal.
    // Pengguna tetap diarahkan ke halaman verifikasi agar dapat mengirim ulang.
    if (response.statusCode < 200 || response.statusCode >= 300) {
      if (!requiresVerification) {
        throw TravSAuthException(_extractErrorMessage(decoded));
      }
    }

    if (registeredEmail.isEmpty) {
      throw const TravSAuthException(
        'Email akun belum dapat dibaca. Silakan coba lagi.',
      );
    }

    return TravSRegistrationResult(
      email: registeredEmail,
      message:
          decoded['message']?.toString() ??
          'Kode verifikasi telah dikirim ke email Anda.',
    );
  }

  Future<TravSUserSession> verifyEmail({
    required String email,
    required String code,
  }) async {
    final response = await TravSApiService.postWithFallback(
      '/api/auth/verify-email',
      headers: TravSApiService.jsonHeaders(),
      body: jsonEncode({
        'email': email.trim(),
        'code': code.trim(),
        'device_name': 'travelku-mobile',
      }),
    );

    return _handleAuthResponse(response);
  }

  Future<String> resendVerificationCode({required String email}) async {
    final response = await TravSApiService.postWithFallback(
      '/api/auth/resend-verification-code',
      headers: TravSApiService.jsonHeaders(),
      timeout: _emailRequestTimeout,
      body: jsonEncode({'email': email.trim()}),
    );
    final decoded = _decodeBody(response.body);

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw TravSAuthException(_extractErrorMessage(decoded));
    }

    return decoded['message']?.toString() ??
        'Jika email terdaftar, kode verifikasi telah dikirim.';
  }

  Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_sessionKey);
  }

  Future<void> saveSession(TravSUserSession session) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_sessionKey, jsonEncode(session.toJson()));
  }

  Future<void> logout(String token) async {
    try {
      await TravSApiService.postWithFallback(
        '/api/auth/logout',
        headers: TravSApiService.jsonHeaders(token: token),
      );
    } catch (_) {
      // Walau logout di server gagal, sesi lokal tetap dibersihkan
      // supaya akun tidak terlihat masih aktif di aplikasi.
    } finally {
      await clearSession();
    }
  }

  Future<TravSUserSession> _handleAuthResponse(http.Response response) async {
    final decoded = _decodeBody(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final data =
          decoded['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
      throw TravSAuthException(
        _extractErrorMessage(decoded),
        requiresEmailVerification: data['requires_email_verification'] == true,
        verificationEmail: data['email']?.toString(),
      );
    }

    final data =
        decoded['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
    final session = TravSUserSession.fromJson(data);
    if (session.token.isEmpty) {
      throw const TravSAuthException(
        'Sesi masuk belum dapat dibuat. Silakan coba lagi.',
      );
    }

    if (session.user.isAdmin) {
      await _revokeUnsupportedAdminSession(session.token);
      throw const TravSAuthException(
        'Akun pengelola hanya dapat digunakan melalui dasbor pengelola.',
      );
    }

    await saveSession(session);
    return session;
  }

  Future<TravSUserSession?> _refreshStoredSession(
    TravSUserSession session,
  ) async {
    try {
      final response = await TravSApiService.getWithFallback(
        '/api/auth/me',
        headers: TravSApiService.jsonHeaders(token: session.token),
      );
      final decoded = _decodeBody(response.body);
      if (response.statusCode == 401) {
        await clearSession();
        return null;
      }

      if (response.statusCode >= 200 && response.statusCode < 300) {
        final data =
            decoded['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
        final refreshedSession = session.copyWith(
          user: TravSUserProfile.fromJson(data),
        );
        await saveSession(refreshedSession);
        return refreshedSession;
      }
    } catch (_) {
      // Kalau backend sedang belum bisa dihubungi, pakai sesi lama dulu
      // agar pengguna tidak langsung terlempar keluar aplikasi.
    }

    return session;
  }

  Future<void> _revokeUnsupportedAdminSession(String token) async {
    try {
      await TravSApiService.postWithFallback(
        '/api/auth/logout',
        headers: TravSApiService.jsonHeaders(token: token),
      );
    } catch (_) {
      // Kegagalan logout admin tidak perlu menghentikan pesan utama ke pengguna.
    }
  }

  Map<String, dynamic> _decodeBody(String body) {
    try {
      final decoded = jsonDecode(body);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
    } catch (_) {
      // Kalau body bukan JSON valid, balikan map kosong agar alur tetap aman.
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
}
