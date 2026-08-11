import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/models/travs_booking_contract.dart';
import '../../core/models/app_settings.dart';
import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/theme/travs_theme.dart';
import '../payment/travs_payment_preview_page.dart';
import '../ticket/travs_ticket_detail_page.dart';

// Bagian besar dipisah per fungsi agar file utama lebih mudah dipelajari.
part 'bagian_detail_pemesanan/widget_pemesan.dart';
part 'bagian_detail_pemesanan/widget_header_pemesanan.dart';
part 'bagian_detail_pemesanan/widget_detail_pemesanan.dart';
part 'bagian_detail_pemesanan/widget_penumpang_dan_bagasi.dart';
part 'bagian_detail_pemesanan/widget_status.dart';

enum _BookingLocationTab { pickup, dropoff }

enum _DetailPaymentFamily { qris, virtualAccount, cash }

class TravSBookingDetailPage extends StatefulWidget {
  const TravSBookingDetailPage({
    required this.session,
    required this.bookingId,
    this.apiService,
    super.key,
  });

  final TravSUserSession session;
  final int bookingId;
  final TravSApiService? apiService;

  @override
  State<TravSBookingDetailPage> createState() => _TravSBookingDetailPageState();
}

class _TravSBookingDetailPageState extends State<TravSBookingDetailPage>
    with WidgetsBindingObserver {
  late final TravSApiService _apiService;

  late Future<TravSBookingResult> _future;
  bool _shouldRefreshParent = false;
  bool _paymentCheckoutOpened = false;
  bool _isRefreshingPayment = false;
  // Penjaga agar tombol buka tiket tidak bisa dispam sehingga halaman tiket
  // tidak menumpuk berlapis (double-push).
  bool _isOpeningTicketPage = false;
  _BookingLocationTab _selectedLocationTab = _BookingLocationTab.pickup;
  bool _hasInitializedLocationTab = false;
  TravSPaymentMethods? _paymentMethods;
  _DetailPaymentFamily? _selectedPaymentFamily;
  String? _selectedVirtualAccount;
  bool _isChoosingPayment = false;
  String? _paymentSelectionError;
  Timer? _paymentDeadlineTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _apiService = widget.apiService ?? TravSApiService();
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
    // Petugas membaca pemesanan loket melalui endpoint khusus milik loket.
    final booking = widget.session.user.isCounterStaff
        ? await _apiService.loadCounterBookingDetail(
            token: widget.session.token,
            bookingId: widget.bookingId,
          )
        : await _apiService.loadPassengerBookingDetail(
            token: widget.session.token,
            bookingId: widget.bookingId,
          );
    if (booking.payment.canChoose && _paymentMethods == null) {
      try {
        _paymentMethods = await _apiService.loadPaymentMethods();
      } catch (_) {
        // Booking tetap dapat dibaca walau opsi pembayaran gagal dimuat.
      }
    }

    if (!_shouldSynchronizeGatewayPayment(booking)) {
      _schedulePaymentDeadlineRefresh(booking);
      return booking;
    }

    try {
      final refreshedBooking = await _apiService.refreshPaymentStatus(
        token: widget.session.token,
        bookingId: widget.bookingId,
        counterMode: widget.session.user.isCounterStaff,
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

  String? get _selectedPaymentMethod => switch (_selectedPaymentFamily) {
    _DetailPaymentFamily.qris => 'qris',
    _DetailPaymentFamily.virtualAccount => _selectedVirtualAccount,
    _DetailPaymentFamily.cash => 'cash',
    null => null,
  };

  Future<void> _choosePayment(TravSBookingResult booking) async {
    if (_isChoosingPayment) return;
    final method = _selectedPaymentMethod;
    if (method == null) {
      setState(() {
        _paymentSelectionError =
            _selectedPaymentFamily == _DetailPaymentFamily.virtualAccount
            ? 'Pilih bank Virtual Account terlebih dahulu.'
            : 'Pilih metode pembayaran terlebih dahulu.';
      });
      return;
    }

    setState(() {
      _isChoosingPayment = true;
      _paymentSelectionError = null;
    });
    try {
      final updated = await _apiService.chooseBookingPaymentMethod(
        token: widget.session.token,
        bookingId: booking.id,
        paymentMethod: method,
        counterMode: widget.session.user.isCounterStaff,
      );
      if (!mounted) return;
      setState(() {
        _future = Future.value(updated);
        _shouldRefreshParent = true;
      });
    } on TravSApiException catch (error) {
      if (mounted) setState(() => _paymentSelectionError = error.message);
    } finally {
      if (mounted) setState(() => _isChoosingPayment = false);
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
    if (booking.jadwalId <= 0) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Detail jadwal pembayaran belum tersedia.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    setState(() => _isRefreshingPayment = true);
    try {
      // Booking dibuka kembali ke halaman pembayaran internal yang sama.
      final detail = await _apiService.loadScheduleDetailById(booking.jadwalId);
      AppSettings settings;
      try {
        settings = await _apiService.loadMobileSettings();
      } catch (_) {
        settings = AppSettings.defaults;
      }

      if (!mounted) return;
      setState(() => _isRefreshingPayment = false);
      final shouldRefresh = await Navigator.of(context).push<bool>(
        MaterialPageRoute<bool>(
          builder: (_) => TravSPaymentPreviewPage.resume(
            detail: detail,
            appSettings: settings,
            session: widget.session,
            booking: booking,
            apiService: _apiService,
          ),
        ),
      );
      if (!mounted) return;
      if (shouldRefresh == true) {
        _shouldRefreshParent = true;
      }
      await _refresh();
    } on TravSApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.message),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } finally {
      if (mounted) setState(() => _isRefreshingPayment = false);
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
        counterMode: widget.session.user.isCounterStaff,
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
        (booking.payment.canChoose || booking.payment.status == 'menunggu');
  }

  DateTime? _paymentDeadline(TravSBookingResult booking) {
    if (booking.payment.methodFamily == 'cash' ||
        booking.payment.method == 'cash') {
      // Tunai dibayar saat dijemput, jadi deadline digital tidak digunakan.
      return null;
    }

    final raw =
        booking.payment.canChoose &&
            booking.payment.selectionExpiresAt.isNotEmpty
        ? booking.payment.selectionExpiresAt
        : booking.payment.expiredAt.isNotEmpty
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

  Widget _buildPaymentChooser(TravSBookingResult booking) {
    final options = _paymentMethods?.activeOptions ?? const [];
    final vaOptions = options.where((option) => option.family == 'va').toList();

    if (options.isEmpty) {
      return const _InlineEmptyState(
        title:
            'Metode pembayaran belum dapat dimuat. Tarik halaman untuk mencoba lagi.',
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _InternalSectionHeading(title: 'Pilih Metode Pembayaran'),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            final compact = constraints.maxWidth < 420;
            return SegmentedButton<_DetailPaymentFamily>(
              segments: [
                if (options.any((option) => option.family == 'qris'))
                  const ButtonSegment(
                    value: _DetailPaymentFamily.qris,
                    label: Text('QRIS'),
                  ),
                if (vaOptions.isNotEmpty)
                  ButtonSegment(
                    value: _DetailPaymentFamily.virtualAccount,
                    label: Text(
                      compact ? 'VA' : 'Virtual Account',
                      textAlign: TextAlign.center,
                    ),
                  ),
                if (options.any((option) => option.family == 'cash'))
                  const ButtonSegment(
                    value: _DetailPaymentFamily.cash,
                    label: Text('Tunai'),
                  ),
              ],
              selected: _selectedPaymentFamily == null
                  ? const {}
                  : {_selectedPaymentFamily!},
              showSelectedIcon: false,
              emptySelectionAllowed: true,
              // Arah horizontal menjaga tinggi tetap terbatas di dalam
              // daftar detail pada layar ponsel.
              direction: Axis.horizontal,
              expandedInsets: EdgeInsets.zero,
              onSelectionChanged: _isChoosingPayment
                  ? null
                  : (selection) {
                      setState(() {
                        // Pilihan kosong tidak boleh membuat detail gagal
                        // ketika metode aktif ditekan kembali.
                        _selectedPaymentFamily = selection.isEmpty
                            ? null
                            : selection.first;
                        if (_selectedPaymentFamily !=
                            _DetailPaymentFamily.virtualAccount) {
                          _selectedVirtualAccount = null;
                        }
                        _paymentSelectionError = null;
                      });
                    },
            );
          },
        ),
        if (_selectedPaymentFamily == _DetailPaymentFamily.virtualAccount) ...[
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _selectedVirtualAccount,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Pilih bank'),
            items: vaOptions
                .map(
                  (option) => DropdownMenuItem(
                    value: option.code,
                    child: Text(
                      option.label.replaceFirst('Virtual Account ', ''),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                )
                .toList(),
            onChanged: _isChoosingPayment
                ? null
                : (value) {
                    setState(() {
                      _selectedVirtualAccount = value;
                      _paymentSelectionError = null;
                    });
                  },
          ),
        ],
        if (_paymentSelectionError != null) ...[
          const SizedBox(height: 10),
          Text(
            _paymentSelectionError!,
            style: const TextStyle(
              color: TravSTheme.danger,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
        const SizedBox(height: 14),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _isChoosingPayment
                ? null
                : () => _choosePayment(booking),
            child: _isChoosingPayment
                ? const SizedBox.square(
                    dimension: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Buat Pembayaran'),
          ),
        ),
      ],
    );
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
                if (!_hasInitializedLocationTab) {
                  _selectedLocationTab = booking.pickup != null
                      ? _BookingLocationTab.pickup
                      : _BookingLocationTab.dropoff;
                  _hasInitializedLocationTab = true;
                }
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
                      if (booking.isCounterBooking) ...[
                        const SizedBox(height: 12),
                        _CounterBookingNotice(booking: booking),
                      ],
                      const SizedBox(height: 16),
                      _DetailSectionCard(
                        title: 'Informasi Pemesanan',
                        subtitle: '',
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (booking.pemesan != null) ...[
                              const _InternalSectionHeading(title: 'Pemesan'),
                              const SizedBox(height: 12),
                              _RequesterDetails(requester: booking.pemesan!),
                              const Divider(height: 34),
                            ],
                            const _InternalSectionHeading(title: 'Pembayaran'),
                            const SizedBox(height: 8),
                            _DetailRow(
                              label: 'Status pemesanan',
                              value: _humanizeStatus(booking.status),
                              alignValueRight: true,
                            ),
                            _DetailRow(
                              label: 'Status pembayaran',
                              value: booking.payment.canChoose
                                  ? 'Belum dipilih'
                                  : _humanizeStatus(booking.payment.status),
                              alignValueRight: true,
                            ),
                            _DetailRow(
                              label: 'Metode bayar',
                              value: booking.payment.canChoose
                                  ? 'Belum dipilih'
                                  : booking.payment.methodLabel,
                              alignValueRight: true,
                            ),
                            if (booking.payment.methodFamily == 'cash' &&
                                _isPaymentPending(booking))
                              const _DetailRow(
                                label: 'Cara pembayaran',
                                value:
                                    'Bayar kepada pengemudi setelah dijemput dan sebelum naik kendaraan.',
                                alignValueRight: true,
                              ),
                            if (_hasActivePaymentDeadline(booking))
                              _DetailRow(
                                label: 'Batas pembayaran',
                                value: _formatDateTime(paymentDeadline),
                                alignValueRight: true,
                              ),
                            if (booking.payment.paidAt.isNotEmpty)
                              _DetailRow(
                                label: 'Dibayarkan pada',
                                value: _formatDateTime(booking.payment.paidAt),
                                alignValueRight: true,
                              ),
                            const Divider(height: 24),
                            _DetailRow(
                              label: 'Nominal transaksi',
                              value: booking.payment.hasSelectedMethod
                                  ? booking.payment.amount > 0
                                        ? 'Rp${_formatCurrency(booking.payment.amount)}'
                                        : '-'
                                  : 'Rp${_formatCurrency(booking.totalHarga)}',
                              alignValueRight: true,
                            ),
                            if (booking.payment.usesGateway &&
                                booking.payment.providerFee != null)
                              _DetailRow(
                                label: 'Biaya Admin Transaksi',
                                value:
                                    'Rp${_formatCurrency(booking.payment.providerFee!)}',
                                alignValueRight: true,
                              ),
                            _DetailRow(
                              label: 'Total pembayaran',
                              value: booking.payment.totalPayment == null
                                  ? '-'
                                  : 'Rp${_formatCurrency(booking.payment.totalPayment!)}',
                              emphasize: true,
                              alignValueRight: true,
                            ),
                            if (booking.payment.canChoose) ...[
                              const Divider(height: 30),
                              _buildPaymentChooser(booking),
                            ],
                            if (_canContinuePayment(booking)) ...[
                              const SizedBox(height: 16),
                              SizedBox(
                                width: double.infinity,
                                child: ElevatedButton.icon(
                                  onPressed: _isRefreshingPayment
                                      ? null
                                      : () => _continuePayment(booking),
                                  icon: const Icon(Icons.payments_rounded),
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
                            const Divider(height: 34),
                            const _InternalSectionHeading(
                              title: 'Penumpang & Kursi',
                            ),
                            const SizedBox(height: 12),
                            if (booking.passengers.isEmpty)
                              const _InlineEmptyState(
                                title: 'Data penumpang belum tersedia.',
                              )
                            else
                              ...booking.passengers.map(
                                (passenger) => Padding(
                                  padding: const EdgeInsets.only(bottom: 12),
                                  child: _PassengerCard(passenger: passenger),
                                ),
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
                      if (booking.pickup != null || booking.dropoff != null)
                        _DetailSectionCard(
                          title: 'Lokasi Perjalanan',
                          subtitle:
                              'Pilih tab untuk melihat titik awal atau tujuan perjalanan.',
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              LayoutBuilder(
                                builder: (context, constraints) {
                                  final compact = constraints.maxWidth < 360;
                                  return SizedBox(
                                    width: double.infinity,
                                    child: SegmentedButton<_BookingLocationTab>(
                                      segments: [
                                        const ButtonSegment(
                                          value: _BookingLocationTab.pickup,
                                          label: Text('Titik Jemput'),
                                        ),
                                        ButtonSegment(
                                          value: _BookingLocationTab.dropoff,
                                          label: Text(
                                            compact
                                                ? 'Tujuan / Turun'
                                                : 'Titik Tujuan / Turun',
                                            textAlign: TextAlign.center,
                                            maxLines: 2,
                                          ),
                                        ),
                                      ],
                                      selected: {_selectedLocationTab},
                                      showSelectedIcon: false,
                                      expandedInsets: EdgeInsets.zero,
                                      onSelectionChanged: (selection) {
                                        setState(() {
                                          _selectedLocationTab =
                                              selection.first;
                                        });
                                      },
                                    ),
                                  );
                                },
                              ),
                              const SizedBox(height: 18),
                              _BookingLocationContent(
                                location:
                                    _selectedLocationTab ==
                                        _BookingLocationTab.pickup
                                    ? booking.pickup
                                    : booking.dropoff,
                                isPickup:
                                    _selectedLocationTab ==
                                    _BookingLocationTab.pickup,
                                onOpenMaps: _openMaps,
                              ),
                            ],
                          ),
                        ),
                      if (booking.pickup != null || booking.dropoff != null)
                        const SizedBox(height: 16),
                      _DetailSectionCard(
                        title: 'Barang Bawaan',
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

class _CounterBookingNotice extends StatelessWidget {
  const _CounterBookingNotice({required this.booking});

  final TravSBookingResult booking;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: TravSTheme.orange.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.storefront_rounded, color: TravSTheme.orange),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Pemesanan Loket',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  'Dicatat oleh Petugas Loket: ${booking.dibuatOlehNama ?? 'Petugas tidak tersedia'}.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                    height: 1.4,
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
