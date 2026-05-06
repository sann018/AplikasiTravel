import 'package:flutter/material.dart';

import 'core/models/travs_user_session.dart';
import 'core/services/travs_auth_service.dart';
import 'core/theme/travs_theme.dart';
import 'features/auth/travs_auth_page.dart';
import 'features/home/travs_home_page.dart';
import 'features/launch/travs_splash_page.dart';

class TravSApp extends StatefulWidget {
  const TravSApp({super.key});

  @override
  State<TravSApp> createState() => _TravSAppState();
}

class _TravSAppState extends State<TravSApp> {
  final TravSAuthService _authService = TravSAuthService();

  bool _isBooting = true;
  bool _isGuestMode = false;
  TravSUserSession? _session;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    final session = await _authService.restoreSession();
    if (!mounted) {
      return;
    }

    setState(() {
      _session = session;
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
      title: 'TravS',
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
      return const TravSSplashPage();
    }

    if (_session != null || _isGuestMode) {
      return TravSHomePage(
        session: _session,
        onOpenAuth: _openAuth,
        onLogout: _logout,
      );
    }

    return TravSAuthPage(
      authService: _authService,
      onAuthenticated: _handleAuthenticated,
      onContinueAsGuest: _continueAsGuest,
    );
  }
}
