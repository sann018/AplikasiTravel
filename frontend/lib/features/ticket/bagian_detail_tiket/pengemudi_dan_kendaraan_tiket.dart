part of '../travs_ticket_detail_page.dart';

class _TicketDriverVehicleCard extends StatelessWidget {
  const _TicketDriverVehicleCard({required this.driver, required this.vehicle});

  final TravSBookingDriverInfo? driver;
  final TravSBookingVehicleInfo? vehicle;

  @override
  Widget build(BuildContext context) {
    final driverPhoto = TravSApiService.resolveMediaUrl(driver?.fotoUrl);
    final vehiclePhoto = TravSApiService.resolveMediaUrl(vehicle?.fotoUrl);
    final vehicleName = vehicle?.merk ?? 'Armada belum tersedia';
    final vehiclePlate = vehicle?.plat ?? 'Nopol belum tersedia';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: TravSTheme.line),
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final useCompactLayout = constraints.maxWidth < 260;
          final content = [
            _TicketPersonOrVehicleInfo(
              imageUrl: driverPhoto,
              previewLabel: driver?.nama ?? 'Sopir',
              imageIcon: Icons.person_rounded,
              circleImage: true,
              title: driver?.nama ?? 'Sopir belum ditentukan',
              subtitle: driver?.noHp ?? 'Nomor HP belum tersedia',
            ),
            _TicketPersonOrVehicleInfo(
              imageUrl: vehiclePhoto,
              previewLabel: vehicleName,
              imageIcon: Icons.directions_car_filled_rounded,
              circleImage: false,
              title: vehicleName,
              subtitle: vehiclePlate,
              reverse: !useCompactLayout,
            ),
          ];

          if (useCompactLayout) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                content[0],
                const SizedBox(height: 12),
                Divider(height: 1, color: TravSTheme.line),
                const SizedBox(height: 12),
                content[1],
              ],
            );
          }

          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: content[0]),
              const SizedBox(width: 12),
              Container(width: 1, height: 64, color: TravSTheme.line),
              const SizedBox(width: 12),
              Expanded(child: content[1]),
            ],
          );
        },
      ),
    );
  }
}

class _TicketPersonOrVehicleInfo extends StatelessWidget {
  const _TicketPersonOrVehicleInfo({
    required this.imageUrl,
    required this.previewLabel,
    required this.imageIcon,
    required this.circleImage,
    required this.title,
    required this.subtitle,
    this.reverse = false,
  });

  final String? imageUrl;
  final String previewLabel;
  final IconData imageIcon;
  final bool circleImage;
  final String title;
  final String subtitle;
  final bool reverse;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final image = _TicketPreviewImage(
      imageUrl: imageUrl,
      label: previewLabel,
      icon: imageIcon,
      circle: circleImage,
      size: 52,
    );
    final text = Expanded(
      child: Column(
        crossAxisAlignment: reverse
            ? CrossAxisAlignment.end
            : CrossAxisAlignment.start,
        children: [
          Text(
            title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            textAlign: reverse ? TextAlign.end : TextAlign.start,
            style: theme.textTheme.titleSmall?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            textAlign: reverse ? TextAlign.end : TextAlign.start,
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: reverse
          ? [text, const SizedBox(width: 10), image]
          : [image, const SizedBox(width: 10), text],
    );
  }
}
