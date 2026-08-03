part of '../travs_schedule_detail_page.dart';

class _DriverBriefCard extends StatelessWidget {
  const _DriverBriefCard({required this.detail, required this.onPrimaryAction});

  final TravSScheduleDetail detail;
  final VoidCallback onPrimaryAction;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Ringkasan Pengemudi',
              style: theme.textTheme.titleLarge?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Ringkasan perjalanan dan informasi manifes penumpang.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 18),
            _PriceRow(
              label: 'Kursi terpesan',
              value: '${detail.bookedSeatCount}',
            ),
            _PriceRow(
              label: 'Kursi dikunci',
              value: '${detail.lockedSeatCount}',
            ),
            _PriceRow(
              label: 'Fasilitas kendaraan',
              value: detail.vehicleFacilities,
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: onPrimaryAction,
                icon: const Icon(Icons.assignment_rounded),
                label: const Text('Buka Manifes'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.data});

  final _MetricCardData data;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final resolvedImageUrl = TravSApiService.resolveMediaUrl(data.imageUrl);

    return Row(
      children: [
        InkWell(
          onTap: resolvedImageUrl == null
              ? null
              : () => _showScheduleImagePreview(
                  context,
                  imageUrl: resolvedImageUrl,
                  title: data.value,
                ),
          borderRadius: BorderRadius.circular(18),
          child: Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: TravSTheme.orangeSoft,
              borderRadius: BorderRadius.circular(18),
            ),
            child: resolvedImageUrl == null
                ? Icon(data.icon, color: TravSTheme.orange)
                : ClipRRect(
                    borderRadius: BorderRadius.circular(18),
                    child: Image.network(
                      resolvedImageUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) =>
                          Icon(data.icon, color: TravSTheme.orange),
                      loadingBuilder: (context, child, loadingProgress) {
                        if (loadingProgress == null) {
                          return child;
                        }

                        return Center(
                          child: SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: TravSTheme.orange,
                            ),
                          ),
                        );
                      },
                    ),
                  ),
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                data.title,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                data.value,
                style: theme.textTheme.titleSmall?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                data.subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _BookingLockBadge extends StatelessWidget {
  const _BookingLockBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: TravSTheme.orange.withValues(alpha: 0.22)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.lock_outline_rounded, size: 15),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _LockedBookingNotice extends StatelessWidget {
  const _LockedBookingNotice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: TravSTheme.orange.withValues(alpha: 0.22)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.lock_outline_rounded, color: TravSTheme.orange),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
