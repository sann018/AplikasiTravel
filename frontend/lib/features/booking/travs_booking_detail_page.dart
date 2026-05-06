import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/models/travs_booking_contract.dart';
import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/theme/travs_theme.dart';
import '../ticket/travs_ticket_detail_page.dart';

class TravSBookingDetailPage extends StatefulWidget {
  const TravSBookingDetailPage({
    required this.session,
    required this.bookingId,
    super.key,
  });

  final TravSUserSession session;
  final int bookingId;

  @override
  State<TravSBookingDetailPage> createState() => _TravSBookingDetailPageState();
}

class _TravSBookingDetailPageState extends State<TravSBookingDetailPage> {
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
          content: Text(
            'Pembayaran simulasi berhasil. Tiket sudah diterbitkan.',
          ),
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

  Future<void> _openMaps(TravSBookingPickupInfo pickup) async {
    final uri = Uri.parse(
      'https://www.google.com/maps/search/?api=1&query=${pickup.latitude},${pickup.longitude}',
    );

    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Google Maps belum dapat dibuka dari perangkat ini.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _openTicketPage(TravSBookingResult booking) async {
    final shouldRefresh = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => TravSTicketDetailPage(
          session: widget.session,
          bookingId: booking.id,
        ),
      ),
    );

    if (shouldRefresh == true && mounted) {
      _shouldRefreshParent = true;
      await _refresh();
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
          title: const Text('Detail Pemesanan'),
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
                  return _BookingErrorState(onRetry: _refresh);
                }

                final booking = snapshot.data!;
                final canSimulate =
                    booking.payment.canSimulate &&
                    booking.payment.status == 'menunggu';

