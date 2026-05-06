import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/models/app_role_preview.dart';
import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/theme/travs_theme.dart';
import '../../core/widgets/travs_async_state.dart';
import '../payment/travs_payment_preview_page.dart';

class TravSScheduleDetailPage extends StatefulWidget {
  const TravSScheduleDetailPage({
    required this.schedule,
    required this.selectedRole,
    this.session,
    super.key,
  });

  final TravSSchedule schedule;
  final AppRolePreview selectedRole;
  final TravSUserSession? session;

  @override
  State<TravSScheduleDetailPage> createState() =>
      _TravSScheduleDetailPageState();
}

class _TravSScheduleDetailPageState extends State<TravSScheduleDetailPage> {
  final TravSApiService _apiService = TravSApiService();
  late Future<TravSScheduleDetail> _future;

  int _seatCount = 0;
  String _baggageMethod = 'berat';
  double _baggageWeight = 0;
  int _baggageSize = 0;
  int _baggageLength = 0;
  int _baggageWidth = 0;
  int _baggageItems = 0;
  final Set<int> _selectedSeatNumbers = <int>{};
  bool _isPreparingCheckout = false;

  bool get _canUseBookingForm =>
      widget.selectedRole == AppRolePreview.passenger && widget.session != null;

  String get _bookingLockedMessage {
    if (widget.selectedRole == AppRolePreview.guest) {
      return 'Mode tamu hanya bisa melihat jadwal dan status kursi. Login sebagai penumpang dulu agar pilihan kursi dapat dikunci dengan aman.';
    }

    if (widget.selectedRole == AppRolePreview.driver) {
      return 'Mode pengemudi hanya bisa melihat informasi kursi untuk kebutuhan manifest.';
    }

    return 'Sesi penumpang belum aktif. Silakan masuk ulang sebelum memilih kursi.';
  }

  @override
  void initState() {
    super.initState();
    _future = _loadDetail();
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
        _syncBaggageLimits(detail);
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

  int _maxSeatCount(TravSScheduleDetail detail) {
    final available = math.min(
      detail.schedule.sisaKursi,
      detail.bookingRules.maxSeatPerBooking,
    );
    return math.max(0, available);
  }

  double _maxWeight(TravSScheduleDetail detail) {
    if (!detail.baggageRules.weightEnabled) {
      return 0;
    }

    final configured = detail.baggageRules.maxWeightPerPersonKg * _seatCount;
    if (configured > 0) {
      return configured.toDouble();
    }

    return (detail.baggageRules.freeWeightKg * _seatCount + 20).toDouble();
  }

  int _maxSize(TravSScheduleDetail detail) {
    if (!detail.baggageRules.sizeEnabled) {
      return 0;
    }

    final configured = detail.baggageRules.maxSizePerPersonCm * _seatCount;
    if (configured > 0) {
      return configured;
    }

    return detail.baggageRules.freeSizeCm * _seatCount + 80;
  }

  int _maxItems(TravSScheduleDetail detail) {
    if (!detail.baggageRules.itemEnabled) {
      return 0;
    }

    final configured = detail.baggageRules.maxItemPerPerson * _seatCount;
    if (configured > 0) {
      return configured;
    }

    return detail.baggageRules.freeItemCount * _seatCount + 3;
  }

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
      _showStageMessage(
        'Maksimal $maxSeatCount kursi per transaksi sesuai pengaturan sistem.',
      );
      return;
    }

