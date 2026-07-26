import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/services/travs_auth_service.dart';
import '../../core/theme/travs_theme.dart';

// Bagian besar dipisah per fungsi agar file utama lebih mudah dipelajari.
part 'bagian_autentikasi/widget_autentikasi.dart';
part 'bagian_autentikasi/verifikasi_email.dart';
part 'bagian_autentikasi/lupa_password.dart';
part 'bagian_autentikasi/kotak_pesan_autentikasi.dart';

class TravSAuthPage extends StatefulWidget {
  const TravSAuthPage({
    required this.authService,
    required this.onAuthenticated,
    required this.onContinueAsGuest,
    super.key,
  });

  final TravSAuthService authService;
  final ValueChanged<TravSUserSession> onAuthenticated;
  final VoidCallback onContinueAsGuest;

  @override
  State<TravSAuthPage> createState() => _TravSAuthPageState();
}

class _TravSAuthPageState extends State<TravSAuthPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  static const String _registerDraftKey = 'draft_register_penumpang';

  final TextEditingController _loginController = TextEditingController();
  final TextEditingController _loginPasswordController =
      TextEditingController();
  final TextEditingController _registerNameController = TextEditingController();
  final TextEditingController _registerEmailController =
      TextEditingController();
  final TextEditingController _registerPhoneController =
      TextEditingController();
  final TextEditingController _registerPasswordController =
      TextEditingController();
  final TextEditingController _registerConfirmPasswordController =
      TextEditingController();

  bool _isRegisterMode = false;
  bool _isSubmitting = false;
  bool _loginPasswordHidden = true;
  bool _registerPasswordHidden = true;
  bool _registerConfirmPasswordHidden = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadRegisterDraft();
    _registerNameController.addListener(_saveRegisterDraft);
    _registerEmailController.addListener(_saveRegisterDraft);
    _registerPhoneController.addListener(_saveRegisterDraft);
  }

  @override
  void dispose() {
    _loginController.dispose();
    _loginPasswordController.dispose();
    _registerNameController.dispose();
    _registerEmailController.dispose();
    _registerPhoneController.dispose();
    _registerPasswordController.dispose();
    _registerConfirmPasswordController.dispose();
    super.dispose();
  }

  /// Draft daftar akun hanya menyimpan identitas dasar.
  /// Password sengaja tidak ikut disimpan agar tetap aman.
  Future<void> _loadRegisterDraft() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final rawDraft = prefs.getString(_registerDraftKey);
      if (rawDraft == null || rawDraft.isEmpty || !mounted) return;

      final draft = jsonDecode(rawDraft);
      if (draft is! Map<String, dynamic>) return;

      _registerNameController.text =
          draft['nama'] as String? ?? _registerNameController.text;
      _registerEmailController.text =
          draft['email'] as String? ?? _registerEmailController.text;
      _registerPhoneController.text =
          draft['no_hp'] as String? ?? _registerPhoneController.text;
    } catch (_) {
      // Draft rusak cukup diabaikan supaya halaman daftar tetap bisa dipakai.
    }
  }

  Future<void> _saveRegisterDraft() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _registerDraftKey,
        jsonEncode({
          'nama': _registerNameController.text,
          'email': _registerEmailController.text,
          'no_hp': _registerPhoneController.text,
        }),
      );
    } catch (_) {
      // Draft hanya bantuan lokal, jadi kegagalan simpan tidak menghambat user.
    }
  }

  Future<void> _clearRegisterDraft() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_registerDraftKey);
    } catch (_) {
      // Jika gagal dibersihkan, proses register tetap mengikuti hasil server.
    }
  }

  Future<void> _submit() async {
    final form = _formKey.currentState;
    if (form == null || !form.validate()) {
      return;
    }

    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
    });

    try {
      if (_isRegisterMode) {
        final registration = await widget.authService.register(
          nama: _registerNameController.text,
          email: _registerEmailController.text,
          noHp: _registerPhoneController.text,
          password: _registerPasswordController.text,
          passwordConfirmation: _registerConfirmPasswordController.text,
        );
        await _clearRegisterDraft();

        if (!mounted) return;
        await _openEmailVerification(
          email: registration.email,
          initialMessage: registration.message,
        );
      } else {
        final session = await widget.authService.login(
          login: _loginController.text,
          password: _loginPasswordController.text,
        );

        if (!mounted) return;
        widget.onAuthenticated(session);
      }
    } on TravSAuthException catch (error) {
      if (!mounted) {
        return;
      }

      if (error.requiresEmailVerification) {
        await _openEmailVerification(
          email: error.verificationEmail?.trim().isNotEmpty == true
              ? error.verificationEmail!.trim()
              : _loginController.text.trim(),
          initialMessage: error.message,
        );
        return;
      }

      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _errorMessage =
            'Proses masuk gagal. Aplikasi belum terhubung ke server. Pastikan server aktif, lalu coba lagi.';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  Future<void> _openEmailVerification({
    required String email,
    String? initialMessage,
  }) async {
    if (email.isEmpty || !mounted) return;

    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => _EmailVerificationPage(
          authService: widget.authService,
          email: email,
          initialMessage: initialMessage,
          onAuthenticated: widget.onAuthenticated,
        ),
      ),
    );
  }

  void _toggleMode() {
    setState(() {
      _isRegisterMode = !_isRegisterMode;
      _errorMessage = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      resizeToAvoidBottomInset: false,
      body: Container(
        decoration: const BoxDecoration(gradient: TravSTheme.headerGradient),
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              return SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
                child: ConstrainedBox(
                  constraints: BoxConstraints(minHeight: constraints.maxHeight),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const SizedBox(height: 40),
                      // Sambutan di atas kartu form login. Diletakkan pada latar
                      // gradien gelap sehingga memakai warna putih agar kontras
                      // dan selaras dengan tema aplikasi.
                      Text(
                        'Selamat Datang di Platform Rusly Travel',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.headlineSmall?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Pemesanan Jasa Transportasi Umum Lintas Kota',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: Colors.white.withValues(alpha: 0.82),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 24),
                      Container(
                        padding: const EdgeInsets.fromLTRB(20, 22, 20, 18),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(30),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.08),
                              blurRadius: 30,
                              offset: const Offset(0, 18),
                            ),
                          ],
                        ),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(
                                _isRegisterMode
                                    ? 'Daftar Akun Penumpang'
                                    : 'Masuk ke Aplikasi Rusly Travel',
                                textAlign: TextAlign.center,
                                style: theme.textTheme.titleLarge?.copyWith(
                                  color: TravSTheme.navy,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 20),
                              if (_errorMessage != null) ...[
                                _InlineMessage(
                                  message: _errorMessage!,
                                  color: TravSTheme.danger,
                                  backgroundColor: const Color(0xFFFFF1F1),
                                  icon: Icons.error_outline_rounded,
                                ),
                                const SizedBox(height: 14),
                              ],
                              if (_isRegisterMode)
                                ..._buildRegisterFields()
                              else
                                ..._buildLoginFields(),
                              const SizedBox(height: 20),
                              SizedBox(
                                width: double.infinity,
                                child: ElevatedButton(
                                  onPressed: _isSubmitting ? null : _submit,
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: TravSTheme.navy,
                                  ),
                                  child: Padding(
                                    padding: const EdgeInsets.symmetric(
                                      vertical: 2,
                                    ),
                                    child: Text(
                                      _isSubmitting
                                          ? 'Memproses...'
                                          : _isRegisterMode
                                          ? 'Daftar Sekarang'
                                          : 'Masuk',
                                    ),
                                  ),
                                ),
                              ),
                              if (!_isRegisterMode) ...[
                                const SizedBox(height: 10),
                                OutlinedButton.icon(
                                  onPressed: _isSubmitting
                                      ? null
                                      : widget.onContinueAsGuest,
                                  icon: const Icon(Icons.visibility_outlined),
                                  label: const Text('Lanjut Sebagai Tamu'),
                                ),
                              ],
                              const SizedBox(height: 10),
                              Text(
                                _isRegisterMode
                                    ? 'Sudah punya akun?'
                                    : 'Belum punya akun?',
                                textAlign: TextAlign.center,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: TravSTheme.navyMuted,
                                ),
                              ),
                              TextButton(
                                onPressed: _isSubmitting ? null : _toggleMode,
                                child: Text(
                                  _isRegisterMode
                                      ? 'Kembali ke Masuk'
                                      : 'Daftar Akun Penumpang',
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  List<Widget> _buildLoginFields() {
    return [
      TextFormField(
        controller: _loginController,
        keyboardType: TextInputType.emailAddress,
        textInputAction: TextInputAction.next,
        style: const TextStyle(color: TravSTheme.navy),
        decoration: const InputDecoration(
          labelText: 'Email atau nomor HP',
          hintText: 'contoh: penumpang@travs.com',
          prefixIcon: Icon(Icons.alternate_email_rounded),
        ),
        validator: (value) {
          if (value == null || value.trim().isEmpty) {
            return 'Email atau nomor HP wajib diisi.';
          }
          return null;
        },
      ),
      const SizedBox(height: 14),
      TextFormField(
        controller: _loginPasswordController,
        obscureText: _loginPasswordHidden,
        style: const TextStyle(color: TravSTheme.navy),
        textInputAction: TextInputAction.done,
        decoration: InputDecoration(
          labelText: 'Kata sandi',
          hintText: 'Masukkan kata sandi',
          prefixIcon: const Icon(Icons.lock_outline_rounded),
          suffixIcon: IconButton(
            onPressed: () {
              setState(() {
                _loginPasswordHidden = !_loginPasswordHidden;
              });
            },
            icon: Icon(
              _loginPasswordHidden
                  ? Icons.visibility_off_outlined
                  : Icons.visibility_outlined,
            ),
          ),
        ),
        validator: (value) {
          if (value == null || value.isEmpty) {
            return 'Kata sandi wajib diisi.';
          }
          return null;
        },
        onFieldSubmitted: (_) => _isSubmitting ? null : _submit(),
      ),
      const SizedBox(height: 8),
      Align(
        alignment: Alignment.centerRight,
        child: TextButton(
          onPressed: _isSubmitting ? null : _openForgotPassword,
          child: const Text(
            'Lupa Password?',
            style: TextStyle(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    ];
  }

  /// Reset password diselesaikan melalui tautan email dari Laravel.
  Future<void> _openForgotPassword() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(builder: (_) => const _ForgotPasswordPage()),
    );
  }

  List<Widget> _buildRegisterFields() {
    return [
      TextFormField(
        controller: _registerNameController,
        textInputAction: TextInputAction.next,
        style: const TextStyle(color: TravSTheme.navy),
        decoration: const InputDecoration(
          labelText: 'Nama lengkap',
          hintText: 'Masukkan nama lengkap',
          prefixIcon: Icon(Icons.person_outline_rounded),
        ),
        validator: (value) {
          if (value == null || value.trim().isEmpty) {
            return 'Nama lengkap wajib diisi.';
          }
          return null;
        },
      ),
      const SizedBox(height: 14),
      TextFormField(
        controller: _registerEmailController,
        keyboardType: TextInputType.emailAddress,
        textInputAction: TextInputAction.next,
        style: const TextStyle(color: TravSTheme.navy),
        decoration: const InputDecoration(
          labelText: 'Email',
          hintText: 'nama@gmail.com',
          prefixIcon: Icon(Icons.mail_outline_rounded),
        ),
        validator: (value) {
          if (value == null || value.trim().isEmpty) {
            return 'Email wajib diisi.';
          }
          if (!RegExp(
            r'^[^@\s]+@gmail\.com$',
            caseSensitive: false,
          ).hasMatch(value.trim())) {
            return 'Email wajib menggunakan alamat @gmail.com.';
          }
          return null;
        },
      ),
      const SizedBox(height: 14),
      TextFormField(
        controller: _registerPhoneController,
        keyboardType: TextInputType.number,
        textInputAction: TextInputAction.next,
        inputFormatters: [
          FilteringTextInputFormatter.digitsOnly,
          LengthLimitingTextInputFormatter(12),
        ],
        style: const TextStyle(color: TravSTheme.navy),
        decoration: const InputDecoration(
          labelText: 'Nomor HP',
          hintText: '08xxxxxxxxxx',
          prefixIcon: Icon(Icons.phone_outlined),
        ),
        validator: (value) {
          if (value == null || value.trim().isEmpty) {
            return 'Nomor HP wajib diisi.';
          }
          final phone = value.trim();
          if (phone.length < 11 || phone.length > 12) {
            return 'Nomor HP harus terdiri dari 11 sampai 12 digit.';
          }
          return null;
        },
      ),
      const SizedBox(height: 14),
      TextFormField(
        controller: _registerPasswordController,
        obscureText: _registerPasswordHidden,
        textInputAction: TextInputAction.next,
        inputFormatters: [LengthLimitingTextInputFormatter(8)],
        style: const TextStyle(color: TravSTheme.navy),
        decoration: InputDecoration(
          labelText: 'Kata sandi',
          hintText: '6-8 karakter, huruf + angka/simbol',
          prefixIcon: const Icon(Icons.lock_outline_rounded),
          suffixIcon: IconButton(
            onPressed: () {
              setState(() {
                _registerPasswordHidden = !_registerPasswordHidden;
              });
            },
            icon: Icon(
              _registerPasswordHidden
                  ? Icons.visibility_off_outlined
                  : Icons.visibility_outlined,
            ),
          ),
        ),
        validator: (value) {
          if (value == null || value.isEmpty) {
            return 'Kata sandi wajib diisi.';
          }
          if (value.length < 6 || value.length > 8) {
            return 'Kata sandi harus terdiri dari 6 sampai 8 karakter.';
          }
          final hasLetter = RegExp(r'[A-Za-z]').hasMatch(value);
          final hasNumberOrSymbol = RegExp(
            r'[0-9]|[^A-Za-z0-9\s]',
          ).hasMatch(value);
          if (!hasLetter || !hasNumberOrSymbol) {
            return 'Gunakan huruf dan minimal satu angka atau simbol.';
          }
          return null;
        },
      ),
      const SizedBox(height: 14),
      TextFormField(
        controller: _registerConfirmPasswordController,
        obscureText: _registerConfirmPasswordHidden,
        textInputAction: TextInputAction.done,
        inputFormatters: [LengthLimitingTextInputFormatter(8)],
        style: const TextStyle(color: TravSTheme.navy),
        decoration: InputDecoration(
          labelText: 'Konfirmasi kata sandi',
          hintText: 'Ulangi kata sandi',
          prefixIcon: const Icon(Icons.verified_user_outlined),
          suffixIcon: IconButton(
            onPressed: () {
              setState(() {
                _registerConfirmPasswordHidden =
                    !_registerConfirmPasswordHidden;
              });
            },
            icon: Icon(
              _registerConfirmPasswordHidden
                  ? Icons.visibility_off_outlined
                  : Icons.visibility_outlined,
            ),
          ),
        ),
        validator: (value) {
          if (value == null || value.isEmpty) {
            return 'Konfirmasi kata sandi wajib diisi.';
          }
          if (value != _registerPasswordController.text) {
            return 'Konfirmasi kata sandi belum sama.';
          }
          return null;
        },
        onFieldSubmitted: (_) => _isSubmitting ? null : _submit(),
      ),
    ];
  }
}
