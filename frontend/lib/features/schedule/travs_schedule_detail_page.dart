import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/models/app_role_preview.dart';
import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/theme/travs_theme.dart';
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
              final detail =
                  snapshot.data ?? TravSScheduleDetail.mock(widget.schedule);
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
                        selectedRole: widget.selectedRole,
                        isPreparingCheckout: _isPreparingCheckout,
                        onClearSeatSelection: () => _clearSeatSelection(detail),
                        onPrimaryAction: () async {
                          if (widget.selectedRole == AppRolePreview.guest) {
                            _showStageMessage(
                              'Mode guest sudah bisa melihat detail jadwal. Login dulu untuk mengunci kursi dan lanjut checkout.',
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
                label: detail.isLive ? 'Live API' : 'Preview Lokal',
                color: detail.isLive ? TravSTheme.success : TravSTheme.orange,
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
    required this.onSeatTap,
  });

  final TravSScheduleDetail detail;
  final Set<int> selectedSeatNumbers;
  final int maxSeatCount;
  final ValueChanged<TravSSeatItem> onSeatTap;

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
              'Denah Kursi',
              style: theme.textTheme.titleLarge?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Pilih kursi langsung dari denah ini. Kursi yang sudah dipilih akan dibawa ke checkout.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 16),
            const _SeatLegend(),
            const SizedBox(height: 18),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: detail.seatMap
                  .map(
                    (seat) => _SeatTile(
                      seat: seat,
                      isSelected: selectedSeatNumbers.contains(seat.seatNumber),
                      onTap: () => onSeatTap(seat),
                    ),
                  )
                  .toList(),
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
    required this.selectedRole,
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
  final AppRolePreview selectedRole;
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
              'Pilih kursi dulu, lalu isi data penumpang, pickup, dan metode pembayaran di langkah berikutnya.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
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
                          onPressed: onClearSeatSelection,
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
                  selectedRole == AppRolePreview.guest
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
                      : selectedRole == AppRolePreview.guest
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

class _SeatLegend extends StatelessWidget {
  const _SeatLegend();

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 12,
      runSpacing: 12,
      children: const [
        _LegendItem(label: 'Dipilih', color: TravSTheme.navy),
        _LegendItem(label: 'Tersedia', color: Color(0xFFE9F8F1)),
        _LegendItem(label: 'Terkunci', color: TravSTheme.orangeSoft),
        _LegendItem(label: 'Terpesan', color: Color(0xFFE8EDF5)),
      ],
    );
  }
}

class _SeatTile extends StatelessWidget {
  const _SeatTile({
    required this.seat,
    required this.isSelected,
    required this.onTap,
  });

  final TravSSeatItem seat;
  final bool isSelected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isSelectable = seat.status == 'tersedia';
    final colors = isSelected
        ? (background: TravSTheme.navy, foreground: Colors.white)
        : switch (seat.status) {
            'dipesan' => (
              background: const Color(0xFFE8EDF5),
              foreground: TravSTheme.navyMuted,
            ),
            'terkunci' => (
              background: TravSTheme.orangeSoft,
              foreground: TravSTheme.orange,
            ),
            _ => (
              background: const Color(0xFFE9F8F1),
              foreground: TravSTheme.success,
            ),
          };

    return InkWell(
      onTap: isSelectable ? onTap : null,
      borderRadius: BorderRadius.circular(22),
      child: Container(
        width: 78,
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: colors.background,
          borderRadius: BorderRadius.circular(22),
          border: isSelected
              ? Border.all(color: TravSTheme.navySoft, width: 1.4)
              : null,
        ),
        child: Column(
          children: [
            Icon(Icons.event_seat, color: colors.foreground),
            const SizedBox(height: 6),
            Text(
              'A${seat.seatNumber}',
              style: TextStyle(
                color: colors.foreground,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
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
    required this.onChanged,
  });

  final String label;
  final String helper;
  final int value;
  final int minValue;
  final int maxValue;
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
                enabled: value > minValue,
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
                enabled: value < maxValue,
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
    required this.onChanged,
  });

  final String selectedMethod;
  final bool weightEnabled;
  final bool sizeEnabled;
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
            onSelectionChanged: (values) {
              if (values.isNotEmpty) {
                onChanged(values.first);
              }
            },
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
    required this.onChanged,
  });

  final String label;
  final String helper;
  final double value;
  final double maxValue;
  final String suffix;
  final bool allowDecimal;
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
            enabled: maxValue > 0,
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
    required this.onLengthChanged,
    required this.onWidthChanged,
  });

  final String helper;
  final int length;
  final int width;
  final int totalSize;
  final int maxSize;
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
                  onChanged: onLengthChanged,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _IntegerInput(
                  label: 'Lebar',
                  value: width,
                  maxValue: maxSize,
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
    required this.onChanged,
  });

  final String label;
  final int value;
  final int maxValue;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      initialValue: value > 0 ? '$value' : '',
      enabled: maxValue > 0,
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
