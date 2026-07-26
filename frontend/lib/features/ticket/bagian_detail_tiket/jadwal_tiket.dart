part of '../travs_ticket_detail_page.dart';

class _TicketScheduleInfoPanel extends StatelessWidget {
  const _TicketScheduleInfoPanel({
    required this.booking,
    required this.passenger,
  });

  final TravSBookingResult booking;
  final TravSBookingPassengerInfo passenger;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 360),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: TravSTheme.line),
          ),
          child: Column(
            children: [
              _TicketInfoRow(
                label: 'Hari/Tanggal',
                value: _formatDayDate(booking.waktuBerangkat),
                icon: Icons.calendar_month_rounded,
              ),
              _TicketInfoDivider(),
              _TicketInfoRow(
                label: 'Jam',
                value: _formatTime(booking.waktuBerangkat),
                icon: Icons.schedule_rounded,
              ),
              _TicketInfoDivider(),
              _TicketInfoRow(
                label: 'Kursi',
                value: 'A${passenger.seatNumber}',
                icon: Icons.event_seat_rounded,
              ),
              _TicketInfoDivider(),
              _TicketInfoRow(
                label: 'Harga Tiket',
                value:
                    'Rp${_formatCurrency(booking.subtotalTiket / (booking.jumlahKursi == 0 ? 1 : booking.jumlahKursi))}',
                icon: Icons.payments_rounded,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TicketInfoDivider extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Divider(height: 1, color: TravSTheme.line),
    );
  }
}

class _TicketInfoRow extends StatelessWidget {
  const _TicketInfoRow({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: TravSTheme.surfaceSoft,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, size: 18, color: TravSTheme.navyMuted),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: TravSTheme.navyMuted,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                value,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navy,
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
