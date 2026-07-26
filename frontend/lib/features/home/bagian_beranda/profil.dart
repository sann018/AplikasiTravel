part of '../travs_home_page.dart';

class _EditProfilePage extends StatefulWidget {
  const _EditProfilePage({
    required this.session,
    required this.apiService,
    required this.authService,
  });

  final TravSUserSession session;
  final TravSApiService apiService;
  final TravSAuthService authService;

  @override
  State<_EditProfilePage> createState() => _EditProfilePageState();
}

class _EditProfilePageState extends State<_EditProfilePage> {
  final _formKey = GlobalKey<FormState>();
  final _imagePicker = ImagePicker();
  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  late final TextEditingController _phoneController;
  late final TextEditingController _driverLicenseController;
  final TextEditingController _currentPasswordController =
      TextEditingController();
  final TextEditingController _newPasswordController = TextEditingController();
  final TextEditingController _confirmPasswordController =
      TextEditingController();
  XFile? _selectedPhoto;
  Uint8List? _selectedPhotoBytes;
  bool _isSaving = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    final user = widget.session.user;
    _nameController = TextEditingController(text: user.nama);
    _emailController = TextEditingController(text: user.email);
    _phoneController = TextEditingController(text: user.noHp);
    _driverLicenseController = TextEditingController(text: user.nomorSim ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _driverLicenseController.dispose();
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _selectPhoto(ImageSource source) async {
    try {
      final photo = await _imagePicker.pickImage(
        source: source,
        imageQuality: 82,
        maxWidth: 1280,
        maxHeight: 1280,
      );

      if (photo == null || !mounted) {
        return;
      }

      final bytes = await photo.readAsBytes();

      setState(() {
        _selectedPhoto = photo;
        _selectedPhotoBytes = bytes;
        _errorMessage = null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _errorMessage =
            'Foto profil belum bisa dipilih. Coba gunakan galeri atau kamera lain.';
      });
    }
  }

  Future<void> _saveProfile() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _isSaving = true;
      _errorMessage = null;
    });

    try {
      String? uploadedProfilePath;
      if (_selectedPhoto != null) {
        final uploadedPhoto = await widget.apiService.uploadProfilePhoto(
          token: widget.session.token,
          photo: _selectedPhoto!,
        );
        uploadedProfilePath = uploadedPhoto.url.isNotEmpty
            ? '${uploadedPhoto.url}?v=${DateTime.now().millisecondsSinceEpoch}'
            : uploadedPhoto.path;
      }

      await widget.apiService.updateProfile(
        token: widget.session.token,
        nama: _nameController.text,
        email: _emailController.text,
        noHp: _phoneController.text,
        nomorSim: widget.session.user.isDriver
            ? _driverLicenseController.text
            : null,
      );

      final shouldChangePassword =
          _currentPasswordController.text.trim().isNotEmpty ||
          _newPasswordController.text.trim().isNotEmpty ||
          _confirmPasswordController.text.trim().isNotEmpty;
      if (shouldChangePassword) {
        await widget.apiService.changePassword(
          token: widget.session.token,
          currentPassword: _currentPasswordController.text,
          password: _newPasswordController.text,
          passwordConfirmation: _confirmPasswordController.text,
        );
      }

      var updatedUser = await widget.apiService.loadCurrentUserProfile(
        token: widget.session.token,
      );
      if (uploadedProfilePath != null && uploadedProfilePath.isNotEmpty) {
        updatedUser = updatedUser.copyWith(fotoProfil: uploadedProfilePath);
      }

      final updatedSession = widget.session.copyWith(user: updatedUser);
      await widget.authService.saveSession(updatedSession);

      if (!mounted) {
        return;
      }

      Navigator.of(context).pop(updatedSession);
    } on TravSApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _errorMessage =
            'Profil belum bisa disimpan. Periksa koneksi lalu coba lagi.';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isSaving = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final user = widget.session.user;
    final avatarUrl = TravSApiService.resolveMediaUrl(user.fotoProfil);
    final photoName = _selectedPhoto?.name;

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Text('Edit Profil'),
      ),
      body: Container(
        decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
        child: SafeArea(
          child: Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 28),
              children: [
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(color: TravSTheme.line),
                  ),
                  child: Column(
                    children: [
                      _ProfileAvatarPreview(
                        name: user.nama,
                        imageBytes: _selectedPhotoBytes,
                        imageUrl: avatarUrl,
                        radius: 44,
                        onTap: _selectedPhotoBytes != null || avatarUrl == null
                            ? null
                            : () => _showTravSImagePreview(
                                context,
                                imageUrl: avatarUrl,
                                title: user.nama,
                              ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        photoName == null
                            ? 'Pilih foto profil dari kamera atau galeri.'
                            : 'Dipilih: $photoName',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: TravSTheme.navyMuted,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _isSaving
                                  ? null
                                  : () => _selectPhoto(ImageSource.camera),
                              icon: const Icon(Icons.photo_camera_outlined),
                              label: const Text('Kamera'),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _isSaving
                                  ? null
                                  : () => _selectPhoto(ImageSource.gallery),
                              icon: const Icon(Icons.photo_library_outlined),
                              label: const Text('Galeri'),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                _SectionCard(
                  title: 'Data Akun',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const _ProfileFieldLabel('Nama lengkap'),
                      TextFormField(
                        controller: _nameController,
                        textCapitalization: TextCapitalization.words,
                        decoration: _profileInputDecoration(
                          hint: 'Masukkan nama lengkap',
                          icon: Icons.person_outline_rounded,
                        ),
                        validator: (value) =>
                            _validateRequiredText(value, 'Nama lengkap'),
                      ),
                      const SizedBox(height: 12),
                      const _ProfileFieldLabel('Email'),
                      TextFormField(
                        controller: _emailController,
                        keyboardType: TextInputType.emailAddress,
                        decoration: _profileInputDecoration(
                          hint: 'contoh@gmail.com',
                          icon: Icons.mail_outline_rounded,
                        ),
                        validator: _validateGmailEmail,
                      ),
                      const SizedBox(height: 12),
                      _ProfileFieldLabel(
                        user.isDriver ? 'Nomor HP pengemudi' : 'Nomor HP',
                      ),
                      TextFormField(
                        controller: _phoneController,
                        keyboardType: TextInputType.number,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                          LengthLimitingTextInputFormatter(12),
                        ],
                        decoration: _profileInputDecoration(
                          hint: '081234567891',
                          icon: Icons.phone_outlined,
                        ),
                        validator: _validatePhoneNumber,
                      ),
                    ],
                  ),
                ),
                if (user.isDriver) ...[
                  const SizedBox(height: 14),
                  _SectionCard(
                    title: 'Data Pengemudi',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const _ProfileFieldLabel('Nomor SIM (NIK KTP)'),
                        TextFormField(
                          controller: _driverLicenseController,
                          keyboardType: TextInputType.number,
                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly,
                            LengthLimitingTextInputFormatter(16),
                          ],
                          decoration: _profileInputDecoration(
                            hint: 'Masukkan 16 digit NIK KTP',
                            icon: Icons.badge_outlined,
                          ),
                          validator: _validateDriverLicense,
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                _SectionCard(
                  title: 'Ganti Password',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const _ProfileFieldLabel('Password lama'),
                      TextFormField(
                        controller: _currentPasswordController,
                        obscureText: true,
                        decoration: _profileInputDecoration(
                          hint: 'Password lama',
                          icon: Icons.lock_outline_rounded,
                        ),
                        validator: _validateOptionalPasswordGroup,
                      ),
                      const SizedBox(height: 12),
                      const _ProfileFieldLabel('Password baru'),
                      TextFormField(
                        controller: _newPasswordController,
                        obscureText: true,
                        inputFormatters: [LengthLimitingTextInputFormatter(8)],
                        decoration: _profileInputDecoration(
                          hint: '6-8 karakter, huruf + angka/simbol',
                          icon: Icons.lock_reset_rounded,
                        ),
                        validator: _validateNewPassword,
                      ),
                      const SizedBox(height: 12),
                      const _ProfileFieldLabel('Konfirmasi password baru'),
                      TextFormField(
                        controller: _confirmPasswordController,
                        obscureText: true,
                        inputFormatters: [LengthLimitingTextInputFormatter(8)],
                        decoration: _profileInputDecoration(
                          hint: 'Konfirmasi password baru',
                          icon: Icons.verified_user_outlined,
                        ),
                        validator: _validateConfirmPassword,
                      ),
                    ],
                  ),
                ),
                if (_errorMessage != null) ...[
                  const SizedBox(height: 14),
                  _ErrorBanner(message: _errorMessage!),
                ],
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _isSaving ? null : _saveProfile,
                  icon: _isSaving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.save_outlined),
                  label: Text(_isSaving ? 'Menyimpan...' : 'Simpan Profil'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  InputDecoration _profileInputDecoration({
    required String hint,
    required IconData icon,
  }) {
    return InputDecoration(
      hintText: hint,
      prefixIcon: Icon(icon),
      filled: true,
      fillColor: Colors.white,
      floatingLabelBehavior: FloatingLabelBehavior.never,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: TravSTheme.line),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: TravSTheme.line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: TravSTheme.orange, width: 1.4),
      ),
    );
  }

  String? _validateOptionalPasswordGroup(String? value) {
    final shouldChangePassword =
        value?.trim().isNotEmpty == true ||
        _newPasswordController.text.trim().isNotEmpty ||
        _confirmPasswordController.text.trim().isNotEmpty;
    if (shouldChangePassword && (value == null || value.trim().isEmpty)) {
      return 'Password lama wajib diisi.';
    }
    return null;
  }

  String? _validateDriverLicense(String? value) {
    final licenseNumber = value?.trim() ?? '';
    if (licenseNumber.isEmpty) {
      return 'Nomor SIM wajib diisi.';
    }
    if (licenseNumber.length != 16) {
      return 'Nomor SIM harus menggunakan NIK KTP sebanyak 16 digit.';
    }
    return null;
  }

  String? _validateNewPassword(String? value) {
    final shouldChangePassword =
        _currentPasswordController.text.trim().isNotEmpty ||
        value?.trim().isNotEmpty == true ||
        _confirmPasswordController.text.trim().isNotEmpty;
    if (!shouldChangePassword) {
      return null;
    }
    if (value == null || value.length < 6 || value.length > 8) {
      return 'Password baru harus terdiri dari 6 sampai 8 karakter.';
    }
    if (!RegExp(r'[A-Za-z]').hasMatch(value) ||
        !RegExp(r'[0-9]|[^A-Za-z0-9\s]').hasMatch(value)) {
      return 'Gunakan huruf dan minimal satu angka atau simbol.';
    }
    if (value == _currentPasswordController.text) {
      return 'Password baru harus berbeda dari password lama.';
    }
    return null;
  }

  String? _validateGmailEmail(String? value) {
    final email = value?.trim() ?? '';
    if (email.isEmpty) {
      return 'Email wajib diisi.';
    }
    if (!RegExp(
      r'^[^@\s]+@gmail\.com$',
      caseSensitive: false,
    ).hasMatch(email)) {
      return 'Email wajib menggunakan alamat @gmail.com.';
    }
    return null;
  }

  String? _validatePhoneNumber(String? value) {
    final phone = value?.trim() ?? '';
    if (phone.isEmpty) {
      return 'Nomor HP wajib diisi.';
    }
    if (!RegExp(r'^[0-9]{11,12}$').hasMatch(phone)) {
      return 'Nomor HP harus terdiri dari 11 sampai 12 digit angka.';
    }
    return null;
  }

  String? _validateConfirmPassword(String? value) {
    if (_newPasswordController.text.trim().isEmpty &&
        (value == null || value.trim().isEmpty)) {
      return null;
    }
    if (value != _newPasswordController.text) {
      return 'Konfirmasi password belum sama.';
    }
    return null;
  }
}

