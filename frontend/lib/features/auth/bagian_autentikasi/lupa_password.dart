part of '../travs_auth_page.dart';

class _ForgotPasswordPage extends StatefulWidget {
  const _ForgotPasswordPage();

  @override
  State<_ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<_ForgotPasswordPage> {
  final TravSApiService _apiService = TravSApiService();
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();

  bool _isSubmitting = false;
  String? _errorMessage;
  String? _successMessage;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _sendResetLink() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
      _successMessage = null;
    });

    try {
      final message = await _apiService.forgotPassword(
        email: _emailController.text.trim(),
      );
      if (!mounted) return;

      setState(() {
        _successMessage = message;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _errorMessage = error is TravSApiException
            ? error.message
            : 'Tautan reset belum dapat dikirim. Periksa koneksi internet.';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
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
        title: const Text('Lupa Kata Sandi'),
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
                  Icons.lock_reset_outlined,
                  size: 54,
                  color: TravSTheme.navy,
                ),
                const SizedBox(height: 16),
                Text(
                  'Atur Ulang Kata Sandi',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Masukkan email akun. Jika email terdaftar, tautan untuk membuat kata sandi baru akan dikirim.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
                const SizedBox(height: 22),
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
                    // Format lengkap mencegah email tidak valid dikirim ke backend.
                    if (!RegExp(
                      r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                    ).hasMatch(value.trim())) {
                      return 'Format email tidak valid.';
                    }
                    return null;
                  },
                  onFieldSubmitted: (_) =>
                      _isSubmitting ? null : _sendResetLink(),
                ),
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: _isSubmitting ? null : _sendResetLink,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: TravSTheme.navy,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  child: Text(
                    _isSubmitting ? 'Mengirim...' : 'Kirim Tautan Reset',
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
