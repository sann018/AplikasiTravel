part of '../travs_ticket_detail_page.dart';

class _TicketHeaderCard extends StatelessWidget {
  const _TicketHeaderCard({required this.booking});

  final TravSBookingResult booking;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: booking.isPaid
            ? const LinearGradient(
                colors: [TravSTheme.navy, TravSTheme.navySoft],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              )
            : const LinearGradient(
                colors: [Color(0xFF48556A), Color(0xFF28313F)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
        borderRadius: BorderRadius.circular(30),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
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
                child: _TicketHeaderScheduleInfo(
                  label: 'Tanggal keberangkatan',
                  value: _formatDayDate(booking.waktuBerangkat),
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: _TicketHeaderScheduleInfo(
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

class _TicketHeaderScheduleInfo extends StatelessWidget {
  const _TicketHeaderScheduleInfo({required this.label, required this.value});

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
