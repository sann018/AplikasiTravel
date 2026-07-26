part of '../travs_schedule_detail_page.dart';

class _SeatLegend extends StatelessWidget {
  const _SeatLegend();

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 12,
      runSpacing: 12,
      children: const [
        _LegendItem(label: 'Tersedia', color: Color(0xFF16C784)),
        _LegendItem(label: 'Terisi', color: Color(0xFFF0445E)),
        _LegendItem(label: 'Dikunci/Ditutup', color: Color(0xFF94A3B8)),
        _LegendItem(label: 'Pilihan', color: Color(0xFFF8B825)),
      ],
    );
  }
}

class _VehicleSeatButton extends StatelessWidget {
  const _VehicleSeatButton({
    required this.size,
    required this.label,
    required this.status,
    required this.selected,
    required this.enabled,
    required this.onTap,
  });

  final double size;
  final String label;
  final String status;
  final bool selected;
  final bool enabled;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final colors = selected
        ? (
            background: const Color(0xFFF8B825),
            foreground: TravSTheme.navy,
            border: Colors.white,
          )
        : switch (status) {
            'driver' => (
              background: TravSTheme.surfaceSoft,
              foreground: TravSTheme.navyMuted,
              border: Colors.white,
            ),
            'dipesan' => (
              background: const Color(0xFFF0445E),
              foreground: Colors.white,
              border: Colors.white,
            ),
            'terkunci' => (
              background: const Color(0xFF94A3B8),
              foreground: Colors.white,
              border: Colors.white,
            ),
            'nonaktif' => (
              background: const Color(0xFFCBD5E1),
              foreground: Colors.white,
              border: Colors.white,
            ),
            _ => (
              background: const Color(0xFF16C784),
              foreground: Colors.white,
              border: Colors.white,
            ),
          };

    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          color: colors.background,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? Colors.white : colors.border,
            width: selected ? 2.4 : 1.4,
          ),
          boxShadow: [
            BoxShadow(
              color: colors.background.withValues(alpha: 0.24),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Center(
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: colors.foreground,
              fontSize: label.length > 2 ? 9 : 14,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ),
    );
  }
}

class _BadgePill extends StatelessWidget {
  const _BadgePill({
    required this.label,
    required this.color,
    this.textColor = Colors.white,
  });

  final String label;
  final Color color;
  final Color textColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: color == Colors.white ? 1 : 0.18),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(color: textColor, fontWeight: FontWeight.w800),
      ),
    );
  }
}

class _HeroInfoPill extends StatelessWidget {
  const _HeroInfoPill({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18, color: Colors.white),
          const SizedBox(width: 8),
          Text(
            text,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _LegendItem extends StatelessWidget {
  const _LegendItem({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 18,
          height: 18,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(6),
          ),
        ),
        const SizedBox(width: 8),
        Text(label),
      ],
    );
  }
}

class _SeatSummaryItem extends StatelessWidget {
  const _SeatSummaryItem({required this.label, required this.value});

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
          style: theme.textTheme.bodySmall?.copyWith(
            color: TravSTheme.navyMuted,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: theme.textTheme.titleLarge?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _PriceRow extends StatelessWidget {
  const _PriceRow({
    required this.label,
    required this.value,
    this.emphasize = false,
  });

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: emphasize ? TravSTheme.navy : TravSTheme.navyMuted,
                fontWeight: emphasize ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
          ),
          Text(
            value,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: emphasize ? TravSTheme.navy : TravSTheme.navy,
              fontWeight: emphasize ? FontWeight.w800 : FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MetricCardData {
  const _MetricCardData({
    required this.title,
    required this.value,
    required this.subtitle,
    required this.icon,
    this.imageUrl,
  });

  final String title;
  final String value;
  final String subtitle;
  final IconData icon;
  final String? imageUrl;
}

String _formatDate(String iso) {
  if (iso.isEmpty) {
    return '-';
  }

  final date = DateTime.tryParse(iso)?.toLocal();
  if (date == null) {
    return '-';
  }

  return DateFormat('EEEE, dd MMM yyyy - HH:mm', 'id_ID').format(date);
}

String _formatDurationMinutes(int minutes) {
  if (minutes <= 0) {
    return '-';
  }

  final hours = minutes ~/ 60;
  final remainingMinutes = minutes % 60;

  // Durasi dari API tetap menit, lalu diubah agar tidak tampil mentah seperti "405 menit".
  if (hours > 0 && remainingMinutes > 0) {
    return '$hours jam $remainingMinutes menit';
  }
  if (hours > 0) {
    return '$hours jam';
  }
  return '$remainingMinutes menit';
}

String _formatCurrency(num value) {
  return NumberFormat.decimalPattern('id_ID').format(value);
}