                return RefreshIndicator(
                  color: TravSTheme.orange,
                  onRefresh: _refresh,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
                    children: [
                      _BookingHeaderCard(booking: booking),
                      const SizedBox(height: 16),
                      _DetailSectionCard(
                        title: 'Ringkasan Pembayaran',
                        subtitle:
                            'Status pemesanan, metode bayar, dan total tagihan disinkronkan langsung dari backend.',
                        child: Column(
                          children: [
                            _DetailRow(
                              label: 'Status pemesanan',
                              value: _humanizeStatus(booking.status),
                            ),
                            _DetailRow(
                              label: 'Status pembayaran',
                              value: _humanizeStatus(booking.payment.status),
                            ),
                            _DetailRow(
                              label: 'Metode bayar',
                              value: booking.payment.method.toUpperCase(),
                            ),
                            _DetailRow(
                              label: 'Batas pembayaran',
                              value: _formatDateTime(booking.batasPembayaran),
                            ),
                            const Divider(height: 24),
                            _DetailRow(
                              label: 'Total tagihan',
                              value: 'Rp${_formatCurrency(booking.totalHarga)}',
                              emphasize: true,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                      _DetailSectionCard(
                        title: 'Tiket Pemesanan',
                        subtitle:
                            'Gunakan tombol ini untuk langsung membuka halaman tiket dan QR perjalanan.',
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _DetailRow(
                              label: 'Tiket aktif',
                              value: '${booking.activeTicketCount}',
                            ),
                            const SizedBox(height: 14),
                            ElevatedButton.icon(
                              onPressed: () => _openTicketPage(booking),
                              icon: const Icon(
                                Icons.confirmation_number_rounded,
                              ),
                              label: Text(
                                booking.activeTicketCount > 0
                                    ? 'Buka Tiket'
                                    : 'Lihat Status Tiket',
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                      if (booking.pickup != null)
                        _DetailSectionCard(
                          title: 'Titik Jemput',
                          subtitle:
                              'Koordinat dan alamat jemput ini akan dipakai admin dan pengemudi sebagai referensi pickup.',
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _DetailRow(
                                label: 'Koordinat',
                                value:
                                    '${booking.pickup!.latitude.toStringAsFixed(6)}, ${booking.pickup!.longitude.toStringAsFixed(6)}',
                              ),
                              const SizedBox(height: 8),
                              Text(
                                booking.pickup!.address,
                                style: Theme.of(context).textTheme.bodyMedium
                                    ?.copyWith(color: TravSTheme.navy),
                              ),
                              const SizedBox(height: 14),
                              OutlinedButton.icon(
                                onPressed: () => _openMaps(booking.pickup!),
                                icon: const Icon(Icons.map_outlined),
                                label: const Text('Buka di Google Maps'),
                              ),
                            ],
                          ),
                        ),
                      if (booking.pickup != null) const SizedBox(height: 16),
                      _DetailSectionCard(
                        title: 'Penumpang & Kursi',
                        subtitle:
                            'Data ini akan muncul juga di manifest admin dan pengemudi.',
                        child: Column(
                          children: booking.passengers.isEmpty
                              ? const [
                                  _InlineEmptyState(
                                    title: 'Data penumpang belum tersedia.',
                                  ),
                                ]
                              : booking.passengers
                                    .map(
                                      (passenger) => Padding(
                                        padding: const EdgeInsets.only(
                                          bottom: 12,
                                        ),
                                        child: _PassengerCard(
                                          passenger: passenger,
                                        ),
                                      ),
                                    )
                                    .toList(),
                        ),
                      ),
                      const SizedBox(height: 16),
                      _DetailSectionCard(
                        title: 'Bagasi',
                        subtitle:
                            'Bagasi tambahan yang dimasukkan saat checkout akan tercatat di pemesanan ini.',
                        child: Column(
                          children: booking.baggageItems.isEmpty
                              ? const [
                                  _InlineEmptyState(
                                    title: 'Tidak ada bagasi tambahan.',
                                  ),
                                ]
                              : booking.baggageItems
                                    .map(
                                      (item) => Padding(
                                        padding: const EdgeInsets.only(
                                          bottom: 12,
                                        ),
                                        child: _BaggageCard(item: item),
                                      ),
                                    )
                                    .toList(),
                        ),
                      ),
                      const SizedBox(height: 18),
                      if (canSimulate)
                        ElevatedButton.icon(
                          onPressed: _isSimulatingPayment
                              ? null
                              : () => _simulatePayment(booking),
                          icon: Icon(
                            _isSimulatingPayment
                                ? Icons.sync_rounded
                                : Icons.bolt_rounded,
                          ),
                          label: Text(
                            _isSimulatingPayment
                                ? 'Memproses Simulasi...'
                                : 'Simulasikan Pembayaran',
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

class _DetailSectionCard extends StatelessWidget {
  const _DetailSectionCard({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  final String title;
  final String subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: theme.textTheme.titleLarge?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              subtitle,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 18),
            child,
          ],
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
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
              color: TravSTheme.navy,
              fontWeight: emphasize ? FontWeight.w800 : FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _PassengerCard extends StatelessWidget {
  const _PassengerCard({required this.passenger});

  final TravSBookingPassengerInfo passenger;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Kursi A${passenger.seatNumber}',
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              _StatusBadge(
                label: _humanizeStatus(passenger.statusKehadiran),
                background: Colors.white,
                foreground: TravSTheme.navy,
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            passenger.namaPenumpang,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            passenger.noHpPenumpang,
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
            ),
          ),
        ],
      ),
    );
  }
}

class _BaggageCard extends StatelessWidget {
  const _BaggageCard({required this.item});

  final TravSBookingBaggageInfo item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            item.deskripsiItem,
            style: theme.textTheme.titleMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _MiniBadge(label: '${item.jumlahItem} item'),
              if (item.beratKg != null)
                _MiniBadge(label: '${item.beratKg!.toStringAsFixed(0)} kg'),
              if (item.ukuranCm != null)
                _MiniBadge(label: '${item.ukuranCm} cm'),
            ],
          ),
        ],
      ),
    );
  }
}

class _MiniBadge extends StatelessWidget {
  const _MiniBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(
          color: TravSTheme.navy,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({
    required this.label,
    required this.background,
    required this.foreground,
  });

  final String label;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(color: foreground, fontWeight: FontWeight.w800),
      ),
    );
  }
}

class _InlineEmptyState extends StatelessWidget {
  const _InlineEmptyState({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        title,
        style: Theme.of(
          context,
        ).textTheme.bodyMedium?.copyWith(color: TravSTheme.navyMuted),
      ),
    );
  }
}

class _BookingErrorState extends StatelessWidget {
  const _BookingErrorState({required this.onRetry});

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
                  'Detail pemesanan belum bisa dimuat.',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  'Coba refresh lagi. Jika backend masih menolak, kemungkinan sesi login perlu diperbarui.',
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

String _formatCurrency(double value) {
  return NumberFormat.decimalPattern('id_ID').format(value);
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

String _humanizeStatus(String value) {
  switch (value) {
    case 'menunggu_pembayaran':
      return 'Menunggu Pembayaran';
    case 'terbayar':
      return 'Terbayar';
    case 'dibatalkan':
      return 'Dibatalkan';
    case 'menunggu':
      return 'Menunggu';
    case 'berhasil':
      return 'Berhasil';
    case 'kedaluwarsa':
      return 'Kedaluwarsa';
    case 'hadir':
      return 'Hadir';
    case 'tidak_hadir':
      return 'Tidak Hadir';
    default:
      return value.replaceAll('_', ' ');
  }
}
