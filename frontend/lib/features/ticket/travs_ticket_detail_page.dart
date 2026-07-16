import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/models/travs_booking_contract.dart';
import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/theme/travs_theme.dart';

class TravSTicketDetailPage extends StatefulWidget {
  const TravSTicketDetailPage({
    required this.session,
    required this.bookingId,
    super.key,
  });

  final TravSUserSession session;
  final int bookingId;

  @override
  State<TravSTicketDetailPage> createState() => _TravSTicketDetailPageState();
}

class _TravSTicketDetailPageState extends State<TravSTicketDetailPage> {
  final TravSApiService _apiService = TravSApiService();

  late Future<TravSBookingResult> _future;

  @override
  void initState() {
    super.initState();
    _future = _loadBooking();
  }

  Future<TravSBookingResult> _loadBooking() {
    return _apiService.loadPassengerBookingDetail(
      token: widget.session.token,
      bookingId: widget.bookingId,
    );
  }

  Future<void> _refresh() async {
    final future = _loadBooking();
    setState(() {
      _future = future;
    });
    await future;
  }

  void _closePage() {
    Navigator.of(context).pop(false);
  }

  @override
  Widget build(BuildContext context) {
    return PopScope<bool>(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) {
          _closePage();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            onPressed: _closePage,
            icon: const Icon(Icons.arrow_back_rounded),
          ),
          title: const Text('Tiket & QR'),
        ),
        body: Container(
          decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
          child: SafeArea(
            child: FutureBuilder<TravSBookingResult>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting &&
                    !snapshot.hasData) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (snapshot.hasError && !snapshot.hasData) {
                  return _TicketErrorState(onRetry: _refresh);
                }

                final booking = snapshot.data!;
                final tickets = booking.passengers
                    .where((item) => item.ticket != null)
                    .toList();
                return RefreshIndicator(
                  color: TravSTheme.orange,
                  onRefresh: _refresh,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
                    children: [
                      _TicketHeaderCard(booking: booking),
                      const SizedBox(height: 16),
                      if (tickets.isEmpty)
                        _EmptyTicketCard(booking: booking)
                      else
                        ...tickets.map(
                          (passenger) => Padding(
                            padding: const EdgeInsets.only(bottom: 16),
                            child: _TicketCard(
                              booking: booking,
                              passenger: passenger,
                            ),
                          ),
                        ),
                    ],
                  ),
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}

class _TicketHeaderCard extends StatelessWidget {
  const _TicketHeaderCard({required this.booking});

  final TravSBookingResult booking;

  @override
  Widget build(BuildContext context) {
    final departure = DateTime.tryParse(booking.waktuBerangkat)?.toLocal();
    final isCompleted =
        booking.status == 'selesai' ||
        (departure != null && departure.isBefore(DateTime.now()));
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
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(
              isCompleted
                  ? 'Perjalanan Selesai'
                  : booking.isPaid
                  ? 'Tiket Aktif'
                  : 'Tiket Belum Aktif',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w800,
              ),
            ),
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

class _EmptyTicketCard extends StatelessWidget {
  const _EmptyTicketCard({required this.booking});

  final TravSBookingResult booking;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          children: [
            const Icon(
              Icons.qr_code_2_outlined,
              color: TravSTheme.navyMuted,
              size: 44,
            ),
            const SizedBox(height: 14),
            Text(
              booking.isPaid
                  ? 'Tiket belum diterbitkan untuk pemesanan ini.'
                  : 'QR tiket belum aktif karena pembayaran belum terverifikasi.',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              booking.isPaid
                  ? 'Refresh kembali beberapa saat lagi atau cek detail pemesanan untuk memastikan tiket telah diterbitkan.'
                  : 'Begitu status pembayaran berubah menjadi berhasil, QR unik akan muncul untuk setiap kursi penumpang.',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: TravSTheme.navyMuted),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}

class _TicketCard extends StatelessWidget {
  const _TicketCard({required this.booking, required this.passenger});

  final TravSBookingResult booking;
  final TravSBookingPassengerInfo passenger;

  @override
  Widget build(BuildContext context) {
    final ticket = passenger.ticket!;
    final qrData = ticket.token.isNotEmpty ? ticket.token : ticket.kodeQr;
    final driver = booking.pengemudi;
    final vehicle = booking.kendaraan;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    passenger.namaPenumpang,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: ticket.sudahDipindai
                        ? const Color(0xFFE8EDF5)
                        : TravSTheme.orangeSoft,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    ticket.sudahDipindai ? 'Sudah Dipindai' : 'Belum Dipindai',
                    style: TextStyle(
                      color: ticket.sudahDipindai
                          ? TravSTheme.navyMuted
                          : TravSTheme.orange,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Kursi A${passenger.seatNumber} - ${booking.rute}',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: TravSTheme.navyMuted),
            ),
            const SizedBox(height: 20),
            LayoutBuilder(
              builder: (context, constraints) {
                final qrBox = _TicketQrBox(data: qrData);
                final infoPanel = _TicketTravelInfoPanel(
                  booking: booking,
                  passenger: passenger,
                  driver: driver,
                  vehicle: vehicle,
                );

                if (constraints.maxWidth >= 720) {
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(child: Center(child: qrBox)),
                      const SizedBox(width: 20),
                      Expanded(child: infoPanel),
                    ],
                  );
                }

                return Column(
                  children: [
                    Center(child: qrBox),
                    const SizedBox(height: 18),
                    infoPanel,
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

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
                icon: Icons.calendar_today_rounded,
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
                icon: Icons.payments_outlined,
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

String _formatCurrency(double value) {
  return NumberFormat('#,##0', 'id_ID').format(value).replaceAll(',', '.');
}
