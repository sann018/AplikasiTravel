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
  bool _isSimulatingPayment = false;
  bool _shouldRefreshParent = false;

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

  Future<void> _simulatePayment(TravSBookingResult booking) async {
    setState(() {
      _isSimulatingPayment = true;
    });

    try {
      final result = await _apiService.simulatePayment(
        token: widget.session.token,
        bookingId: booking.id,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _future = Future<TravSBookingResult>.value(result);
        _shouldRefreshParent = true;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pembayaran berhasil diverifikasi. QR tiket sudah aktif.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } on TravSApiException catch (error) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.message),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isSimulatingPayment = false;
        });
      }
    }
  }

  void _closePage() {
    Navigator.of(context).pop(_shouldRefreshParent);
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
                final canSimulate =
                    booking.payment.canSimulate &&
                    booking.payment.status == 'menunggu';

                return RefreshIndicator(
                  color: TravSTheme.orange,
                  onRefresh: _refresh,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
                    children: [
                      _TicketHeaderCard(booking: booking),
                      const SizedBox(height: 16),
                      if (tickets.isEmpty)
                        _EmptyTicketCard(
                          booking: booking,
                          isSimulatingPayment: _isSimulatingPayment,
                          canSimulate: canSimulate,
                          onSimulatePayment: () => _simulatePayment(booking),
                        )
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
              booking.isPaid ? 'Tiket Aktif' : 'Tiket Belum Aktif',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            booking.kodeBooking,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            booking.rute,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _formatDateTime(booking.waktuBerangkat),
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: Colors.white70),
          ),
        ],
      ),
    );
  }
}

class _EmptyTicketCard extends StatelessWidget {
  const _EmptyTicketCard({
    required this.booking,
    required this.isSimulatingPayment,
    required this.canSimulate,
    required this.onSimulatePayment,
  });

  final TravSBookingResult booking;
  final bool isSimulatingPayment;
  final bool canSimulate;
  final VoidCallback onSimulatePayment;

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
            if (canSimulate) ...[
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: isSimulatingPayment ? null : onSimulatePayment,
                icon: Icon(
                  isSimulatingPayment ? Icons.sync_rounded : Icons.bolt_rounded,
                ),
                label: Text(
                  isSimulatingPayment
                      ? 'Memproses...'
                      : 'Verifikasi Pembayaran (Dev)',
                ),
              ),
            ],
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
              'Kursi A${passenger.seatNumber} • ${booking.rute}',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: TravSTheme.navyMuted),
            ),
            const SizedBox(height: 20),
            Center(
              child: Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: TravSTheme.line),
                ),
                child: QrImageView(
                  data: qrData,
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
              ),
            ),
            const SizedBox(height: 18),
            _TicketMetaRow(label: 'Kode QR', value: ticket.kodeQr),
            _TicketMetaRow(label: 'Token unik', value: qrData),
            _TicketMetaRow(
              label: 'Waktu berangkat',
              value: _formatDateTime(booking.waktuBerangkat),
            ),
          ],
        ),
      ),
    );
  }
}

class _TicketMetaRow extends StatelessWidget {
  const _TicketMetaRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 104,
            child: Text(
              label,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: TravSTheme.navyMuted),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
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
                  'Coba muat ulang kembali. Jika pemesanan baru saja berubah status, backend mungkin masih menyinkronkan tiketnya.',
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

String _formatDateTime(String value) {
  if (value.isEmpty) {
    return '-';
  }

  final parsed = DateTime.tryParse(value)?.toLocal();
  if (parsed == null) {
    return '-';
  }

  return DateFormat('dd MMM yyyy, HH:mm', 'id_ID').format(parsed);
}
