import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/app_settings.dart';
import 'travs_api_service.dart';

class SettingsApiService {
  SettingsApiService({TravSApiService? apiService})
    : _apiService = apiService ?? TravSApiService();

  static const String _cacheKey = 'ruslytravel_app_settings';
  static const String _cacheTimeKey = 'ruslytravel_app_settings_cached_at';
  static const Duration _cacheDuration = Duration(minutes: 15);

  final TravSApiService _apiService;

  Future<AppSettings> load({bool forceRefresh = false}) async {
    final cached = await _readCache();
    if (!forceRefresh && cached != null && !cached.isExpired) {
      return cached.settings;
    }

    try {
      final settings = await _apiService.loadMobileSettings();
      await _saveCache(settings);
      return settings;
    } catch (error) {
      // Cache lama masih lebih baik daripada membuat aplikasi berhenti saat backend sulit dijangkau.
      debugPrint('Pengaturan backend belum dapat dimuat: $error');
      return cached?.settings ?? AppSettings.defaults;
    }
  }

  Future<_CachedSettings?> _readCache() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_cacheKey);
      final cachedAt = prefs.getInt(_cacheTimeKey);
      if (raw == null || cachedAt == null) return null;

      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;

      return _CachedSettings(
        settings: AppSettings.fromJson(Map<String, dynamic>.from(decoded)),
        cachedAt: DateTime.fromMillisecondsSinceEpoch(cachedAt),
      );
    } catch (error) {
      debugPrint('Cache pengaturan tidak dapat dibaca: $error');
      return null;
    }
  }

  Future<void> _saveCache(AppSettings settings) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_cacheKey, jsonEncode(settings.toJson()));
      await prefs.setInt(_cacheTimeKey, DateTime.now().millisecondsSinceEpoch);
    } catch (error) {
      debugPrint('Cache pengaturan tidak dapat disimpan: $error');
    }
  }
}

class _CachedSettings {
  const _CachedSettings({required this.settings, required this.cachedAt});

  final AppSettings settings;
  final DateTime cachedAt;

  bool get isExpired =>
      DateTime.now().difference(cachedAt) > SettingsApiService._cacheDuration;
}
