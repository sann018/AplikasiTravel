part of '../travs_home_page.dart';

class _ImportantInformationSection extends StatelessWidget {
  const _ImportantInformationSection({required this.items, this.onShowMore});

  final List<TravSImportantInformation> items;
  final VoidCallback? onShowMore;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: TravSTheme.orange.withValues(alpha: 0.28)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.campaign_outlined, color: TravSTheme.orange),
              const SizedBox(width: 10),
              Text(
                'Informasi Penting',
                style: theme.textTheme.titleMedium?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (var index = 0; index < items.length; index++) ...[
            Text(
              items[index].title,
              style: theme.textTheme.titleSmall?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              items[index].description,
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
                height: 1.45,
              ),
            ),
            if (index != items.length - 1) ...[
              const SizedBox(height: 12),
              const Divider(height: 1, color: TravSTheme.line),
              const SizedBox(height: 12),
            ],
          ],
          if (onShowMore != null) ...[
            const SizedBox(height: 12),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: onShowMore,
                style: TextButton.styleFrom(
                  foregroundColor: TravSTheme.navy,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 4,
                    vertical: 6,
                  ),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'Info lainnya',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    SizedBox(width: 4),
                    Icon(Icons.arrow_forward_rounded, size: 18),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _PassengerDashboardHero extends StatefulWidget {
  const _PassengerDashboardHero({
    required this.name,
    required this.stats,
    required this.profileImageUrl,
    required this.banners,
    this.subtitle,
  });

  final String name;
  final List<_HeroStat> stats;
  final String? profileImageUrl;
  final List<TravSHomeBanner> banners;
  final String? subtitle;

  @override
  State<_PassengerDashboardHero> createState() =>
      _PassengerDashboardHeroState();
}

class _PassengerDashboardHeroState extends State<_PassengerDashboardHero> {
  final PageController _pageController = PageController();
  int _currentBannerIndex = 0;

  @override
  void didUpdateWidget(covariant _PassengerDashboardHero oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.banners.isEmpty) {
      _currentBannerIndex = 0;
      return;
    }

    if (_currentBannerIndex >= widget.banners.length) {
      _currentBannerIndex = 0;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _pageController.hasClients) {
          _pageController.jumpToPage(0);
        }
      });
    }
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _moveBanner(int direction) {
    if (widget.banners.length < 2 || !_pageController.hasClients) {
      return;
    }

    final target =
        (_currentBannerIndex + direction + widget.banners.length) %
        widget.banners.length;
    _pageController.animateToPage(
      target,
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(14),
      constraints: const BoxConstraints(minHeight: 86),
      decoration: BoxDecoration(
        gradient: TravSTheme.headerGradient,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: TravSTheme.navy.withValues(alpha: 0.12),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _PassengerHomeAvatar(
                name: widget.name,
                imageUrl: widget.profileImageUrl,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Halo, ${widget.name}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (widget.subtitle != null)
                      Text(
                        widget.subtitle!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: Colors.white70,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 124,
            width: double.infinity,
            child: widget.banners.isEmpty
                ? const _PassengerBannerFallback()
                : Stack(
                    children: [
                      PageView.builder(
                        controller: _pageController,
                        itemCount: widget.banners.length,
                        onPageChanged: (index) {
                          setState(() => _currentBannerIndex = index);
                        },
                        itemBuilder: (context, index) => _PassengerBannerSlide(
                          banner: widget.banners[index],
                        ),
                      ),
                      if (widget.banners.length > 1) ...[
                        Positioned(
                          left: 8,
                          top: 0,
                          bottom: 0,
                          child: _BannerNavigationButton(
                            tooltip: 'Banner sebelumnya',
                            icon: Icons.chevron_left_rounded,
                            onPressed: () => _moveBanner(-1),
                          ),
                        ),
                        Positioned(
                          right: 8,
                          top: 0,
                          bottom: 0,
                          child: _BannerNavigationButton(
                            tooltip: 'Banner berikutnya',
                            icon: Icons.chevron_right_rounded,
                            onPressed: () => _moveBanner(1),
                          ),
                        ),
                      ],
                    ],
                  ),
          ),
          if (widget.banners.length > 1) ...[
            const SizedBox(height: 8),
            Center(
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: List.generate(
                  widget.banners.length,
                  (index) => _HeroDot(active: index == _currentBannerIndex),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _BannerNavigationButton extends StatelessWidget {
  const _BannerNavigationButton({
    required this.tooltip,
    required this.icon,
    required this.onPressed,
  });

  final String tooltip;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Tooltip(
        message: tooltip,
        child: Material(
          color: TravSTheme.navy.withValues(alpha: 0.72),
          shape: const CircleBorder(),
          child: IconButton(
            onPressed: onPressed,
            icon: Icon(icon, color: Colors.white),
            iconSize: 22,
            constraints: const BoxConstraints.tightFor(width: 36, height: 36),
            padding: EdgeInsets.zero,
          ),
        ),
      ),
    );
  }
}

class _PassengerHomeAvatar extends StatelessWidget {
  const _PassengerHomeAvatar({required this.name, required this.imageUrl});

  final String name;
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty ? '?' : name.trim()[0].toUpperCase();
    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: Container(
        width: 32,
        height: 32,
        color: Colors.white.withValues(alpha: 0.16),
        child: imageUrl == null
            ? Center(
                child: Text(
                  initial,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              )
            : Image.network(
                imageUrl!,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) => Center(
                  child: Text(
                    initial,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
      ),
    );
  }
}

class _PassengerBannerFallback extends StatelessWidget {
  const _PassengerBannerFallback();

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
      ),
      child: const Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.landscape_rounded, color: Colors.white, size: 34),
          SizedBox(height: 8),
          Text(
            'Armada Nyaman',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900),
          ),
          Text(
            'Kendaraan siap untuk perjalanan lintas kota.',
            style: TextStyle(color: Colors.white70, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _PassengerBannerSlide extends StatelessWidget {
  const _PassengerBannerSlide({required this.banner});

  final TravSHomeBanner banner;

  @override
  Widget build(BuildContext context) {
    final imageUrl = TravSApiService.resolveMediaUrl(banner.imageUrl);
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (imageUrl != null)
            Image.network(
              imageUrl,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) =>
                  const _PassengerBannerFallback(),
            )
          else
            const _PassengerBannerFallback(),
          Container(color: Colors.black.withValues(alpha: 0.30)),
          Positioned(
            left: 16,
            right: 16,
            bottom: 14,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  banner.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (banner.subtitle.isNotEmpty)
                  Text(
                    banner.subtitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white, fontSize: 11),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroDot extends StatelessWidget {
  const _HeroDot({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: active ? 18 : 5,
      height: 5,
      margin: const EdgeInsets.symmetric(horizontal: 3),
      decoration: BoxDecoration(
        color: active ? TravSTheme.orange : Colors.white70,
        borderRadius: BorderRadius.circular(99),
      ),
    );
  }
}

class _NextTripCard extends StatelessWidget {
  const _NextTripCard({required this.booking, required this.onTap});

  final TravSBookingResult booking;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Besok ada perjalanan',
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  _compactRouteName(booking.rute),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 22,
                  runSpacing: 8,
                  children: [
                    _TripMeta(
                      label: 'Tanggal',
                      value: _formatShortDate(booking.waktuBerangkat),
                    ),
                    _TripMeta(
                      label: 'Waktu',
                      value: _formatScheduleTime(booking.waktuBerangkat),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              _StatusPill(
                label: 'Aktif',
                color: TravSTheme.success,
                textColor: TravSTheme.success,
              ),
              // Jarak tetap mencegah Column meminta tinggi tak terbatas saat
              // card berada di dalam ListView beranda.
              const SizedBox(height: 54),
              FilledButton(
                onPressed: onTap,
                style: FilledButton.styleFrom(
                  backgroundColor: TravSTheme.navy,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 14,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text('Lihat\nDetail', textAlign: TextAlign.center),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TripMeta extends StatelessWidget {
  const _TripMeta({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
            color: TravSTheme.navyMuted,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _DriverHeroCard extends StatelessWidget {
  const _DriverHeroCard({
    required this.displayName,
    required this.phoneNumber,
    required this.simNumber,
    required this.profileImageUrl,
    required this.stats,
  });

  final String displayName;
  final String phoneNumber;
  final String simNumber;
  final String? profileImageUrl;
  final List<_HeroStat> stats;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final initials = displayName.isEmpty
        ? 'S'
        : displayName.substring(0, 1).toUpperCase();

    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: TravSTheme.headerGradient,
        borderRadius: BorderRadius.circular(30),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _DriverProfileThumb(
                imageUrl: profileImageUrl,
                initials: initials,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      displayName,
                      style: theme.textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Nomor HP: $phoneNumber',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: Colors.white70,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Nomor SIM: $simNumber',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: Colors.white70,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              for (var index = 0; index < stats.length; index++) ...[
                Expanded(
                  child: SizedBox(
                    height: 76,
                    child: _HeroStatTile(stat: stats[index], dark: true),
                  ),
                ),
                if (index != stats.length - 1) const SizedBox(width: 10),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _DriverProfileWorkCard extends StatelessWidget {
  const _DriverProfileWorkCard({
    required this.session,
    required this.onEditProfile,
    required this.onOpenGuide,
  });

  final TravSUserSession session;
  final VoidCallback onEditProfile;
  final VoidCallback onOpenGuide;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final user = session.user;
    final avatarUrl = TravSApiService.resolveMediaUrl(user.fotoProfil);

    return Container(
      key: const Key('driver-profile-main-card'),
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
              _ProfileAvatarPreview(
                name: user.nama,
                radius: 30,
                imageUrl: avatarUrl,
                onTap: avatarUrl == null
                    ? null
                    : () => _showTravSImagePreview(
                        context,
                        imageUrl: avatarUrl,
                        title: user.nama,
                      ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.nama,
                      style: theme.textTheme.titleLarge?.copyWith(
                        color: TravSTheme.navy,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      user.email,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: TravSTheme.navyMuted,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _humanizeRole(user.peran),
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: TravSTheme.orange,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          const Divider(height: 1, color: TravSTheme.line),
          const SizedBox(height: 18),
          Text(
            'Identitas',
            style: theme.textTheme.titleMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 14),
          _InfoRow(label: 'Nomor HP', value: user.noHp),
          const SizedBox(height: 10),
          _InfoRow(label: 'Nomor SIM / NIK', value: user.nomorSim ?? '-'),
          const SizedBox(height: 18),
          const Divider(height: 1, color: TravSTheme.line),
          _ProfileMenuRow(
            icon: Icons.edit_outlined,
            title: 'Edit Profil',
            onTap: onEditProfile,
          ),
          const Divider(height: 1, color: TravSTheme.line),
          _ProfileMenuRow(
            icon: Icons.info_rounded,
            title: 'Tata Cara dan Aturan Pengemudi',
            onTap: onOpenGuide,
          ),
        ],
      ),
    );
  }
}

class _AssignedVehicleCard extends StatelessWidget {
  const _AssignedVehicleCard({required this.vehicle, this.message});

  final TravSDriverAssignedVehicle? vehicle;
  final String? message;

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      title: 'Kendaraan yang Ditugaskan',
      child: _AssignedVehicleContent(vehicle: vehicle, message: message),
    );
  }
}

class _AssignedVehiclePhotoPreview extends StatelessWidget {
  const _AssignedVehiclePhotoPreview({
    required this.photoUrl,
    required this.title,
  });

  final String? photoUrl;
  final String title;

  @override
  Widget build(BuildContext context) {
    Widget fallback() {
      return Container(
        color: TravSTheme.surfaceSoft,
        child: const Icon(
          Icons.directions_car_filled_rounded,
          color: TravSTheme.navyMuted,
        ),
      );
    }

    final thumbnail = ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: SizedBox(
        width: 76,
        height: 64,
        child: photoUrl == null
            ? fallback()
            : Image.network(
                '$photoUrl?v=${DateTime.now().millisecondsSinceEpoch}',
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) => fallback(),
              ),
      ),
    );

    if (photoUrl == null) {
      return thumbnail;
    }

    return Semantics(
      button: true,
      label: 'Preview foto kendaraan',
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () =>
            _showTravSImagePreview(context, imageUrl: photoUrl!, title: title),
        child: thumbnail,
      ),
    );
  }
}

class _AssignedVehicleContent extends StatelessWidget {
  const _AssignedVehicleContent({required this.vehicle, this.message});

  final TravSDriverAssignedVehicle? vehicle;
  final String? message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final photoUrl = TravSApiService.resolveMediaUrl(vehicle?.photoUrl);
    final assignedVehicle = vehicle;

    if (assignedVehicle == null) {
      return Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: TravSTheme.surfaceSoft,
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(
              Icons.directions_car_filled_rounded,
              color: TravSTheme.navyMuted,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message ??
                  'Belum ada kendaraan yang ditugaskan. Kendaraan akan tampil setelah admin membuat jadwal perjalanan.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
          ),
        ],
      );
    }

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _AssignedVehiclePhotoPreview(
          photoUrl: photoUrl,
          title: assignedVehicle.displayName,
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                assignedVehicle.displayName,
                style: theme.textTheme.titleSmall?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${assignedVehicle.route} - ${_formatDateTime(assignedVehicle.departureAt)}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _MiniInfoChip(
                    icon: Icons.directions_car_filled_rounded,
                    label: assignedVehicle.plateNumber,
                  ),
                  _MiniInfoChip(
                    icon: Icons.event_seat_rounded,
                    label: '${assignedVehicle.capacity} kursi',
                  ),
                  _MiniInfoChip(
                    icon: Icons.palette_outlined,
                    label: assignedVehicle.color,
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        Flexible(
          flex: 2,
          child: Text(
            value,
            textAlign: TextAlign.end,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }
}

class _MiniInfoChip extends StatelessWidget {
  const _MiniInfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: TravSTheme.navyMuted),
          const SizedBox(width: 5),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _DriverProfileThumb extends StatelessWidget {
  const _DriverProfileThumb({required this.imageUrl, required this.initials});

  final String? imageUrl;
  final String initials;

  @override
  Widget build(BuildContext context) {
    Widget fallback() {
      return Center(
        child: Text(
          initials,
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w800,
          ),
        ),
      );
    }

    return ClipOval(
      child: Container(
        width: 56,
        height: 56,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: Colors.white.withValues(alpha: 0.16),
          border: Border.all(color: Colors.white24),
        ),
        child: imageUrl == null
            ? fallback()
            : Image.network(
                '$imageUrl?v=${DateTime.now().millisecondsSinceEpoch}',
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) => fallback(),
                loadingBuilder: (context, child, loadingProgress) {
                  if (loadingProgress == null) {
                    return child;
                  }

                  return const Center(
                    child: SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    ),
                  );
                },
              ),
      ),
    );
  }
}

class _HeroStat {
  const _HeroStat({required this.label, required this.value});

  final String label;
  final String value;
}

class _HeroStatTile extends StatelessWidget {
  const _HeroStatTile({required this.stat, this.dark = false});

  final _HeroStat stat;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: dark
            ? Colors.white.withValues(alpha: 0.12)
            : TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: dark ? Border.all(color: Colors.white24) : null,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: double.infinity,
            child: FittedBox(
              alignment: Alignment.centerLeft,
              fit: BoxFit.scaleDown,
              child: Text(
                stat.value,
                maxLines: 1,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: dark ? Colors.white : TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
          const SizedBox(height: 4),
          SizedBox(
            width: double.infinity,
            child: FittedBox(
              alignment: Alignment.centerLeft,
              fit: BoxFit.scaleDown,
              child: Text(
                stat.label,
                maxLines: 1,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: dark ? Colors.white70 : TravSTheme.navyMuted,
                  fontWeight: FontWeight.w500,
                  height: 1.1,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _InlineActionCard extends StatelessWidget {
  const _InlineActionCard({
    required this.title,
    required this.description,
    required this.actionLabel,
    required this.icon,
    required this.onTap,
  });

  final String title;
  final String description;
  final String actionLabel;
  final IconData icon;
  final VoidCallback onTap;

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
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: TravSTheme.orangeSoft,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(icon, color: TravSTheme.orange),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  description,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
                const SizedBox(height: 12),
                TextButton(onPressed: onTap, child: Text(actionLabel)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    this.icon,
    this.selected = false,
    this.onTap,
  });

  final String label;
  final IconData? icon;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final content = Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
      decoration: BoxDecoration(
        color: selected ? TravSTheme.navy : Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: selected ? TravSTheme.navy : TravSTheme.line),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(
              icon,
              size: 17,
              color: selected ? Colors.white : TravSTheme.navy,
            ),
            const SizedBox(width: 6),
          ],
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: selected ? Colors.white : TravSTheme.navy,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );

    if (onTap == null) {
      return content;
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: content,
    );
  }
}

class _DateRangeFilterChips extends StatelessWidget {
  const _DateRangeFilterChips({
    required this.startDate,
    required this.endDate,
    required this.onSelectRange,
    required this.onReset,
  });

  final DateTime? startDate;
  final DateTime? endDate;
  final VoidCallback onSelectRange;
  final VoidCallback onReset;

  bool get _hasRange => startDate != null && endDate != null;

  String get _rangeLabel {
    if (!_hasRange) return 'Pilih Tanggal';
    return '${DateFormat('dd MMM', 'id_ID').format(startDate!)} - '
        '${DateFormat('dd MMM yyyy', 'id_ID').format(endDate!)}';
  }

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        _FilterChip(label: 'Semua', selected: !_hasRange, onTap: onReset),
        _FilterChip(
          label: _rangeLabel,
          icon: Icons.calendar_month_rounded,
          selected: _hasRange,
          onTap: onSelectRange,
        ),
      ],
    );
  }
}
