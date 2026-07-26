part of '../travs_booking_detail_page.dart';

class _DetailSectionCard extends StatelessWidget {
  const _DetailSectionCard({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  final String title;
  final String subtitle;
  final Widget child;

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
              title,
              style: theme.textTheme.titleMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (subtitle.trim().isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
            ],
            Padding(padding: const EdgeInsets.only(top: 18), child: child),
          ],
        ),
      ),
    );
  }
}

class _InternalSectionHeading extends StatelessWidget {
  const _InternalSectionHeading({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: Theme.of(context).textTheme.titleSmall?.copyWith(
        color: TravSTheme.navy,
        fontWeight: FontWeight.w800,
      ),
    );
  }
}

class _BookingLocationContent extends StatelessWidget {
  const _BookingLocationContent({
    required this.location,
    required this.isPickup,
    required this.onOpenMaps,
  });

  final TravSBookingPickupInfo? location;
  final bool isPickup;
  final ValueChanged<TravSBookingPickupInfo> onOpenMaps;

  @override
  Widget build(BuildContext context) {
    final selectedLocation = location;
    if (selectedLocation == null) {
      return const _InlineEmptyState(title: 'Data lokasi belum tersedia.');
    }

    final note = selectedLocation.note?.trim() ?? '';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _DetailRow(
          label: 'Koordinat',
          value:
              '${selectedLocation.latitude.toStringAsFixed(6)}, ${selectedLocation.longitude.toStringAsFixed(6)}',
          stacked: true,
        ),
        const SizedBox(height: 8),
        Text(
          selectedLocation.address,
          style: Theme.of(
            context,
          ).textTheme.bodyMedium?.copyWith(color: TravSTheme.navy),
        ),
        if (note.isNotEmpty) ...[
          const SizedBox(height: 10),
          _DetailRow(
            label: isPickup ? 'Patokan jemput' : 'Patokan turun',
            value: note,
            stacked: true,
          ),
        ],
        const SizedBox(height: 14),
        OutlinedButton.icon(
          onPressed: () => onOpenMaps(selectedLocation),
          icon: const Icon(Icons.map_rounded),
          label: const Text('Buka di Google Maps'),
        ),
      ],
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.label,
    required this.value,
    this.emphasize = false,
    this.stacked = false,
    this.alignValueRight = false,
  });

  final String label;
  final String value;
  final bool emphasize;
  final bool stacked;
  final bool alignValueRight;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    final labelStyle = theme.textTheme.labelLarge?.copyWith(
      color: emphasize ? TravSTheme.navy : TravSTheme.navyMuted,
      fontWeight: emphasize ? FontWeight.w800 : FontWeight.w600,
    );
    final valueStyle = theme.textTheme.bodyMedium?.copyWith(
      color: TravSTheme.navy,
      fontWeight: emphasize ? FontWeight.w800 : FontWeight.w600,
    );

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: stacked
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: labelStyle),
                const SizedBox(height: 6),
                Text(value, style: valueStyle),
              ],
            )
          : Row(
              children: [
                Expanded(child: Text(label, style: labelStyle)),
                const SizedBox(width: 14),
                if (alignValueRight)
                  Expanded(
                    child: Text(
                      value,
                      textAlign: TextAlign.right,
                      style: valueStyle,
                    ),
                  )
                else
                  Flexible(
                    child: Text(
                      value,
                      textAlign: TextAlign.end,
                      style: valueStyle,
                    ),
                  ),
              ],
            ),
    );
  }
}
