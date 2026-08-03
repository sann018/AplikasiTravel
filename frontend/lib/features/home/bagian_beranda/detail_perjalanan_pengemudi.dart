part of '../travs_home_page.dart';

class _DriverJourneyDetailPage extends StatefulWidget {
  const _DriverJourneyDetailPage({
    required this.record,
    required this.expenseCategories,
    required this.onOpenScanner,
    required this.onStartJourney,
    required this.onRecordExpense,
    required this.onConfirmCash,
    required this.onDropoffPassenger,
    required this.onRefreshJourney,
  });

  final _DriverJourneyRecord record;
  final List<String> expenseCategories;
  final VoidCallback onOpenScanner;
  final Future<_DriverJourneyRecord?> Function(int tripId) onStartJourney;
  final Future<bool> Function({
    required int tripId,
    required String jenisBiaya,
    required double nominal,
    required DateTime tanggal,
    required String keterangan,
    required XFile fotoBuktiNota,
  })
  onRecordExpense;
  final Future<TravSDriverManifestPassenger?> Function({
    required int tripId,
    required int detailId,
  })
  onConfirmCash;
  final Future<TravSDriverManifestPassenger?> Function({
    required int tripId,
    required int detailId,
  })
  onDropoffPassenger;
  final Future<_DriverJourneyRecord?> Function(int tripId) onRefreshJourney;

  @override
  State<_DriverJourneyDetailPage> createState() =>
      _DriverJourneyDetailPageState();
}

class _DriverJourneyDetailPageState extends State<_DriverJourneyDetailPage> {
  late _DriverJourneyRecord _record;
  bool _isStartingJourney = false;
  int? _confirmingDetailId;
  int? _droppingOffDetailId;

  @override
  void initState() {
    super.initState();
    _record = widget.record;
  }

  void _handleOpenScanner() {
    handleDriverTicketScanRequest(
      context: context,
      scheduleStatus: _record.status,
      onAllowed: widget.onOpenScanner,
    );
  }

  Future<void> _handleStartJourney() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text(
          'Mulai Perjalanan',
          style: TextStyle(color: TravSTheme.navy, fontWeight: FontWeight.w800),
        ),
        content: Text(
          'Pastikan kendaraan, rute, dan manifes sudah diperiksa. Mulai perjalanan ${_record.route}?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Ya, Mulai'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) {
      return;
    }

    setState(() => _isStartingJourney = true);
    final updatedRecord = await widget.onStartJourney(_record.id);

    if (!mounted) {
      return;
    }

