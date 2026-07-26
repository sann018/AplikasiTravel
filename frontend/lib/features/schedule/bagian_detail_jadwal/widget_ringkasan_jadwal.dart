part of '../travs_schedule_detail_page.dart';

class _HeroCard extends StatelessWidget {
  const _HeroCard({required this.detail, required this.selectedRole});

  final TravSScheduleDetail detail;
  final AppRolePreview selectedRole;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(32),
        gradient: const LinearGradient(
          colors: [TravSTheme.navy, TravSTheme.navySoft],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x2410233F),
            blurRadius: 28,
            offset: Offset(0, 18),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _BadgePill(label: 'Tersedia', color: TravSTheme.success),
              _BadgePill(
                label: selectedRole == AppRolePreview.driver
                    ? 'Mode Pengemudi'
                    : selectedRole == AppRolePreview.passenger
                    ? 'Mode Penumpang'
                    : 'Mode Guest',
                color: Colors.white,
                textColor: TravSTheme.navy,
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            '${detail.schedule.asal} - ${detail.schedule.tujuan}',
            style: theme.textTheme.headlineSmall?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            detail.schedule.jalur,
            style: theme.textTheme.bodyLarge?.copyWith(
              color: Colors.white.withValues(alpha: 0.82),
            ),
          ),
          const SizedBox(height: 18),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _HeroInfoPill(
                icon: Icons.schedule_rounded,
                text: _formatDate(detail.schedule.waktuBerangkat),
              ),
              _HeroInfoPill(
                icon: Icons.timer_rounded,
                text:
                    '${_formatDurationMinutes(detail.schedule.durasiMenit)} perjalanan',
              ),
              _HeroInfoPill(
                icon: Icons.event_seat_rounded,
                text:
                    '${detail.schedule.sisaKursi}/${detail.schedule.kapasitas} kursi tersedia',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoGrid extends StatelessWidget {
  const _InfoGrid({required this.detail});

  final TravSScheduleDetail detail;

  @override
  Widget build(BuildContext context) {
    final cards = [
      _MetricCardData(
        title: 'Harga Tiket',
        value: 'Rp${_formatCurrency(detail.schedule.hargaTiket)}',
        subtitle: 'Barang bawaan biasa dicatat tanpa biaya tambahan',
        icon: Icons.payments_rounded,
      ),
      _MetricCardData(
        title: 'Kendaraan',
        value: detail.schedule.kendaraan,
        subtitle: detail.vehicleType,
        icon: Icons.directions_car_filled_rounded,
        imageUrl: detail.vehiclePhotoUrl ?? detail.schedule.kendaraanFotoUrl,
      ),
      _MetricCardData(
        title: 'Pengemudi',
        value: detail.schedule.pengemudi,
        subtitle: detail.driverLicenseNumber,
        icon: Icons.badge_rounded,
        imageUrl: detail.driverPhotoUrl,
      ),
      _MetricCardData(
        title: 'Metode Pembayaran',
        value: detail.paymentMethods.conciseSummary,
        subtitle:
            'Kunci form ${detail.bookingRules.seatLockFormMinutes} menit, bayar ${detail.bookingRules.seatLockPaymentMinutes} menit',
        icon: Icons.payments_rounded,
      ),
    ];

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Informasi Perjalanan',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 6),
            ...List.generate(
              cards.length,
              (index) => Column(
                children: [
                  if (index > 0) const Divider(height: 28),
                  _MetricCard(data: cards[index]),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