    setState(() {
      if (_selectedSeatNumbers.contains(seat.seatNumber)) {
        _selectedSeatNumbers.remove(seat.seatNumber);
      } else if (_selectedSeatNumbers.length < maxSeatCount) {
        _selectedSeatNumbers.add(seat.seatNumber);
      }

      _seatCount = _selectedSeatNumbers.length;
      _syncBaggageLimits(detail);
    });
  }

  void _clearSeatSelection(TravSScheduleDetail detail) {
    setState(() {
      _selectedSeatNumbers.clear();
      _seatCount = 0;
      _syncBaggageLimits(detail);
    });
  }

  void _syncBaggageLimits(TravSScheduleDetail detail) {
    if (_baggageMethod == 'ukuran' && !detail.baggageRules.sizeEnabled) {
      _baggageMethod = 'berat';
    }
    if (_baggageMethod == 'berat' && !detail.baggageRules.weightEnabled) {
      _baggageMethod = 'ukuran';
    }

    _baggageWeight = _baggageWeight.clamp(0, _maxWeight(detail)).toDouble();
    _syncDimensionTotal(detail);
    _baggageItems = _clampInt(_baggageItems, 0, _maxItems(detail));

    if (_baggageMethod == 'berat') {
      _baggageLength = 0;
      _baggageWidth = 0;
      _baggageSize = 0;
    } else {
      _baggageWeight = 0;
    }
  }

  void _syncDimensionTotal(TravSScheduleDetail detail) {
    final maxSize = _maxSize(detail);
    _baggageLength = _clampInt(_baggageLength, 0, maxSize);
    _baggageWidth = _clampInt(_baggageWidth, 0, maxSize);

    final totalSize = _baggageLength + _baggageWidth;
    if (maxSize > 0 && totalSize > maxSize) {
      final overflow = totalSize - maxSize;
      _baggageWidth = math.max(0, _baggageWidth - overflow);
    }

    _baggageSize = _baggageLength + _baggageWidth;
  }

  void _setBaggageMethod(TravSScheduleDetail detail, String value) {
    setState(() {
      _baggageMethod = value;
      _syncBaggageLimits(detail);
    });
  }

  void _setBaggageWeight(TravSScheduleDetail detail, double value) {
    setState(() {
      _baggageWeight = value.clamp(0, _maxWeight(detail)).toDouble();
    });
  }

  void _setBaggageLength(TravSScheduleDetail detail, int value) {
    setState(() {
      _baggageLength = _clampInt(value, 0, _maxSize(detail));
      _syncDimensionTotal(detail);
    });
  }

  void _setBaggageWidth(TravSScheduleDetail detail, int value) {
    setState(() {
      _baggageWidth = _clampInt(value, 0, _maxSize(detail));
      _syncDimensionTotal(detail);
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
      appBar: AppBar(title: const Text('Pemesanan Jadwal')),
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
              // State loading — tampilkan spinner saat detail jadwal dimuat
              if (snapshot.connectionState == ConnectionState.waiting &&
                  !snapshot.hasData) {
                return TravSAsyncState.loading(
                  message: 'Memuat detail jadwal...',
                );
              }

              // State error — tampilkan pesan error jujur dengan tombol retry
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
                          'Manifest detail, scan QR, dan status hadir akan saya sambungkan pada tahap pengemudi berikutnya.',
                        ),
                      )
                    else
                      _CheckoutPreviewCard(
                        detail: detail,
                        estimate: estimate,
                        selectedSeatNumbers: _selectedSeatNumbers.toList()
                          ..sort(),
                        baggageMethod: _baggageMethod,
                        baggageWeight: _baggageWeight,
                        baggageSize: _baggageSize,
                        baggageLength: _baggageLength,
                        baggageWidth: _baggageWidth,
                        baggageItems: _baggageItems,
                        onBaggageMethodChanged: (value) =>
                            _setBaggageMethod(detail, value),
                        onWeightChanged: detail.baggageRules.weightEnabled
                            ? (value) => _setBaggageWeight(detail, value)
                            : null,
                        onLengthChanged: detail.baggageRules.sizeEnabled
                            ? (value) => _setBaggageLength(detail, value)
                            : null,
                        onWidthChanged: detail.baggageRules.sizeEnabled
                            ? (value) => _setBaggageWidth(detail, value)
                            : null,
                        onItemsChanged: detail.baggageRules.itemEnabled
                            ? (value) => setState(() => _baggageItems = value)
                            : null,
                        maxSeatCount: _maxSeatCount(detail),
                        maxWeight: _maxWeight(detail),
                        maxSize: _maxSize(detail),
                        maxItems: _maxItems(detail),
                        isSoldOut: isSoldOut,
                        canEditBooking: _canUseBookingForm,
                        lockMessage: _bookingLockedMessage,
                        isPreparingCheckout: _isPreparingCheckout,
                        onClearSeatSelection: () => _clearSeatSelection(detail),
                        onPrimaryAction: () async {
                          if (widget.selectedRole == AppRolePreview.guest) {
                            _showStageMessage(
                              'Mode tamu hanya bisa melihat detail jadwal. Login dulu untuk mengunci kursi dan lanjut checkout.',
                            );
                            return;
                          }

                          if (widget.session == null) {
                            _showStageMessage(
                              'Sesi login tidak ditemukan. Silakan masuk ulang lalu coba booking lagi.',
                            );
                            return;
                          }

                          if (_selectedSeatNumbers.isEmpty) {
                            _showStageMessage(
                              'Pilih minimal satu kursi dari denah sebelum lanjut checkout.',
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
                            final createdBooking = await navigator.push<bool>(
                              MaterialPageRoute<bool>(
                                builder: (_) => TravSPaymentPreviewPage(
                                  detail: detail,
                                  checkoutDraft: estimate,
                                  selectedRole: widget.selectedRole,
                                  session: widget.session!,
                                  selectedSeatNumbers: lockResult.seatNumbers,
                                  baggageMethod: _baggageMethod,
                                  baggageWeight: _baggageWeight,
                                  baggageSize: _baggageSize,
                                  baggageLength: _baggageLength,
                                  baggageWidth: _baggageWidth,
                                  baggageItems: _baggageItems,
                                  lockExpiresAt: lockResult.lockExpiresAt,
                                ),
                              ),
                            );

                            if (!context.mounted) {
                              return;
                            }

                            if (createdBooking == true) {
                              navigator.pop(true);
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
    final baggageRules = detail.baggageRules;
    final ticketSubtotal = detail.schedule.hargaTiket * _seatCount;
    final adminFee = detail.bookingRules.adminFee;

    final freeWeight = baggageRules.freeWeightKg * _seatCount;
    final freeSize = baggageRules.freeSizeCm * _seatCount;
    final freeItems = baggageRules.freeItemCount * _seatCount;

    final weightFee = baggageRules.weightEnabled && _baggageMethod == 'berat'
        ? math.max(0, _baggageWeight - freeWeight) * baggageRules.extraFeePerKg
        : 0.0;
    final sizeFee = baggageRules.sizeEnabled && _baggageMethod == 'ukuran'
        ? math.max(0, _baggageSize - freeSize) * baggageRules.extraFeePerCm
        : 0.0;
    final itemFee = baggageRules.itemEnabled
        ? math.max(0, _baggageItems - freeItems) * baggageRules.extraFeePerItem
        : 0.0;

    return TravSCheckoutDraft(
      seatCount: _seatCount,
      baggageMethod: _baggageMethod,
      baggageWeight: _baggageWeight,
      baggageSize: _baggageSize,
      baggageLength: _baggageLength,
      baggageWidth: _baggageWidth,
      baggageItems: _baggageItems,
      ticketSubtotal: ticketSubtotal,
      adminFee: adminFee,
      weightFee: weightFee.toDouble(),
      sizeFee: sizeFee.toDouble(),
      itemFee: itemFee.toDouble(),
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
              _BadgePill(
                label: 'Data Langsung',
                color: TravSTheme.success,
              ),
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
            style: theme.textTheme.headlineMedium?.copyWith(
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
                text: '${detail.schedule.durasiMenit ~/ 60} jam perjalanan',
              ),
              _HeroInfoPill(
                icon: Icons.event_seat_outlined,
                text:
                    '${detail.schedule.sisaKursi}/${detail.schedule.kapasitas} kursi tersedia',
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            detail.sourceLabel,
            style: theme.textTheme.bodySmall?.copyWith(
              color: Colors.white.withValues(alpha: 0.76),
            ),
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
            subtitle: 'Belum termasuk admin fee dan bagasi tambahan',
            icon: Icons.payments_outlined,
          ),
          _MetricCardData(
            title: 'Kendaraan',
            value: detail.schedule.kendaraan,
            subtitle: detail.vehicleType,
            icon: Icons.directions_car_filled_outlined,
          ),
          _MetricCardData(
            title: 'Pengemudi',
            value: detail.schedule.pengemudi,
            subtitle: detail.driverLicenseNumber,
            icon: Icons.badge_outlined,
          ),
          _MetricCardData(
            title: 'Metode Bayar',
            value: detail.paymentMethods.activeLabels.join(', '),
            subtitle:
                'Lock form ${detail.bookingRules.seatLockFormMinutes} menit, bayar ${detail.bookingRules.seatLockPaymentMinutes} menit',
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
            _VehicleSummaryCard(detail: detail),
            const SizedBox(height: 16),
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
                  ? 'Pilih kursi langsung dari denah mobil. Kursi akan dikunci saat lanjut ke checkout.'
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
    required this.detail,
    required this.estimate,
    required this.selectedSeatNumbers,
    required this.baggageMethod,
    required this.baggageWeight,
    required this.baggageSize,
    required this.baggageLength,
    required this.baggageWidth,
    required this.baggageItems,
    required this.onBaggageMethodChanged,
    required this.onWeightChanged,
    required this.onLengthChanged,
    required this.onWidthChanged,
    required this.onItemsChanged,
    required this.maxSeatCount,
    required this.maxWeight,
    required this.maxSize,
    required this.maxItems,
    required this.isSoldOut,
    required this.canEditBooking,
    required this.lockMessage,
    required this.isPreparingCheckout,
    required this.onClearSeatSelection,
    required this.onPrimaryAction,
  });

  final TravSScheduleDetail detail;
  final TravSCheckoutDraft estimate;
  final List<int> selectedSeatNumbers;
  final String baggageMethod;
  final double baggageWeight;
  final int baggageSize;
  final int baggageLength;
  final int baggageWidth;
  final int baggageItems;
  final ValueChanged<String> onBaggageMethodChanged;
  final ValueChanged<double>? onWeightChanged;
  final ValueChanged<int>? onLengthChanged;
  final ValueChanged<int>? onWidthChanged;
  final ValueChanged<int>? onItemsChanged;
  final int maxSeatCount;
  final double maxWeight;
  final int maxSize;
  final int maxItems;
  final bool isSoldOut;
  final bool canEditBooking;
  final String lockMessage;
  final bool isPreparingCheckout;
  final VoidCallback onClearSeatSelection;
  final VoidCallback onPrimaryAction;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final baggageRules = detail.baggageRules;

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
            if (baggageRules.weightEnabled || baggageRules.sizeEnabled) ...[
              const SizedBox(height: 18),
              _BaggageMethodPicker(
                selectedMethod: baggageMethod,
                weightEnabled: baggageRules.weightEnabled,
                sizeEnabled: baggageRules.sizeEnabled,
                enabled: canEditBooking,
                onChanged: onBaggageMethodChanged,
              ),
            ],
            if (baggageRules.weightEnabled && baggageMethod == 'berat') ...[
              const SizedBox(height: 18),
              _NumericBaggageField(
                label: 'Berat bagasi',
                helper:
                    'Gratis ${baggageRules.freeWeightKg * estimate.seatCount} kg, maksimal ${maxWeight.toStringAsFixed(0)} kg',
                value: baggageWeight,
                maxValue: maxWeight,
                suffix: 'kg',
                allowDecimal: true,
                enabled: canEditBooking,
                onChanged: onWeightChanged ?? (_) {},
              ),
            ],
            if (baggageRules.sizeEnabled && baggageMethod == 'ukuran') ...[
              const SizedBox(height: 18),
              _DimensionInputField(
                helper:
                    'Panjang + lebar maksimal ${baggageRules.maxSizePerPersonCm * estimate.seatCount} cm',
                length: baggageLength,
                width: baggageWidth,
                totalSize: baggageSize,
                maxSize: maxSize,
                enabled: canEditBooking,
                onLengthChanged: onLengthChanged ?? (_) {},
                onWidthChanged: onWidthChanged ?? (_) {},
              ),
            ],
            if (baggageRules.itemEnabled) ...[
              const SizedBox(height: 18),
              _CounterField(
                label: 'Jumlah item bagasi',
                helper:
                    'Gratis ${baggageRules.freeItemCount * estimate.seatCount} item, tambahan Rp${_formatCurrency(baggageRules.extraFeePerItem)} per item',
                value: baggageItems,
                minValue: 0,
                maxValue: maxItems,
                enabled: canEditBooking,
                onChanged: onItemsChanged ?? (_) {},
              ),
            ],
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
                  _PriceRow(
                    label: 'Biaya admin',
                    value: 'Rp${_formatCurrency(estimate.adminFee)}',
                  ),
                  if (estimate.weightFee > 0)
                    _PriceRow(
                      label:
                          'Tambahan berat (${_formatOverage(baggageWeight, baggageRules.freeWeightKg * estimate.seatCount)} kg)',
                      value: 'Rp${_formatCurrency(estimate.weightFee)}',
                    ),
                  if (estimate.sizeFee > 0)
                    _PriceRow(
                      label:
                          'Tambahan ukuran (${math.max(0, baggageSize - baggageRules.freeSizeCm * estimate.seatCount)} cm)',
                      value: 'Rp${_formatCurrency(estimate.sizeFee)}',
                    ),
                  if (estimate.itemFee > 0)
                    _PriceRow(
                      label:
                          'Tambahan item (${math.max(0, baggageItems - baggageRules.freeItemCount * estimate.seatCount)} item)',
                      value: 'Rp${_formatCurrency(estimate.itemFee)}',
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
            const SizedBox(height: 14),
            if (baggageRules.photoRequired)
              Text(
                'Catatan: foto bagasi diwajibkan oleh pengaturan sistem pada tahap submit booking.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.orange,
                  fontWeight: FontWeight.w700,
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
                      ? 'Login Untuk Booking'
                      : 'Lanjut ke Checkout',
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
              'Halaman ini menyiapkan konteks perjalanan sebelum modul manifest, scan QR, dan update kehadiran disambungkan penuh.',
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
              label: 'Seat lock aktif',
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
                label: const Text('Siapkan Modul Manifest'),
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

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Row(
          children: [
            Container(
              width: 50,
              height: 50,
              decoration: BoxDecoration(
                color: TravSTheme.orangeSoft,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Icon(data.icon, color: TravSTheme.orange),
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
                    style: theme.textTheme.titleMedium?.copyWith(
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

class _VehicleSummaryCard extends StatelessWidget {
  const _VehicleSummaryCard({required this.detail});

  final TravSScheduleDetail detail;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: TravSTheme.line),
        boxShadow: [
          BoxShadow(
            color: TravSTheme.navy.withValues(alpha: 0.04),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  detail.schedule.kendaraan,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  '${detail.vehicleType} | ${detail.schedule.kapasitas} Kursi',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: TravSTheme.navySoft,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 14),
          Container(
            width: 96,
            height: 64,
            decoration: BoxDecoration(
              color: TravSTheme.surfaceSoft,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: TravSTheme.line),
            ),
            child: const Icon(
              Icons.directions_car_filled_rounded,
              color: TravSTheme.navy,
              size: 42,
            ),
          ),
        ],
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
    required this.selectedSeatNumbers,
    required this.canSelectSeats,
    required this.onLockedTap,
    required this.onSeatTap,
  });

  final List<TravSSeatItem> seats;
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

    final layout = _buildVehicleSeatLayout(sortedSeats);
    final rowCount = _seatRowPattern(sortedSeats.length).length;
    final deckAspectRatio = rowCount <= 4
        ? 0.68
        : rowCount <= 6
        ? 0.58
        : 0.48;

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 390),
        child: AspectRatio(
          aspectRatio: deckAspectRatio,
          child: LayoutBuilder(
            builder: (context, constraints) {
              final width = constraints.maxWidth;
              final height = constraints.maxHeight;
              final seatSize = width < 340 ? 48.0 : 54.0;

              return Stack(
                clipBehavior: Clip.none,
                children: [
                  Positioned.fill(
                    child: CustomPaint(painter: _VehicleCabinPainter()),
                  ),
                  Positioned(
                    left: width * 0.64 - seatSize / 2,
                    top: height * 0.18 - seatSize / 2,
                    child: _VehicleSeatButton(
                      size: seatSize,
                      label: 'SOPIR',
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

List<_VehicleSeatLayoutItem> _buildVehicleSeatLayout(
  List<TravSSeatItem> seats,
) {
  final rowPattern = _seatRowPattern(seats.length);
  final rowCount = math.max(1, rowPattern.length);
  final items = <_VehicleSeatLayoutItem>[];
  var seatIndex = 0;

  for (var rowIndex = 0; rowIndex < rowPattern.length; rowIndex++) {
    final rowSize = rowPattern[rowIndex];
    final y = rowCount == 1
        ? 0.55
        : 0.28 + (rowIndex * (0.58 / (rowCount - 1)));

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
    return const [1, 2, 3];
  }
  if (count == 7) {
    return const [2, 2, 3];
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
    return rowIndex == 0 ? 0.28 : 0.50;
  }

  if (rowSize == 2) {
    return columnIndex == 0 ? 0.28 : 0.72;
  }

  const positions = [0.24, 0.50, 0.76];
  final safeIndex = columnIndex.clamp(0, positions.length - 1).toInt();
  return positions[safeIndex];
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
        _LegendItem(label: 'Dikunci', color: Color(0xFF3B82F6)),
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
              background: const Color(0xFF3B82F6),
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

class _CounterField extends StatelessWidget {
  const _CounterField({
    required this.label,
    required this.helper,
    required this.value,
    required this.minValue,
    required this.maxValue,
    required this.enabled,
    required this.onChanged,
  });

  final String label;
  final String helper;
  final int value;
  final int minValue;
  final int maxValue;
  final bool enabled;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: theme.textTheme.titleMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            helper,
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _RoundIconButton(
                icon: Icons.remove_rounded,
                enabled: enabled && value > minValue,
                onTap: () => onChanged(value - 1),
              ),
              Expanded(
                child: Center(
                  child: Text(
                    '$value',
                    style: theme.textTheme.headlineSmall?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
              _RoundIconButton(
                icon: Icons.add_rounded,
                enabled: enabled && value < maxValue,
                onTap: () => onChanged(value + 1),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _BaggageMethodPicker extends StatelessWidget {
  const _BaggageMethodPicker({
    required this.selectedMethod,
    required this.weightEnabled,
    required this.sizeEnabled,
    required this.enabled,
    required this.onChanged,
  });

  final String selectedMethod;
  final bool weightEnabled;
  final bool sizeEnabled;
  final bool enabled;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final segments = <ButtonSegment<String>>[
      if (weightEnabled)
        const ButtonSegment<String>(
          value: 'berat',
          icon: Icon(Icons.scale_outlined),
          label: Text('Berat + item'),
        ),
      if (sizeEnabled)
        const ButtonSegment<String>(
          value: 'ukuran',
          icon: Icon(Icons.straighten_outlined),
          label: Text('Ukuran + item'),
        ),
    ];

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Metode hitung barang',
            style: theme.textTheme.titleMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Pilih salah satu: berat dengan jumlah item, atau ukuran dengan jumlah item.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
            ),
          ),
          const SizedBox(height: 14),
          SegmentedButton<String>(
            segments: segments,
            selected: {selectedMethod},
            onSelectionChanged: enabled
                ? (values) {
                    if (values.isNotEmpty) {
                      onChanged(values.first);
                    }
                  }
                : null,
          ),
        ],
      ),
    );
  }
}

class _NumericBaggageField extends StatelessWidget {
  const _NumericBaggageField({
    required this.label,
    required this.helper,
    required this.value,
    required this.maxValue,
    required this.suffix,
    required this.allowDecimal,
    required this.enabled,
    required this.onChanged,
  });

  final String label;
  final String helper;
  final double value;
  final double maxValue;
  final String suffix;
  final bool allowDecimal;
  final bool enabled;
  final ValueChanged<double> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  label,
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Text(
                '${value.toStringAsFixed(0)} $suffix',
                style: theme.textTheme.titleMedium?.copyWith(
                  color: TravSTheme.orange,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            helper,
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
            ),
          ),
          const SizedBox(height: 14),
          TextFormField(
            initialValue: value > 0
                ? (allowDecimal
                      ? value.toStringAsFixed(1)
                      : value.toStringAsFixed(0))
                : '',
            enabled: enabled && maxValue > 0,
            style: const TextStyle(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
            keyboardType: TextInputType.numberWithOptions(
              decimal: allowDecimal,
            ),
            decoration: InputDecoration(hintText: '0', suffixText: suffix),
            onChanged: (raw) {
              final parsed = double.tryParse(raw.replaceAll(',', '.')) ?? 0;
              onChanged(parsed);
            },
          ),
        ],
      ),
    );
  }
}

class _DimensionInputField extends StatelessWidget {
  const _DimensionInputField({
    required this.helper,
    required this.length,
    required this.width,
    required this.totalSize,
    required this.maxSize,
    required this.enabled,
    required this.onLengthChanged,
    required this.onWidthChanged,
  });

  final String helper;
  final int length;
  final int width;
  final int totalSize;
  final int maxSize;
  final bool enabled;
  final ValueChanged<int> onLengthChanged;
  final ValueChanged<int> onWidthChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Ukuran barang',
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Text(
                '$totalSize cm',
                style: theme.textTheme.titleMedium?.copyWith(
                  color: TravSTheme.orange,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            helper,
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _IntegerInput(
                  label: 'Panjang',
                  value: length,
                  maxValue: maxSize,
                  enabled: enabled,
                  onChanged: onLengthChanged,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _IntegerInput(
                  label: 'Lebar',
                  value: width,
                  maxValue: maxSize,
                  enabled: enabled,
                  onChanged: onWidthChanged,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _IntegerInput extends StatelessWidget {
  const _IntegerInput({
    required this.label,
    required this.value,
    required this.maxValue,
    required this.enabled,
    required this.onChanged,
  });

  final String label;
  final int value;
  final int maxValue;
  final bool enabled;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      initialValue: value > 0 ? '$value' : '',
      enabled: enabled && maxValue > 0,
      style: const TextStyle(
        color: TravSTheme.navy,
        fontWeight: FontWeight.w800,
      ),
      keyboardType: TextInputType.number,
      decoration: InputDecoration(labelText: label, suffixText: 'cm'),
      onChanged: (raw) => onChanged(int.tryParse(raw) ?? 0),
    );
  }
}

class _RoundIconButton extends StatelessWidget {
  const _RoundIconButton({
    required this.icon,
    required this.enabled,
    required this.onTap,
  });

  final IconData icon;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        width: 48,
        height: 48,
        decoration: BoxDecoration(
          color: enabled ? Colors.white : const Color(0xFFF1F4F8),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFD9E2EC)),
        ),
        child: Icon(
          icon,
          color: enabled ? TravSTheme.navy : TravSTheme.navyMuted,
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
  });

  final String title;
  final String value;
  final String subtitle;
  final IconData icon;
}

int _clampInt(int value, int min, int max) {
  return math.min(math.max(value, min), max);
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

String _formatOverage(double value, int freeLimit) {
  return math.max(0, value - freeLimit).toStringAsFixed(0);
}

String _formatCurrency(double value) {
  return NumberFormat.decimalPattern('id_ID').format(value);
}
