import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../models/travs_user_session.dart';
import 'travs_api_service.dart';

class TravSAuthException implements Exception {
  const TravSAuthException(this.message);

  final String message;

  @override
  String toString() => message;
}

class TravSAuthService {
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

      return session;
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

  Future<TravSUserSession> register({
    required String nama,
    required String email,
    required String noHp,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await TravSApiService.postWithFallback(
      '/api/auth/register',
      headers: TravSApiService.jsonHeaders(),
      body: jsonEncode({
        'nama': nama.trim(),
        'email': email.trim(),
        'no_hp': noHp.trim(),
        'password': password,
        'password_confirmation': passwordConfirmation,
        'device_name': 'travelku-mobile',
      }),
    );

    return _handleAuthResponse(response);
  }

  Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_sessionKey);
  }

  Future<void> logout(String token) async {
    try {
      await TravSApiService.postWithFallback(
        '/api/auth/logout',
        headers: TravSApiService.jsonHeaders(token: token),
      );
    } catch (_) {
      // Best effort only. Local session tetap dihapus.
    } finally {
      await clearSession();
    }
  }

  Future<TravSUserSession> _handleAuthResponse(http.Response response) async {
    final decoded = _decodeBody(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw TravSAuthException(_extractErrorMessage(decoded));
    }

    final data =
        decoded['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
    final session = TravSUserSession.fromJson(data);
    if (session.token.isEmpty) {
      throw const TravSAuthException('Token login tidak diterima dari server.');
    }

    if (session.user.isAdmin) {
      await _revokeUnsupportedAdminSession(session.token);
      throw const TravSAuthException(
        'Akun admin dikelola lewat web admin, bukan lewat aplikasi mobile.',
      );
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_sessionKey, jsonEncode(session.toJson()));
    return session;
  }

  Future<void> _revokeUnsupportedAdminSession(String token) async {
    try {
      await TravSApiService.postWithFallback(
        '/api/auth/logout',
        headers: TravSApiService.jsonHeaders(token: token),
      );
    } catch (_) {
      // Tidak memblokir UX login mobile.
    }
  }

  Map<String, dynamic> _decodeBody(String body) {
    try {
      final decoded = jsonDecode(body);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
    } catch (_) {
      // Balikkan fallback di bawah.
    }

    return <String, dynamic>{};
  }

  String _extractErrorMessage(Map<String, dynamic> body) {
    final errors = body['errors'];
    if (errors is Map) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty) {
          final message = value.first?.toString();
          if (message != null && message.isNotEmpty) {
            return message;
          }
        }
      }
    }

    final message = body['message']?.toString();
    if (message != null && message.isNotEmpty) {
      return message;
    }

    return 'Permintaan tidak dapat diproses. Silakan coba lagi.';
  }
}
