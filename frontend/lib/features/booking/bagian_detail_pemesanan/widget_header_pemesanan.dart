part of '../travs_booking_detail_page.dart';

class _BookingHeaderCard extends StatelessWidget {
  const _BookingHeaderCard({required this.booking});

  final TravSBookingResult booking;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: booking.isPaid
            ? const LinearGradient(
                colors: [Color(0xFF1F9D67), Color(0xFF176D4A)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              )
            : TravSTheme.headerGradient,
        borderRadius: BorderRadius.circular(30),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _StatusBadge(
            label: _humanizeStatus(booking.status),
            background: Colors.white,
            foreground: booking.isPaid ? TravSTheme.success : TravSTheme.navy,
          ),
          const SizedBox(height: 16),
          Text(
            booking.rute,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _HeaderScheduleInfo(
                  label: 'Tanggal keberangkatan',
                  value: _formatDate(booking.waktuBerangkat),
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: _HeaderScheduleInfo(
                  label: 'Waktu keberangkatan',
                  value: _formatTime(booking.waktuBerangkat),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeaderScheduleInfo extends StatelessWidget {
  const _HeaderScheduleInfo({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(color: Colors.white70),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}
