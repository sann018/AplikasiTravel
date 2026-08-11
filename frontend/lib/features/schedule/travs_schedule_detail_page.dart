import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/models/app_role_preview.dart';
import '../../core/models/app_settings.dart';
import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/services/travs_seat_realtime_service.dart';
import '../../core/theme/travs_theme.dart';
import '../../core/widgets/travs_async_state.dart';
import '../payment/travs_payment_preview_page.dart';

// Bagian besar dipisah per fungsi agar file utama lebih mudah dipelajari.
part 'bagian_detail_jadwal/widget_ringkasan_jadwal.dart';
part 'bagian_detail_jadwal/peta_kursi.dart';
part 'bagian_detail_jadwal/widget_pengemudi_dan_metrik.dart';
part 'bagian_detail_jadwal/susunan_kursi_kendaraan.dart';
part 'bagian_detail_jadwal/widget_kursi.dart';

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
                tooltip: 'Tutup pratinjau',
                onPressed: () => Navigator.of(dialogContext).pop(),
                icon: const Icon(Icons.close_rounded),
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
    this.onRequireAuthentication,
    super.key,
  });

  final TravSSchedule schedule;
  final AppRolePreview selectedRole;
  final AppSettings appSettings;
  final TravSUserSession? session;
  final VoidCallback? onRequireAuthentication;

  @override
  State<TravSScheduleDetailPage> createState() =>
      _TravSScheduleDetailPageState();
}

class _TravSScheduleDetailPageState extends State<TravSScheduleDetailPage>
    with WidgetsBindingObserver {
  final TravSApiService _apiService = TravSApiService();
  late Future<TravSScheduleDetail> _future;

  int _seatCount = 0;
  final Set<int> _selectedSeatNumbers = <int>{};
  bool _isPreparingCheckout = false;
  bool _isRefreshingSeatAvailability = false;
  Timer? _seatRefreshTimer;
  late final TravSSeatRealtimeService _seatRealtimeService;

  bool get _canUseBookingForm =>
      (widget.selectedRole == AppRolePreview.passenger ||
          widget.selectedRole == AppRolePreview.counterStaff) &&
      widget.session != null;

  String get _bookingLockedMessage {
    if (widget.selectedRole == AppRolePreview.guest) {
      return 'Mode tamu hanya bisa melihat jadwal dan status kursi. Login sebagai penumpang dulu agar pilihan kursi dapat dikunci dengan aman.';
    }

    if (widget.selectedRole == AppRolePreview.driver) {
      return 'Mode pengemudi hanya bisa melihat informasi kursi untuk kebutuhan manifes.';
    }

    if (widget.selectedRole == AppRolePreview.counterStaff) {
      return 'Sesi Petugas Loket belum aktif. Silakan masuk ulang sebelum membuat pemesanan.';
    }

    return 'Sesi penumpang belum aktif. Silakan masuk ulang sebelum memilih kursi.';
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _future = _loadDetail();
    _seatRealtimeService = TravSSeatRealtimeService(
      settings: widget.appSettings.realtime,
      scheduleId: widget.schedule.id,
      onSeatChanged: _refreshSeatAvailability,
    );
    unawaited(_seatRealtimeService.connect());
    // Status kursi disegarkan berkala agar lock dari perangkat lain segera
    // terlihat dan kursi yang dilepas dapat dipilih kembali tanpa buka ulang halaman.
    _seatRefreshTimer = Timer.periodic(
      const Duration(seconds: 3),
      (_) => _refreshSeatAvailability(),
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _seatRefreshTimer?.cancel();
    unawaited(_seatRealtimeService.dispose());
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Sambungkan ulang setelah aplikasi kembali dari background.
      unawaited(_seatRealtimeService.reconnect());
      unawaited(_refreshSeatAvailability());
    }
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

  void _openAuthentication() {
    final callback = widget.onRequireAuthentication;
    if (callback == null) {
      Navigator.of(context).pop('open_login');
      return;
    }

    // Tutup detail jadwal dahulu, lalu buka autentikasi dari halaman induk.
    Navigator.of(context).pop();
    WidgetsBinding.instance.addPostFrameCallback((_) => callback());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Text('Detail Jadwal & Pilih Kursi'),
      ),
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
                        // Halaman induk membuka manifes lengkap untuk jadwal ini.
                        onPrimaryAction: () =>
                            Navigator.of(context).pop('open_driver_manifest'),
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
                            _openAuthentication();
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
                                  counterMode:
                                      widget.session!.user.isCounterStaff,
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
    final ticketSubtotal = detail.schedule.hargaTiket.round() * _seatCount;
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