class _ProfileFieldLabel extends StatelessWidget {
  const _ProfileFieldLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 6),
      child: Text(
        text,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: TravSTheme.navy,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _ProfileAvatarPreview extends StatelessWidget {
  const _ProfileAvatarPreview({
    required this.name,
    required this.radius,
    this.imageBytes,
    this.imageUrl,
    this.onTap,
  });

  final String name;
  final double radius;
  final Uint8List? imageBytes;
  final String? imageUrl;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty
        ? 'P'
        : name.trim().substring(0, 1).toUpperCase();
    final size = radius * 2;

    Widget initialAvatar() {
      return CircleAvatar(
        radius: radius,
        backgroundColor: TravSTheme.navy,
        child: Text(
          initial,
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w900,
          ),
        ),
      );
    }

    Widget tappable(Widget child) {
      if (onTap == null) {
        return child;
      }

      return GestureDetector(onTap: onTap, child: child);
    }

    if (imageBytes != null) {
      return tappable(
        CircleAvatar(
          radius: radius,
          backgroundColor: TravSTheme.navy,
          child: ClipOval(
            child: Image.memory(
              imageBytes!,
              width: size,
              height: size,
              fit: BoxFit.cover,
            ),
          ),
        ),
      );
    }

    if (imageUrl != null && imageUrl!.trim().isNotEmpty) {
      return tappable(
        CircleAvatar(
          radius: radius,
          backgroundColor: TravSTheme.navy,
          child: ClipOval(
            child: Image.network(
              imageUrl!,
              width: size,
              height: size,
              fit: BoxFit.cover,
              loadingBuilder: (context, child, progress) {
                if (progress == null) {
                  return child;
                }

                return SizedBox(
                  width: size,
                  height: size,
                  child: const Center(
                    child: SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  ),
                );
              },
              errorBuilder: (context, error, stackTrace) {
                return initialAvatar();
              },
            ),
          ),
        ),
      );
    }

    return tappable(initialAvatar());
  }
}

