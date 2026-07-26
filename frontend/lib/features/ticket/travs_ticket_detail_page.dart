import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:gal/gal.dart';
import 'package:intl/intl.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/models/travs_booking_contract.dart';
import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/theme/travs_theme.dart';

// Bagian besar dipisah per fungsi agar file utama lebih mudah dipelajari.
part 'bagian_detail_tiket/pemilih_pemilik_tiket.dart';
part 'bagian_detail_tiket/ringkasan_tiket.dart';
part 'bagian_detail_tiket/kartu_tiket.dart';
part 'bagian_detail_tiket/qr_dan_perjalanan_tiket.dart';
part 'bagian_detail_tiket/pengemudi_dan_kendaraan_tiket.dart';
part 'bagian_detail_tiket/jadwal_tiket.dart';
part 'bagian_detail_tiket/pratinjau_unduh_tiket.dart';

typedef TicketImageSaver =
    Future<void> Function(Uint8List bytes, String fileName);
typedef TicketGalleryAccessRequester = Future<bool> Function();
typedef TicketGallerySettingsOpener = Future<bool> Function();

class TravSTicketDetailPage extends StatefulWidget {
  const TravSTicketDetailPage({
    required this.session,
    required this.bookingId,
    this.apiService,
    this.saveTicketImage,
    this.requestGalleryAccess,
    this.openGallerySettings,
    super.key,
  });

  final TravSUserSession session;
  final int bookingId;
  final TravSApiService? apiService;
  final TicketImageSaver? saveTicketImage;
  final TicketGalleryAccessRequester? requestGalleryAccess;
  final TicketGallerySettingsOpener? openGallerySettings;

  @override
  State<TravSTicketDetailPage> createState() => _TravSTicketDetailPageState();
}

class _TravSTicketDetailPageState extends State<TravSTicketDetailPage> {
  late final TravSApiService _apiService;
  late Future<TravSBookingResult> _future;
  final GlobalKey _ticketCaptureKey = GlobalKey();
  int _selectedTicketIndex = 0;
  bool _isSavingTicket = false;

  @override
  void initState() {
    super.initState();
    _apiService = widget.apiService ?? TravSApiService();
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

  Future<void> _downloadTicket(
    TravSBookingResult booking,
    TravSBookingPassengerInfo passenger,
  ) async {
    if (_isSavingTicket) return;

    setState(() => _isSavingTicket = true);
    try {
      if (!await _ensureGalleryAccess()) {
        return;
      }

      // Card yang terlihat dirender langsung agar file sama dengan tampilan.
      final boundary =
          _ticketCaptureKey.currentContext?.findRenderObject()
              as RenderRepaintBoundary?;
      if (boundary == null) {
        throw StateError('Card tiket belum siap.');
      }

      final image = await boundary.toImage(pixelRatio: 3);
      final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
      image.dispose();
      if (byteData == null) {
        throw StateError('Gambar tiket belum dapat dibuat.');
      }

      final bytes = byteData.buffer.asUint8List();
      final fileName = _ticketFileName(booking, passenger);
      final customSaver = widget.saveTicketImage;
      if (customSaver != null) {
        await customSaver(bytes, fileName);
      } else {
        await Gal.putImageBytes(
          bytes,
          album: 'RuslyTravel',
          name: fileName.replaceFirst(RegExp(r'\.png$'), ''),
        );
      }

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tiket berhasil disimpan ke galeri.')),
      );
    } on GalException catch (error) {
      debugPrint('Gagal menyimpan tiket: $error');
      if (!mounted) return;

      if (error.type == GalExceptionType.accessDenied) {
        await _showGalleryPermissionDialog();
        return;
      }

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_galleryErrorMessage(error.type))));
    } catch (error) {
      debugPrint('Gagal menyimpan tiket: $error');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Tiket belum dapat disimpan. Silakan coba kembali.'),
        ),
      );
    } finally {
      if (mounted) setState(() => _isSavingTicket = false);
    }
  }

  Future<bool> _ensureGalleryAccess() async {
    final customRequester = widget.requestGalleryAccess;
    if (customRequester != null) {
      final granted = await customRequester();
      if (!granted && mounted) {
        await _showGalleryPermissionDialog();
      }
      return granted;
    }

    // Penyimpan uji tidak memakai galeri perangkat sehingga tidak memerlukan izin.
    if (widget.saveTicketImage != null) {
      return true;
    }

    final hasAccess = await Gal.hasAccess(toAlbum: true);
    if (hasAccess) {
      return true;
    }

    // Dialog sistem hanya muncul saat perangkat memang membutuhkan izin galeri.
    final granted = await Gal.requestAccess(toAlbum: true);
    if (!granted && mounted) {
      await _showGalleryPermissionDialog();
    }
    return granted;
  }

  Future<void> _showGalleryPermissionDialog() async {
    if (!mounted) return;

    final shouldOpenSettings = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Izin Galeri Diperlukan'),
        content: const Text(
          'Izinkan RuslyTravel menyimpan foto agar tiket dapat ditambahkan ke galeri. '
          'Jika izin pernah ditolak, aktifkan kembali melalui pengaturan aplikasi.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Nanti'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Buka Pengaturan'),
          ),
        ],
      ),
    );

    if (shouldOpenSettings == true) {
      final opener = widget.openGallerySettings ?? openAppSettings;
      await opener();
    }
  }

  String _galleryErrorMessage(GalExceptionType type) {
    return switch (type) {
      GalExceptionType.notEnoughSpace =>
        'Ruang penyimpanan tidak cukup untuk menyimpan tiket.',
      GalExceptionType.notSupportedFormat =>
        'Format gambar tiket tidak didukung perangkat.',
      GalExceptionType.accessDenied =>
        'Izin galeri belum diberikan. Aktifkan izin lalu coba lagi.',
      GalExceptionType.unexpected =>
        'Tiket belum dapat disimpan. Silakan coba kembali.',
    };
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
                final selectedIndex = tickets.isEmpty
                    ? 0
                    : _selectedTicketIndex.clamp(0, tickets.length - 1);
                final selectedPassenger = tickets.isEmpty
                    ? null
                    : tickets[selectedIndex];
                return RefreshIndicator(
                  color: TravSTheme.orange,
                  onRefresh: _refresh,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
                    children: [
                      if (tickets.isEmpty) ...[
                        _TicketHeaderCard(booking: booking),
                        const SizedBox(height: 16),
                        _EmptyTicketCard(booking: booking),
                      ] else ...[
                        if (tickets.length > 1) ...[
                          _TicketOwnerSelector(
                            passengers: tickets,
                            selectedIndex: selectedIndex,
                            onSelected: (index) {
                              setState(() => _selectedTicketIndex = index);
                            },
                          ),
                          const SizedBox(height: 16),
                        ],
                        RepaintBoundary(
                          key: _ticketCaptureKey,
                          child: KeyedSubtree(
                            key: const Key('active-ticket-card'),
                            child: Column(
                              children: [
                                _TicketHeaderCard(booking: booking),
                                const SizedBox(height: 16),
                                _TicketCard(
                                  booking: booking,
                                  passenger: selectedPassenger!,
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: _isSavingTicket
                                ? null
                                : () => _downloadTicket(
                                    booking,
                                    selectedPassenger,
                                  ),
                            icon: _isSavingTicket
                                ? const SizedBox.square(
                                    dimension: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.download_rounded),
                            label: Text(
                              _isSavingTicket
                                  ? 'Menyimpan Tiket...'
                                  : 'Download Tiket',
                            ),
                          ),
                        ),
                      ],
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
