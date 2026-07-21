import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/models/app_role_preview.dart';
import '../../core/models/app_settings.dart';
import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/theme/travs_theme.dart';
import '../../core/widgets/travs_async_state.dart';
import '../payment/travs_payment_preview_page.dart';

void _showScheduleImagePreview(
  BuildContext context, {
  required String imageUrl,
  required String title,
}) {
  showDialog<void>(
    context: context,
    builder: (dialogContext) => Dialog(
      insetPadding: const EdgeInsets.all(24),
      backgroundColor: Colors.transparent,
      child: Stack(
        children: [
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ConstrainedBox(
                  constraints: const BoxConstraints(
                    maxWidth: 500,
                    maxHeight: 500,
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(18),
                    child: InteractiveViewer(
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
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 10,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    title,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
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
                onPressed: () => Navigator.of(dialogContext).pop(),
                icon: const Icon(Icons.arrow_back_rounded),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class TravSScheduleDetailPage extends StatefulWidget {
  const TravSScheduleDetailPage({
    required this.schedule,
    required this.selectedRole,
    required this.appSettings,
    this.session,
    super.key,
  });

  final TravSSchedule schedule;
  final AppRolePreview selectedRole;
  final AppSettings appSettings;
  final TravSUserSession? session;

  @override
  State<TravSScheduleDetailPage> createState() =>
      _TravSScheduleDetailPageState();
}

class _TravSScheduleDetailPageState extends State<TravSScheduleDetailPage> {
  final TravSApiService _apiService = TravSApiService();
  late Future<TravSScheduleDetail> _future;

  int _seatCount = 0;
  final Set<int> _selectedSeatNumbers = <int>{};
  bool _isPreparingCheckout = false;
  bool _isRefreshingSeatAvailability = false;
  Timer? _seatRefreshTimer;

  bool get _canUseBookingForm =>
      widget.selectedRole == AppRolePreview.passenger && widget.session != null;

  String get _bookingLockedMessage {
    if (widget.selectedRole == AppRolePreview.guest) {
      return 'Mode tamu hanya bisa melihat jadwal dan status kursi. Login sebagai penumpang dulu agar pilihan kursi dapat dikunci dengan aman.';
    }

    if (widget.selectedRole == AppRolePreview.driver) {
      return 'Mode pengemudi hanya bisa melihat informasi kursi untuk kebutuhan manifes.';
    }

    return 'Sesi penumpang belum aktif. Silakan masuk ulang sebelum memilih kursi.';
  }

  @override
  void initState() {
    super.initState();
    _future = _loadDetail();
    // Status kursi disegarkan berkala agar lock dari perangkat lain segera
    // terlihat dan kursi yang dilepas dapat dipilih kembali tanpa buka ulang halaman.
    _seatRefreshTimer = Timer.periodic(
      const Duration(seconds: 3),
      (_) => _refreshSeatAvailability(),
    );
  }

  @override
  void dispose() {
    _seatRefreshTimer?.cancel();
    super.dispose();
  }

  Future<TravSScheduleDetail> _loadDetail() async {
    final detail = await _apiService.loadScheduleDetail(widget.schedule);
    final maxSeat = _maxSeatCount(detail);
    final availableSeatNumbers = detail.seatMap
        .where((seat) => seat.status == 'tersedia')
        .map((seat) => seat.seatNumber)
        .toSet();

    if (mounted) {
      setState(() {
        _selectedSeatNumbers.removeWhere(
          (seatNumber) => !availableSeatNumbers.contains(seatNumber),
        );
        while (_selectedSeatNumbers.length > maxSeat &&
            _selectedSeatNumbers.isNotEmpty) {
          _selectedSeatNumbers.remove(_selectedSeatNumbers.last);
        }
        _seatCount = _selectedSeatNumbers.length;
      });
    }

    return detail;
  }

  Future<void> _refresh() async {
    final future = _loadDetail();
    setState(() {
      _future = future;
    });
    await future;
  }

  Future<void> _refreshSeatAvailability() async {
    if (!mounted || _isPreparingCheckout || _isRefreshingSeatAvailability) {
      return;
    }

    _isRefreshingSeatAvailability = true;
    try {
      final detail = await _apiService.loadScheduleDetail(widget.schedule);
      if (!mounted) {
        return;
      }

      final maxSeat = _maxSeatCount(detail);
      final availableSeatNumbers = detail.seatMap
          .where((seat) => seat.status == 'tersedia')
          .map((seat) => seat.seatNumber)
          .toSet();

      setState(() {
        _selectedSeatNumbers.removeWhere(
          (seatNumber) => !availableSeatNumbers.contains(seatNumber),
        );
        while (_selectedSeatNumbers.length > maxSeat &&
            _selectedSeatNumbers.isNotEmpty) {
          _selectedSeatNumbers.remove(_selectedSeatNumbers.last);
        }
        _seatCount = _selectedSeatNumbers.length;
        _future = Future<TravSScheduleDetail>.value(detail);
      });
    } catch (_) {
      // Kegagalan refresh otomatis tidak menutup halaman. Refresh manual tetap
      // tersedia untuk menampilkan pesan API secara jelas kepada pengguna.
    } finally {
      _isRefreshingSeatAvailability = false;
    }
  }

  int _maxSeatCount(TravSScheduleDetail detail) {
    final available = math.min(
      detail.schedule.sisaKursi,
      widget.appSettings.maxSeatPerBooking,
    );
    return math.max(0, available);
  }

  int get _registeredPassengerCount => math.max(1, _selectedSeatNumbers.length);

  void _toggleSeatSelection(TravSScheduleDetail detail, TravSSeatItem seat) {
    if (!_canUseBookingForm) {
      _showStageMessage(_bookingLockedMessage);
      return;
    }

    if (seat.status != 'tersedia') {
      return;
    }

    final maxSeatCount = _maxSeatCount(detail);
    if (!_selectedSeatNumbers.contains(seat.seatNumber) &&
        _selectedSeatNumbers.length >= maxSeatCount &&
        maxSeatCount > 0) {
      _showStageMessage('Maksimal $maxSeatCount kursi per transaksi.');
      return;
    }

    setState(() {
      if (_selectedSeatNumbers.contains(seat.seatNumber)) {
        _selectedSeatNumbers.remove(seat.seatNumber);
      } else if (_selectedSeatNumbers.length < maxSeatCount) {
        _selectedSeatNumbers.add(seat.seatNumber);
      }

      _seatCount = _selectedSeatNumbers.length;
    });
  }

  void _clearSeatSelection(TravSScheduleDetail detail) {
    setState(() {
      _selectedSeatNumbers.clear();
      _seatCount = 0;
    });
  }

  void _showStageMessage(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Detail Jadwal & Pilih Kursi')),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFFF9FBFE), Color(0xFFF0F4FA)],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: SafeArea(
          child: FutureBuilder<TravSScheduleDetail>(
            future: _future,
            builder: (context, snapshot) {
              // Spinner memberi tanda bahwa detail jadwal masih diambil dari backend.
              if (snapshot.connectionState == ConnectionState.waiting &&
                  !snapshot.hasData) {
                return TravSAsyncState.loading(
                  message: 'Memuat detail jadwal...',
                );
              }

              // Saat gagal, tampilkan pesan dan tombol untuk mencoba memuat ulang.
              if (snapshot.hasError && !snapshot.hasData) {
                return TravSAsyncState.error(
                  error: snapshot.error!,
                  onRetry: _refresh,
                );
              }

              final detail = snapshot.data!;
              final estimate = _estimate(detail);
              final isDriver = widget.selectedRole == AppRolePreview.driver;
              final isSoldOut = detail.schedule.sisaKursi <= 0;

              return RefreshIndicator(
                color: TravSTheme.orange,
                onRefresh: _refresh,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
                  children: [
                    _HeroCard(
                      detail: detail,
                      selectedRole: widget.selectedRole,
                    ),
                    const SizedBox(height: 16),
                    _InfoGrid(detail: detail),
                    const SizedBox(height: 16),
                    _SeatMapCard(
                      detail: detail,
                      selectedSeatNumbers: _selectedSeatNumbers,
                      maxSeatCount: _maxSeatCount(detail),
                      canSelectSeats: _canUseBookingForm,
                      lockMessage: _bookingLockedMessage,
                      onLockedTap: () =>
                          _showStageMessage(_bookingLockedMessage),
                      onSeatTap: (seat) => _toggleSeatSelection(detail, seat),
                    ),
                    const SizedBox(height: 16),
                    if (isDriver)
                      _DriverBriefCard(
                        detail: detail,
                        onPrimaryAction: () => _showStageMessage(
                          'Fitur manifes detail akan segera tersedia.',
                        ),
                      )
                    else
                      _CheckoutPreviewCard(
                        estimate: estimate,
                        selectedSeatNumbers: _selectedSeatNumbers.toList()
                          ..sort(),
                        maxSeatCount: _maxSeatCount(detail),
                        isSoldOut: isSoldOut,
                        canEditBooking: _canUseBookingForm,
                        lockMessage: _bookingLockedMessage,
                        isPreparingCheckout: _isPreparingCheckout,
                        onClearSeatSelection: () => _clearSeatSelection(detail),
                        onPrimaryAction: () async {
                          // Tamu harus masuk akun sebelum memilih kursi agar
                          // penguncian kursi tercatat pada pengguna yang benar.
                          if (widget.selectedRole == AppRolePreview.guest ||
                              widget.session == null) {
                            Navigator.of(context).pop('open_login');
                            return;
                          }

                          if (_selectedSeatNumbers.isEmpty) {
                            _showStageMessage(
                              'Pilih minimal satu kursi dari denah sebelum lanjut ke konfirmasi pemesanan.',
                            );
                            return;
                          }

                          setState(() {
                            _isPreparingCheckout = true;
                          });

                          try {
                            final lockResult = await _apiService
                                .lockSeatsForCheckout(
                                  token: widget.session!.token,
                                  scheduleId: detail.schedule.id,
                                  seatNumbers: _selectedSeatNumbers.toList()
                                    ..sort(),
                                );

                            if (!context.mounted) {
                              return;
                            }

                            final navigator = Navigator.of(context);
                            final paymentResult = await navigator.push<Object>(
                              MaterialPageRoute<Object>(
                                builder: (_) => TravSPaymentPreviewPage(
                                  detail: detail,
                                  appSettings: widget.appSettings,
                                  checkoutDraft: estimate,
                                  selectedRole: widget.selectedRole,
                                  session: widget.session!,
                                  selectedSeatNumbers: lockResult.seatNumbers,
                                  baggagePlacement: 'masuk_bagasi',
                                  baggageWeight: 0,
                                  baggageItems: 0,
                                  lockExpiresAt: lockResult.lockExpiresAt,
                                ),
                              ),
                            );

                            if (!context.mounted) {
                              return;
                            }

                            // Hasil khusus diteruskan agar beranda dapat membuka
                            // menu Tiket tanpa menumpuk halaman baru.
                            if (paymentResult == 'open_tickets') {
                              navigator.pop('open_tickets');
                            } else if (paymentResult == true) {
                              navigator.pop(true);
                            } else {
                              setState(() {
                                _selectedSeatNumbers.clear();
                                _seatCount = 0;
                              });
                              await _refresh();
                            }
                          } on TravSApiException catch (error) {
                            _showStageMessage(error.message);
                            await _refresh();
                          } finally {
                            if (mounted) {
                              setState(() {
                                _isPreparingCheckout = false;
                              });
                            }
                          }
                        },
                      ),
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  TravSCheckoutDraft _estimate(TravSScheduleDetail detail) {
    final ticketSubtotal = detail.schedule.hargaTiket * _seatCount;
    final passengerCount = _registeredPassengerCount;

    return TravSCheckoutDraft(
      seatCount: _seatCount,
      passengerCount: passengerCount,
      baggageMethod: 'berat',
      baggagePlacement: 'masuk_bagasi',
      baggageSizeCategory: 'sederhana',
      largeBaggageType: 'tidak_ada',
      baggageWeight: 0,
      baggageSize: 0,
      baggageLength: 0,
      baggageWidth: 0,
      baggageItems: 0,
      ticketSubtotal: ticketSubtotal,
      weightFee: 0,
      sizeFee: 0,
      itemFee: 0,
    );
  }
}

class _HeroCard extends StatelessWidget {
  const _HeroCard({required this.detail, required this.selectedRole});

  final TravSScheduleDetail detail;
  final AppRolePreview selectedRole;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(32),
        gradient: const LinearGradient(
          colors: [TravSTheme.navy, TravSTheme.navySoft],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x2410233F),
            blurRadius: 28,
            offset: Offset(0, 18),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _BadgePill(label: 'Tersedia', color: TravSTheme.success),
              _BadgePill(
                label: selectedRole == AppRolePreview.driver
                    ? 'Mode Pengemudi'
                    : selectedRole == AppRolePreview.passenger
                    ? 'Mode Penumpang'
                    : 'Mode Guest',
                color: Colors.white,
                textColor: TravSTheme.navy,
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            '${detail.schedule.asal} - ${detail.schedule.tujuan}',
            style: theme.textTheme.headlineSmall?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            detail.schedule.jalur,
            style: theme.textTheme.bodyLarge?.copyWith(
              color: Colors.white.withValues(alpha: 0.82),
            ),
          ),
          const SizedBox(height: 18),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _HeroInfoPill(
                icon: Icons.schedule_outlined,
                text: _formatDate(detail.schedule.waktuBerangkat),
              ),
              _HeroInfoPill(
                icon: Icons.route_outlined,
                text:
                    '${_formatDurationMinutes(detail.schedule.durasiMenit)} perjalanan',
              ),
              _HeroInfoPill(
                icon: Icons.event_seat_outlined,
                text:
                    '${detail.schedule.sisaKursi}/${detail.schedule.kapasitas} kursi tersedia',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoGrid extends StatelessWidget {
  const _InfoGrid({required this.detail});

  final TravSScheduleDetail detail;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final cards = [
          _MetricCardData(
            title: 'Harga Tiket',
            value: 'Rp${_formatCurrency(detail.schedule.hargaTiket)}',
            subtitle: 'Barang bawaan biasa dicatat tanpa biaya tambahan',
            icon: Icons.payments_outlined,
          ),
          _MetricCardData(
            title: 'Kendaraan',
            value: detail.schedule.kendaraan,
            subtitle: detail.vehicleType,
            icon: Icons.directions_car_filled_outlined,
            imageUrl:
                detail.vehiclePhotoUrl ?? detail.schedule.kendaraanFotoUrl,
          ),
          _MetricCardData(
            title: 'Pengemudi',
            value: detail.schedule.pengemudi,
            subtitle: detail.driverLicenseNumber,
            icon: Icons.badge_outlined,
            imageUrl: detail.driverPhotoUrl,
          ),
          _MetricCardData(
            title: 'Metode Bayar',
            value: detail.paymentMethods.activeLabels.join(', '),
            subtitle:
                'Kunci form ${detail.bookingRules.seatLockFormMinutes} menit, bayar ${detail.bookingRules.seatLockPaymentMinutes} menit',
            icon: Icons.account_balance_wallet_outlined,
          ),
        ];

        if (constraints.maxWidth < 760) {
          return Column(
            children: List.generate(
              cards.length,
              (index) => Padding(
                padding: EdgeInsets.only(
                  bottom: index == cards.length - 1 ? 0 : 12,
                ),
                child: _MetricCard(data: cards[index]),
              ),
            ),
          );
        }

        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: cards
              .map(
                (card) => SizedBox(
                  width: (constraints.maxWidth - 12) / 2,
                  child: _MetricCard(data: card),
                ),
              )
              .toList(),
        );
      },
    );
  }
}

class _SeatMapCard extends StatelessWidget {
  const _SeatMapCard({
    required this.detail,
    required this.selectedSeatNumbers,
    required this.maxSeatCount,
    required this.canSelectSeats,
    required this.lockMessage,
    required this.onLockedTap,
    required this.onSeatTap,
  });

  final TravSScheduleDetail detail;
  final Set<int> selectedSeatNumbers;
  final int maxSeatCount;
  final bool canSelectSeats;
  final String lockMessage;
  final VoidCallback onLockedTap;
  final ValueChanged<TravSSeatItem> onSeatTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Pilih Kursi',
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                if (!canSelectSeats) const _BookingLockBadge(label: 'Terkunci'),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              canSelectSeats
                  ? 'Pilih kursi langsung dari denah mobil. Kursi akan dikunci saat lanjut ke konfirmasi pemesanan.'
                  : lockMessage,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 16),
            const _SeatLegend(),
            const SizedBox(height: 18),
            _VehicleSeatDeck(
              seats: detail.seatMap,
              seatLayout: detail.seatLayout,
              selectedSeatNumbers: selectedSeatNumbers,
              canSelectSeats: canSelectSeats,
              onLockedTap: onLockedTap,
              onSeatTap: onSeatTap,
            ),
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: TravSTheme.surfaceSoft,
                borderRadius: BorderRadius.circular(22),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: _SeatSummaryItem(
                      label: 'Dipilih',
                      value: '${selectedSeatNumbers.length}/$maxSeatCount',
                    ),
                  ),
                  Expanded(
                    child: _SeatSummaryItem(
                      label: 'Tersedia',
                      value: '${detail.schedule.sisaKursi}',
                    ),
                  ),
                  Expanded(
                    child: _SeatSummaryItem(
                      label: 'Terkunci',
                      value: '${detail.lockedSeatCount}',
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

class _CheckoutPreviewCard extends StatelessWidget {
  const _CheckoutPreviewCard({
    required this.estimate,
    required this.selectedSeatNumbers,
    required this.maxSeatCount,
    required this.isSoldOut,
    required this.canEditBooking,
    required this.lockMessage,
    required this.isPreparingCheckout,
    required this.onClearSeatSelection,
    required this.onPrimaryAction,
  });

  final TravSCheckoutDraft estimate;
  final List<int> selectedSeatNumbers;
  final int maxSeatCount;
  final bool isSoldOut;
  final bool canEditBooking;
  final String lockMessage;
  final bool isPreparingCheckout;
  final VoidCallback onClearSeatSelection;
  final VoidCallback onPrimaryAction;

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
              'Ringkasan Pemesanan',
              style: theme.textTheme.titleLarge?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              canEditBooking
                  ? 'Pilih kursi dulu, lalu isi data penumpang, pickup, dan metode pembayaran di langkah berikutnya.'
                  : 'Bagian pemesanan dikunci agar kursi tidak terpilih oleh pengguna yang belum login sebagai penumpang.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 18),
            if (!canEditBooking) ...[
              _LockedBookingNotice(message: lockMessage),
              const SizedBox(height: 18),
            ],
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: TravSTheme.surfaceSoft,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Pilihan kursi',
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Maksimal $maxSeatCount kursi per transaksi.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navyMuted,
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (selectedSeatNumbers.isEmpty)
                    Text(
                      'Belum ada kursi yang dipilih.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: TravSTheme.navyMuted,
                        fontWeight: FontWeight.w700,
                      ),
                    )
                  else
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: selectedSeatNumbers
                              .map(
                                (seatNumber) =>
                                    Chip(label: Text('Kursi A$seatNumber')),
                              )
                              .toList(),
                        ),
                        const SizedBox(height: 10),
                        TextButton.icon(
                          onPressed: canEditBooking
                              ? onClearSeatSelection
                              : null,
                          icon: const Icon(Icons.restart_alt_rounded),
                          label: const Text('Reset pilihan kursi'),
                        ),
                      ],
                    ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: TravSTheme.surfaceSoft,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Column(
                children: [
                  _PriceRow(
                    label: 'Subtotal tiket',
                    value: 'Rp${_formatCurrency(estimate.ticketSubtotal)}',
                  ),
                  const Divider(height: 24),
                  _PriceRow(
                    label: 'Estimasi total',
                    value: 'Rp${_formatCurrency(estimate.total)}',
                    emphasize: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: isSoldOut || isPreparingCheckout
                    ? null
                    : onPrimaryAction,
                icon: Icon(
                  !canEditBooking
                      ? Icons.login_rounded
                      : isPreparingCheckout
                      ? Icons.sync_rounded
                      : Icons.shopping_bag_outlined,
                ),
                label: Text(
                  isSoldOut
                      ? 'Jadwal penuh'
                      : isPreparingCheckout
                      ? 'Mengunci Kursi...'
                      : !canEditBooking
                      ? 'Masuk untuk Memesan'
                      : 'Lanjut ke Konfirmasi',
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DriverBriefCard extends StatelessWidget {
  const _DriverBriefCard({required this.detail, required this.onPrimaryAction});

  final TravSScheduleDetail detail;
  final VoidCallback onPrimaryAction;

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
              'Brief Pengemudi',
              style: theme.textTheme.titleLarge?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Ringkasan perjalanan dan informasi manifes penumpang.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 18),
            _PriceRow(
              label: 'Kursi terpesan',
              value: '${detail.bookedSeatCount}',
            ),
            _PriceRow(
              label: 'Kursi dikunci',
              value: '${detail.lockedSeatCount}',
            ),
            _PriceRow(
              label: 'Fasilitas kendaraan',
              value: detail.vehicleFacilities,
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: onPrimaryAction,
                icon: const Icon(Icons.qr_code_scanner_outlined),
                label: const Text('Buka Manifes'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.data});

  final _MetricCardData data;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final resolvedImageUrl = TravSApiService.resolveMediaUrl(data.imageUrl);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Row(
          children: [
            InkWell(
              onTap: resolvedImageUrl == null
                  ? null
                  : () => _showScheduleImagePreview(
                      context,
                      imageUrl: resolvedImageUrl,
                      title: data.value,
                    ),
              borderRadius: BorderRadius.circular(18),
              child: Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  color: TravSTheme.orangeSoft,
                  borderRadius: BorderRadius.circular(18),
                ),
                child: resolvedImageUrl == null
                    ? Icon(data.icon, color: TravSTheme.orange)
                    : ClipRRect(
                        borderRadius: BorderRadius.circular(18),
                        child: Image.network(
                          resolvedImageUrl,
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) =>
                              Icon(data.icon, color: TravSTheme.orange),
                          loadingBuilder: (context, child, loadingProgress) {
                            if (loadingProgress == null) {
                              return child;
                            }

                            return Center(
                              child: SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: TravSTheme.orange,
                                ),
                              ),
                            );
                          },
                        ),
                      ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    data.title,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navyMuted,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    data.value,
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    data.subtitle,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navyMuted,
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

class _BookingLockBadge extends StatelessWidget {
  const _BookingLockBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: TravSTheme.orange.withValues(alpha: 0.22)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.lock_outline_rounded, size: 15),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _LockedBookingNotice extends StatelessWidget {
  const _LockedBookingNotice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: TravSTheme.orange.withValues(alpha: 0.22)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.lock_outline_rounded, color: TravSTheme.orange),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: theme.textTheme.bodySmall?.copyWith(
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

class _VehicleSeatDeck extends StatelessWidget {
  const _VehicleSeatDeck({
    required this.seats,
    required this.seatLayout,
    required this.selectedSeatNumbers,
    required this.canSelectSeats,
    required this.onLockedTap,
    required this.onSeatTap,
  });

  final List<TravSSeatItem> seats;

  /// Konfigurasi baris kursi dari admin (per kendaraan).
  /// Contoh: [1, 3, 2] = baris depan 1 kursi, tengah 3 kursi, belakang 2 kursi.
  final List<int> seatLayout;

  final Set<int> selectedSeatNumbers;
  final bool canSelectSeats;
  final VoidCallback onLockedTap;
  final ValueChanged<TravSSeatItem> onSeatTap;

  @override
  Widget build(BuildContext context) {
    final sortedSeats = seats.toList()
      ..sort((first, second) => first.seatNumber.compareTo(second.seatNumber));

    if (sortedSeats.isEmpty) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: TravSTheme.surfaceSoft,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Text(
          'Denah kursi belum tersedia untuk jadwal ini.',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: TravSTheme.navyMuted,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    final layout = _buildVehicleSeatLayout(sortedSeats, seatLayout);
    const deckAspectRatio = 2 / 3;

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 390),
        child: AspectRatio(
          aspectRatio: deckAspectRatio,
          child: LayoutBuilder(
            builder: (context, constraints) {
              final width = constraints.maxWidth;
              final height = constraints.maxHeight;
              final seatSize = width < 340 ? 46.0 : 52.0;

              return Stack(
                clipBehavior: Clip.none,
                children: [
                  Positioned.fill(
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(28),
                      ),
                    ),
                  ),

                  // Gambar mobil menjadi dasar denah, sedangkan tombol kursi
                  // tetap dibangun dari data backend agar fleksibel per armada.
                  Positioned.fill(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      child: Image.asset(
                        'assets/images/seat_taksi_top.png',
                        fit: BoxFit.contain,
                        errorBuilder: (context, error, stackTrace) {
                          return CustomPaint(painter: _VehicleCabinPainter());
                        },
                      ),
                    ),
                  ),
                  Positioned(
                    left: width * 0.62 - seatSize / 2,
                    top: height * 0.36 - seatSize / 2,
                    child: _VehicleSeatButton(
                      size: seatSize,
                      label: 'P',
                      status: 'driver',
                      selected: false,
                      enabled: false,
                      onTap: null,
                    ),
                  ),
                  for (final item in layout)
                    Positioned(
                      left: width * item.x - seatSize / 2,
                      top: height * item.y - seatSize / 2,
                      child: _VehicleSeatButton(
                        size: seatSize,
                        label: item.seat.seatNumber.toString().padLeft(2, '0'),
                        status: item.seat.status,
                        selected: selectedSeatNumbers.contains(
                          item.seat.seatNumber,
                        ),
                        enabled: item.seat.status == 'tersedia',
                        onTap: item.seat.status == 'tersedia'
                            ? () {
                                if (canSelectSeats) {
                                  onSeatTap(item.seat);
                                } else {
                                  onLockedTap();
                                }
                              }
                            : null,
                      ),
                    ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _VehicleSeatLayoutItem {
  const _VehicleSeatLayoutItem({
    required this.seat,
    required this.x,
    required this.y,
  });

  final TravSSeatItem seat;
  final double x;
  final double y;
}

/// Menyusun posisi tombol kursi berdasarkan pengaturan armada dari admin.
/// Jika belum ada pengaturan, susunan dibuat dari jumlah kursi yang tersedia.
List<_VehicleSeatLayoutItem> _buildVehicleSeatLayout(
  List<TravSSeatItem> seats,
  List<int> seatLayout,
) {
  final seatByNumber = <int, TravSSeatItem>{
    for (final seat in seats) seat.seatNumber: seat,
  };
  final maxSeatNumber = seats.isEmpty
      ? 0
      : seats.map((seat) => seat.seatNumber).reduce(math.max);
  final configuredSeatTotal = seatLayout.fold<int>(
    0,
    (total, rowSize) => total + rowSize,
  );
  final fixedSeatTotal = math.min(
    6,
    math.max(maxSeatNumber, configuredSeatTotal),
  );

  if (fixedSeatTotal > 0 && fixedSeatTotal <= 6) {
    return List<_VehicleSeatLayoutItem>.generate(fixedSeatTotal, (index) {
      final seatNumber = index + 1;
      final position = _fixedVehicleSeatPosition(seatNumber);

      return _VehicleSeatLayoutItem(
        seat:
            seatByNumber[seatNumber] ??
            TravSSeatItem(
              id: -seatNumber,
              seatNumber: seatNumber,
              status: 'nonaktif',
            ),
        x: position.dx,
        y: position.dy,
      );
    });
  }

  final rowPattern = seatLayout.isNotEmpty
      ? seatLayout
      : _seatRowPattern(seats.length);
  final rowCount = math.max(1, rowPattern.length);
  final items = <_VehicleSeatLayoutItem>[];
  var seatIndex = 0;

  for (var rowIndex = 0; rowIndex < rowPattern.length; rowIndex++) {
    final rowSize = rowPattern[rowIndex];
    final y = _seatYPosition(rowCount, rowIndex);

    for (var columnIndex = 0; columnIndex < rowSize; columnIndex++) {
      if (seatIndex >= seats.length) {
        break;
      }

      items.add(
        _VehicleSeatLayoutItem(
          seat: seats[seatIndex],
          x: _seatXPosition(rowSize, columnIndex, rowIndex),
          y: y,
        ),
      );
      seatIndex++;
    }
  }

  return items;
}

Offset _fixedVehicleSeatPosition(int seatNumber) {
  // Nomor kursi dibuat tetap mengikuti denah mobil agar kursi yang ditutup
  // tetap terlihat di posisi aslinya, hanya berubah warna menjadi terkunci.
  const positions = <int, Offset>{
    1: Offset(0.38, 0.38),
    2: Offset(0.70, 0.57),
    3: Offset(0.50, 0.57),
    4: Offset(0.30, 0.57),
    5: Offset(0.38, 0.74),
    6: Offset(0.62, 0.74),
  };

  return positions[seatNumber] ?? const Offset(0.50, 0.55);
}

List<int> _seatRowPattern(int count) {
  if (count <= 0) {
    return const [];
  }
  if (count == 1) {
    return const [1];
  }
  if (count == 2) {
    return const [1, 1];
  }
  if (count == 3) {
    return const [1, 2];
  }
  if (count == 4) {
    return const [2, 2];
  }
  if (count == 5) {
    return const [1, 2, 2];
  }
  if (count == 6) {
    return const [1, 3, 2];
  }
  if (count == 7) {
    return const [1, 3, 3];
  }

  final rows = <int>[];
  var remaining = count;
  while (remaining > 3) {
    rows.add(2);
    remaining -= 2;
  }
  if (remaining > 0) {
    rows.add(remaining);
  }
  return rows;
}

double _seatXPosition(int rowSize, int columnIndex, int rowIndex) {
  if (rowSize == 1) {
    return rowIndex == 0 ? 0.38 : 0.50;
  }

  if (rowSize == 2) {
    return columnIndex == 0 ? 0.32 : 0.68;
  }

  const positions = [0.28, 0.50, 0.72];
  final safeIndex = columnIndex.clamp(0, positions.length - 1).toInt();
  return positions[safeIndex];
}

double _seatYPosition(int rowCount, int rowIndex) {
  if (rowCount <= 1) {
    return 0.55;
  }

  if (rowCount == 2) {
    const positions = [0.43, 0.67];
    return positions[rowIndex.clamp(0, positions.length - 1).toInt()];
  }

  if (rowCount == 3) {
    const positions = [0.39, 0.57, 0.73];
    return positions[rowIndex.clamp(0, positions.length - 1).toInt()];
  }

  const start = 0.36;
  const end = 0.82;
  return start + (rowIndex * ((end - start) / (rowCount - 1)));
}

class _VehicleCabinPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final bodyPaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.fill;
    final linePaint = Paint()
      ..color = TravSTheme.line
      ..strokeWidth = 1.2
      ..style = PaintingStyle.stroke;
    final softPaint = Paint()
      ..color = TravSTheme.surfaceSoft
      ..style = PaintingStyle.fill;

    final body = RRect.fromRectAndRadius(
      Rect.fromLTWH(
        size.width * 0.18,
        size.height * 0.06,
        size.width * 0.64,
        size.height * 0.88,
      ),
      Radius.circular(size.width * 0.20),
    );
    canvas.drawRRect(body, bodyPaint);
    canvas.drawRRect(body, linePaint);

    final hood = RRect.fromRectAndRadius(
      Rect.fromLTWH(
        size.width * 0.28,
        size.height * 0.09,
        size.width * 0.44,
        size.height * 0.15,
      ),
      Radius.circular(size.width * 0.10),
    );
    canvas.drawRRect(hood, softPaint);
    canvas.drawRRect(hood, linePaint);

    final cabin = RRect.fromRectAndRadius(
      Rect.fromLTWH(
        size.width * 0.25,
        size.height * 0.25,
        size.width * 0.50,
        size.height * 0.47,
      ),
      Radius.circular(size.width * 0.08),
    );
    canvas.drawRRect(cabin, softPaint);
    canvas.drawRRect(cabin, linePaint);

    canvas.drawLine(
      Offset(size.width * 0.24, size.height * 0.46),
      Offset(size.width * 0.76, size.height * 0.46),
      linePaint,
    );
    canvas.drawLine(
      Offset(size.width * 0.24, size.height * 0.66),
      Offset(size.width * 0.76, size.height * 0.66),
      linePaint,
    );
    canvas.drawLine(
      Offset(size.width * 0.50, size.height * 0.25),
      Offset(size.width * 0.50, size.height * 0.88),
      linePaint..color = TravSTheme.line.withValues(alpha: 0.7),
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _SeatLegend extends StatelessWidget {
  const _SeatLegend();

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 12,
      runSpacing: 12,
      children: const [
        _LegendItem(label: 'Tersedia', color: Color(0xFF16C784)),
        _LegendItem(label: 'Terisi', color: Color(0xFFF0445E)),
        _LegendItem(label: 'Dikunci/Ditutup', color: Color(0xFF94A3B8)),
        _LegendItem(label: 'Pilihan', color: Color(0xFFF8B825)),
      ],
    );
  }
}

class _VehicleSeatButton extends StatelessWidget {
  const _VehicleSeatButton({
    required this.size,
    required this.label,
    required this.status,
    required this.selected,
    required this.enabled,
    required this.onTap,
  });

  final double size;
  final String label;
  final String status;
  final bool selected;
  final bool enabled;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final colors = selected
        ? (
            background: const Color(0xFFF8B825),
            foreground: TravSTheme.navy,
            border: Colors.white,
          )
        : switch (status) {
            'driver' => (
              background: TravSTheme.surfaceSoft,
              foreground: TravSTheme.navyMuted,
              border: Colors.white,
            ),
            'dipesan' => (
              background: const Color(0xFFF0445E),
              foreground: Colors.white,
              border: Colors.white,
            ),
            'terkunci' => (
              background: const Color(0xFF94A3B8),
              foreground: Colors.white,
              border: Colors.white,
            ),
            'nonaktif' => (
              background: const Color(0xFFCBD5E1),
              foreground: Colors.white,
              border: Colors.white,
            ),
            _ => (
              background: const Color(0xFF16C784),
              foreground: Colors.white,
              border: Colors.white,
            ),
          };

    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          color: colors.background,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? Colors.white : colors.border,
            width: selected ? 2.4 : 1.4,
          ),
          boxShadow: [
            BoxShadow(
              color: colors.background.withValues(alpha: 0.24),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Center(
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: colors.foreground,
              fontSize: label.length > 2 ? 9 : 14,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ),
    );
  }
}

class _BadgePill extends StatelessWidget {
  const _BadgePill({
    required this.label,
    required this.color,
    this.textColor = Colors.white,
  });

  final String label;
  final Color color;
  final Color textColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: color == Colors.white ? 1 : 0.18),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(color: textColor, fontWeight: FontWeight.w800),
      ),
    );
  }
}

class _HeroInfoPill extends StatelessWidget {
  const _HeroInfoPill({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18, color: Colors.white),
          const SizedBox(width: 8),
          Text(
            text,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _LegendItem extends StatelessWidget {
  const _LegendItem({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 18,
          height: 18,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(6),
          ),
        ),
        const SizedBox(width: 8),
        Text(label),
      ],
    );
  }
}

class _SeatSummaryItem extends StatelessWidget {
  const _SeatSummaryItem({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: theme.textTheme.bodySmall?.copyWith(
            color: TravSTheme.navyMuted,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: theme.textTheme.titleLarge?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _PriceRow extends StatelessWidget {
  const _PriceRow({
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
              color: emphasize ? TravSTheme.navy : TravSTheme.navy,
              fontWeight: emphasize ? FontWeight.w800 : FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MetricCardData {
  const _MetricCardData({
    required this.title,
    required this.value,
    required this.subtitle,
    required this.icon,
    this.imageUrl,
  });

  final String title;
  final String value;
  final String subtitle;
  final IconData icon;
  final String? imageUrl;
}

String _formatDate(String iso) {
  if (iso.isEmpty) {
    return '-';
  }

  final date = DateTime.tryParse(iso)?.toLocal();
  if (date == null) {
    return '-';
  }

  return DateFormat('EEEE, dd MMM yyyy - HH:mm', 'id_ID').format(date);
}

String _formatDurationMinutes(int minutes) {
  if (minutes <= 0) {
    return '-';
  }

  final hours = minutes ~/ 60;
  final remainingMinutes = minutes % 60;

  // Durasi dari API tetap menit, lalu diubah agar tidak tampil mentah seperti "405 menit".
  if (hours > 0 && remainingMinutes > 0) {
    return '$hours jam $remainingMinutes menit';
  }
  if (hours > 0) {
    return '$hours jam';
  }
  return '$remainingMinutes menit';
}

String _formatCurrency(double value) {
  return NumberFormat.decimalPattern('id_ID').format(value);
}
