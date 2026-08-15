part of '../travs_home_page.dart';

class _RouteBookmark {
  const _RouteBookmark({
    required this.key,
    required this.origin,
    required this.destination,
    required this.path,
    required this.scheduleCount,
    required this.availableSeats,
    required this.startingPrice,
  });

  final String key;
  final String origin;
  final String destination;
  final String path;
  final int scheduleCount;
  final int availableSeats;
  final double startingPrice;

  String get title => '$origin - $destination';
  String get subtitle => '$origin menuju $destination';
}

class _RouteBookmarkCard extends StatelessWidget {
  const _RouteBookmarkCard({required this.route, required this.onTap});

  final _RouteBookmark route;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: TravSTheme.line),
          boxShadow: [
            BoxShadow(
              color: TravSTheme.navy.withValues(alpha: 0.04),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          children: [
            _NetworkThumbnail(
              imageUrl: _destinationImageUrl(route.destination),
              fallbackIcon: Icons.location_city_rounded,
              label: route.destination,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    route.title,
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Rute utama perjalanan',
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    route.path == '-' ? route.subtitle : route.path,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navyMuted,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: [
                      _TinyInfoPill(
                        icon: Icons.event_available_rounded,
                        label: '${route.scheduleCount} jadwal',
                      ),
                      _TinyInfoPill(
                        icon: Icons.event_seat_rounded,
                        label: '${route.availableSeats} kursi',
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            const Icon(Icons.chevron_right_rounded, color: TravSTheme.navy),
          ],
        ),
      ),
    );
  }
}

class _ScheduleBookmarkCard extends StatelessWidget {
  const _ScheduleBookmarkCard({
    required this.schedule,
    required this.ctaLabel,
    required this.onTap,
  });

