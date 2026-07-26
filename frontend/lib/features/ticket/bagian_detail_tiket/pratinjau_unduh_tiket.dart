part of '../travs_ticket_detail_page.dart';

class _TicketPreviewImage extends StatelessWidget {
  const _TicketPreviewImage({
    required this.imageUrl,
    required this.label,
    required this.icon,
    required this.circle,
    this.size = 58,
  });

  final String? imageUrl;
  final String label;
  final IconData icon;
  final bool circle;
  final double size;

  @override
  Widget build(BuildContext context) {
    final hasImage = imageUrl != null && imageUrl!.isNotEmpty;
    final radius = BorderRadius.circular(circle ? 999 : 16);

    Widget fallback() {
      return Container(
        decoration: BoxDecoration(
          color: TravSTheme.surfaceSoft,
          borderRadius: radius,
        ),
        child: Icon(icon, color: TravSTheme.navyMuted),
      );
    }

    Widget image() {
      if (!hasImage) {
        return fallback();
      }

      return Image.network(
        imageUrl!,
        fit: BoxFit.cover,
        errorBuilder: (context, error, stackTrace) => fallback(),
        loadingBuilder: (context, child, progress) {
          if (progress == null) {
            return child;
          }

          return Container(
            color: TravSTheme.surfaceSoft,
            child: const Center(
              child: SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
          );
        },
      );
    }

    return Semantics(
      button: hasImage,
      label: 'Preview foto $label',
      child: InkWell(
        onTap: hasImage
            ? () => _showTicketImagePreview(context, imageUrl!, label)
            : null,
        borderRadius: radius,
        child: ClipRRect(
          borderRadius: radius,
          child: SizedBox(width: size, height: size, child: image()),
        ),
      ),
    );
  }
}

void _showTicketImagePreview(
  BuildContext context,
  String imageUrl,
  String label,
) {
  showDialog<void>(
    context: context,
    builder: (context) {
      return Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        label,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              color: TravSTheme.navy,
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: AspectRatio(
                    aspectRatio: 1,
                    child: Image.network(
                      imageUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) {
                        return Container(
                          color: TravSTheme.surfaceSoft,
                          child: const Icon(
                            Icons.broken_image_outlined,
                            color: TravSTheme.navyMuted,
                          ),
                        );
                      },
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    },
  );
}

class _TicketErrorState extends StatelessWidget {
  const _TicketErrorState({required this.onRetry});

  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 40, 20, 20),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(22),
            child: Column(
              children: [
                const Icon(
                  Icons.error_outline_rounded,
                  color: TravSTheme.danger,
                  size: 42,
                ),
                const SizedBox(height: 14),
                Text(
                  'Data tiket belum bisa dimuat.',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  'Coba muat ulang kembali. Jika pemesanan baru saja berubah status, tiket mungkin masih dalam proses.',
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(color: TravSTheme.navyMuted),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 16),
                ElevatedButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Muat Ulang'),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

String _ticketFileName(
  TravSBookingResult booking,
  TravSBookingPassengerInfo passenger,
) {
  final owner = passenger.namaPenumpang
      .trim()
      .toLowerCase()
      .replaceAll(RegExp(r'[^a-z0-9]+'), '-')
      .replaceAll(RegExp(r'^-+|-+$'), '');
  final bookingCode = booking.kodeBooking
      .trim()
      .toLowerCase()
      .replaceAll(RegExp(r'[^a-z0-9]+'), '-')
      .replaceAll(RegExp(r'^-+|-+$'), '');
  return 'tiket-$bookingCode-$owner.png';
}

String _formatDayDate(String value) {
  if (value.isEmpty) {
    return '-';
  }

  final parsed = DateTime.tryParse(value)?.toLocal();
  if (parsed == null) {
    return '-';
  }

  return DateFormat('EEEE, dd MMM yyyy', 'id_ID').format(parsed);
}

String _formatTime(String value) {
  if (value.isEmpty) {
    return '-';
  }

  final parsed = DateTime.tryParse(value)?.toLocal();
  if (parsed == null) {
    return '-';
  }

  return DateFormat('HH:mm', 'id_ID').format(parsed);
}

String _formatCurrency(num value) {
  return NumberFormat('#,##0', 'id_ID').format(value).replaceAll(',', '.');
}
