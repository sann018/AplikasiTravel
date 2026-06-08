import 'package:flutter/material.dart';

import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/services/travs_auth_service.dart';
import '../../core/theme/travs_theme.dart';

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
  String _loginRole = 'penumpang';
  String? _errorMessage;

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
      final session = _isRegisterMode
          ? await widget.authService.register(
              nama: _registerNameController.text,
              email: _registerEmailController.text,
              noHp: _registerPhoneController.text,
              password: _registerPasswordController.text,
              passwordConfirmation: _registerConfirmPasswordController.text,
            )
          : await widget.authService.login(
              login: _loginController.text,
              password: _loginPasswordController.text,
            );

      if (!mounted) {
        return;
      }

      if (!_isRegisterMode && session.user.peran != _loginRole) {
        await widget.authService.clearSession();
        throw TravSAuthException(
          _loginRole == 'pengemudi'
              ? 'Akun ini bukan akun pengemudi. Pilih Penumpang jika ingin masuk sebagai penumpang.'
              : 'Akun ini bukan akun penumpang. Pilih Pengemudi jika ingin masuk sebagai pengemudi.',
        );
      }

      widget.onAuthenticated(session);
    } on TravSAuthException catch (error) {
      if (!mounted) {
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
            'Proses masuk gagal. Backend lokal belum terhubung. Jika memakai HP fisik, jalankan adb reverse tcp:8000 tcp:8000 atau pakai IP laptop sebagai API_BASE_URL.';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
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
                      const SizedBox(height: 24),
                      _AuthHero(isRegisterMode: _isRegisterMode),
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
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      _isRegisterMode
                                          ? 'Daftar Akun Penumpang'
                                          : 'Masuk ke RuslyTravel',
                                      style: theme.textTheme.titleLarge
                                          ?.copyWith(
                                            color: TravSTheme.navy,
                                            fontWeight: FontWeight.w800,
                                          ),
                                    ),
                                  ),
                                  TextButton(
                                    onPressed: _isSubmitting
                                        ? null
                                        : _toggleMode,
                                    child: Text(
                                      _isRegisterMode ? 'Masuk' : 'Daftar',
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 6),
                              Text(
                                _isRegisterMode
                                    ? 'Pendaftaran mandiri dipakai untuk penumpang. Akun pengemudi dibuat admin agar data SIM dan kendaraan tetap valid.'
                                    : 'Pilih jenis akun terlebih dulu, lalu masuk sesuai peran yang terdaftar.',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: TravSTheme.navyMuted,
                                ),
                              ),
                              const SizedBox(height: 14),
                              if (_isRegisterMode)
                                _RoleAccessSummary(
                                  isRegisterMode: _isRegisterMode,
                                )
                              else
                                _LoginRolePicker(
                                  selectedRole: _loginRole,
                                  onChanged: (value) {
                                    setState(() {
                                      _loginRole = value;
                                      _errorMessage = null;
                                    });
                                  },
                                ),
                              const SizedBox(height: 18),
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
                                          : _loginRole == 'pengemudi'
                                          ? 'Masuk sebagai Pengemudi'
                                          : 'Masuk sebagai Penumpang',
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(height: 10),
                              OutlinedButton.icon(
                                onPressed: _isSubmitting
                                    ? null
                                    : widget.onContinueAsGuest,
                                icon: const Icon(Icons.visibility_outlined),
                                label: const Text('Lanjut Sebagai Tamu'),
                              ),
                              const SizedBox(height: 10),
                              Text(
                                _isRegisterMode
                                    ? 'Sudah punya akun? Gunakan tombol Masuk di kanan atas kartu ini.'
                                    : 'Belum punya akun? Tekan Daftar untuk membuat akun penumpang baru.',
                                textAlign: TextAlign.center,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: TravSTheme.navyMuted,
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

  /// Membuka halaman lupa password agar pengguna bisa mereset password.
  Future<void> _openForgotPassword() async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(builder: (_) => const _ForgotPasswordPage()),
    );

    if (result == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Password berhasil direset. Silakan login dengan password baru.',
          ),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
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
          hintText: 'nama@email.com',
          prefixIcon: Icon(Icons.mail_outline_rounded),
        ),
        validator: (value) {
          if (value == null || value.trim().isEmpty) {
            return 'Email wajib diisi.';
          }
          if (!value.contains('@')) {
            return 'Format email belum valid.';
          }
          return null;
        },
      ),
      const SizedBox(height: 14),
      TextFormField(
        controller: _registerPhoneController,
        keyboardType: TextInputType.phone,
        textInputAction: TextInputAction.next,
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
          if (value.trim().length < 10) {
            return 'Nomor HP minimal 10 digit.';
          }
          return null;
        },
      ),
      const SizedBox(height: 14),
      TextFormField(
        controller: _registerPasswordController,
        obscureText: _registerPasswordHidden,
        textInputAction: TextInputAction.next,
        style: const TextStyle(color: TravSTheme.navy),
        decoration: InputDecoration(
          labelText: 'Kata sandi',
          hintText: 'Minimal 8 karakter',
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
          if (value.length < 8) {
            return 'Kata sandi minimal 8 karakter.';
          }
          return null;
        },
      ),
      const SizedBox(height: 14),
      TextFormField(
        controller: _registerConfirmPasswordController,
        obscureText: _registerConfirmPasswordHidden,
        textInputAction: TextInputAction.done,
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

class _AuthHero extends StatelessWidget {
  const _AuthHero({required this.isRegisterMode});

  final bool isRegisterMode;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      children: [
        Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.center,
          children: [
            Container(
              width: 92,
              height: 92,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(30),
                border: Border.all(color: Colors.white24),
              ),
              child: const Icon(
                Icons.directions_bus_filled_rounded,
                color: Colors.white,
                size: 44,
              ),
            ),
            const Positioned(
              top: -2,
              right: -2,
              child: CircleAvatar(
                radius: 12,
                backgroundColor: TravSTheme.orange,
                child: Icon(Icons.place_rounded, size: 15, color: Colors.white),
              ),
            ),
          ],
        ),
        const SizedBox(height: 18),
        Text(
          'RuslyTravel',
          style: theme.textTheme.headlineSmall?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          isRegisterMode
              ? 'Daftar lebih cepat untuk mulai pemesanan travel, memilih kursi, dan menerima tiket digital.'
              : 'Masuk agar pemesanan, tiket QR, dan status layanan lebih tertata.',
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyMedium?.copyWith(color: Colors.white70),
        ),
      ],
    );
  }
}

