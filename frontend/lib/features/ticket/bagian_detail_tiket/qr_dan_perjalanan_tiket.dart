part of '../travs_ticket_detail_page.dart';

class _TicketQrBox extends StatelessWidget {
  const _TicketQrBox({required this.data});

  final String data;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: TravSTheme.line),
      ),
      child: QrImageView(
        data: data,
        size: 220,
        backgroundColor: Colors.white,
        eyeStyle: const QrEyeStyle(
          eyeShape: QrEyeShape.square,
          color: TravSTheme.navy,
        ),
        dataModuleStyle: const QrDataModuleStyle(
          dataModuleShape: QrDataModuleShape.square,
          color: TravSTheme.navy,
        ),
      ),
    );
  }
}

class _TicketTravelInfoPanel extends StatelessWidget {
  const _TicketTravelInfoPanel({
    required this.booking,
    required this.passenger,
    required this.driver,
    required this.vehicle,
  });

  final TravSBookingResult booking;
  final TravSBookingPassengerInfo passenger;
  final TravSBookingDriverInfo? driver;
  final TravSBookingVehicleInfo? vehicle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: TravSTheme.orangeSoft,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.verified_user_outlined,
                  color: TravSTheme.orange,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Tunjukkan QR ini hanya kepada petugas atau pengemudi saat keberangkatan.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          _TicketDriverVehicleCard(driver: driver, vehicle: vehicle),
          const SizedBox(height: 14),
          _TicketScheduleInfoPanel(booking: booking, passenger: passenger),
        ],
      ),
    );
  }
}