class _ProfileMainCard extends StatelessWidget {
  const _ProfileMainCard({required this.profile, required this.actions});

  final Widget profile;
  final List<_ProfileMenuAction> actions;

  @override
  Widget build(BuildContext context) {
    return Container(
      key: const Key('passenger-profile-main-card'),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          profile,
          for (final action in actions) ...[
            const SizedBox(height: 18),
            const Divider(height: 1, color: TravSTheme.line),
            _ProfileMenuRow(
              icon: action.icon,
              title: action.title,
              onTap: action.onTap,
            ),
          ],
        ],
      ),
    );
  }
}

class _ProfileMenuAction {
  const _ProfileMenuAction({
    required this.icon,
    required this.title,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final VoidCallback onTap;
}

class _ProfileSummaryContent extends StatelessWidget {
  const _ProfileSummaryContent({required this.session});

  final TravSUserSession session;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final avatarUrl = TravSApiService.resolveMediaUrl(session.user.fotoProfil);

    return Row(
      children: [
        _ProfileAvatarPreview(
          name: session.user.nama,
          radius: 30,
          imageUrl: avatarUrl,
          onTap: avatarUrl == null
              ? null
              : () => _showTravSImagePreview(
                  context,
                  imageUrl: avatarUrl,
                  title: session.user.nama,
                ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                session.user.nama,
                style: theme.textTheme.titleLarge?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                session.user.email,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                _humanizeRole(session.user.peran),
                style: theme.textTheme.labelMedium?.copyWith(
                  color: TravSTheme.orange,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ProfileMenuRow extends StatelessWidget {
  const _ProfileMenuRow({
    required this.icon,
    required this.title,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: TravSTheme.surfaceSoft,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: TravSTheme.navy),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                title,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            const Icon(
              Icons.chevron_right_rounded,
              color: TravSTheme.navyMuted,
            ),
          ],
        ),
      ),
    );
  }
}

class _GuestProfileCard extends StatelessWidget {
  const _GuestProfileCard({required this.onOpenAuth, required this.onOpenInfo});

  final VoidCallback onOpenAuth;
  final VoidCallback onOpenInfo;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      key: const Key('guest-profile-main-card'),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: TravSTheme.orangeSoft,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(
                  Icons.person_outline_rounded,
                  color: TravSTheme.orange,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text(
                  'Mode Tamu',
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          const Divider(height: 1, color: TravSTheme.line),
          _ProfileMenuRow(
            icon: Icons.login_rounded,
            title: 'Masuk sebagai penumpang atau pengemudi',
            onTap: onOpenAuth,
          ),
          const Divider(height: 1, color: TravSTheme.line),
          _ProfileMenuRow(
            icon: Icons.info_rounded,
            title: 'Info',
            onTap: onOpenInfo,
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: theme.textTheme.titleLarge?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F1),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFFFB7B7)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline_rounded, color: Color(0xFFEF4444)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: const Color(0xFFB91C1C),
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({
    required this.title,
    required this.description,
    this.icon,
    this.justifyDescription = false,
  });

  final String title;
  final String description;
  final IconData? icon;
  final bool justifyDescription;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (icon != null) ...[
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: TravSTheme.orangeSoft,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: TravSTheme.orange),
            ),
            const SizedBox(width: 12),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  description,
                  textAlign: justifyDescription
                      ? TextAlign.justify
                      : TextAlign.start,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
