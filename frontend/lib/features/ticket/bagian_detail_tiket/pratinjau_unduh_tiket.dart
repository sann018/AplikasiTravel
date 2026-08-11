part of '../travs_ticket_detail_page.dart';

class _TicketLandscapeDownloadCard extends StatelessWidget {
  const _TicketLandscapeDownloadCard({
    required this.booking,
    required this.passenger,
  });

  final TravSBookingResult booking;
  final TravSBookingPassengerInfo passenger;

  @override
  Widget build(BuildContext context) {
    final ticket = passenger.ticket!;
    final qrData = ticket.token.isNotEmpty ? ticket.token : ticket.kodeQr;
    final driver = booking.pengemudi;
    final vehicle = booking.kendaraan;
    final scanStatus = ticket.sudahDipindai
        ? 'Sudah Dipindai'
        : 'Belum Dipindai';
    final vehicleName = vehicle == null
        ? 'Kendaraan belum tersedia'
        : '${vehicle.merk} • ${vehicle.plat}';
    final driverName = driver?.nama.trim().isNotEmpty == true
        ? driver!.nama
        : 'Pengemudi belum tersedia';

    return Material(
      color: Colors.transparent,
      child: Container(
        width: 1200,
        height: 675,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(32),
          border: Border.all(color: TravSTheme.line, width: 2),
        ),
        clipBehavior: Clip.antiAlias,
        child: Row(
          children: [
            Container(
              width: 310,
              padding: const EdgeInsets.all(34),
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [TravSTheme.navy, TravSTheme.navySoft],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Spacer(),
                  Text(
                    booking.rute,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 30,
                      height: 1.25,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 28),
                  _LandscapeDarkInfo(
                    label: 'Tanggal keberangkatan',
                    value: _formatDayDate(booking.waktuBerangkat),
                  ),
                  const SizedBox(height: 20),
                  _LandscapeDarkInfo(
                    label: 'Waktu keberangkatan',
                    value: _formatTime(booking.waktuBerangkat),
                  ),
                  const Spacer(),
                  const Text(
                    'RUSLY TRAVEL',
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 16,
                      letterSpacing: 2,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(36, 34, 30, 34),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Pemilik Tiket',
                                style: TextStyle(
                                  color: TravSTheme.navyMuted,
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                passenger.namaPenumpang,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: TravSTheme.navy,
                                  fontSize: 28,
                                  height: 1.15,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 16),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 9,
                          ),
                          decoration: BoxDecoration(
                            color: ticket.sudahDipindai
                                ? const Color(0xFFE8EDF5)
                                : TravSTheme.orangeSoft,
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Text(
                            scanStatus,
                            style: TextStyle(
                              color: ticket.sudahDipindai
                                  ? TravSTheme.navyMuted
                                  : TravSTheme.orange,
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),
                    const Divider(color: TravSTheme.line, height: 1),
                    const SizedBox(height: 22),
                    _LandscapeInfoRow(
                      icon: Icons.event_seat_rounded,
                      label: 'Nomor kursi',
                      value: 'A${passenger.seatNumber}',
                    ),
                    _LandscapeInfoRow(
                      icon: Icons.person_outline_rounded,
                      label: 'Pengemudi',
                      value: driverName,
                      detail: driver?.noHp ?? '',
                    ),
                    _LandscapeInfoRow(
                      icon: Icons.directions_car_filled_rounded,
                      label: 'Kendaraan',
                      value: vehicleName,
                    ),
                    _LandscapeInfoRow(
                      icon: Icons.payments_outlined,
                      label: 'Harga tiket',
                      value:
                          'Rp${_formatCurrency(booking.jumlahKursi > 0 ? booking.subtotalTiket / booking.jumlahKursi : booking.subtotalTiket)}',
                    ),
                    const Spacer(),
                    Text(
                      'Kode pemesanan: ${booking.kodeBooking}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: TravSTheme.navyMuted,
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            Container(
              width: 330,
              color: TravSTheme.surfaceSoft,
              padding: const EdgeInsets.fromLTRB(30, 34, 30, 30),
              child: Column(
                children: [
                  const Text(
                    'QR TIKET',
                    style: TextStyle(
                      color: TravSTheme.navy,
                      fontSize: 20,
                      letterSpacing: 1.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 22),
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(22),
                      border: Border.all(color: TravSTheme.line, width: 2),
                    ),
                    child: QrImageView(
                      data: qrData,
                      size: 230,
                      padding: EdgeInsets.zero,
                      backgroundColor: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 24),
                  const Icon(
                    Icons.verified_user_outlined,
                    color: TravSTheme.orange,
                    size: 30,
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Tunjukkan QR ini hanya kepada petugas atau pengemudi saat keberangkatan.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: TravSTheme.navy,
                      fontSize: 15,
                      height: 1.35,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LandscapeDarkInfo extends StatelessWidget {
  const _LandscapeDarkInfo({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(color: Colors.white70, fontSize: 15),
        ),
        const SizedBox(height: 5),
        Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 20,
            height: 1.25,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _LandscapeInfoRow extends StatelessWidget {
  const _LandscapeInfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.detail = '',
  });

  final IconData icon;
  final String label;
  final String value;
  final String detail;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: TravSTheme.surfaceSoft,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: TravSTheme.navyMuted, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    color: TravSTheme.navyMuted,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: TravSTheme.navy,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (detail.isNotEmpty)
                  Text(
                    detail,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: TravSTheme.navyMuted,
                      fontSize: 13,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

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
