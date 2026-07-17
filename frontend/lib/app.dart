import 'package:flutter/material.dart';

import 'core/models/travs_user_session.dart';
import 'core/models/app_settings.dart';
import 'core/services/settings_api_service.dart';
import 'core/services/travs_auth_service.dart';
import 'core/theme/travs_theme.dart';
import 'features/auth/travs_auth_page.dart';
import 'features/home/travs_home_page.dart';

class TravSApp extends StatefulWidget {
  const TravSApp({super.key});

  @override
  State<TravSApp> createState() => _TravSAppState();
}

class _TravSAppState extends State<TravSApp> {
  final TravSAuthService _authService = TravSAuthService();
  final SettingsApiService _settingsService = SettingsApiService();

  bool _isBooting = true;
  bool _isGuestMode = false;
  TravSUserSession? _session;
  AppSettings _appSettings = AppSettings.defaults;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    final sessionFuture = _authService.restoreSession();
    // Saat aplikasi dibuka, identitas seperti nama dan logo harus memakai versi terbaru dari admin.
    final settingsFuture = _settingsService.load(forceRefresh: true);
    final session = await sessionFuture;
    final settings = await settingsFuture;
    if (!mounted) {
      return;
    }

    setState(() {
      _session = session;
      _appSettings = settings;
      _isBooting = false;
      _isGuestMode = false;
    });
  }

  void _handleAuthenticated(TravSUserSession session) {
    setState(() {
      _session = session;
      _isGuestMode = false;
    });
  }

  void _continueAsGuest() {
    setState(() {
      _session = null;
      _isGuestMode = true;
    });
  }

  void _openAuth() {
    setState(() {
      _isGuestMode = false;
    });
  }

  Future<void> _logout() async {
    final token = _session?.token;
    if (token != null && token.isNotEmpty) {
      await _authService.logout(token);
    } else {
      await _authService.clearSession();
    }

    if (!mounted) {
      return;
    }

    setState(() {
      _session = null;
      _isGuestMode = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: _appSettings.appName,
      debugShowCheckedModeBanner: false,
      theme: TravSTheme.light(),
      home: AnimatedSwitcher(
        duration: const Duration(milliseconds: 240),
        child: _buildHome(),
      ),
    );
  }

  Widget _buildHome() {
    if (_isBooting) {
      // Splash native menangani layar awal. Flutter cukup menunggu pemulihan
      // sesi tanpa menampilkan splash kedua.
      return const Scaffold(body: ColoredBox(color: Color(0xFFF7F9FC)));
    }

    if (_session != null || _isGuestMode) {
      return TravSHomePage(
        session: _session,
        onOpenAuth: _openAuth,
        onLogout: _logout,
        onSessionUpdated: _handleAuthenticated,
      );
    }

    return TravSAuthPage(
      authService: _authService,
      onAuthenticated: _handleAuthenticated,
      onContinueAsGuest: _continueAsGuest,
    );
  }
}