class _RoleAccessSummary extends StatelessWidget {
  const _RoleAccessSummary({required this.isRegisterMode});

  final bool isRegisterMode;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Akses masuk',
            style: theme.textTheme.labelLarge?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _RoleAccessPill(
                label: 'Penumpang',
                icon: Icons.event_seat_outlined,
                active: true,
              ),
              _RoleAccessPill(
                label: 'Pengemudi',
                icon: Icons.directions_car_filled_rounded,
                active: !isRegisterMode,
              ),
              _RoleAccessPill(
                label: 'Tamu',
                icon: Icons.visibility_outlined,
                active: true,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _LoginRolePicker extends StatelessWidget {
  const _LoginRolePicker({
    required this.selectedRole,
    required this.onChanged,
  });

  final String selectedRole;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Row(
        children: [
          Expanded(
            child: _LoginRoleButton(
              label: 'Penumpang',
              icon: Icons.event_seat_outlined,
              selected: selectedRole == 'penumpang',
              onTap: () => onChanged('penumpang'),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _LoginRoleButton(
              label: 'Pengemudi',
              icon: Icons.directions_car_filled_rounded,
              selected: selectedRole == 'pengemudi',
              onTap: () => onChanged('pengemudi'),
            ),
          ),
        ],
      ),
    );
  }
}

class _LoginRoleButton extends StatelessWidget {
  const _LoginRoleButton({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
        decoration: BoxDecoration(
          color: selected ? TravSTheme.navy : Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? TravSTheme.navy : TravSTheme.line,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 17,
              color: selected ? Colors.white : TravSTheme.navyMuted,
            ),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                label,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.labelMedium?.copyWith(
                  color: selected ? Colors.white : TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RoleAccessPill extends StatelessWidget {
  const _RoleAccessPill({
    required this.label,
    required this.icon,
    required this.active,
  });

  final String label;
  final IconData icon;
  final bool active;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: active ? Colors.white : TravSTheme.surfaceMuted,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: active
              ? TravSTheme.orange.withValues(alpha: 0.36)
              : TravSTheme.line,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            color: active ? TravSTheme.orange : TravSTheme.navyMuted,
            size: 16,
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: theme.textTheme.labelMedium?.copyWith(
              color: active ? TravSTheme.navy : TravSTheme.navyMuted,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _InlineMessage extends StatelessWidget {
  const _InlineMessage({
    required this.message,
    required this.color,
    required this.backgroundColor,
    required this.icon,
  });

  final String message;
  final Color color;
  final Color backgroundColor;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: color.withValues(alpha: 0.18)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 18),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: theme.textTheme.bodySmall?.copyWith(
                color: color,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Halaman Lupa Password — dua langkah:
/// 1. Masukkan email untuk menerima token reset
/// 2. Masukkan token dan password baru untuk mereset
class _ForgotPasswordPage extends StatefulWidget {
  const _ForgotPasswordPage();

  @override
  State<_ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<_ForgotPasswordPage> {
  final TravSApiService _apiService = TravSApiService();
  final _formKey = GlobalKey<FormState>();

  // Controllers untuk step 1 (request token)
  final _emailController = TextEditingController();

  // Controllers untuk step 2 (reset password)
  final _tokenController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  bool _isStep2 = false;
  bool _isSubmitting = false;
  String? _errorMessage;
  String? _successMessage;
  bool _passwordHidden = true;

  @override
  void dispose() {
    _emailController.dispose();
    _tokenController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  /// Step 1: Request token reset via email
  Future<void> _requestToken() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
      _successMessage = null;
    });

    try {
      final token = await _apiService.forgotPassword(
        email: _emailController.text.trim(),
      );

      if (!mounted) return;

      setState(() {
        _isSubmitting = false;
        _isStep2 = true;
        _successMessage = 'Token reset telah dikirim ke email Anda.';
        // Untuk demo/testing, token diisi otomatis dari response
        if (token != null && token.isNotEmpty) {
          _tokenController.text = token;
        }
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
        _errorMessage = error is TravSApiException
            ? error.message
            : 'Gagal mengirim token reset. Periksa koneksi internet.';
      });
    }
  }

  /// Step 2: Reset password dengan token
  Future<void> _resetPassword() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
    });

    try {
      await _apiService.resetPassword(
        email: _emailController.text.trim(),
        token: _tokenController.text.trim(),
        password: _newPasswordController.text,
        passwordConfirmation: _confirmPasswordController.text,
      );

      if (!mounted) return;

      // Kembali ke halaman login dengan indikasi sukses
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
        _errorMessage = error is TravSApiException
            ? error.message
            : 'Gagal mereset password. Silakan coba lagi.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(_isStep2 ? 'Reset Password' : 'Lupa Password'),
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFFF9FBFE), Color(0xFFF0F4FA)],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Header
                  Icon(
                    _isStep2 ? Icons.lock_reset_rounded : Icons.email_outlined,
                    size: 56,
                    color: TravSTheme.navy,
                  ),
                  const SizedBox(height: 16),
                  Text(
                    _isStep2
                        ? 'Masukkan Token dan Password Baru'
                        : 'Masukkan Email Terdaftar',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _isStep2
                        ? 'Masukkan token yang diterima dan buat password baru minimal 8 karakter.'
                        : 'Kami akan mengirimkan token reset password ke email Anda.',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: TravSTheme.navyMuted,
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Error/Success messages
                  if (_errorMessage != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF1F1),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.error_outline,
                            color: TravSTheme.danger,
                            size: 20,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _errorMessage!,
                              style: const TextStyle(
                                color: TravSTheme.danger,
                                fontSize: 13,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                  ],
                  if (_successMessage != null && !_isStep2) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEDF7ED),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.check_circle_outline,
                            color: TravSTheme.success,
                            size: 20,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _successMessage!,
                              style: const TextStyle(
                                color: TravSTheme.success,
                                fontSize: 13,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                  ],

                  // Form fields
                  if (!_isStep2) ...[
                    // Step 1: Email input
                    TextFormField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.done,
                      style: const TextStyle(color: TravSTheme.navy),
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        hintText: 'Masukkan email yang terdaftar',
                        prefixIcon: Icon(Icons.mail_outline_rounded),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Email wajib diisi.';
                        }
                        if (!value.contains('@')) {
                          return 'Format email tidak valid.';
                        }
                        return null;
                      },
                      onFieldSubmitted: (_) =>
                          _isSubmitting ? null : _requestToken(),
                    ),
                  ] else ...[
                    // Step 2: Token + new password
                    TextFormField(
                      controller: _tokenController,
                      textInputAction: TextInputAction.next,
                      style: const TextStyle(color: TravSTheme.navy),
                      decoration: const InputDecoration(
                        labelText: 'Token Reset',
                        hintText: 'Masukkan token dari email',
                        prefixIcon: Icon(Icons.vpn_key_outlined),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Token reset wajib diisi.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _newPasswordController,
                      obscureText: _passwordHidden,
                      textInputAction: TextInputAction.next,
                      style: const TextStyle(color: TravSTheme.navy),
                      decoration: InputDecoration(
                        labelText: 'Password Baru',
                        hintText: 'Minimal 8 karakter',
                        prefixIcon: const Icon(Icons.lock_outline_rounded),
                        suffixIcon: IconButton(
                          onPressed: () => setState(
                            () => _passwordHidden = !_passwordHidden,
                          ),
                          icon: Icon(
                            _passwordHidden
                                ? Icons.visibility_off_outlined
                                : Icons.visibility_outlined,
                          ),
                        ),
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Password baru wajib diisi.';
                        }
                        if (value.length < 8) {
                          return 'Password minimal 8 karakter.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _confirmPasswordController,
                      obscureText: _passwordHidden,
                      textInputAction: TextInputAction.done,
                      style: const TextStyle(color: TravSTheme.navy),
                      decoration: const InputDecoration(
                        labelText: 'Konfirmasi Password',
                        hintText: 'Ulangi password baru',
                        prefixIcon: Icon(Icons.lock_outline_rounded),
                      ),
                      validator: (value) {
                        if (value != _newPasswordController.text) {
                          return 'Konfirmasi password tidak cocok.';
                        }
                        return null;
                      },
                      onFieldSubmitted: (_) =>
                          _isSubmitting ? null : _resetPassword(),
                    ),
                  ],

                  const SizedBox(height: 24),

                  // Submit button
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _isSubmitting
                          ? null
                          : _isStep2
                          ? _resetPassword
                          : _requestToken,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: TravSTheme.navy,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      child: Text(
                        _isSubmitting
                            ? 'Memproses...'
                            : _isStep2
                            ? 'Reset Password'
                            : 'Kirim Token Reset',
                      ),
                    ),
                  ),

                  if (_isStep2) ...[
                    const SizedBox(height: 12),
                    TextButton(
                      onPressed: _isSubmitting
                          ? null
                          : () => setState(() {
                              _isStep2 = false;
                              _errorMessage = null;
                            }),
                      child: const Text('Kembali ke langkah pertama'),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