    if (updatedRecord != null) {
      setState(() => _record = updatedRecord);
    }
    setState(() => _isStartingJourney = false);
  }

  Future<void> _handleRecordExpense() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => _DriverOperationalExpenseSheet(
        routeLabel: _record.route,
        expenseCategories: widget.expenseCategories,
        onSubmit:
            ({
              required jenisBiaya,
              required nominal,
              required tanggal,
              required keterangan,
              required fotoBuktiNota,
            }) async {
              final saved = await widget.onRecordExpense(
                tripId: _record.id,
                jenisBiaya: jenisBiaya,
                nominal: nominal,
                tanggal: tanggal,
                keterangan: keterangan,
                fotoBuktiNota: fotoBuktiNota,
              );

              if (saved && sheetContext.mounted) {
                Navigator.of(sheetContext).pop();
              }
              return saved;
            },
      ),
    );
  }

  Future<void> _handleConfirmCash(_ManifestPassengerRecord passenger) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text(
          'Konfirmasi Pembayaran Tunai',
          style: TextStyle(color: TravSTheme.navy, fontWeight: FontWeight.w800),
        ),
        content: Text(
          'Pastikan ${passenger.name} sudah membayar tunai sebesar ${passenger.fareLabel}. Setelah dikonfirmasi, tiket digital akan diterbitkan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Ya, Sudah Bayar'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) {
      return;
    }

    setState(() {
      _confirmingDetailId = passenger.detailId;
    });

    final updatedPassenger = await widget.onConfirmCash(
      tripId: _record.id,
      detailId: passenger.detailId,
    );

    if (!mounted) {
      return;
    }

    if (updatedPassenger != null) {
      final updatedRecord = _ManifestPassengerRecord.fromApi(updatedPassenger);
      setState(() {
        _record = _record.copyWith(
          manifest: _record.manifest
              .map(
                (item) => item.detailId == updatedRecord.detailId
                    ? updatedRecord
                    : item,
              )
              .toList(),
        );
      });
    }

    setState(() {
      _confirmingDetailId = null;
    });
  }

  Future<void> _handleRefresh() async {
    final updatedRecord = await widget.onRefreshJourney(_record.id);
    if (!mounted || updatedRecord == null) {
      return;
    }

    setState(() {
      _record = updatedRecord;
    });
  }

  Future<void> _handleDropoff(_ManifestPassengerRecord passenger) async {
    var baggageHasBeenChecked = false;
    final seenBaggageUrls = <String>{};
    final baggagePhotoUrls = passenger.baggagePhotoUrls
        .map(TravSApiService.resolveMediaUrl)
        .whereType<String>()
        .where((url) {
          final normalized = url.trim();
          return normalized.isNotEmpty && seenBaggageUrls.add(normalized);
        })
        .take(3)
        .toList();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          scrollable: true,
          backgroundColor: TravSTheme.surface,
          surfaceTintColor: Colors.transparent,
          title: const Text(
            'Konfirmasi Penumpang Turun',
            style: TextStyle(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w700,
            ),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Pastikan ${passenger.name} sudah turun di tujuan sebelum perjalanan penumpang diselesaikan.',
                style: const TextStyle(
                  color: TravSTheme.navyMuted,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 14),
              CheckboxListTile(
                value: baggageHasBeenChecked,
                onChanged: (value) {
                  setDialogState(() {
                    baggageHasBeenChecked = value ?? false;
                  });
                },
                contentPadding: const EdgeInsets.symmetric(horizontal: 10),
                controlAffinity: ListTileControlAffinity.leading,
                activeColor: TravSTheme.orange,
                tileColor: TravSTheme.surfaceSoft,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: const BorderSide(color: TravSTheme.line),
                ),
                title: const Text(
                  'Barang bawaan sudah lengkap',
                  style: TextStyle(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                subtitle: Text(
                  '${passenger.baggageLabel}. Saya sudah memastikan tidak ada barang yang tertinggal.',
                  style: const TextStyle(
                    color: TravSTheme.navyMuted,
                    height: 1.35,
                  ),
                ),
              ),
              if (baggagePhotoUrls.isNotEmpty) ...[
                const SizedBox(height: 12),
                const Text(
                  'Foto barang bawaan',
                  style: TextStyle(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (
                      var index = 0;
                      index < baggagePhotoUrls.length;
                      index++
                    )
                      InkWell(
                        onTap: () => _showTravSImagePreview(
                          dialogContext,
                          imageUrl: baggagePhotoUrls[index],
                          title: 'Barang bawaan ${passenger.name}',
                        ),
                        borderRadius: BorderRadius.circular(12),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: SizedBox(
                            width: 64,
                            height: 64,
                            child: Image.network(
                              baggagePhotoUrls[index],
                              fit: BoxFit.cover,
                              errorBuilder: (context, error, stackTrace) =>
                                  const ColoredBox(
                                    color: TravSTheme.surfaceSoft,
                                    child: Icon(
                                      Icons.broken_image_outlined,
                                      color: TravSTheme.navyMuted,
                                    ),
                                  ),
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ],
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              style: TextButton.styleFrom(foregroundColor: TravSTheme.navy),
              child: const Text('Batal'),
            ),
            ElevatedButton(
              onPressed: baggageHasBeenChecked
                  ? () => Navigator.of(dialogContext).pop(true)
                  : null,
              style: ElevatedButton.styleFrom(
                foregroundColor: Colors.white,
                backgroundColor: TravSTheme.orange,
                disabledForegroundColor: TravSTheme.navyMuted,
                disabledBackgroundColor: TravSTheme.surfaceMuted,
              ),
              child: const Text('Ya, Penumpang Turun'),
            ),
          ],
        ),
      ),
    );

    if (confirmed != true || !mounted) {
      return;
    }

    setState(() {
      _droppingOffDetailId = passenger.detailId;
    });

    final updatedPassenger = await widget.onDropoffPassenger(
      tripId: _record.id,
      detailId: passenger.detailId,
    );

    if (!mounted) {
      return;
    }

    if (updatedPassenger != null) {
      final updatedRecord = _ManifestPassengerRecord.fromApi(updatedPassenger);
      setState(() {
        _record = _record.copyWith(
          manifest: _record.manifest
              .map(
                (item) => item.detailId == updatedRecord.detailId
                    ? updatedRecord
                    : item,
              )
              .toList(),
        );
      });
    }

    setState(() {
      _droppingOffDetailId = null;
    });
  }

  Future<void> _openPassengerLocation({
    required double? latitude,
    required double? longitude,
  }) async {
    if (latitude == null || longitude == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Koordinat lokasi belum tersedia.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final uri = Uri.parse(
      'https://www.google.com/maps/search/?api=1&query=$latitude,$longitude',
    );
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);

    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Google Maps belum dapat dibuka dari perangkat ini.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  String? _normalizeWhatsAppNumber(String value) {
    final digits = value.replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.isEmpty) {
      return null;
    }

    if (digits.startsWith('08')) {
      return '62${digits.substring(1)}';
    }

    if (digits.startsWith('8')) {
      return '62$digits';
    }

    return digits;
  }

  Future<void> _openPhoneOrWhatsApp(
    String value, {
    required bool whatsapp,
  }) async {
    final number = whatsapp
        ? _normalizeWhatsAppNumber(value)
        : value.replaceAll(RegExp(r'[^0-9+]'), '');
    if (number == null || number.isEmpty || number == '-') {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Nomor belum tersedia.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final uri = whatsapp
        ? Uri.parse('https://wa.me/$number')
        : Uri(scheme: 'tel', path: number);
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);

    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            whatsapp
                ? 'WhatsApp belum dapat dibuka.'
                : 'Aplikasi telepon belum dapat dibuka.',
          ),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  void _showImagePreview(String imageUrl) {
    _showTravSImagePreview(context, imageUrl: imageUrl);
  }

  void _showPassengerDetail(_ManifestPassengerRecord passenger) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (sheetContext) {
        final theme = Theme.of(sheetContext);
        final profileUrl = TravSApiService.resolveMediaUrl(
          passenger.profilePhotoUrl,
        );
        final seenBaggageUrls = <String>{};
        final baggageUrls = passenger.baggagePhotoUrls
            .map(TravSApiService.resolveMediaUrl)
            .whereType<String>()
            .where((url) {
              final normalized = url.trim();
              return normalized.isNotEmpty && seenBaggageUrls.add(normalized);
            })
            .take(3)
            .toList();

        return DraggableScrollableSheet(
          expand: false,
          initialChildSize: 0.78,
          maxChildSize: 0.92,
          minChildSize: 0.45,
          builder: (context, scrollController) => ListView(
            controller: scrollController,
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 28),
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: TravSTheme.line,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  _PassengerPhotoCircle(
                    name: passenger.name,
                    imageUrl: profileUrl,
                    size: 64,
                    onTap: profileUrl == null
                        ? null
                        : () => _showTravSImagePreview(
                            sheetContext,
                            imageUrl: profileUrl,
                            title: passenger.name,
                          ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          passenger.name,
                          style: theme.textTheme.titleLarge?.copyWith(
                            color: TravSTheme.navy,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          passenger.seatLabel,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: TravSTheme.orange,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _StatusPill(
                    label: passenger.canConfirmCash
                        ? 'Belum Lunas'
                        : passenger.paymentStatusLabel,
                    color: passenger.canConfirmCash
                        ? TravSTheme.orange
                        : TravSTheme.success,
                    textColor: passenger.canConfirmCash
                        ? TravSTheme.orange
                        : TravSTheme.success,
                  ),
                  _StatusPill(
                    label: passenger.attendanceStatus.toLowerCase() == 'hadir'
                        ? 'Hadir'
                        : 'Belum Hadir',
                    color: passenger.attendanceStatus.toLowerCase() == 'hadir'
                        ? TravSTheme.success
                        : TravSTheme.warning,
                    textColor:
                        passenger.attendanceStatus.toLowerCase() == 'hadir'
                        ? TravSTheme.success
                        : TravSTheme.warning,
                  ),
                  _StatusPill(
                    label: passenger.isDroppedOff ? 'Turun' : 'Belum Turun',
                    color: passenger.isDroppedOff
                        ? TravSTheme.success
                        : TravSTheme.navyMuted,
                    textColor: passenger.isDroppedOff
                        ? TravSTheme.success
                        : TravSTheme.navyMuted,
                  ),
                ],
              ),
              if (passenger.canConfirmCash) ...[
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      Navigator.of(sheetContext).pop();
                      _handleConfirmCash(passenger);
                    },
                    icon: const Icon(Icons.payments_rounded),
                    label: const Text('Konfirmasi Pembayaran Tunai'),
                  ),
                ),
              ],
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _openPhoneOrWhatsApp(
                        passenger.phone,
                        whatsapp: false,
                      ),
                      icon: const Icon(Icons.call_outlined),
                      label: const Text('Telepon'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () =>
                          _openPhoneOrWhatsApp(passenger.phone, whatsapp: true),
                      icon: const Icon(Icons.chat_outlined),
                      label: const Text('WhatsApp'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              _PassengerDetailBlock(
                title: 'Identitas penumpang',
                rows: [
                  _InfoRowData('Nama akun', passenger.accountName),
                  _InfoRowData('Email', passenger.accountEmail),
                  _InfoRowData('No. HP penumpang', passenger.phone),
                ],
              ),
              const SizedBox(height: 12),
              _PassengerDetailBlock(
                title: 'Informasi pemesanan',
                rows: [
                  _InfoRowData('Barang bawaan', passenger.baggageLabel),
                  _InfoRowData('Harga Tiket', passenger.fareLabel),
                  _InfoRowData('Pembayaran', passenger.paymentStatusLabel),
                  _InfoRowData(
                    'Kehadiran',
                    _humanizeStatus(passenger.attendanceStatus),
                  ),
                  _InfoRowData(
                    'Status turun',
                    passenger.isDroppedOff ? 'Sudah turun' : 'Belum turun',
                  ),
                ],
              ),
              const SizedBox(height: 12),
              _PassengerLocationBlock(
                title: 'Lokasi Penjemputan',
                address: passenger.pickupAddress,
                icon: Icons.my_location_rounded,
                canOpenMaps:
                    passenger.pickupLatitude != null &&
                    passenger.pickupLongitude != null,
                onOpenMaps: () => _openPassengerLocation(
                  latitude: passenger.pickupLatitude,
                  longitude: passenger.pickupLongitude,
                ),
              ),
              const SizedBox(height: 12),
              _PassengerLocationBlock(
                title: 'Lokasi Tujuan / Turun',
                address: passenger.dropoffAddress,
                icon: Icons.location_on_rounded,
                canOpenMaps:
                    passenger.dropoffLatitude != null &&
                    passenger.dropoffLongitude != null,
                onOpenMaps: () => _openPassengerLocation(
                  latitude: passenger.dropoffLatitude,
                  longitude: passenger.dropoffLongitude,
                ),
              ),
              if (baggageUrls.isNotEmpty) ...[
                const SizedBox(height: 12),
                Text(
                  'Foto barang bawaan',
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                GridView.builder(
                  itemCount: baggageUrls.length,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 3,
                    crossAxisSpacing: 8,
                    mainAxisSpacing: 8,
                  ),
                  itemBuilder: (context, index) {
                    final photoUrl = baggageUrls[index];
                    return GestureDetector(
                      onTap: () => _showImagePreview(photoUrl),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(14),
                        child: Image.network(
                          photoUrl,
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) {
                            return const _EmptyStateCard(
                              title: 'Foto tidak tersedia',
                              subtitle: '',
                            );
                          },
                        ),
                      ),
                    );
                  },
                ),
              ],
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final record = _record;
    final statusBackground = _journeyStatusBackground(record.status);
    final statusTextColor = _journeyStatusTextColor(record.status);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Text('Detail Perjalanan'),
      ),
      body: Container(
        decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
        child: RefreshIndicator(
          onRefresh: _handleRefresh,
          color: TravSTheme.navy,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
            children: [
              Container(
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  gradient: TravSTheme.headerGradient,
                  borderRadius: BorderRadius.circular(28),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _StatusPill(
                      label: record.statusLabel,
                      color: statusBackground,
                      textColor: statusTextColor,
                      solid: true,
                    ),
                    const SizedBox(height: 14),
                    Text(
                      record.route,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Tanggal keberangkatan',
                                style: Theme.of(context).textTheme.bodySmall
                                    ?.copyWith(color: Colors.white70),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                _formatDateTime(
                                  record.departureAt,
                                ).split('\n').first,
                                style: Theme.of(context).textTheme.bodyMedium
                                    ?.copyWith(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w600,
                                    ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Waktu keberangkatan',
                                style: Theme.of(context).textTheme.bodySmall
                                    ?.copyWith(color: Colors.white70),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                _formatDateTime(
                                          record.departureAt,
                                        ).split('\n').length >
                                        1
                                    ? _formatDateTime(
                                        record.departureAt,
                                      ).split('\n').skip(1).join(' ')
                                    : '-',
                                style: Theme.of(context).textTheme.bodyMedium
                                    ?.copyWith(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w600,
                                    ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Kendaraan',
                      style: Theme.of(
                        context,
                      ).textTheme.bodySmall?.copyWith(color: Colors.white70),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      record.vehicle,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 16),
                    // Pengemudi cukup melihat informasi operasional penumpang.
                    _HeroStatTile(
                      stat: _HeroStat(
                        label: 'Penumpang',
                        value: '${record.passengerCount}',
                      ),
                      dark: true,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: TravSTheme.line),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Manifes Penumpang',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: TravSTheme.navy,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 14),
                    ...record.manifest.map(
                      (passenger) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _ManifestPassengerTile(
                          passenger: passenger,
                          isConfirming:
                              _confirmingDetailId == passenger.detailId,
                          isDroppingOff:
                              _droppingOffDetailId == passenger.detailId,
                          // Perjalanan dianggap sudah dimulai ketika statusnya
                          // "berangkat", sehingga tombol turun baru bisa dipakai.
                          journeyStarted: _record.status == 'berangkat',
                          onConfirmCash: passenger.canConfirmCash
                              ? () => _handleConfirmCash(passenger)
                              : null,
                          onDropoff: passenger.canDropoff
                              ? () => _handleDropoff(passenger)
                              : null,
                          onOpenDetail: () => _showPassengerDetail(passenger),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              if (_record.status == 'dibuka' ||
                  _record.status == 'ditutup') ...[
                ElevatedButton.icon(
                  onPressed: _isStartingJourney ? null : _handleStartJourney,
                  icon: _isStartingJourney
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.directions_car_filled_rounded),
                  label: Text(
                    _isStartingJourney
                        ? 'Memulai Perjalanan...'
                        : 'Mulai Perjalanan',
                  ),
                ),
                const SizedBox(height: 12),
              ],
              if (const {
                'dibuka',
                'ditutup',
                'berangkat',
              }.contains(_record.status)) ...[
                OutlinedButton.icon(
                  onPressed: _handleRecordExpense,
                  icon: const Icon(Icons.receipt_long_outlined),
                  label: const Text('Catat Biaya Operasional'),
                ),
                const SizedBox(height: 12),
              ],
              ElevatedButton.icon(
                onPressed: _handleOpenScanner,
                icon: const Icon(Icons.qr_code_scanner_rounded),
                label: const Text('Pindai Tiket'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

typedef _DriverExpenseSubmitCallback =
    Future<bool> Function({
      required String jenisBiaya,
      required double nominal,
      required DateTime tanggal,
      required String keterangan,
      required XFile fotoBuktiNota,
    });

class _DriverOperationalExpenseSheet extends StatefulWidget {
  const _DriverOperationalExpenseSheet({
    required this.routeLabel,
    required this.expenseCategories,
    required this.onSubmit,
  });

  final String routeLabel;
  final List<String> expenseCategories;
  final _DriverExpenseSubmitCallback onSubmit;

  @override
  State<_DriverOperationalExpenseSheet> createState() =>
      _DriverOperationalExpenseSheetState();
}

class _DriverOperationalExpenseSheetState
    extends State<_DriverOperationalExpenseSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nominalController = TextEditingController();
  final _keteranganController = TextEditingController();
  final _imagePicker = ImagePicker();
  DateTime _tanggal = DateTime.now();
  String? _selectedCategory;
  XFile? _fotoBuktiNota;
  String? _fotoBuktiError;
  bool _isSubmitting = false;

  @override
  void dispose() {
    _nominalController.dispose();
    _keteranganController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: _tanggal,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 1)),
      helpText: 'Pilih tanggal biaya',
    );
    if (!mounted || selected == null) {
      return;
    }

    final now = DateTime.now();
    setState(() {
      _tanggal = DateTime(
        selected.year,
        selected.month,
        selected.day,
        now.hour,
        now.minute,
      );
    });
  }

  Future<void> _pickProof(ImageSource source) async {
    try {
      final selected = await _imagePicker.pickImage(
        source: source,
        imageQuality: 82,
        maxWidth: 1600,
        maxHeight: 1600,
      );
      if (!mounted || selected == null) {
        return;
      }

      setState(() {
        _fotoBuktiNota = selected;
        _fotoBuktiError = null;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _fotoBuktiError =
              'Foto belum dapat dipilih. Periksa izin kamera atau galeri.';
        });
      }
    }
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    final formValid = _formKey.currentState?.validate() ?? false;
    if (_fotoBuktiNota == null) {
      setState(() => _fotoBuktiError = 'Foto bukti/nota wajib dipilih.');
    }
    if (!formValid || _fotoBuktiNota == null) {
      return;
    }

    final nominal = double.tryParse(_nominalController.text.trim());
    if (nominal == null || nominal <= 0) {
      return;
    }

    setState(() => _isSubmitting = true);
    final saved = await widget.onSubmit(
      jenisBiaya: _selectedCategory!,
      nominal: nominal,
      tanggal: _tanggal,
      keterangan: _keteranganController.text.trim(),
      fotoBuktiNota: _fotoBuktiNota!,
    );
    if (mounted && !saved) {
      setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;

    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.92,
      ),
      padding: EdgeInsets.fromLTRB(24, 12, 24, 24 + bottomInset),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 44,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 20),
                  decoration: BoxDecoration(
                    color: TravSTheme.line,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
              Text(
                'Catat Biaya Operasional',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                widget.routeLabel,
                style: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(color: TravSTheme.slate),
              ),
              const SizedBox(height: 20),
              DropdownButtonFormField<String>(
                initialValue: _selectedCategory,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'Kategori pengeluaran',
                  hintText: 'Pilih kategori',
                  prefixIcon: Icon(Icons.category_outlined),
                ),
                items: widget.expenseCategories
                    .map(
                      (category) => DropdownMenuItem<String>(
                        value: category,
                        child: Text(category),
                      ),
                    )
                    .toList(growable: false),
                onChanged: _isSubmitting
                    ? null
                    : (value) => setState(() => _selectedCategory = value),
                validator: (value) => value == null || value.trim().isEmpty
                    ? 'Kategori pengeluaran wajib dipilih.'
                    : null,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _nominalController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: const InputDecoration(
                  labelText: 'Nominal biaya',
                  hintText: 'Contoh: 50000',
                  prefixText: 'Rp ',
                  prefixIcon: Icon(Icons.payments_rounded),
                ),
                validator: (value) {
                  final nominal = double.tryParse(value?.trim() ?? '');
                  if (nominal == null || nominal <= 0) {
                    return 'Nominal biaya wajib lebih dari Rp0.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 14),
              InkWell(
                onTap: _pickDate,
                borderRadius: BorderRadius.circular(16),
                child: InputDecorator(
                  decoration: const InputDecoration(
                    labelText: 'Tanggal biaya',
                    prefixIcon: Icon(Icons.calendar_month_rounded),
                  ),
                  child: Text(
                    DateFormat('dd MMMM yyyy', 'id_ID').format(_tanggal),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _keteranganController,
                minLines: 3,
                maxLines: 5,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'Keterangan',
                  hintText: 'Jelaskan tujuan pengeluaran dengan singkat.',
                  alignLabelWithHint: true,
                ),
                validator: (value) {
                  final description = value?.trim() ?? '';
                  if (description.length < 10) {
                    return 'Keterangan minimal 10 karakter.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 18),
              Text(
                'Foto bukti/nota *',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                'Ambil atau pilih foto nota/struk. Format gambar maksimal 3 MB.',
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: TravSTheme.slate),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  OutlinedButton.icon(
                    onPressed: _isSubmitting
                        ? null
                        : () => _pickProof(ImageSource.camera),
                    icon: const Icon(Icons.photo_camera_outlined),
                    label: const Text('Kamera'),
                  ),
                  OutlinedButton.icon(
                    onPressed: _isSubmitting
                        ? null
                        : () => _pickProof(ImageSource.gallery),
                    icon: const Icon(Icons.photo_library_outlined),
                    label: const Text('Galeri'),
                  ),
                ],
              ),
              if (_fotoBuktiNota != null) ...[
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 10,
                  ),
                  decoration: BoxDecoration(
                    color: TravSTheme.surfaceSoft,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.check_circle_outline_rounded,
                        color: TravSTheme.success,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _fotoBuktiNota!.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      IconButton(
                        tooltip: 'Hapus foto',
                        visualDensity: VisualDensity.compact,
                        onPressed: _isSubmitting
                            ? null
                            : () => setState(() => _fotoBuktiNota = null),
                        icon: const Icon(Icons.close_rounded),
                      ),
                    ],
                  ),
                ),
              ],
              if (_fotoBuktiError != null) ...[
                const SizedBox(height: 7),
                Text(
                  _fotoBuktiError!,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.error,
                  ),
                ),
              ],
              const SizedBox(height: 22),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _isSubmitting ? null : _submit,
                  icon: _isSubmitting
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.save_outlined),
                  label: Text(
                    _isSubmitting ? 'Menyimpan...' : 'Simpan Pencatatan',
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ManifestPassengerTile extends StatelessWidget {
  const _ManifestPassengerTile({
    required this.passenger,
    required this.isConfirming,
    required this.isDroppingOff,
    required this.journeyStarted,
    this.onConfirmCash,
    this.onDropoff,
    this.onOpenDetail,
  });

  final _ManifestPassengerRecord passenger;
  final bool isConfirming;
  final bool isDroppingOff;
  // Tombol turun hanya boleh aktif setelah perjalanan dimulai (status berangkat).
  final bool journeyStarted;
  final VoidCallback? onConfirmCash;
  final VoidCallback? onDropoff;
  final VoidCallback? onOpenDetail;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  passenger.name,
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              _StatusPill(
                label: passenger.seatLabel,
                color: TravSTheme.navy,
                textColor: TravSTheme.navy,
              ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text(
                passenger.phone,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
              _StatusPill(
                label: passenger.paymentStatusLabel,
                color: passenger.canConfirmCash
                    ? TravSTheme.orange
                    : TravSTheme.success,
                textColor: passenger.canConfirmCash
                    ? TravSTheme.orange
                    : TravSTheme.success,
              ),
              _StatusPill(
                label: passenger.journeyStatusLabel,
                color: passenger.isDroppedOff
                    ? TravSTheme.navyMuted
                    : passenger.attendanceStatus.toLowerCase() == 'hadir'
                    ? TravSTheme.success
                    : TravSTheme.warning,
                textColor: passenger.isDroppedOff
                    ? TravSTheme.navyMuted
                    : passenger.attendanceStatus.toLowerCase() == 'hadir'
                    ? TravSTheme.success
                    : TravSTheme.warning,
              ),
            ],
          ),
          if (passenger.canConfirmCash) ...[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: isConfirming ? null : onConfirmCash,
                icon: isConfirming
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.payments_rounded),
                label: Text(
                  isConfirming
                      ? 'Mengonfirmasi...'
                      : 'Konfirmasi Penumpang Sudah Bayar',
                ),
              ),
            ),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onOpenDetail,
                  icon: const Icon(Icons.info_rounded),
                  label: const Text('Detail'),
                ),
              ),
              if (passenger.canDropoff) ...[
                const SizedBox(width: 10),
                Expanded(
                  // Tombol turun dikunci sampai perjalanan dimulai. Tooltip
                  // menjelaskan alasannya agar pengemudi tidak bingung.
                  child: Tooltip(
                    message: journeyStarted
                        ? ''
                        : 'Mulai perjalanan dulu sebelum menandai penumpang turun.',
                    triggerMode: journeyStarted
                        ? TooltipTriggerMode.manual
                        : TooltipTriggerMode.tap,
                    child: ElevatedButton.icon(
                      onPressed: (isDroppingOff || !journeyStarted)
                          ? null
                          : onDropoff,
                      icon: isDroppingOff
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.check_circle_outline_rounded),
                      label: Text(isDroppingOff ? 'Memproses...' : 'Turun'),
                    ),
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 8),
          _MetaColumn(label: 'Patokan Jemput', value: passenger.pickupAddress),
          const SizedBox(height: 8),
          _MetaColumn(label: 'Patokan Turun', value: passenger.dropoffAddress),
          const SizedBox(height: 8),
          _MetaColumn(label: 'Barang bawaan', value: passenger.baggageLabel),
          const SizedBox(height: 8),
          _MetaColumn(label: 'Harga Tiket', value: passenger.fareLabel),
        ],
      ),
    );
  }
}

