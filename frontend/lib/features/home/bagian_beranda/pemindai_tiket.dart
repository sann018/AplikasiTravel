part of '../travs_home_page.dart';

enum _ScanPreviewState { scanner, processing, valid, invalid }

class _ScanTicketPage extends StatefulWidget {
  const _ScanTicketPage({required this.apiService, required this.token});

  final TravSApiService apiService;
  final String token;

  @override
  State<_ScanTicketPage> createState() => _ScanTicketPageState();
}

class _ScanTicketPageState extends State<_ScanTicketPage> {
  final MobileScannerController _scannerController = MobileScannerController();

  _ScanPreviewState _state = _ScanPreviewState.scanner;
  String _message = 'Arahkan kamera ke QR tiket penumpang.';
  List<String> _detailLines = const [];
  bool _isProcessing = false;

  @override
  void dispose() {
    _scannerController.dispose();
    super.dispose();
  }

  Future<void> _handleDetect(BarcodeCapture capture) async {
    if (_isProcessing || _state != _ScanPreviewState.scanner) {
      return;
    }

    final rawValue = capture.barcodes
        .map((barcode) => barcode.rawValue)
        .whereType<String>()
        .map((value) => value.trim())
        .where((value) => value.isNotEmpty)
        .firstOrNull;

    if (rawValue == null) {
      return;
    }

    setState(() {
      _isProcessing = true;
      _state = _ScanPreviewState.processing;
      _message = 'Memvalidasi tiket ke server...';
      _detailLines = const [];
    });

    await _scannerController.stop();

    try {
      final result = await widget.apiService.scanDriverTicket(
        token: widget.token,
        ticketToken: rawValue,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _state = result.valid
            ? _ScanPreviewState.valid
            : _ScanPreviewState.invalid;
        _message = result.valid
            ? 'Tiket cocok dengan manifes dan sudah ditandai hadir.'
            : 'Tiket tidak valid untuk manifes aktif.';
        _detailLines = result.detailLines;
      });
    } on TravSApiException catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _state = _ScanPreviewState.invalid;
        _message = error.message;
        _detailLines = const [
          'Periksa ulang QR tiket.',
          'Pastikan jadwal sesuai dengan pengemudi aktif.',
          'Hubungi admin jika tiket perlu verifikasi manual.',
        ];
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _state = _ScanPreviewState.invalid;
        _message = 'Tiket belum bisa divalidasi. Periksa koneksi internet.';
        _detailLines = const [
          'Pastikan koneksi internet stabil.',
          'Coba scan ulang setelah koneksi stabil.',
        ];
      });
    } finally {
      _isProcessing = false;
    }
  }

  Future<void> _resetScanner() async {
    setState(() {
      _state = _ScanPreviewState.scanner;
      _message = 'Arahkan kamera ke QR tiket penumpang.';
      _detailLines = const [];
    });
    await _scannerController.start();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Text('Pindai Tiket'),
      ),
      body: Container(
        decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
          children: [
            const _SectionHeader(eyebrow: 'Pindai', title: 'Pindai tiket'),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(28),
                border: Border.all(color: TravSTheme.line),
              ),
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 220),
                child: switch (_state) {
                  _ScanPreviewState.scanner => Column(
                    key: const ValueKey('scanner'),
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(24),
                        child: SizedBox(
                          height: 320,
                          child: Stack(
                            fit: StackFit.expand,
                            children: [
                              MobileScanner(
                                controller: _scannerController,
                                onDetect: _handleDetect,
                              ),
                              Center(
                                child: Container(
                                  width: 220,
                                  height: 220,
                                  decoration: BoxDecoration(
                                    borderRadius: BorderRadius.circular(24),
                                    border: Border.all(
                                      color: Colors.white,
                                      width: 2,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        _message,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: TravSTheme.navy,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                  _ScanPreviewState.processing => _ScanResultCard(
                    key: const ValueKey('processing'),
                    title: 'Memvalidasi',
                    subtitle: _message,
                    tone: TravSTheme.navy,
                    icon: Icons.sync_rounded,
                    detailLines: const ['Mohon tunggu sebentar.'],
                    onReset: _resetScanner,
                  ),
                  _ScanPreviewState.valid => _ScanResultCard(
                    key: const ValueKey('valid'),
                    title: 'Tiket Valid',
                    subtitle: _message,
                    tone: TravSTheme.success,
                    icon: Icons.check_circle_outline_rounded,
                    detailLines: _detailLines,
                    onReset: _resetScanner,
                  ),
                  _ScanPreviewState.invalid => _ScanResultCard(
                    key: const ValueKey('invalid'),
                    title: 'Tiket Tidak Valid',
                    subtitle: _message,
                    tone: TravSTheme.warning,
                    icon: Icons.info_outline_rounded,
                    detailLines: _detailLines,
                    onReset: _resetScanner,
                  ),
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull {
    for (final item in this) {
      return item;
    }

    return null;
  }
}

class _NetworkThumbnail extends StatelessWidget {
  const _NetworkThumbnail({
    required this.imageUrl,
    required this.fallbackIcon,
    required this.label,
  });

  final String imageUrl;
  final IconData fallbackIcon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: SizedBox(
        width: 58,
        height: 58,
        child: Image.network(
          imageUrl,
          fit: BoxFit.cover,
          semanticLabel: label,
          errorBuilder: (context, error, stackTrace) {
            return _ThumbnailFallback(icon: fallbackIcon);
          },
          loadingBuilder: (context, child, loadingProgress) {
            if (loadingProgress == null) {
              return child;
            }

            return _ThumbnailFallback(icon: fallbackIcon);
          },
        ),
      ),
    );
  }
}

class _ThumbnailFallback extends StatelessWidget {
  const _ThumbnailFallback({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        border: Border.all(color: TravSTheme.line),
      ),
      child: Icon(icon, color: TravSTheme.navyMuted),
    );
  }
}
