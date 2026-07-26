part of '../travs_auth_page.dart';

class _EmailVerificationPage extends StatefulWidget {
  const _EmailVerificationPage({
    required this.authService,
    required this.email,
    required this.onAuthenticated,
    this.initialMessage,
  });

  final TravSAuthService authService;
  final String email;
  final ValueChanged<TravSUserSession> onAuthenticated;
  final String? initialMessage;

  @override
  State<_EmailVerificationPage> createState() => _EmailVerificationPageState();
}

class _EmailVerificationPageState extends State<_EmailVerificationPage> {
  final _formKey = GlobalKey<FormState>();
  final _codeController = TextEditingController();

  Timer? _cooldownTimer;
  int _resendSeconds = 0;
  bool _isVerifying = false;
  bool _isResending = false;
  String? _errorMessage;
  String? _successMessage;

  @override
  void initState() {
    super.initState();
    _successMessage = widget.initialMessage;
  }

  @override
  void dispose() {
    _cooldownTimer?.cancel();
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _isVerifying = true;
      _errorMessage = null;
    });

    try {
      final session = await widget.authService.verifyEmail(
        email: widget.email,
        code: _codeController.text,
      );

      if (!mounted) return;
      Navigator.of(context).pop();
      widget.onAuthenticated(session);
    } on TravSAuthException catch (error) {
      if (!mounted) return;
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _errorMessage =
            'Verifikasi gagal. Periksa koneksi internet, lalu coba lagi.';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isVerifying = false;
        });
      }
    }
  }

  Future<void> _resend() async {
    if (_isResending || _resendSeconds > 0) return;

    setState(() {
      _isResending = true;
      _errorMessage = null;
    });

    try {
      final message = await widget.authService.resendVerificationCode(
        email: widget.email,
      );
      if (!mounted) return;

      _startResendCooldown();
      setState(() {
        _successMessage = message;
      });
    } on TravSAuthException catch (error) {
      if (!mounted) return;
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _errorMessage =
            'Kode belum dapat dikirim ulang. Periksa koneksi internet.';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isResending = false;
        });
      }
    }
  }

  void _startResendCooldown() {
    _cooldownTimer?.cancel();
    _resendSeconds = 60;
    _cooldownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }

      setState(() {
        _resendSeconds--;
        if (_resendSeconds <= 0) {
          timer.cancel();
        }
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Text('Verifikasi Email'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Icon(
                  Icons.mark_email_read_outlined,
                  size: 54,
                  color: TravSTheme.navy,
                ),
                const SizedBox(height: 16),
                Text(
                  'Masukkan Kode Verifikasi',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Kode enam digit dikirim ke ${widget.email}. Kode berlaku selama 15 menit.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
                const SizedBox(height: 20),
                if (_successMessage != null) ...[
                  _AuthMessageBox(
                    message: _successMessage!,
                    color: TravSTheme.success,
                    icon: Icons.check_circle_outline,
                  ),
                  const SizedBox(height: 14),
                ],
                if (_errorMessage != null) ...[
                  _AuthMessageBox(
                    message: _errorMessage!,
                    color: TravSTheme.danger,
                    icon: Icons.error_outline,
                  ),
                  const SizedBox(height: 14),
                ],
                TextFormField(
                  controller: _codeController,
                  keyboardType: TextInputType.number,
                  textInputAction: TextInputAction.done,
                  textAlign: TextAlign.center,
                  maxLength: 6,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  style: const TextStyle(
                    color: TravSTheme.navy,
                    fontSize: 22,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 8,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Kode verifikasi',
                    hintText: '000000',
                    counterText: '',
                  ),
                  validator: (value) {
                    if (value == null || value.length != 6) {
                      return 'Kode verifikasi harus terdiri dari 6 angka.';
                    }
                    return null;
                  },
                  onFieldSubmitted: (_) => _isVerifying ? null : _verify(),
                ),
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: _isVerifying ? null : _verify,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: TravSTheme.navy,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  child: Text(
                    _isVerifying ? 'Memverifikasi...' : 'Verifikasi Email',
                  ),
                ),
                const SizedBox(height: 10),
                TextButton(
                  onPressed: _isResending || _resendSeconds > 0
                      ? null
                      : _resend,
                  child: Text(
                    _resendSeconds > 0
                        ? 'Kirim ulang dalam $_resendSeconds detik'
                        : _isResending
                        ? 'Mengirim ulang...'
                        : 'Kirim Ulang Kode',
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