  final TravSSchedule schedule;
  final String ctaLabel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: TravSTheme.line),
          boxShadow: [
            BoxShadow(
              color: TravSTheme.navy.withValues(alpha: 0.04),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _NetworkThumbnail(
                  imageUrl:
                      TravSApiService.resolveMediaUrl(
                        schedule.kendaraanFotoUrl,
                      ) ??
                      _vehicleImageUrl(schedule.kendaraan),
                  fallbackIcon: Icons.directions_car_filled_rounded,
                  label: schedule.kendaraan,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _formatScheduleDate(schedule.waktuBerangkat),
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: TravSTheme.navy,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          const Icon(
                            Icons.schedule_rounded,
                            size: 17,
                            color: TravSTheme.navyMuted,
                          ),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              _formatScheduleTime(schedule.waktuBerangkat),
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: TravSTheme.navy,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            // Informasi dibuat selebar card agar kata tidak terpotong sempit.
            _ScheduleInfoLine(
              label: 'Rute',
              value: '${schedule.asal} - ${schedule.tujuan}',
            ),
            _ScheduleInfoLine(label: 'Kendaraan', value: schedule.kendaraan),
            _ScheduleInfoLine(
              label: 'Sisa Kursi',
              value: '${schedule.sisaKursi} / ${schedule.kapasitas} Kursi',
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Harga',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: TravSTheme.navyMuted,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        _formatCurrency(schedule.hargaTiket),
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: TravSTheme.navy,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                ElevatedButton(
                  onPressed: onTap,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: TravSTheme.navy,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 18,
                      vertical: 12,
                    ),
                    minimumSize: const Size(76, 42),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: Text(ctaLabel),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ScheduleInfoLine extends StatelessWidget {
  const _ScheduleInfoLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 82,
            child: Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
                height: 1.35,
              ),
            ),
          ),
          Text(
            ': ',
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
              height: 1.35,
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TinyInfoPill extends StatelessWidget {
  const _TinyInfoPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: TravSTheme.orange),
          const SizedBox(width: 5),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _ScanResultCard extends StatelessWidget {
  const _ScanResultCard({
    required this.title,
    required this.subtitle,
    required this.tone,
    required this.icon,
    required this.detailLines,
    required this.onReset,
    super.key,
  });

  final String title;
  final String subtitle;
  final Color tone;
  final IconData icon;
  final List<String> detailLines;
  final VoidCallback onReset;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          height: 260,
          decoration: BoxDecoration(
            color: tone.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(24),
          ),
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                CircleAvatar(
                  radius: 34,
                  backgroundColor: tone.withValues(alpha: 0.16),
                  child: Icon(icon, color: tone, size: 34),
                ),
                const SizedBox(height: 14),
                Text(
                  title,
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: tone,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          subtitle,
          style: theme.textTheme.bodyMedium?.copyWith(color: TravSTheme.navy),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: TravSTheme.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final line in detailLines) ...[
                Text(
                  line,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
                if (line != detailLines.last) const SizedBox(height: 6),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),
        OutlinedButton(onPressed: onReset, child: const Text('Ulangi Pindai')),
      ],
    );
  }
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({
    required this.schedule,
    required this.onTap,
    required this.ctaLabel,
  });

  final TravSSchedule schedule;
  final VoidCallback onTap;
  final String ctaLabel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final route = '${schedule.asal} - ${schedule.tujuan}';
    final routePath = schedule.jalur.trim();
    final showRoutePath =
        routePath.isNotEmpty && routePath.toLowerCase() != route.toLowerCase();

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(26),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(26),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _NetworkThumbnail(
                  imageUrl:
                      TravSApiService.resolveMediaUrl(
                        schedule.kendaraanFotoUrl,
                      ) ??
                      _vehicleImageUrl(schedule.kendaraan),
                  fallbackIcon: Icons.directions_car_filled_rounded,
                  label: schedule.kendaraan,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        route,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          color: TravSTheme.navy,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (showRoutePath) ...[
                        const SizedBox(height: 6),
                        Text(
                          routePath,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: TravSTheme.navyMuted,
                            height: 1.35,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    _StatusPill(
                      label: '${schedule.sisaKursi} kursi',
                      color: schedule.sisaKursi > 2
                          ? TravSTheme.success
                          : TravSTheme.warning,
                      textColor: schedule.sisaKursi > 2
                          ? TravSTheme.success
                          : TravSTheme.warning,
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 14),
            _ScheduleInfoLine(
              label: 'Berangkat',
              value: _formatDateTime(schedule.waktuBerangkat),
            ),
            const SizedBox(height: 4),
            _ScheduleInfoLine(label: 'Kendaraan', value: schedule.kendaraan),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: Text(
                    _formatCurrency(schedule.hargaTiket),
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                TextButton(onPressed: onTap, child: Text(ctaLabel)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _MetaColumn extends StatelessWidget {
  const _MetaColumn({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: theme.textTheme.labelSmall?.copyWith(
            color: TravSTheme.navyMuted,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: theme.textTheme.bodySmall?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w600,
            height: 1.35,
          ),
        ),
      ],
    );
  }
}

class _BookingCard extends StatelessWidget {
  const _BookingCard({required this.booking, required this.onTap});

  final TravSBookingResult booking;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (booking.isStafPelayananBooking) ...[
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 11,
                  vertical: 7,
                ),
                decoration: BoxDecoration(
                  color: TravSTheme.orangeSoft,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: TravSTheme.orange.withValues(alpha: 0.35),
                  ),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.storefront_rounded,
                      size: 17,
                      color: TravSTheme.orange,
                    ),
                    const SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        'Dicatat oleh Staf Pelayanan: ${booking.dibuatOlehNama ?? 'Staf Pelayanan tidak tersedia'}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: TravSTheme.navy,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            Row(
              children: [
                Expanded(
                  child: Text(
                    booking.rute,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                _StatusPill(
                  label: _humanizeStatus(booking.status),
                  color: booking.isPaid
                      ? TravSTheme.success
                      : TravSTheme.warning,
                  textColor: booking.isPaid
                      ? TravSTheme.success
                      : TravSTheme.warning,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: _MetaColumn(
                    label: 'Tanggal keberangkatan',
                    value: _formatScheduleDate(booking.waktuBerangkat),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _MetaColumn(
                    label: 'Waktu keberangkatan',
                    value: _formatScheduleTime(booking.waktuBerangkat),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _MetaColumn(
                    label: 'Jumlah kursi',
                    value: '${booking.jumlahKursi}',
                  ),
                ),
                Expanded(
                  child: _MetaColumn(
                    label: booking.payment.canChoose ? 'Nominal' : 'Total',
                    value: booking.payment.canChoose
                        ? _formatCurrency(booking.totalHarga)
                        : booking.payment.totalPayment == null
                        ? '-'
                        : _formatCurrency(booking.payment.totalPayment!),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _TicketPreviewCard extends StatelessWidget {
  const _TicketPreviewCard({
    required this.booking,
    required this.onTap,
    required this.isCompleted,
  });

  final TravSBookingResult booking;
  final VoidCallback onTap;
  final bool isCompleted;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(26),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: isCompleted ? const Color(0xFFE8EDF5) : TravSTheme.navy,
          borderRadius: BorderRadius.circular(26),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    booking.rute,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: isCompleted ? TravSTheme.navy : Colors.white,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Icon(
                  Icons.qr_code_2_rounded,
                  color: isCompleted ? TravSTheme.navyMuted : Colors.white,
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              isCompleted ? 'Perjalanan selesai' : 'Tiket perjalanan',
              style: theme.textTheme.bodySmall?.copyWith(
                color: isCompleted ? TravSTheme.navy : Colors.white,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _TicketScheduleMeta(
                    label: 'Tanggal keberangkatan',
                    value: _formatScheduleDate(booking.waktuBerangkat),
                    isCompleted: isCompleted,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _TicketScheduleMeta(
                    label: 'Waktu keberangkatan',
                    value: _formatScheduleTime(booking.waktuBerangkat),
                    isCompleted: isCompleted,
                  ),
                ),
              ],
            ),
            // Tiket aktif sudah mempunyai ikon QR dan dapat diketuk, sehingga
            // keterangan pembuka detail tidak perlu diulang di dalam card.
            if (isCompleted || !booking.isPaid) ...[
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: isCompleted
                      ? Colors.white
                      : Colors.white.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.verified_outlined,
                      color: isCompleted ? TravSTheme.success : Colors.white,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        isCompleted
                            ? 'Perjalanan selesai. Tiket ini tersimpan sebagai riwayat.'
                            : 'Pemesanan ini masih menunggu pembayaran, jadi QR belum aktif.',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: isCompleted ? TravSTheme.navy : Colors.white,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _TicketScheduleMeta extends StatelessWidget {
  const _TicketScheduleMeta({
    required this.label,
    required this.value,
    required this.isCompleted,
  });

  final String label;
  final String value;
  final bool isCompleted;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final labelColor = isCompleted ? TravSTheme.navyMuted : Colors.white70;
    final valueColor = isCompleted ? TravSTheme.navy : Colors.white;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: theme.textTheme.labelSmall?.copyWith(color: labelColor),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          style: theme.textTheme.bodySmall?.copyWith(
            color: valueColor,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _DriverJourneyCard extends StatelessWidget {
  const _DriverJourneyCard({required this.record, required this.onTap});

  final _DriverJourneyRecord record;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    record.route,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                _StatusPill(
                  label: record.statusLabel,
                  color: record.status == 'selesai'
                      ? TravSTheme.success
                      : TravSTheme.orange,
                  textColor: record.status == 'selesai'
                      ? TravSTheme.success
                      : TravSTheme.orange,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              record.vehicle,
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _MetaColumn(
                    label: 'Waktu berangkat',
                    value: _formatDateTime(record.departureAt),
                  ),
                ),
                Expanded(
                  child: _MetaColumn(
                    label: 'Penumpang',
                    value: '${record.passengerCount} penumpang',
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _DriverHistoryCard extends StatelessWidget {
  const _DriverHistoryCard({required this.record, required this.onTap});

  final _DriverJourneyRecord record;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    record.route,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                _StatusPill(
                  label: record.statusLabel,
                  color: record.status == 'selesai'
                      ? TravSTheme.success
                      : TravSTheme.orange,
                  textColor: record.status == 'selesai'
                      ? TravSTheme.success
                      : TravSTheme.orange,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              _formatDateTime(record.departureAt),
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _MetaColumn(
                    label: 'Pendapatan',
                    value: _formatCurrency(record.revenue),
                  ),
                ),
                Expanded(
                  child: _MetaColumn(
                    label: 'Penumpang',
                    value: '${record.passengerCount} orang',
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