class _PassengerPhotoCircle extends StatelessWidget {
  const _PassengerPhotoCircle({
    required this.name,
    required this.imageUrl,
    required this.size,
    this.onTap,
  });

  final String name;
  final String? imageUrl;
  final double size;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty ? '?' : name.trim()[0].toUpperCase();
    final fallback = Container(
      width: size,
      height: size,
      decoration: const BoxDecoration(
        color: TravSTheme.navy,
        shape: BoxShape.circle,
      ),
      child: Center(
        child: Text(
          initial,
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );

    if (imageUrl == null || imageUrl!.isEmpty) {
      return fallback;
    }

    final photo = ClipOval(
      child: Image.network(
        imageUrl!,
        width: size,
        height: size,
        fit: BoxFit.cover,
        errorBuilder: (context, error, stackTrace) => fallback,
        loadingBuilder: (context, child, loadingProgress) {
          if (loadingProgress == null) {
            return child;
          }

          return SizedBox(
            width: size,
            height: size,
            child: const Center(
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          );
        },
      ),
    );

    if (onTap == null) {
      return photo;
    }

    return GestureDetector(onTap: onTap, child: photo);
  }
}

class _InfoRowData {
  const _InfoRowData(this.label, this.value);

  final String label;
  final String value;
}

class _PassengerDetailBlock extends StatelessWidget {
  const _PassengerDetailBlock({required this.title, required this.rows});

  final String title;
  final List<_InfoRowData> rows;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: theme.textTheme.titleMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 12),
          ...rows.map(
            (row) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _MetaColumn(label: row.label, value: row.value),
            ),
          ),
        ],
      ),
    );
  }
}

class _PassengerLocationBlock extends StatelessWidget {
  const _PassengerLocationBlock({
    required this.title,
    required this.address,
    required this.icon,
    required this.canOpenMaps,
    required this.onOpenMaps,
  });

  final String title;
  final String address;
  final IconData icon;
  final bool canOpenMaps;
  final VoidCallback onOpenMaps;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 20, color: TravSTheme.orange),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            address.trim().isEmpty || address == '-'
                ? 'Alamat belum diisi.'
                : address,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: TravSTheme.navy,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: canOpenMaps ? onOpenMaps : null,
            icon: const Icon(Icons.map_rounded),
            label: const Text('Buka di Google Maps'),
          ),
        ],
      ),
    );
  }
}
