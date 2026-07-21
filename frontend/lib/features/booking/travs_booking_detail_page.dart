import 'dart:async';

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

class _TravSBookingDetailPageState extends State<TravSBookingDetailPage>
    with WidgetsBindingObserver {
  final TravSApiService _apiService = TravSApiService();

  late Future<TravSBookingResult> _future;
  bool _shouldRefreshParent = false;
  bool _paymentCheckoutOpened = false;
  bool _isRefreshingPayment = false;
  // Penjaga agar tombol buka tiket tidak bisa dispam sehingga halaman tiket
  // tidak menumpuk berlapis (double-push).
  bool _isOpeningTicketPage = false;
  Timer? _paymentDeadlineTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _future = _loadBooking();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _paymentDeadlineTimer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed || !_paymentCheckoutOpened) {
      return;
    }

    _paymentCheckoutOpened = false;
    Future<void>.delayed(const Duration(milliseconds: 800), () {
      if (mounted) {
        _refreshPaymentStatus(showMessage: true);
      }
    });
  }

  Future<TravSBookingResult> _loadBooking() async {
    final booking = await _apiService.loadPassengerBookingDetail(
      token: widget.session.token,
      bookingId: widget.bookingId,
    );

    if (!_shouldSynchronizeGatewayPayment(booking)) {
      _schedulePaymentDeadlineRefresh(booking);
      return booking;
    }

    try {
      final refreshedBooking = await _apiService.refreshPaymentStatus(
        token: widget.session.token,
        bookingId: widget.bookingId,
      );
      if (refreshedBooking.isPaid) {
        _shouldRefreshParent = true;
      }
      _schedulePaymentDeadlineRefresh(refreshedBooking);
      return refreshedBooking;
    } catch (_) {
      _schedulePaymentDeadlineRefresh(booking);
      return booking;
    }
  }

  void _schedulePaymentDeadlineRefresh(TravSBookingResult booking) {
    _paymentDeadlineTimer?.cancel();
    final deadline = _paymentDeadline(booking);
    if (!_isPaymentPending(booking) || deadline == null) {
      return;
    }

    final remaining = deadline.difference(DateTime.now());
    if (remaining <= Duration.zero) {
      return;
    }

    _paymentDeadlineTimer = Timer(remaining + const Duration(seconds: 1), () {
      if (mounted) {
        _refresh();
      }
    });
  }

  Future<void> _refresh() async {
    final future = _loadBooking();
    setState(() {
      _future = future;
    });
    await future;
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
    // Cegah klik berulang membuka halaman tiket berlapis-lapis.
    if (_isOpeningTicketPage) {
      return;
    }

    _isOpeningTicketPage = true;
    bool? shouldRefresh;
    try {
      shouldRefresh = await Navigator.of(context).push<bool>(
        MaterialPageRoute<bool>(
          builder: (_) => TravSTicketDetailPage(
            session: widget.session,
            bookingId: booking.id,
          ),
        ),
      );
    } finally {
      _isOpeningTicketPage = false;
    }

    if (shouldRefresh == true && mounted) {
      _shouldRefreshParent = true;
      await _refresh();
    }
  }

  Future<void> _continuePayment(TravSBookingResult booking) async {
    final uri = Uri.tryParse(booking.payment.checkoutUrl);
    if (uri == null || !uri.hasScheme) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Tautan pembayaran belum tersedia.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    _paymentCheckoutOpened = true;
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      _paymentCheckoutOpened = false;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Halaman pembayaran belum dapat dibuka.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  bool _shouldSynchronizeGatewayPayment(TravSBookingResult booking) {
    return !booking.isPaid &&
        booking.status == 'menunggu_pembayaran' &&
        booking.payment.usesGateway;
  }

  Future<void> _refreshPaymentStatus({bool showMessage = false}) async {
    if (_isRefreshingPayment) {
      return;
    }

    setState(() {
      _isRefreshingPayment = true;
    });

    try {
      final refreshedBooking = await _apiService.refreshPaymentStatus(
        token: widget.session.token,
        bookingId: widget.bookingId,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _future = Future<TravSBookingResult>.value(refreshedBooking);
        if (refreshedBooking.isPaid) {
          _shouldRefreshParent = true;
        }
      });

      if (showMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              refreshedBooking.isPaid
                  ? 'Pembayaran berhasil dikonfirmasi. Tiket sudah aktif.'
                  : refreshedBooking.status == 'dibatalkan'
                  ? 'Waktu pembayaran berakhir. Pemesanan sudah dibatalkan.'
                  : 'Pembayaran masih menunggu konfirmasi. Silakan periksa kembali beberapa saat lagi.',
            ),
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } on TravSApiException catch (error) {
      if (mounted && showMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(error.message),
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } catch (_) {
      if (mounted && showMessage) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Status pembayaran belum dapat diperbarui. Silakan coba lagi.',
            ),
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isRefreshingPayment = false;
        });
      }
    }
  }

  bool _canContinuePayment(TravSBookingResult booking) {
    return !booking.isPaid &&
        booking.status == 'menunggu_pembayaran' &&
        booking.payment.hasCheckoutUrl &&
        _hasActivePaymentDeadline(booking);
  }

  bool _isPaymentPending(TravSBookingResult booking) {
    return !booking.isPaid &&
        booking.status == 'menunggu_pembayaran' &&
        booking.payment.status == 'menunggu';
  }

  DateTime? _paymentDeadline(TravSBookingResult booking) {
    final raw = booking.payment.expiredAt.isNotEmpty
        ? booking.payment.expiredAt
        : booking.batasPembayaran;
    return DateTime.tryParse(raw)?.toLocal();
  }

  bool _hasActivePaymentDeadline(TravSBookingResult booking) {
    final deadline = _paymentDeadline(booking);
    return _isPaymentPending(booking) &&
        deadline != null &&
        deadline.isAfter(DateTime.now());
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
                // Detail penumpang memakai deadline pembayaran yang sama dengan
                // transaksi. Field pemesanan tetap menjadi fallback data lama.
                final paymentDeadline = booking.payment.expiredAt.isNotEmpty
                    ? booking.payment.expiredAt
                    : booking.batasPembayaran;
                return RefreshIndicator(
                  color: TravSTheme.orange,
                  onRefresh: _refresh,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
                    children: [
                      _BookingHeaderCard(booking: booking),
                      const SizedBox(height: 16),
                      if (booking.pemesan != null) ...[
                        _RequesterCard(requester: booking.pemesan!),
                        const SizedBox(height: 16),
                      ],
                      _DetailSectionCard(
                        title: 'Ringkasan Pembayaran',
                        subtitle:
                            'Status pemesanan, metode bayar, dan total tagihan selalu diperbarui.',
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
                            if (_hasActivePaymentDeadline(booking))
                              _DetailRow(
                                label: 'Batas pembayaran',
                                value: _formatDateTime(paymentDeadline),
                              ),
                            if (booking.payment.paidAt.isNotEmpty)
                              _DetailRow(
                                label: 'Dibayarkan pada',
                                value: _formatDateTime(booking.payment.paidAt),
                              ),
                            const Divider(height: 24),
                            _DetailRow(
                              label: 'Total tagihan',
                              value: 'Rp${_formatCurrency(booking.totalHarga)}',
                              emphasize: true,
                            ),
                            if (_canContinuePayment(booking)) ...[
                              const SizedBox(height: 16),
                              SizedBox(
                                width: double.infinity,
                                child: ElevatedButton.icon(
                                  onPressed: _isRefreshingPayment
                                      ? null
                                      : () => _continuePayment(booking),
                                  icon: const Icon(Icons.payment_rounded),
                                  label: const Text('Lanjutkan Pembayaran'),
                                ),
                              ),
                              const SizedBox(height: 10),
                              SizedBox(
                                width: double.infinity,
                                child: OutlinedButton.icon(
                                  onPressed: _isRefreshingPayment
                                      ? null
                                      : () => _refreshPaymentStatus(
                                          showMessage: true,
                                        ),
                                  icon: _isRefreshingPayment
                                      ? const SizedBox.square(
                                          dimension: 18,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                          ),
                                        )
                                      : const Icon(Icons.sync_rounded),
                                  label: Text(
                                    _isRefreshingPayment
                                        ? 'Memeriksa Pembayaran'
                                        : 'Periksa Status Pembayaran',
                                  ),
                                ),
                              ),
                            ],
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
                              label: booking.status == 'selesai'
                                  ? 'Tiket selesai'
                                  : 'Tiket aktif',
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
                              'Lokasi yang dipilih sebagai titik awal perjalanan.',
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _DetailRow(
                                label: 'Koordinat',
                                value:
                                    '${booking.pickup!.latitude.toStringAsFixed(6)}, ${booking.pickup!.longitude.toStringAsFixed(6)}',
                                stacked: true,
                              ),
                              const SizedBox(height: 8),
                              Text(
                                booking.pickup!.address,
                                style: Theme.of(context).textTheme.bodyMedium
                                    ?.copyWith(color: TravSTheme.navy),
                              ),
                              if ((booking.pickup!.note ?? '')
                                  .trim()
                                  .isNotEmpty) ...[
                                const SizedBox(height: 10),
                                _DetailRow(
                                  label: 'Patokan jemput',
                                  value: booking.pickup!.note!.trim(),
                                  stacked: true,
                                ),
                              ],
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
                      if (booking.dropoff != null)
                        _DetailSectionCard(
                          title: 'Titik Tujuan / Turun',
                          subtitle:
                              'Lokasi yang dipilih sebagai tujuan akhir perjalanan.',
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _DetailRow(
                                label: 'Koordinat',
                                value:
                                    '${booking.dropoff!.latitude.toStringAsFixed(6)}, ${booking.dropoff!.longitude.toStringAsFixed(6)}',
                                stacked: true,
                              ),
                              const SizedBox(height: 8),
                              Text(
                                booking.dropoff!.address,
                                style: Theme.of(context).textTheme.bodyMedium
                                    ?.copyWith(color: TravSTheme.navy),
                              ),
                              if ((booking.dropoff!.note ?? '')
                                  .trim()
                                  .isNotEmpty) ...[
                                const SizedBox(height: 10),
                                _DetailRow(
                                  label: 'Patokan turun',
                                  value: booking.dropoff!.note!.trim(),
                                  stacked: true,
                                ),
                              ],
                              const SizedBox(height: 14),
                              OutlinedButton.icon(
                                onPressed: () => _openMaps(booking.dropoff!),
                                icon: const Icon(Icons.map_outlined),
                                label: const Text('Buka di Google Maps'),
                              ),
                            ],
                          ),
                        ),
                      if (booking.dropoff != null) const SizedBox(height: 16),
                      _DetailSectionCard(
                        title: 'Penumpang & Kursi',
                        subtitle:
                            'Informasi penumpang dan nomor kursi yang telah dipesan.',
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
                        title: 'Barang Bawaan',
                        initiallyExpanded: false,
                        subtitle:
                            'Barang yang dicatat saat pemesanan tampil di bagian ini.',
                        child: Column(
                          children: booking.baggageItems.isEmpty
                              ? const [
                                  _InlineEmptyState(
                                    title:
                                        'Tidak ada barang bawaan yang dicatat.',
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

class _RequesterCard extends StatelessWidget {
  const _RequesterCard({required this.requester});

  final TravSBookingRequesterInfo requester;

  @override
  Widget build(BuildContext context) {
    final imageUrl = TravSApiService.resolveMediaUrl(requester.fotoUrl);

    return _DetailSectionCard(
      title: 'Pemesanan Atas',
      subtitle: '',
      child: Row(
        children: [
          _RequesterAvatar(name: requester.nama, imageUrl: imageUrl),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  requester.nama,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  requester.email,
                  style: Theme.of(
                    context,
                  ).textTheme.bodySmall?.copyWith(color: TravSTheme.navyMuted),
                ),
                const SizedBox(height: 2),
                Text(
                  requester.noHp,
                  style: Theme.of(
                    context,
                  ).textTheme.bodySmall?.copyWith(color: TravSTheme.navyMuted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RequesterAvatar extends StatelessWidget {
  const _RequesterAvatar({required this.name, this.imageUrl});

  final String name;
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty
        ? 'P'
        : name.trim().substring(0, 1).toUpperCase();

    return ClipOval(
      child: Container(
        width: 54,
        height: 54,
        color: TravSTheme.navy,
        child: imageUrl == null
            ? _RequesterInitial(initial: initial)
            : Image.network(
                imageUrl!,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) =>
                    _RequesterInitial(initial: initial),
              ),
      ),
    );
  }
}

class _RequesterInitial extends StatelessWidget {
  const _RequesterInitial({required this.initial});

  final String initial;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Text(
        initial,
        style: Theme.of(context).textTheme.titleLarge?.copyWith(
          color: Colors.white,
          fontWeight: FontWeight.w900,
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

class _DetailSectionCard extends StatefulWidget {
  const _DetailSectionCard({
    required this.title,
    required this.subtitle,
    required this.child,
    this.initiallyExpanded = true,
  });

  final String title;
  final String subtitle;
  final Widget child;
  final bool initiallyExpanded;

  @override
  State<_DetailSectionCard> createState() => _DetailSectionCardState();
}

class _DetailSectionCardState extends State<_DetailSectionCard> {
  late bool _expanded;

  @override
  void initState() {
    super.initState();
    _expanded = widget.initiallyExpanded;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => setState(() => _expanded = !_expanded),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      widget.title,
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: TravSTheme.navy,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  Icon(
                    _expanded
                        ? Icons.keyboard_arrow_up_rounded
                        : Icons.keyboard_arrow_down_rounded,
                    color: TravSTheme.navy,
                  ),
                ],
              ),
            ),
            if (widget.subtitle.trim().isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                widget.subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
            ],
            AnimatedCrossFade(
              duration: const Duration(milliseconds: 180),
              crossFadeState: _expanded
                  ? CrossFadeState.showSecond
                  : CrossFadeState.showFirst,
              firstChild: const SizedBox.shrink(),
              secondChild: Padding(
                padding: const EdgeInsets.only(top: 18),
                child: widget.child,
              ),
            ),
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
    this.stacked = false,
  });

  final String label;
  final String value;
  final bool emphasize;
  final bool stacked;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    final labelStyle = theme.textTheme.labelLarge?.copyWith(
      color: emphasize ? TravSTheme.navy : TravSTheme.navyMuted,
      fontWeight: emphasize ? FontWeight.w800 : FontWeight.w600,
    );
    final valueStyle = theme.textTheme.bodyMedium?.copyWith(
      color: TravSTheme.navy,
      fontWeight: emphasize ? FontWeight.w800 : FontWeight.w600,
    );

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: stacked
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: labelStyle),
                const SizedBox(height: 6),
                Text(value, style: valueStyle),
              ],
            )
          : Row(
              children: [
                Expanded(child: Text(label, style: labelStyle)),
                const SizedBox(width: 14),
                Flexible(
                  child: Text(
                    value,
                    textAlign: TextAlign.end,
                    style: valueStyle,
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
                  style: theme.textTheme.titleSmall?.copyWith(
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
    final baggagePhotoUrls = _uniquePhotoUrls(
      item.fotoUrls.map(TravSApiService.resolveMediaUrl).whereType<String>(),
    ).take(3).toList();

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
            style: theme.textTheme.titleSmall?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _MiniBadge(
                label: item.posisiBarang == 'dipegang_sendiri'
                    ? 'Dipegang sendiri'
                    : 'Masuk bagasi',
              ),
            ],
          ),
          if (baggagePhotoUrls.isNotEmpty) ...[
            const SizedBox(height: 14),
            GridView.builder(
              itemCount: baggagePhotoUrls.length,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                crossAxisSpacing: 8,
                mainAxisSpacing: 8,
              ),
              itemBuilder: (context, index) {
                final photoUrl = baggagePhotoUrls[index];
                return GestureDetector(
                  onTap: () => _showPhotoPreview(context, photoUrl),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(14),
                    child: Image.network(
                      photoUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) {
                        return Container(
                          color: Colors.white,
                          alignment: Alignment.center,
                          child: Text(
                            'Foto tidak tersedia',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: TravSTheme.navyMuted,
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                );
              },
            ),
          ] else ...[
            const SizedBox(height: 14),
            Text(
              'Foto barang belum tersedia.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
          ],
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
                  'Coba refresh lagi. Jika masih gagal, silakan login ulang.',
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

List<String> _uniquePhotoUrls(Iterable<String> urls) {
  final seen = <String>{};
  final result = <String>[];
  for (final url in urls) {
    final normalized = url.trim();
    if (normalized.isEmpty || !seen.add(normalized)) {
      continue;
    }
    result.add(normalized);
  }
  return result;
}

void _showPhotoPreview(BuildContext context, String imageUrl) {
  showDialog<void>(
    context: context,
    builder: (context) => Dialog(
      insetPadding: const EdgeInsets.all(20),
      backgroundColor: Colors.transparent,
      child: Stack(
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 500, maxHeight: 500),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: Image.network(
                  imageUrl,
                  fit: BoxFit.contain,
                  errorBuilder: (context, error, stackTrace) => Container(
                    padding: const EdgeInsets.all(20),
                    color: Colors.white,
                    child: const Text('Foto belum dapat ditampilkan.'),
                  ),
                ),
              ),
            ),
          ),
          Positioned(
            top: 8,
            left: 8,
            child: Material(
              color: Colors.white,
              shape: const CircleBorder(),
              child: IconButton(
                tooltip: 'Tutup preview',
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.arrow_back_rounded),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

String _formatDateTime(String value) {
  if (value.isEmpty) {
    return '-';
  }

  final parsed = DateTime.tryParse(value)?.toLocal();
  if (parsed == null) {
    return '-';
  }

  return DateFormat('dd MMM yyyy\nHH:mm', 'id_ID').format(parsed);
}

String _formatDate(String value) {
  final parsed = DateTime.tryParse(value)?.toLocal();
  return parsed == null
      ? '-'
      : DateFormat('d MMMM yyyy', 'id_ID').format(parsed);
}

String _formatTime(String value) {
  final parsed = DateTime.tryParse(value)?.toLocal();
  return parsed == null ? '-' : DateFormat('HH:mm', 'id_ID').format(parsed);
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
