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
            'Proses autentikasi gagal. Pastikan backend berjalan di ${TravSApiService.backendTargetsLabel}. Untuk HP fisik gunakan USB + adb reverse atau API_BASE_URL ke IP laptop.';
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
                                          : 'Masuk ke TravS',
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
                                    : 'Gunakan akun penumpang atau pengemudi agar pemesanan, tiket, dan status layanan tetap sinkron.',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: TravSTheme.navyMuted,
                                ),
                              ),
                              const SizedBox(height: 14),
                              _RoleAccessSummary(
                                isRegisterMode: _isRegisterMode,
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
                                          : 'Masuk',
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
    ];
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
          'TravS',
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
