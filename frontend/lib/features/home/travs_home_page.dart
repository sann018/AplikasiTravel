import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/models/app_role_preview.dart';
import '../../core/models/app_settings.dart';
import '../../core/models/travs_booking_contract.dart';
import '../../core/models/travs_notification.dart';
import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/services/settings_api_service.dart';
import '../../core/services/travs_auth_service.dart';
import '../../core/theme/travs_theme.dart';
import '../../core/widgets/travs_async_state.dart';
import '../booking/travs_booking_detail_page.dart';
import '../schedule/travs_schedule_detail_page.dart';
import '../ticket/travs_ticket_detail_page.dart';

enum _ScheduleDateFilter { all, tomorrow, byDate }

const String _notificationsEnabledPrefsKey = 'travs_notifications_enabled';
const String _aboutRuslyTravelDescription =
    'RuslyTravel adalah aplikasi layanan pemesanan travel lintas kota yang membantu pengguna melihat informasi perjalanan dan membuat pemesanan secara lebih praktis dari perangkat mobile.\n\n'
    'Melalui RuslyTravel, pengguna dapat:\n'
    '1. Melihat rute, jadwal, harga tiket, armada, pengemudi, dan ketersediaan kursi.\n'
    '2. Memilih kursi dan mengisi data penumpang sesuai kebutuhan perjalanan.\n'
    '3. Mengatur titik penjemputan serta titik tujuan atau titik turun agar pengemudi lebih mudah menemukan lokasi.\n'
    '4. Mencatat nama atau keterangan barang bawaan dan menambahkan foto jika diperlukan.\n'
    '5. Memilih metode pembayaran yang tersedia sesuai aturan travel.\n'
    '6. Menerima tiket digital dengan QR Code sebagai bukti perjalanan setelah pembayaran terverifikasi.\n'
    '7. Memantau status pemesanan, tiket, notifikasi, dan riwayat perjalanan melalui aplikasi.\n\n'
    'Untuk pengguna tamu, aplikasi dapat digunakan untuk melihat informasi rute, jadwal, dan aturan pemesanan sebelum membuat akun. Fitur pemesanan, pembayaran, dan tiket digital tersedia setelah pengguna masuk sebagai penumpang.';

void _showTravSImagePreview(
  BuildContext context, {
  required String imageUrl,
  String? title,
}) {
  final cleanTitle = title?.trim();

  showDialog<void>(
    context: context,
    builder: (context) => Dialog(
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
                if (cleanTitle != null && cleanTitle.isNotEmpty) ...[
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
                      cleanTitle,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: TravSTheme.navy,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
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

class TravSHomePage extends StatefulWidget {
  const TravSHomePage({
    required this.onOpenAuth,
    this.session,
    this.onLogout,
    this.onSessionUpdated,
    super.key,
  });

  final TravSUserSession? session;
  final VoidCallback onOpenAuth;
  final Future<void> Function()? onLogout;
  final ValueChanged<TravSUserSession>? onSessionUpdated;

  @override
  State<TravSHomePage> createState() => _TravSHomePageState();
}

class _TravSHomePageState extends State<TravSHomePage> {
  final TravSApiService _apiService = TravSApiService();
  final SettingsApiService _settingsApiService = SettingsApiService();
  final TravSAuthService _authService = TravSAuthService();

  late Future<_HomeBundle> _future;
  int _selectedTabIndex = 0;
  String? _selectedRouteKey;
  _ScheduleDateFilter _selectedScheduleFilter = _ScheduleDateFilter.all;
  DateTime? _selectedScheduleDate;
  DateTime? _driverHistoryStartDate;
  DateTime? _driverHistoryEndDate;
  DateTime? _appliedDriverHistoryStartDate;
  DateTime? _appliedDriverHistoryEndDate;
  bool _hasSyncedSessionProfile = false;
  bool _notificationsEnabled = false;
  AppSettings _appSettings = AppSettings.defaults;

  AppRolePreview get _role =>
      widget.session?.rolePreview ?? AppRolePreview.guest;

  String get _displayName {
    final fullName = widget.session?.user.nama.trim();
    if (fullName == null || fullName.isEmpty) {
      return 'Tamu';
    }

    return fullName.split(' ').first;
  }

  String get _fullDisplayName {
    final fullName = widget.session?.user.nama.trim();
    return fullName == null || fullName.isEmpty ? _displayName : fullName;
  }

  @override
  void initState() {
    super.initState();
    _future = _loadBundle();
    Future<void>.microtask(_loadLocalSettings);
    Future<void>.microtask(_syncSessionProfileFromBackend);
  }

  Future<void> _loadLocalSettings() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      if (!mounted) {
        return;
      }

      setState(() {
        _notificationsEnabled =
            prefs.getBool(_notificationsEnabledPrefsKey) ?? false;
      });
    } catch (_) {
      // Jika data pengaturan lokal rusak, aplikasi tetap memakai pengaturan awal.
    }
  }

  @override
  void didUpdateWidget(covariant TravSHomePage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.session?.token != widget.session?.token) {
      _selectedTabIndex = 0;
      _selectedRouteKey = null;
      _selectedScheduleFilter = _ScheduleDateFilter.all;
      _selectedScheduleDate = null;
      _hasSyncedSessionProfile = false;
      _future = _loadBundle();
      Future<void>.microtask(_syncSessionProfileFromBackend);
    }
  }

  Future<void> _syncSessionProfileFromBackend() async {
    final session = widget.session;
    if (_hasSyncedSessionProfile || session == null) {
      return;
    }

    _hasSyncedSessionProfile = true;

    try {
      final latestUser = await _apiService.loadCurrentUserProfile(
        token: session.token,
      );
      if (!mounted) {
        return;
      }

      final latestSession = session.copyWith(user: latestUser);
      await _authService.saveSession(latestSession);
      widget.onSessionUpdated?.call(latestSession);
    } catch (_) {
      // Data sesi lama dipakai sementara agar profil tetap bisa dibuka saat backend lambat.
    }
  }

  Future<_HomeBundle> _loadBundle({bool forceSettingsRefresh = false}) async {
    final payloadFuture = _apiService.loadHomePayload();
    final settingsFuture = _settingsApiService.load(
      forceRefresh: forceSettingsRefresh,
    );
    final payload = await payloadFuture;
    final appSettings = await settingsFuture;
    _appSettings = appSettings;
    if (_role == AppRolePreview.passenger && widget.session != null) {
      final dashboardFuture = _apiService.loadPassengerDashboard(
        widget.session!.token,
      );
      final bookingsFuture = _apiService.loadPassengerBookings(
        widget.session!.token,
      );
      final notificationsFuture = _loadNotificationFeedSafely();
      final dashboard = await dashboardFuture;
      final bookings = await bookingsFuture;
      final notifications = await notificationsFuture;
      return _HomeBundle(
        payload: payload,
        appSettings: appSettings,
        passengerDashboard: dashboard,
        passengerBookings: bookings,
        notifications: notifications,
      );
    }

    if (_role == AppRolePreview.driver && widget.session != null) {
      final dashboardFuture = _apiService.loadDriverDashboard(
        widget.session!.token,
      );
      final tripsFuture = _apiService.loadDriverTrips(widget.session!.token);
      final notificationsFuture = _loadNotificationFeedSafely();
      final dashboard = await dashboardFuture;
      final trips = await tripsFuture;
      final notifications = await notificationsFuture;
      return _HomeBundle(
        payload: payload,
        appSettings: appSettings,
        driverDashboard: dashboard,
        driverTrips: trips,
        notifications: notifications,
      );
    }

    return _HomeBundle(payload: payload, appSettings: appSettings);
  }

  Future<TravSNotificationFeed> _loadNotificationFeedSafely({
    bool markAsRead = false,
  }) async {
    final session = widget.session;
    if (session == null) {
      return TravSNotificationFeed.empty;
    }

    try {
      return await _apiService.loadNotifications(
        token: session.token,
        markAsRead: markAsRead,
      );
    } catch (_) {
      // Gangguan notifikasi tidak boleh membuat seluruh beranda gagal dimuat.
      return TravSNotificationFeed.empty;
    }
  }

  Future<void> _refresh() async {
    final future = _loadBundle(forceSettingsRefresh: true);
    setState(() {
      _future = future;
    });
    await future;
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  Future<void> _openScheduleDetail(TravSSchedule schedule) async {
    final result = await Navigator.of(context).push<Object>(
      MaterialPageRoute<Object>(
        builder: (_) => TravSScheduleDetailPage(
          schedule: schedule,
          selectedRole: _role,
          session: widget.session,
          appSettings: _appSettings,
        ),
      ),
    );

    if (!mounted) return;

    // Tamu diarahkan ke login karena pemesanan hanya boleh dibuat oleh akun penumpang.
    if (result == 'open_login') {
      widget.onOpenAuth();
      return;
    }

    if (result == 'open_tickets') {
      await _refresh();
      if (!mounted) return;
      setState(() => _selectedTabIndex = 3);
      return;
    }

    if (result == true) {
      await _refresh();
      _showMessage(
        'Pemesanan berhasil dibuat. Ringkasan pemesanan sudah diperbarui.',
      );
    }
  }

  Future<void> _openBookingDetail(int bookingId) async {
    if (widget.session == null) {
      return;
    }

    final shouldRefresh = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => TravSBookingDetailPage(
          session: widget.session!,
          bookingId: bookingId,
        ),
      ),
    );

    if (shouldRefresh == true && mounted) {
      await _refresh();
    }
  }

  Future<void> _openTicketDetail(int bookingId) async {
    if (widget.session == null) {
      return;
    }

    final shouldRefresh = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => TravSTicketDetailPage(
          session: widget.session!,
          bookingId: bookingId,
        ),
      ),
    );

    if (shouldRefresh == true && mounted) {
      await _refresh();
    }
  }

  void _goToHomeTab() {
    setState(() {
      _selectedTabIndex = 0;
      _selectedRouteKey = null;
      _selectedScheduleFilter = _ScheduleDateFilter.all;
      _selectedScheduleDate = null;
    });
  }

  Future<void> _openEditProfile(TravSUserSession session) async {
    final updatedSession = await Navigator.of(context).push<TravSUserSession>(
      MaterialPageRoute<TravSUserSession>(
        builder: (_) => _EditProfilePage(
          session: session,
          apiService: _apiService,
          authService: _authService,
        ),
      ),
    );

    if (updatedSession == null || !mounted) {
      return;
    }

    widget.onSessionUpdated?.call(updatedSession);
    await _refresh();
    _showMessage('Profil berhasil diperbarui.');
  }

  Future<void> _openPassengerInfoPage() {
    return Navigator.of(context).push<void>(
      MaterialPageRoute<void>(builder: (_) => const _PassengerInfoPage()),
    );
  }

  Future<void> _pickScheduleDate() async {
    final now = DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: _selectedScheduleDate ?? now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 2),
    );

    if (selected == null || !mounted) {
      return;
    }

    setState(() {
      _selectedScheduleDate = selected;
      _selectedScheduleFilter = _ScheduleDateFilter.byDate;
    });
  }

  Future<void> _pickDriverHistoryDate({required bool isStart}) async {
    final now = DateTime.now();
    final current = isStart ? _driverHistoryStartDate : _driverHistoryEndDate;
    final selected = await showDatePicker(
      context: context,
      initialDate: current ?? now,
      firstDate: DateTime(now.year - 5),
      lastDate: DateTime(now.year + 2),
    );

    if (selected == null || !mounted) return;

    setState(() {
      if (isStart) {
        _driverHistoryStartDate = selected;
      } else {
        _driverHistoryEndDate = selected;
      }
    });
  }

  void _applyDriverHistoryFilter() {
    final start = _driverHistoryStartDate;
    final end = _driverHistoryEndDate;
    if (start != null && end != null && start.isAfter(end)) {
      _showMessage('Tanggal mulai tidak boleh melewati tanggal selesai.');
      return;
    }

    setState(() {
      _appliedDriverHistoryStartDate = start;
      _appliedDriverHistoryEndDate = end;
    });
  }

  void _resetDriverHistoryFilter() {
    setState(() {
      _driverHistoryStartDate = null;
      _driverHistoryEndDate = null;
      _appliedDriverHistoryStartDate = null;
      _appliedDriverHistoryEndDate = null;
    });
  }

  List<_DriverJourneyRecord> _filterDriverHistory(
    List<_DriverJourneyRecord> records,
  ) {
    // Filter baru diterapkan setelah tombol ditekan agar input tanggal tidak memicu request.
    final start = _appliedDriverHistoryStartDate;
    final end = _appliedDriverHistoryEndDate;

    return records.where((record) {
      final departure = DateTime.tryParse(record.departureAt)?.toLocal();
      if (departure == null) return start == null && end == null;

      final date = DateTime(departure.year, departure.month, departure.day);
      final afterStart = start == null || !date.isBefore(start);
      final beforeEnd = end == null || !date.isAfter(end);
      return afterStart && beforeEnd;
    }).toList();
  }

  Widget _backToHomeButton() {
    return IconButton(
      onPressed: _goToHomeTab,
      icon: const Icon(Icons.arrow_back_rounded),
    );
  }

  Future<void> _openNotifications(_HomeBundle bundle) async {
    var feed = bundle.notifications;
    try {
      final session = widget.session;
      if (session != null) {
        // Status awal dipakai untuk membagi notifikasi baru dan lama di layar.
        // Setelah halaman dibuka, server baru diberi tanda bahwa notifikasi sudah dibaca.
        feed = await _apiService.loadNotifications(token: session.token);
        await _apiService.loadNotifications(
          token: session.token,
          markAsRead: true,
        );
      }
    } catch (_) {
      if (mounted) {
        _showMessage(
          'Notifikasi terbaru belum bisa dimuat. Daftar terakhir tetap ditampilkan.',
        );
      }
    }

    if (!mounted) {
      return;
    }

    final selectedEntry = await Navigator.of(context).push<_NotificationEntry>(
      MaterialPageRoute<_NotificationEntry>(
        builder: (_) => _NotificationPage(
          title: 'Notifikasi',
          subtitle: _role == AppRolePreview.driver
              ? 'Update operasional, pengingat jadwal, dan aktivitas scan.'
              : 'Status pemesanan, tiket, dan promo perjalanan terbaru.',
          entries: _buildApiNotifications(feed.items),
          notificationsEnabled: _notificationsEnabled,
          showPermissionSettings: false,
          onToggleNotifications: _setNotificationsEnabled,
          onClearHistory: _clearNotificationHistory,
        ),
      ),
    );

    if (!mounted) {
      return;
    }

    if (selectedEntry != null) {
      await _openNotificationTarget(selectedEntry);
    }

    if (mounted) {
      await _refresh();
    }
  }

  Future<void> _openNotificationTarget(_NotificationEntry entry) async {
    final referenceId = entry.referenceId;
    if (referenceId == null) {
      _showMessage('Notifikasi ini belum memiliki halaman tujuan.');
      return;
    }

    if (_role == AppRolePreview.driver &&
        entry.notificationType == 'perjalanan') {
      await _openDriverJourneyById(referenceId);
      return;
    }

    if (_role == AppRolePreview.passenger) {
      final message = '${entry.title} ${entry.message}'.toLowerCase();
      if (entry.notificationType == 'pembayaran' &&
          (message.contains('berhasil') || message.contains('tiket'))) {
        await _openTicketDetail(referenceId);
        return;
      }

      if (entry.notificationType == 'pembayaran' ||
          entry.notificationType == 'pemesanan' ||
          entry.notificationType == 'perjalanan') {
        await _openBookingDetail(referenceId);
        return;
      }
    }

    _showMessage('Halaman tujuan notifikasi belum tersedia untuk akun ini.');
  }

  Future<List<_NotificationEntry>> _clearNotificationHistory(
    String period,
  ) async {
    final session = widget.session;
    if (session == null) {
      return const [];
    }

    try {
      final deletedCount = await _apiService.clearNotificationHistory(
        token: session.token,
        period: period,
      );
      final refreshed = await _apiService.loadNotifications(
        token: session.token,
      );
      if (mounted) {
        _showMessage(
          deletedCount > 0
              ? '$deletedCount riwayat notifikasi berhasil dihapus.'
              : 'Tidak ada riwayat notifikasi yang perlu dihapus.',
        );
      }
      return _buildApiNotifications(refreshed.items);
    } catch (_) {
      if (mounted) {
        _showMessage('Riwayat notifikasi belum bisa dibersihkan. Coba lagi.');
      }
      rethrow;
    }
  }

  Future<bool> _setNotificationsEnabled(bool enabled) async {
    if (enabled) {
      final status = await Permission.notification.request();
      if (!status.isGranted) {
        if (mounted) {
          _showMessage(
            'Izin notifikasi belum diberikan. Aktifkan izin dari pengaturan perangkat jika diperlukan.',
          );
        }
        return false;
      }
    }

    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_notificationsEnabledPrefsKey, enabled);
      if (mounted) {
        setState(() => _notificationsEnabled = enabled);
        _showMessage(
          enabled
              ? 'Notifikasi aplikasi diaktifkan.'
              : 'Notifikasi aplikasi dimatikan.',
        );
      }
      return true;
    } catch (_) {
      if (mounted) {
        _showMessage('Pengaturan notifikasi belum bisa disimpan.');
      }
      return false;
    }
  }

  Future<void> _openDriverJourney(_DriverJourneyRecord record) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => _DriverJourneyDetailPage(
          record: record,
          onOpenScanner: _openScanner,
          onStartJourney: _startDriverJourney,
          onRecordExpense: _recordDriverOperationalExpense,
          onConfirmCash: _confirmDriverPassengerCash,
          onDropoffPassenger: _markDriverPassengerDropoff,
          onRefreshJourney: _refreshDriverJourney,
        ),
      ),
    );
  }

  Future<void> _openDriverJourneyById(int tripId) async {
    final record = await _refreshDriverJourney(tripId);
    if (!mounted || record == null) {
      return;
    }

    await _openDriverJourney(record);
  }

  Future<_DriverJourneyRecord?> _refreshDriverJourney(int tripId) async {
    final session = widget.session;
    if (session == null) {
      return null;
    }

    try {
      final trip = await _apiService.loadDriverTripManifest(
        token: session.token,
        tripId: tripId,
      );

      if (mounted) {
        await _refresh();
      }

      return _DriverJourneyRecord.fromTrip(trip);
    } on TravSApiException catch (error) {
      if (mounted) {
        _showMessage(error.message);
      }
    } catch (_) {
      if (mounted) {
        _showMessage('Gagal memuat ulang detail perjalanan.');
      }
    }

    return null;
  }

  Future<_DriverJourneyRecord?> _startDriverJourney(int tripId) async {
    final session = widget.session;
    if (session == null) {
      _showMessage(
        'Login sebagai pengemudi diperlukan untuk memulai perjalanan.',
      );
      return null;
    }

    try {
      // Status disimpan di backend agar aplikasi pengemudi dan web admin
      // membaca kondisi perjalanan yang sama dari jadwal.
      final trip = await _apiService.updateDriverTripStatus(
        token: session.token,
        tripId: tripId,
        status: 'berangkat',
      );

      if (mounted) {
        _showMessage('Perjalanan berhasil dimulai.');
        await _refresh();
      }

      return _DriverJourneyRecord.fromTrip(trip);
    } on TravSApiException catch (error) {
      if (mounted) {
        _showMessage(error.message);
      }
    } catch (_) {
      if (mounted) {
        _showMessage('Perjalanan belum dapat dimulai. Coba lagi.');
      }
    }

    return null;
  }

  Future<bool> _recordDriverOperationalExpense({
    required int tripId,
    required String jenisBiaya,
    required double nominal,
    required DateTime tanggal,
    required String keterangan,
    required XFile fotoBuktiNota,
  }) async {
    final session = widget.session;
    if (session == null) {
      _showMessage('Login sebagai pengemudi diperlukan untuk mencatat biaya.');
      return false;
    }

    try {
      // Backend menyimpan nominal final agar catatan admin dan pengemudi selalu
      // memakai sumber data operasional yang sama.
      await _apiService.submitDriverOperationalExpense(
        token: session.token,
        tripId: tripId,
        jenisBiaya: jenisBiaya,
        nominal: nominal,
        tanggal: tanggal,
        keterangan: keterangan,
        fotoBuktiNota: fotoBuktiNota,
      );

      if (mounted) {
        _showMessage('Biaya operasional berhasil dicatat.');
        await _refresh();
      }
      return true;
    } on TravSApiException catch (error) {
      if (mounted) {
        _showMessage(error.message);
      }
    } catch (_) {
      if (mounted) {
        _showMessage('Biaya operasional belum dapat dicatat. Coba lagi.');
      }
    }

    return false;
  }

  Future<TravSDriverManifestPassenger?> _confirmDriverPassengerCash({
    required int tripId,
    required int detailId,
  }) async {
    final session = widget.session;
    if (session == null) {
      _showMessage(
        'Login sebagai pengemudi diperlukan untuk konfirmasi tunai.',
      );
      return null;
    }

    try {
      final passenger = await _apiService.confirmDriverPassengerCash(
        token: session.token,
        tripId: tripId,
        detailId: detailId,
      );

      if (mounted) {
        _showMessage(
          'Pembayaran tunai dikonfirmasi. Tiket digital diterbitkan.',
        );
        await _refresh();
      }

      return passenger;
    } on TravSApiException catch (error) {
      if (mounted) {
        _showMessage(error.message);
      }
    } catch (_) {
      if (mounted) {
        _showMessage('Konfirmasi tunai gagal. Coba lagi beberapa saat.');
      }
    }

    return null;
  }

  Future<TravSDriverManifestPassenger?> _markDriverPassengerDropoff({
    required int tripId,
    required int detailId,
  }) async {
    final session = widget.session;
    if (session == null) {
      _showMessage('Login sebagai pengemudi diperlukan untuk validasi turun.');
      return null;
    }

    try {
      final passenger = await _apiService.markDriverPassengerDropoff(
        token: session.token,
        tripId: tripId,
        detailId: detailId,
      );

      if (mounted) {
        _showMessage('Penumpang berhasil ditandai turun.');
        await _refresh();
      }

      return passenger;
    } on TravSApiException catch (error) {
      if (mounted) {
        _showMessage(error.message);
      }
    } catch (_) {
      if (mounted) {
        _showMessage('Validasi turun gagal. Coba lagi beberapa saat.');
      }
    }

    return null;
  }

  Future<void> _openScanner() async {
    if (widget.session == null) {
      _showMessage('Login sebagai pengemudi diperlukan untuk scan tiket.');
      return;
    }

    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => _ScanTicketPage(
          apiService: _apiService,
          token: widget.session!.token,
        ),
      ),
    );

    if (mounted) {
      await _refresh();
    }
  }

  List<_DriverJourneyRecord> _driverJourneys(_HomeBundle bundle) {
    final trips = bundle.driverTrips ?? const [];
    return _DriverJourneyRecord.fromTrips(trips);
  }

  List<_DriverJourneyRecord> _activeDriverJourneys(_HomeBundle bundle) {
    final trips = bundle.driverDashboard?.upcomingTrips ?? const [];
    return _DriverJourneyRecord.fromTrips(trips);
  }

  List<_NotificationEntry> _buildApiNotifications(
    List<TravSNotification> notifications,
  ) {
    return notifications.map((notification) {
      final tone = switch (notification.jenis) {
        'pembayaran' => _NotificationTone.success,
        'pemesanan' || 'perjalanan' => _NotificationTone.warning,
        _ => _NotificationTone.info,
      };

      return _NotificationEntry(
        notificationType: notification.jenis,
        referenceId: notification.referensiId,
        title: notification.judul,
        message: notification.pesan,
        timeLabel: notification.createdAt == null
            ? '-'
            : DateFormat(
                'dd MMM yyyy HH:mm',
              ).format(notification.createdAt!.toLocal()),
        category: _notificationCategoryLabel(notification.jenis),
        tone: tone,
        isUnread: !notification.sudahDibaca,
      );
    }).toList();
  }

  String _notificationCategoryLabel(String type) {
    return switch (type) {
      'pembayaran' => 'Pembayaran',
      'pemesanan' => 'Pemesanan',
      'perjalanan' => 'Perjalanan',
      _ => 'Sistem',
    };
  }

  Widget _notificationButton(_HomeBundle bundle) {
    return _NotificationBadgeButton(
      count: bundle.notifications.unreadCount,
      animateUnread: _role != AppRolePreview.guest,
      onTap: () => _openNotifications(bundle),
    );
  }

  List<Widget> _pagesForRole(_HomeBundle bundle) {
    switch (_role) {
      case AppRolePreview.driver:
        return [
          _buildDriverHome(bundle),
          _buildDriverManifest(bundle),
          _buildDriverHistory(bundle),
          _buildDriverProfile(bundle),
        ];
      case AppRolePreview.passenger:
        return [
          _buildPassengerHome(bundle),
          _buildPassengerRoutes(bundle),
          _buildPassengerOrders(bundle),
          _buildPassengerTickets(bundle),
          _buildPassengerProfile(bundle),
        ];
      case AppRolePreview.guest:
        return [
          _buildGuestHome(bundle),
          _buildGuestRoutes(bundle),
          _buildGuestInfo(bundle),
          _buildGuestAccount(bundle),
        ];
    }
  }

  Widget _buildPassengerHome(_HomeBundle bundle) {
    final dashboard =
        bundle.passengerDashboard ??
        const TravSPassengerDashboard(
          totalBooking: 0,
          bookingAktif: 0,
          tiketAktif: 0,
          maxSeatPerBooking: 4,
          recentBookings: [],
        );
    final bookings = bundle.passengerBookings ?? const [];
    final nextPaidBooking = _nextPaidUpcomingBooking(bookings);
    final waitingPayment = bookings
        .where((booking) => booking.payment.status == 'menunggu')
        .toList();
    final routes = _buildRouteBookmarks(bundle.payload.featuredSchedules);
    final hasHomeTripContent =
        nextPaidBooking != null ||
        waitingPayment.isNotEmpty ||
        routes.isNotEmpty;
    final activeTicketCount = bookings.fold<int>(
      0,
      (total, booking) => total + booking.activeTicketCount,
    );

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          title: bundle.appSettings.appName,
          subtitle: 'Layanan pemesanan travel lintas kota.',
          trailing: _notificationButton(bundle),
        ),
        const SizedBox(height: 16),
        _PassengerDashboardHero(
          name: _displayName,
          profileImageUrl: TravSApiService.resolveMediaUrl(
            widget.session?.user.fotoProfil,
          ),
          banners: bundle.payload.banners,
          stats: [
            _HeroStat(
              label: 'Pemesanan aktif',
              value: '${dashboard.bookingAktif}',
            ),
            _HeroStat(label: 'Tiket aktif', value: '$activeTicketCount'),
            _HeroStat(
              label: 'Total pemesanan',
              value: '${dashboard.totalBooking}',
            ),
          ],
        ),
        if (bundle.payload.importantInformation.isNotEmpty) ...[
          const SizedBox(height: 16),
          _ImportantInformationSection(
            items: bundle.payload.importantInformation,
          ),
        ],
        const SizedBox(height: 18),
        if (!hasHomeTripContent) ...[
          const _EmptyStateCard(
            title: 'Tidak ada perjalanan yang tersedia saat ini.',
            subtitle:
                'Jadwal akan tampil setelah admin membuka perjalanan baru untuk penumpang.',
          ),
          const SizedBox(height: 18),
        ],
        if (nextPaidBooking != null) ...[
          _NextTripCard(
            booking: nextPaidBooking,
            onTap: () => _openBookingDetail(nextPaidBooking.id),
          ),
          const SizedBox(height: 12),
        ],
        if (waitingPayment.isNotEmpty) ...[
          _InlineActionCard(
            title: 'Pembayaran menunggu',
            description:
                '${waitingPayment.first.rute} - ${_formatShortDate(waitingPayment.first.waktuBerangkat)}',
            actionLabel: 'Bayar',
            icon: Icons.account_balance_wallet_rounded,
            onTap: () => _openBookingDetail(waitingPayment.first.id),
          ),
          const SizedBox(height: 18),
        ],
        if (bookings.isEmpty && routes.isNotEmpty)
          const _EmptyStateCard(
            title: 'Belum ada pemesanan.',
            subtitle:
                'Mulai dari menu rute dan jadwal untuk membuat pemesanan pertama.',
          ),
        const SizedBox(height: 18),
        if (routes.isNotEmpty) ...[
          Row(
            children: [
              Expanded(
                child: Text(
                  'Rute Populer',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              TextButton(
                onPressed: () => setState(() => _selectedTabIndex = 1),
                child: const Text('Lihat Semua'),
              ),
            ],
          ),
          const SizedBox(height: 8),
          ...routes
              .take(2)
              .map(
                (route) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _RouteBookmarkCard(
                    route: route,
                    onTap: () {
                      setState(() {
                        _selectedRouteKey = route.key;
                        _selectedTabIndex = 1;
                      });
                    },
                  ),
                ),
              ),
        ],
      ],
    );
  }

  Widget _buildPassengerRoutes(_HomeBundle bundle) {
    return _buildRouteBookmarkFlow(
      bundle: bundle,
      routeSubtitle: 'Pilih rute perjalanan yang tersedia.',
      scheduleSubtitle: 'Pilih tanggal dan waktu keberangkatan.',
      ctaLabel: 'Pesan',
      routeTrailing: IconButton(
        onPressed: () => _openNotifications(bundle),
        icon: const Icon(Icons.notifications_none_rounded),
      ),
    );
  }

  Widget _buildPassengerOrders(_HomeBundle bundle) {
    final bookings = bundle.passengerBookings ?? const [];
    final latestBookings = bookings.where((item) => !_isOldBooking(item));
    final oldBookings = bookings.where(_isOldBooking);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          leading: _backToHomeButton(),
          title: 'Daftar Pemesanan',
          subtitle: 'Status pemesanan, pembayaran, dan akses tiket.',
          trailing: _notificationButton(bundle),
        ),
        const SizedBox(height: 16),
        if (bookings.isEmpty)
          const _EmptyStateCard(
            title: 'Belum ada riwayat pemesanan.',
            subtitle:
                'Setelah pemesanan pertama dibuat, daftar pemesanan akan muncul di halaman ini.',
          )
        else ...[
          const _SectionHeader(
            eyebrow: 'Pemesanan',
            title: 'Pemesanan terbaru',
            subtitle:
                'Pemesanan yang masih berjalan atau masih perlu dipantau.',
          ),
          const SizedBox(height: 12),
          if (latestBookings.isEmpty)
            const _EmptyStateCard(
              title: 'Belum ada pemesanan terbaru.',
              subtitle:
                  'Pemesanan aktif akan muncul ketika Anda membuat perjalanan baru.',
            )
          else
            ...latestBookings.map(
              (booking) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _BookingCard(
                  booking: booking,
                  onTap: () => _openBookingDetail(booking.id),
                ),
              ),
            ),
          if (oldBookings.isNotEmpty) ...[
            const SizedBox(height: 14),
            const Divider(),
            const SizedBox(height: 14),
            const _SectionHeader(
              eyebrow: 'Riwayat Pemesanan',
              title: 'Pemesanan lama',
              subtitle:
                  'Pemesanan yang sudah selesai, dibatalkan, atau melewati jadwal.',
            ),
            const SizedBox(height: 12),
            ...oldBookings.map(
              (booking) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _BookingCard(
                  booking: booking,
                  onTap: () => _openBookingDetail(booking.id),
                ),
              ),
            ),
          ],
        ],
      ],
    );
  }

  Widget _buildPassengerTickets(_HomeBundle bundle) {
    final bookings = bundle.passengerBookings ?? const [];
    final tickets = bookings.where((item) => item.isPaid).toList();
    final activeTickets = tickets
        .where((item) => !_isCompletedTicket(item))
        .toList();
    final completedTickets = tickets.where(_isCompletedTicket).toList();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          leading: _backToHomeButton(),
          title: 'Daftar Tiket',
          subtitle: 'Tiket aktif dan QR keberangkatan.',
          trailing: IconButton(
            onPressed: () => _openNotifications(bundle),
            icon: const Icon(Icons.notifications_none_rounded),
          ),
        ),
        const SizedBox(height: 16),
        _SectionHeader(
          eyebrow: 'Tiket',
          title: 'Tiket aktif',
          subtitle:
              'Tiket muncul setelah pembayaran terverifikasi dan QR diterbitkan.',
        ),
        const SizedBox(height: 12),
        if (tickets.isEmpty)
          const _EmptyStateCard(
            title: 'Belum ada tiket aktif.',
            subtitle:
                'Selesaikan pembayaran pemesanan agar tiket bisa dibuka di halaman ini.',
          )
        else ...[
          ...activeTickets.map(
            (booking) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _TicketPreviewCard(
                booking: booking,
                isCompleted: false,
                onTap: () => _openTicketDetail(booking.id),
              ),
            ),
          ),
          if (completedTickets.isNotEmpty) ...[
            const SizedBox(height: 14),
            const Divider(),
            const SizedBox(height: 14),
            const _SectionHeader(
              eyebrow: 'Riwayat tiket',
              title: 'Tiket Selesai',
              subtitle: 'Tiket untuk perjalanan yang sudah selesai digunakan.',
            ),
            const SizedBox(height: 12),
            ...completedTickets.map(
              (booking) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _TicketPreviewCard(
                  booking: booking,
                  isCompleted: true,
                  onTap: () => _openTicketDetail(booking.id),
                ),
              ),
            ),
          ],
        ],
      ],
    );
  }

  Widget _buildPassengerProfile(_HomeBundle bundle) {
    final session = widget.session;
    if (session == null) {
      return _buildGuestAccount(bundle);
    }

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          leading: _backToHomeButton(),
          title: 'Profil',
          subtitle: 'Info akun dan pengaturan cepat.',
        ),
        const SizedBox(height: 16),
        _ProfileSummaryCard(session: session),
        const SizedBox(height: 16),
        _ProfileMenuTile(
          icon: Icons.edit_outlined,
          title: 'Edit Profil',
          subtitle: 'Atur nama dan data kontak akun.',
          onTap: () => _openEditProfile(session),
        ),
        const SizedBox(height: 10),
        _ProfileMenuTile(
          icon: Icons.fact_check_outlined,
          title: 'Info',
          subtitle: 'Aturan dan tata cara pemesanan RuslyTravel.',
          onTap: _openPassengerInfoPage,
        ),
        const SizedBox(height: 10),
        _ProfileMenuTile(
          icon: Icons.info_outline_rounded,
          title: 'Tentang Aplikasi',
          subtitle: 'Kenali layanan dan cara kerja RuslyTravel.',
          onTap: () => _showAboutSheet(
            title: 'Tentang RuslyTravel',
            description: _aboutRuslyTravelDescription,
          ),
        ),
        const SizedBox(height: 18),
        if (widget.onLogout != null)
          OutlinedButton.icon(
            onPressed: widget.onLogout,
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Keluar'),
          ),
      ],
    );
  }

  Widget _buildDriverHome(_HomeBundle bundle) {
    final dashboard =
        bundle.driverDashboard ??
        const TravSDriverDashboard(
          jadwalAktif: 0,
          penumpangTerdaftar: 0,
          upcomingTrips: [],
        );
    final records = _activeDriverJourneys(bundle);
    final assignedVehicle = dashboard.assignedVehicle;
    final nextTrip = records.isEmpty ? null : records.first;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          title: 'Halo, $_displayName!',
          subtitle: 'Pantau perjalanan yang ditugaskan hari ini.',
          trailing: _notificationButton(bundle),
        ),
        const SizedBox(height: 16),
        _DriverHeroCard(
          // Kotak operasional menampilkan nama lengkap agar identitas tugas jelas.
          displayName: _fullDisplayName,
          phoneNumber: widget.session?.user.noHp ?? '-',
          simNumber: widget.session?.user.nomorSim ?? '-',
          profileImageUrl: TravSApiService.resolveMediaUrl(
            widget.session?.user.fotoProfil,
          ),
          stats: [
            _HeroStat(label: 'Jadwal aktif', value: '${dashboard.jadwalAktif}'),
            _HeroStat(
              label: 'Penumpang',
              value: '${dashboard.penumpangTerdaftar}',
            ),
            _HeroStat(label: 'Manifest', value: '${records.length}'),
          ],
        ),
        const SizedBox(height: 14),
        _AssignedVehicleCard(
          vehicle: assignedVehicle,
          message: dashboard.assignedVehicleMessage,
        ),
        const SizedBox(height: 18),
        if (nextTrip != null)
          _InlineActionCard(
            title: 'Tugas terdekat',
            description:
                '${nextTrip.route} - ${_formatDateTime(nextTrip.departureAt)} - ${nextTrip.vehicle}',
            actionLabel: 'Lihat Manifest',
            icon: Icons.assignment_turned_in_outlined,
            onTap: () => _openDriverJourney(nextTrip),
          ),
        if (nextTrip != null) const SizedBox(height: 16),
        _SectionHeader(
          eyebrow: 'Perjalanan',
          title: 'Trip aktif',
          subtitle:
              'Fokus ke jadwal, manifest, dan akses pindai tiket untuk tugas pengemudi.',
        ),
        const SizedBox(height: 12),
        if (records.isEmpty)
          const _EmptyStateCard(
            title: 'Tidak ada perjalanan yang tersedia hari ini.',
            subtitle:
                'Jadwal aktif akan muncul setelah admin menugaskan perjalanan yang masih dibuka atau sedang berjalan.',
          )
        else
          ...records
              .take(3)
              .map(
                (record) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _DriverJourneyCard(
                    record: record,
                    onTap: () => _openDriverJourney(record),
                  ),
                ),
              ),
      ],
    );
  }

  Widget _buildDriverManifest(_HomeBundle bundle) {
    final records = _activeDriverJourneys(bundle);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          leading: _backToHomeButton(),
          title: 'Manifest',
          subtitle: 'Daftar penumpang per perjalanan.',
          trailing: _notificationButton(bundle),
        ),
        const SizedBox(height: 16),
        if (records.isEmpty)
          const _EmptyStateCard(
            title: 'Belum ada manifest aktif.',
            subtitle:
                'Saat jadwal ditetapkan ke akun pengemudi, data perjalanan akan tampil di sini.',
          )
        else
          ...records.map(
            (record) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _DriverJourneyCard(
                record: record,
                onTap: () => _openDriverJourney(record),
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildDriverHistory(_HomeBundle bundle) {
    final records = _filterDriverHistory(_driverJourneys(bundle));

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          leading: _backToHomeButton(),
          title: 'Riwayat Perjalanan',
          subtitle: 'Trip yang berjalan dan yang sudah selesai.',
        ),
        const SizedBox(height: 16),
        _DriverHistoryDateFilter(
          startDate: _driverHistoryStartDate,
          endDate: _driverHistoryEndDate,
          onPickStart: () => _pickDriverHistoryDate(isStart: true),
          onPickEnd: () => _pickDriverHistoryDate(isStart: false),
          onApply: _applyDriverHistoryFilter,
          onReset: _resetDriverHistoryFilter,
        ),
        const SizedBox(height: 16),
        if (records.isEmpty)
          const _EmptyStateCard(
            title: 'Riwayat perjalanan tidak ditemukan.',
            subtitle: 'Coba ubah rentang tanggal lalu terapkan filter kembali.',
          )
        else
          ...records.map(
            (record) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _DriverHistoryCard(
                record: record,
                onTap: () => _openDriverJourney(record),
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildDriverProfile(_HomeBundle bundle) {
    final session = widget.session;
    if (session == null) {
      return _buildGuestAccount(bundle);
    }
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          leading: _backToHomeButton(),
          title: 'Profil',
          subtitle: 'Ringkasan akun pengemudi dan alat bantu kerja.',
        ),
        const SizedBox(height: 16),
        _DriverProfileWorkCard(
          session: session,
          vehicle: bundle.driverDashboard?.assignedVehicle,
          message: bundle.driverDashboard?.assignedVehicleMessage,
        ),
        const SizedBox(height: 16),
        _ProfileMenuTile(
          icon: Icons.edit_outlined,
          title: 'Edit Profil',
          subtitle: 'Atur nama, kontak, password, dan foto akun pengemudi.',
          onTap: () => _openEditProfile(session),
        ),
        const SizedBox(height: 10),
        _ProfileMenuTile(
          icon: Icons.info_outline_rounded,
          title: 'Tata Cara dan Aturan Pengemudi',
          subtitle: 'Panduan kerja pengemudi saat menjalankan perjalanan.',
          onTap: () => Navigator.of(context).push<void>(
            MaterialPageRoute<void>(builder: (_) => const _DriverInfoPage()),
          ),
        ),
        const SizedBox(height: 18),
        if (widget.onLogout != null)
          OutlinedButton.icon(
            onPressed: widget.onLogout,
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Keluar'),
          ),
      ],
    );
  }

  Widget _buildGuestHome(_HomeBundle bundle) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          title: 'Halo, Tamu!',
          subtitle: 'Mau cek rute mana hari ini?',
          trailing: IconButton(
            onPressed: widget.onOpenAuth,
            icon: const Icon(Icons.login_rounded),
          ),
        ),
        const SizedBox(height: 16),
        _PassengerDashboardHero(
          name: 'Tamu',
          profileImageUrl: bundle.appSettings.appLogoUrl,
          banners: bundle.payload.banners,
          subtitle:
              'Cek rute, jadwal, harga, kursi, dan kendaraan sebelum login.',
          stats: const [],
        ),
        if (bundle.payload.importantInformation.isNotEmpty) ...[
          const SizedBox(height: 16),
          _ImportantInformationSection(
            items: bundle.payload.importantInformation,
          ),
        ],
        if (bundle.payload.featuredSchedules.isEmpty) ...[
          const SizedBox(height: 16),
          const _EmptyStateCard(
            title: 'Tidak ada perjalanan yang tersedia saat ini.',
            subtitle:
                'Jadwal akan tampil setelah admin membuka perjalanan baru. Silakan cek kembali nanti.',
          ),
        ],
        if (bundle.payload.featuredSchedules.isNotEmpty) ...[
          const SizedBox(height: 18),
          _SectionHeader(
            eyebrow: 'Jadwal populer',
            title: 'Rute siap dicek',
            subtitle: 'Pilih rute yang ingin kamu cek sebelum masuk.',
          ),
          const SizedBox(height: 12),
          ...bundle.payload.featuredSchedules
              .take(3)
              .map(
                (schedule) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _ScheduleCard(
                    schedule: schedule,
                    onTap: () => _openScheduleDetail(schedule),
                    ctaLabel: 'Lihat Detail',
                  ),
                ),
              ),
        ],
      ],
    );
  }

  Widget _buildGuestRoutes(_HomeBundle bundle) {
    return _buildRouteBookmarkFlow(
      bundle: bundle,
      routeSubtitle: 'Mode tamu dapat melihat rute sebelum login.',
      scheduleSubtitle: 'Login diperlukan saat mulai membuat pemesanan.',
      ctaLabel: 'Detail',
      routeTrailing: IconButton(
        onPressed: widget.onOpenAuth,
        icon: const Icon(Icons.login_rounded),
      ),
    );
  }

  Widget _buildRouteBookmarkFlow({
    required _HomeBundle bundle,
    required String routeSubtitle,
    required String scheduleSubtitle,
    required String ctaLabel,
    Widget? routeTrailing,
  }) {
    final schedules = bundle.payload.featuredSchedules;
    final routes = _buildRouteBookmarks(schedules);
    final selectedRoute = _findRouteBookmark(routes, _selectedRouteKey);

    if (selectedRoute == null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
        children: [
          _PageTopBar(
            leading: _backToHomeButton(),
            title: 'Daftar Rute',
            subtitle: routeSubtitle,
            trailing: routeTrailing,
          ),
          const SizedBox(height: 18),
          if (routes.isEmpty)
            const _EmptyStateCard(
              title: 'Belum ada rute tersedia.',
              subtitle: 'Data rute akan muncul setelah jadwal dibuka admin.',
            )
          else
            ...routes.map(
              (route) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _RouteBookmarkCard(
                  route: route,
                  onTap: () {
                    setState(() {
                      _selectedRouteKey = route.key;
                      _selectedScheduleFilter = _ScheduleDateFilter.all;
                      _selectedScheduleDate = null;
                    });
                  },
                ),
              ),
            ),
        ],
      );
    }

    final filteredSchedules = _filterSchedulesForRoute(
      schedules: schedules,
      routeKey: selectedRoute.key,
      filter: _selectedScheduleFilter,
      selectedDate: _selectedScheduleDate,
    );

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          leading: IconButton(
            onPressed: () {
              setState(() {
                _selectedRouteKey = null;
              });
            },
            icon: const Icon(Icons.arrow_back_rounded),
          ),
          title: 'Daftar Jadwal',
          subtitle: scheduleSubtitle,
          trailing: routeTrailing,
        ),
        const SizedBox(height: 14),
        _SelectedRouteLabel(route: selectedRoute),
        const SizedBox(height: 14),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            _FilterChip(
              label: 'Semua',
              selected: _selectedScheduleFilter == _ScheduleDateFilter.all,
              onTap: () {
                setState(() {
                  _selectedScheduleFilter = _ScheduleDateFilter.all;
                  _selectedScheduleDate = null;
                });
              },
            ),
            _FilterChip(
              label: 'Besok',
              selected: _selectedScheduleFilter == _ScheduleDateFilter.tomorrow,
              onTap: () {
                setState(() {
                  _selectedScheduleFilter = _ScheduleDateFilter.tomorrow;
                  _selectedScheduleDate = null;
                });
              },
            ),
            _FilterChip(
              label: _selectedScheduleDate == null
                  ? 'Pilih Tanggal'
                  : DateFormat(
                      'dd MMM yyyy',
                      'id_ID',
                    ).format(_selectedScheduleDate!),
              selected: _selectedScheduleFilter == _ScheduleDateFilter.byDate,
              onTap: _pickScheduleDate,
            ),
          ],
        ),
        const SizedBox(height: 16),
        if (filteredSchedules.isEmpty)
          const _EmptyStateCard(
            title: 'Jadwal belum tersedia.',
            subtitle: 'Coba pilih rute lain atau tarik halaman untuk refresh.',
          )
        else
          ...filteredSchedules.map(
            (schedule) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _ScheduleBookmarkCard(
                schedule: schedule,
                ctaLabel: ctaLabel,
                onTap: () => _openScheduleDetail(schedule),
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildGuestInfo(_HomeBundle bundle) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          leading: _backToHomeButton(),
          title: 'Info Pemesanan',
          subtitle: 'Aturan dan tata cara pemesanan penumpang.',
        ),
        const SizedBox(height: 16),
        const _BookingInfoSections(),
      ],
    );
  }

  Widget _buildGuestAccount(_HomeBundle bundle) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          leading: _backToHomeButton(),
          title: 'Akun',
          subtitle: 'Mode tamu hanya untuk melihat informasi perjalanan.',
        ),
        const SizedBox(height: 16),
        _InlineActionCard(
          title: 'Masuk sebagai penumpang atau pengemudi',
          description:
              'Penumpang bisa menyimpan pemesanan dan tiket. Pengemudi memakai akun dari admin untuk manifest dan scan.',
          actionLabel: 'Buka Login',
          icon: Icons.login_rounded,
          onTap: widget.onOpenAuth,
        ),
        const SizedBox(height: 12),
        _ProfileMenuTile(
          icon: Icons.info_outline_rounded,
          title: 'Tentang Aplikasi',
          subtitle: 'Kenali layanan dan manfaat RuslyTravel.',
          onTap: () => _showAboutSheet(
            title: 'Tentang RuslyTravel',
            description: _aboutRuslyTravelDescription,
          ),
        ),
      ],
    );
  }

  void _showAboutSheet({required String title, required String description}) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) {
        return ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.sizeOf(context).height * 0.82,
          ),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  description,
                  textAlign: TextAlign.justify,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: TravSTheme.navyMuted,
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  List<_NavItem> _navItemsForRole(AppRolePreview role) {
    switch (role) {
      case AppRolePreview.driver:
        return const [
          _NavItem(
            label: 'Beranda',
            icon: Icons.home_outlined,
            selectedIcon: Icons.home_rounded,
          ),
          _NavItem(
            label: 'Manifest',
            icon: Icons.assignment_outlined,
            selectedIcon: Icons.assignment_rounded,
          ),
          _NavItem(
            label: 'Riwayat',
            icon: Icons.history_rounded,
            selectedIcon: Icons.history_toggle_off_rounded,
          ),
          _NavItem(
            label: 'Profil',
            icon: Icons.person_outline_rounded,
            selectedIcon: Icons.person_rounded,
          ),
        ];
      case AppRolePreview.passenger:
        return const [
          _NavItem(
            label: 'Beranda',
            icon: Icons.home_outlined,
            selectedIcon: Icons.home_rounded,
          ),
          _NavItem(
            label: 'Rute',
            icon: Icons.route_outlined,
            selectedIcon: Icons.route_rounded,
          ),
          _NavItem(
            label: 'Pesanan',
            icon: Icons.receipt_long_outlined,
            selectedIcon: Icons.receipt_long_rounded,
          ),
          _NavItem(
            label: 'Tiket',
            icon: Icons.confirmation_number_outlined,
            selectedIcon: Icons.confirmation_number_rounded,
          ),
          _NavItem(
            label: 'Profil',
            icon: Icons.person_outline_rounded,
            selectedIcon: Icons.person_rounded,
          ),
        ];
      case AppRolePreview.guest:
        return const [
          _NavItem(
            label: 'Beranda',
            icon: Icons.home_outlined,
            selectedIcon: Icons.home_rounded,
          ),
          _NavItem(
            label: 'Rute',
            icon: Icons.route_outlined,
            selectedIcon: Icons.route_rounded,
          ),
          _NavItem(
            label: 'Info',
            icon: Icons.info_outline_rounded,
            selectedIcon: Icons.info_rounded,
          ),
          _NavItem(
            label: 'Akun',
            icon: Icons.person_outline_rounded,
            selectedIcon: Icons.person_rounded,
          ),
        ];
    }
  }

  @override
  Widget build(BuildContext context) {
    final navItems = _navItemsForRole(_role);

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
        child: SafeArea(
          child: FutureBuilder<_HomeBundle>(
            future: _future,
            builder: (context, snapshot) {
              // Tampilkan spinner selama data beranda masih dimuat.
              if (snapshot.connectionState == ConnectionState.waiting &&
                  !snapshot.hasData) {
                return TravSAsyncState.loading(message: 'Memuat beranda...');
              }

              // Jika gagal, beri pesan yang jelas dan tombol untuk mencoba lagi.
              if (snapshot.hasError && !snapshot.hasData) {
                return TravSAsyncState.error(
                  error: snapshot.error!,
                  onRetry: _refresh,
                );
              }

              // Setelah data tersedia, tampilkan isi beranda sesuai role pengguna.
              final bundle = snapshot.data!;
              final pages = _pagesForRole(bundle);

              return RefreshIndicator(
                color: TravSTheme.orange,
                onRefresh: _refresh,
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 220),
                  child: KeyedSubtree(
                    key: ValueKey('${_role.name}-$_selectedTabIndex'),
                    child: pages[_selectedTabIndex],
                  ),
                ),
              );
            },
          ),
        ),
      ),
      bottomNavigationBar: _TravSBottomNav(
        items: navItems,
        selectedIndex: _selectedTabIndex,
        onTap: (index) {
          setState(() {
            _selectedTabIndex = index;
            if (index == 0) {
              _selectedRouteKey = null;
              _selectedScheduleFilter = _ScheduleDateFilter.all;
              _selectedScheduleDate = null;
            }
          });
        },
      ),
    );
  }
}

class _NavItem {
  const _NavItem({
    required this.label,
    required this.icon,
    required this.selectedIcon,
  });

  final String label;
  final IconData icon;
  final IconData selectedIcon;
}

class _TravSBottomNav extends StatelessWidget {
  const _TravSBottomNav({
    required this.items,
    required this.selectedIndex,
    required this.onTap,
  });

  final List<_NavItem> items;
  final int selectedIndex;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        height: 86,
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: TravSTheme.line)),
          boxShadow: [
            BoxShadow(
              color: TravSTheme.navy.withValues(alpha: 0.08),
              blurRadius: 24,
              offset: const Offset(0, -10),
            ),
          ],
        ),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final itemWidth = constraints.maxWidth / items.length;
            final circleLeft =
                (itemWidth * selectedIndex) + (itemWidth / 2) - 22;

            return Stack(
              clipBehavior: Clip.none,
              children: [
                AnimatedPositioned(
                  duration: const Duration(milliseconds: 360),
                  curve: Curves.easeOutBack,
                  left: circleLeft,
                  top: 0,
                  child: Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: TravSTheme.navy,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: TravSTheme.navy.withValues(alpha: 0.28),
                          blurRadius: 18,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                  ),
                ),
                Row(
                  children: [
                    for (var index = 0; index < items.length; index++)
                      Expanded(
                        child: _TravSBottomNavItem(
                          item: items[index],
                          selected: index == selectedIndex,
                          onTap: () => onTap(index),
                        ),
                      ),
                  ],
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _TravSBottomNavItem extends StatelessWidget {
  const _TravSBottomNavItem({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  final _NavItem item;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(
            width: 44,
            height: 44,
            child: Center(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 220),
                transitionBuilder: (child, animation) {
                  return ScaleTransition(scale: animation, child: child);
                },
                child: Icon(
                  selected ? item.selectedIcon : item.icon,
                  key: ValueKey('${item.label}-$selected'),
                  color: selected ? Colors.white : TravSTheme.navyMuted,
                  size: selected ? 24 : 22,
                ),
              ),
            ),
          ),
          const SizedBox(height: 4),
          AnimatedDefaultTextStyle(
            duration: const Duration(milliseconds: 220),
            curve: Curves.easeOut,
            style: theme.textTheme.labelMedium!.copyWith(
              color: selected ? TravSTheme.navy : TravSTheme.navyMuted,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
            ),
            child: Text(
              item.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeBundle {
  const _HomeBundle({
    required this.payload,
    required this.appSettings,
    this.passengerDashboard,
    this.passengerBookings,
    this.driverDashboard,
    this.driverTrips,
    this.notifications = TravSNotificationFeed.empty,
  });

  final TravSHomePayload payload;
  final AppSettings appSettings;
  final TravSPassengerDashboard? passengerDashboard;
  final List<TravSBookingResult>? passengerBookings;
  final TravSDriverDashboard? driverDashboard;
  final List<TravSDriverTripItem>? driverTrips;
  final TravSNotificationFeed notifications;
}

class _PageTopBar extends StatelessWidget {
  const _PageTopBar({
    required this.title,
    required this.subtitle,
    this.leading,
    this.trailing,
  });

  final String title;
  final String subtitle;
  final Widget? leading;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      children: [
        if (leading != null) ...[leading!, const SizedBox(width: 10)],
        Expanded(
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
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
            ],
          ),
        ),
        if (trailing != null) ...[const SizedBox(width: 12), trailing!],
      ],
    );
  }
}

class _NotificationBadgeButton extends StatefulWidget {
  const _NotificationBadgeButton({
    required this.count,
    required this.onTap,
    this.animateUnread = false,
  });

  final int count;
  final VoidCallback onTap;
  final bool animateUnread;

  @override
  State<_NotificationBadgeButton> createState() =>
      _NotificationBadgeButtonState();
}

class _NotificationBadgeButtonState extends State<_NotificationBadgeButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _opacity;

  bool get _shouldAnimate => widget.animateUnread && widget.count > 0;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 850),
    );
    _opacity = Tween<double>(
      begin: 0.35,
      end: 1,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));
    _syncAnimation();
  }

  @override
  void didUpdateWidget(covariant _NotificationBadgeButton oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.count != widget.count ||
        oldWidget.animateUnread != widget.animateUnread) {
      _syncAnimation();
    }
  }

  void _syncAnimation() {
    // Kedipan merah hanya aktif selama masih ada notifikasi yang belum dibaca.
    if (_shouldAnimate) {
      _controller.repeat(reverse: true);
    } else {
      _controller.stop();
      _controller.value = 1;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final visibleCount = widget.count > 99 ? '99+' : '${widget.count}';

    return Stack(
      clipBehavior: Clip.none,
      children: [
        IconButton(
          onPressed: widget.onTap,
          style: IconButton.styleFrom(
            backgroundColor: TravSTheme.surfaceSoft,
            foregroundColor: TravSTheme.navy,
          ),
          icon: const Icon(Icons.notifications_none_rounded),
        ),
        if (widget.count > 0)
          Positioned(
            right: 2,
            top: 2,
            child: FadeTransition(
              opacity: _opacity,
              child: Container(
                constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                padding: const EdgeInsets.symmetric(horizontal: 5),
                decoration: BoxDecoration(
                  color: widget.animateUnread
                      ? TravSTheme.danger
                      : TravSTheme.orange,
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(color: Colors.white, width: 2),
                ),
                child: Center(
                  child: Text(
                    visibleCount,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      fontSize: 10,
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.eyebrow,
    required this.title,
    required this.subtitle,
  });

  final String eyebrow;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          eyebrow.toUpperCase(),
          style: theme.textTheme.labelMedium?.copyWith(
            color: TravSTheme.orange,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.8,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          title,
          style: theme.textTheme.titleMedium?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          subtitle,
          style: theme.textTheme.bodySmall?.copyWith(
            color: TravSTheme.navyMuted,
          ),
        ),
      ],
    );
  }
}

class _ImportantInformationSection extends StatelessWidget {
  const _ImportantInformationSection({required this.items});

  final List<TravSImportantInformation> items;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: TravSTheme.orange.withValues(alpha: 0.28)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.campaign_outlined, color: TravSTheme.orange),
              const SizedBox(width: 10),
              Text(
                'Informasi Penting',
                style: theme.textTheme.titleMedium?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (var index = 0; index < items.length; index++) ...[
            Text(
              items[index].title,
              style: theme.textTheme.titleSmall?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              items[index].description,
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
                height: 1.45,
              ),
            ),
            if (index != items.length - 1) ...[
              const SizedBox(height: 12),
              const Divider(height: 1, color: TravSTheme.line),
              const SizedBox(height: 12),
            ],
          ],
        ],
      ),
    );
  }
}

class _PassengerDashboardHero extends StatelessWidget {
  const _PassengerDashboardHero({
    required this.name,
    required this.stats,
    required this.profileImageUrl,
    required this.banners,
    this.subtitle = 'Mau pergi ke mana hari ini?',
  });

  final String name;
  final List<_HeroStat> stats;
  final String? profileImageUrl;
  final List<TravSHomeBanner> banners;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(14),
      constraints: const BoxConstraints(minHeight: 86),
      decoration: BoxDecoration(
        gradient: TravSTheme.headerGradient,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: TravSTheme.navy.withValues(alpha: 0.12),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _PassengerHomeAvatar(name: name, imageUrl: profileImageUrl),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Halo, $name',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: Colors.white70,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 124,
            width: double.infinity,
            child: banners.isEmpty
                ? const _PassengerBannerFallback()
                : PageView.builder(
                    itemCount: banners.length,
                    itemBuilder: (context, index) =>
                        _PassengerBannerSlide(banner: banners[index]),
                  ),
          ),
          const SizedBox(height: 8),
          const Center(
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                _HeroDot(active: false),
                _HeroDot(active: true),
                _HeroDot(active: false),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PassengerHomeAvatar extends StatelessWidget {
  const _PassengerHomeAvatar({required this.name, required this.imageUrl});

  final String name;
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty ? '?' : name.trim()[0].toUpperCase();
    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: Container(
        width: 32,
        height: 32,
        color: Colors.white.withValues(alpha: 0.16),
        child: imageUrl == null
            ? Center(
                child: Text(
                  initial,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              )
            : Image.network(
                imageUrl!,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) => Center(
                  child: Text(
                    initial,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
      ),
    );
  }
}

class _PassengerBannerFallback extends StatelessWidget {
  const _PassengerBannerFallback();

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
      ),
      child: const Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.landscape_rounded, color: Colors.white, size: 34),
          SizedBox(height: 8),
          Text(
            'Armada Nyaman',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900),
          ),
          Text(
            'Kendaraan siap untuk perjalanan lintas kota.',
            style: TextStyle(color: Colors.white70, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _PassengerBannerSlide extends StatelessWidget {
  const _PassengerBannerSlide({required this.banner});

  final TravSHomeBanner banner;

  @override
  Widget build(BuildContext context) {
    final imageUrl = TravSApiService.resolveMediaUrl(banner.imageUrl);
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (imageUrl != null)
            Image.network(
              imageUrl,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) =>
                  const _PassengerBannerFallback(),
            )
          else
            const _PassengerBannerFallback(),
          Container(color: Colors.black.withValues(alpha: 0.30)),
          Positioned(
            left: 16,
            right: 16,
            bottom: 14,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  banner.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (banner.subtitle.isNotEmpty)
                  Text(
                    banner.subtitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white, fontSize: 11),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroDot extends StatelessWidget {
  const _HeroDot({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: active ? 18 : 5,
      height: 5,
      margin: const EdgeInsets.symmetric(horizontal: 3),
      decoration: BoxDecoration(
        color: active ? TravSTheme.orange : Colors.white70,
        borderRadius: BorderRadius.circular(99),
      ),
    );
  }
}

class _NextTripCard extends StatelessWidget {
  const _NextTripCard({required this.booking, required this.onTap});

  final TravSBookingResult booking;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Besok ada perjalanan',
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  _compactRouteName(booking.rute),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    _TripMeta(
                      label: 'Tanggal',
                      value: _formatShortDate(booking.waktuBerangkat),
                    ),
                    const SizedBox(width: 22),
                    _TripMeta(
                      label: 'Waktu',
                      value: _formatScheduleTime(booking.waktuBerangkat),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              _StatusPill(
                label: 'Aktif',
                color: TravSTheme.success,
                textColor: TravSTheme.success,
              ),
              // Jarak tetap mencegah Column meminta tinggi tak terbatas saat
              // card berada di dalam ListView beranda.
              const SizedBox(height: 54),
              FilledButton(
                onPressed: onTap,
                style: FilledButton.styleFrom(
                  backgroundColor: TravSTheme.navy,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 14,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text('Lihat\nDetail', textAlign: TextAlign.center),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TripMeta extends StatelessWidget {
  const _TripMeta({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
            color: TravSTheme.navyMuted,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _DriverHeroCard extends StatelessWidget {
  const _DriverHeroCard({
    required this.displayName,
    required this.phoneNumber,
    required this.simNumber,
    required this.profileImageUrl,
    required this.stats,
  });

  final String displayName;
  final String phoneNumber;
  final String simNumber;
  final String? profileImageUrl;
  final List<_HeroStat> stats;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final initials = displayName.isEmpty
        ? 'S'
        : displayName.substring(0, 1).toUpperCase();

    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: TravSTheme.headerGradient,
        borderRadius: BorderRadius.circular(30),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _DriverProfileThumb(
                imageUrl: profileImageUrl,
                initials: initials,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      displayName,
                      style: theme.textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Nomor HP: $phoneNumber',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: Colors.white70,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Nomor SIM: $simNumber',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: Colors.white70,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              for (var index = 0; index < stats.length; index++) ...[
                Expanded(
                  child: SizedBox(
                    height: 76,
                    child: _HeroStatTile(stat: stats[index], dark: true),
                  ),
                ),
                if (index != stats.length - 1) const SizedBox(width: 10),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _DriverProfileWorkCard extends StatelessWidget {
  const _DriverProfileWorkCard({
    required this.session,
    required this.vehicle,
    this.message,
  });

  final TravSUserSession session;
  final TravSDriverAssignedVehicle? vehicle;
  final String? message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final user = session.user;
    final avatarUrl = TravSApiService.resolveMediaUrl(user.fotoProfil);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _ProfileAvatarPreview(
                name: user.nama,
                radius: 30,
                imageUrl: avatarUrl,
                onTap: avatarUrl == null
                    ? null
                    : () => _showTravSImagePreview(
                        context,
                        imageUrl: avatarUrl,
                        title: user.nama,
                      ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.nama,
                      style: theme.textTheme.titleLarge?.copyWith(
                        color: TravSTheme.navy,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      user.email,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: TravSTheme.navyMuted,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _humanizeRole(user.peran),
                      style: theme.textTheme.labelMedium?.copyWith(
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
          const Divider(height: 1, color: TravSTheme.line),
          const SizedBox(height: 18),
          Text(
            'Identitas dan Penugasan',
            style: theme.textTheme.titleMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Data pengemudi dan kendaraan yang digunakan untuk menjalankan perjalanan.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
            ),
          ),
          const SizedBox(height: 16),
          _InfoRow(label: 'Nomor HP', value: user.noHp),
          const SizedBox(height: 10),
          _InfoRow(label: 'Nomor SIM / NIK', value: user.nomorSim ?? '-'),
          const SizedBox(height: 10),
          const _InfoRow(label: 'Status', value: 'Aktif'),
          const SizedBox(height: 18),
          const Divider(height: 1, color: TravSTheme.line),
          const SizedBox(height: 18),
          Text(
            'Kendaraan yang Ditugaskan',
            style: theme.textTheme.titleMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Data kendaraan ditentukan berdasarkan jadwal perjalanan yang dibuat admin.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
            ),
          ),
          const SizedBox(height: 14),
          _AssignedVehicleContent(vehicle: vehicle, message: message),
        ],
      ),
    );
  }
}

class _AssignedVehicleCard extends StatelessWidget {
  const _AssignedVehicleCard({required this.vehicle, this.message});

  final TravSDriverAssignedVehicle? vehicle;
  final String? message;

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      title: 'Kendaraan yang Ditugaskan',
      subtitle:
          'Data kendaraan ditentukan berdasarkan jadwal perjalanan yang dibuat admin.',
      child: _AssignedVehicleContent(vehicle: vehicle, message: message),
    );
  }
}

class _AssignedVehiclePhotoPreview extends StatelessWidget {
  const _AssignedVehiclePhotoPreview({
    required this.photoUrl,
    required this.title,
  });

  final String? photoUrl;
  final String title;

  @override
  Widget build(BuildContext context) {
    Widget fallback() {
      return Container(
        color: TravSTheme.surfaceSoft,
        child: const Icon(
          Icons.directions_car_filled_rounded,
          color: TravSTheme.navyMuted,
        ),
      );
    }

    final thumbnail = ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: SizedBox(
        width: 76,
        height: 64,
        child: photoUrl == null
            ? fallback()
            : Image.network(
                '$photoUrl?v=${DateTime.now().millisecondsSinceEpoch}',
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) => fallback(),
              ),
      ),
    );

    if (photoUrl == null) {
      return thumbnail;
    }

    return Semantics(
      button: true,
      label: 'Preview foto kendaraan',
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () =>
            _showTravSImagePreview(context, imageUrl: photoUrl!, title: title),
        child: thumbnail,
      ),
    );
  }
}

class _AssignedVehicleContent extends StatelessWidget {
  const _AssignedVehicleContent({required this.vehicle, this.message});

  final TravSDriverAssignedVehicle? vehicle;
  final String? message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final photoUrl = TravSApiService.resolveMediaUrl(vehicle?.photoUrl);
    final assignedVehicle = vehicle;

    if (assignedVehicle == null) {
      return Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: TravSTheme.surfaceSoft,
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(
              Icons.directions_car_filled_rounded,
              color: TravSTheme.navyMuted,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message ??
                  'Belum ada kendaraan yang ditugaskan. Kendaraan akan tampil setelah admin membuat jadwal perjalanan.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
          ),
        ],
      );
    }

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _AssignedVehiclePhotoPreview(
          photoUrl: photoUrl,
          title: assignedVehicle.displayName,
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                assignedVehicle.displayName,
                style: theme.textTheme.titleSmall?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${assignedVehicle.route} - ${_formatDateTime(assignedVehicle.departureAt)}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _MiniInfoChip(
                    icon: Icons.confirmation_number_outlined,
                    label: assignedVehicle.plateNumber,
                  ),
                  _MiniInfoChip(
                    icon: Icons.event_seat_outlined,
                    label: '${assignedVehicle.capacity} kursi',
                  ),
                  _MiniInfoChip(
                    icon: Icons.palette_outlined,
                    label: assignedVehicle.color,
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        Flexible(
          flex: 2,
          child: Text(
            value,
            textAlign: TextAlign.end,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }
}

class _MiniInfoChip extends StatelessWidget {
  const _MiniInfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: TravSTheme.navyMuted),
          const SizedBox(width: 5),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _DriverProfileThumb extends StatelessWidget {
  const _DriverProfileThumb({required this.imageUrl, required this.initials});

  final String? imageUrl;
  final String initials;

  @override
  Widget build(BuildContext context) {
    Widget fallback() {
      return Center(
        child: Text(
          initials,
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w800,
          ),
        ),
      );
    }

    return ClipOval(
      child: Container(
        width: 56,
        height: 56,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: Colors.white.withValues(alpha: 0.16),
          border: Border.all(color: Colors.white24),
        ),
        child: imageUrl == null
            ? fallback()
            : Image.network(
                '$imageUrl?v=${DateTime.now().millisecondsSinceEpoch}',
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) => fallback(),
                loadingBuilder: (context, child, loadingProgress) {
                  if (loadingProgress == null) {
                    return child;
                  }

                  return const Center(
                    child: SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    ),
                  );
                },
              ),
      ),
    );
  }
}

class _HeroStat {
  const _HeroStat({required this.label, required this.value});

  final String label;
  final String value;
}

class _HeroStatTile extends StatelessWidget {
  const _HeroStatTile({required this.stat, this.dark = false});

  final _HeroStat stat;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: dark
            ? Colors.white.withValues(alpha: 0.12)
            : TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: dark ? Border.all(color: Colors.white24) : null,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: double.infinity,
            child: FittedBox(
              alignment: Alignment.centerLeft,
              fit: BoxFit.scaleDown,
              child: Text(
                stat.value,
                maxLines: 1,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: dark ? Colors.white : TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
          const SizedBox(height: 4),
          SizedBox(
            width: double.infinity,
            child: FittedBox(
              alignment: Alignment.centerLeft,
              fit: BoxFit.scaleDown,
              child: Text(
                stat.label,
                maxLines: 1,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: dark ? Colors.white70 : TravSTheme.navyMuted,
                  fontWeight: FontWeight.w500,
                  height: 1.1,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _InlineActionCard extends StatelessWidget {
  const _InlineActionCard({
    required this.title,
    required this.description,
    required this.actionLabel,
    required this.icon,
    required this.onTap,
  });

  final String title;
  final String description;
  final String actionLabel;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: TravSTheme.orangeSoft,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(icon, color: TravSTheme.orange),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  description,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
                const SizedBox(height: 12),
                TextButton(onPressed: onTap, child: Text(actionLabel)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, this.selected = false, this.onTap});

  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final content = Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
      decoration: BoxDecoration(
        color: selected ? TravSTheme.navy : Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: selected ? TravSTheme.navy : TravSTheme.line),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: selected ? Colors.white : TravSTheme.navy,
          fontWeight: FontWeight.w700,
        ),
      ),
    );

    if (onTap == null) {
      return content;
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: content,
    );
  }
}

class _DriverJourneyRecord {
  const _DriverJourneyRecord({
    required this.id,
    required this.code,
    required this.route,
    required this.departureAt,
    required this.vehicle,
    required this.vehiclePhotoUrl,
    required this.status,
    required this.revenue,
    required this.manifest,
  });

  final int id;
  final String code;
  final String route;
  final String departureAt;
  final String vehicle;
  final String? vehiclePhotoUrl;
  final String status;
  final double revenue;
  final List<_ManifestPassengerRecord> manifest;

  String get statusLabel => _humanizeStatus(status);
  int get passengerCount => manifest.length;

  _DriverJourneyRecord copyWith({List<_ManifestPassengerRecord>? manifest}) {
    return _DriverJourneyRecord(
      id: id,
      code: code,
      route: route,
      departureAt: departureAt,
      vehicle: vehicle,
      vehiclePhotoUrl: vehiclePhotoUrl,
      status: status,
      revenue: revenue,
      manifest: manifest ?? this.manifest,
    );
  }

  factory _DriverJourneyRecord.fromTrip(TravSDriverTripItem trip) {
    return _DriverJourneyRecord(
      id: trip.id,
      code: trip.code,
      route: trip.rute,
      departureAt: trip.waktuBerangkat,
      vehicle: trip.kendaraan,
      vehiclePhotoUrl: trip.kendaraanFotoUrl,
      status: trip.status,
      revenue: trip.revenue,
      manifest: trip.manifest
          .map((passenger) => _ManifestPassengerRecord.fromApi(passenger))
          .toList(),
    );
  }

  static List<_DriverJourneyRecord> fromTrips(List<TravSDriverTripItem> trips) {
    final records = <_DriverJourneyRecord>[];
    for (final trip in trips) {
      records.add(_DriverJourneyRecord.fromTrip(trip));
    }

    return records;
  }
}

class _ManifestPassengerRecord {
  const _ManifestPassengerRecord({
    required this.detailId,
    required this.name,
    required this.accountName,
    required this.accountEmail,
    required this.accountPhone,
    required this.profilePhotoUrl,
    required this.seatLabel,
    required this.phone,
    required this.pickupAddress,
    required this.pickupLatitude,
    required this.pickupLongitude,
    required this.dropoffAddress,
    required this.dropoffLatitude,
    required this.dropoffLongitude,
    required this.baggageLabel,
    required this.baggagePhotoUrl,
    required this.baggagePhotoUrls,
    required this.fareLabel,
    required this.attendanceStatus,
    required this.paymentStatusLabel,
    required this.canConfirmCash,
    required this.canDropoff,
    required this.isDroppedOff,
  });

  final int detailId;
  final String name;
  final String accountName;
  final String accountEmail;
  final String accountPhone;
  final String? profilePhotoUrl;
  final String seatLabel;
  final String phone;
  final String pickupAddress;
  final double? pickupLatitude;
  final double? pickupLongitude;
  final String dropoffAddress;
  final double? dropoffLatitude;
  final double? dropoffLongitude;
  final String baggageLabel;
  final String? baggagePhotoUrl;
  final List<String> baggagePhotoUrls;
  final String fareLabel;
  final String attendanceStatus;
  final String paymentStatusLabel;
  final bool canConfirmCash;
  final bool canDropoff;
  final bool isDroppedOff;

  String get journeyStatusLabel {
    if (isDroppedOff) {
      return 'Turun';
    }

    return attendanceStatus.toLowerCase() == 'hadir' ? 'Hadir' : 'Belum Hadir';
  }

  factory _ManifestPassengerRecord.fromApi(
    TravSDriverManifestPassenger passenger,
  ) {
    final isCashPending = passenger.canConfirmCash;
    final paymentStatusLabel = isCashPending
        ? 'Belum bayar tunai'
        : passenger.paymentStatus == 'berhasil'
        ? 'Lunas'
        : _humanizeStatus(passenger.paymentStatus);

    return _ManifestPassengerRecord(
      detailId: passenger.detailId,
      name: passenger.name,
      accountName: passenger.accountName,
      accountEmail: passenger.accountEmail,
      accountPhone: passenger.accountPhone,
      profilePhotoUrl: passenger.profilePhotoUrl,
      seatLabel: 'No. ${passenger.seatNumber.toString().padLeft(2, '0')}',
      phone: passenger.phone,
      pickupAddress: passenger.pickupLandmark,
      pickupLatitude: passenger.pickupLatitude,
      pickupLongitude: passenger.pickupLongitude,
      dropoffAddress: passenger.dropoffLandmark,
      dropoffLatitude: passenger.dropoffLatitude,
      dropoffLongitude: passenger.dropoffLongitude,
      baggageLabel: passenger.baggageLabel,
      baggagePhotoUrl: passenger.baggagePhotoUrl,
      baggagePhotoUrls: passenger.baggagePhotoUrls,
      fareLabel: passenger.fareLabel,
      attendanceStatus: passenger.attendanceStatus,
      paymentStatusLabel: paymentStatusLabel,
      canConfirmCash: passenger.canConfirmCash,
      canDropoff:
          passenger.attendanceStatus == 'hadir' && !passenger.isDroppedOff,
      isDroppedOff: passenger.isDroppedOff,
    );
  }
}

enum _NotificationTone { info, success, warning }

class _NotificationEntry {
  const _NotificationEntry({
    required this.notificationType,
    required this.referenceId,
    required this.title,
    required this.message,
    required this.timeLabel,
    required this.category,
    required this.tone,
    required this.isUnread,
  });

  final String notificationType;
  final int? referenceId;
  final String title;
  final String message;
  final String timeLabel;
  final String category;
  final _NotificationTone tone;
  final bool isUnread;
}

class _NotificationPage extends StatefulWidget {
  const _NotificationPage({
    required this.title,
    required this.subtitle,
    required this.entries,
    required this.notificationsEnabled,
    required this.showPermissionSettings,
    required this.onToggleNotifications,
    required this.onClearHistory,
  });

  final String title;
  final String subtitle;
  final List<_NotificationEntry> entries;
  final bool notificationsEnabled;
  final bool showPermissionSettings;
  final Future<bool> Function(bool enabled) onToggleNotifications;
  final Future<List<_NotificationEntry>> Function(String period) onClearHistory;

  @override
  State<_NotificationPage> createState() => _NotificationPageState();
}

class _NotificationPageState extends State<_NotificationPage> {
  late bool _notificationsEnabled;
  late List<_NotificationEntry> _entries;
  bool _isUpdatingPermission = false;
  bool _isClearingHistory = false;

  @override
  void initState() {
    super.initState();
    _notificationsEnabled = widget.notificationsEnabled;
    _entries = List<_NotificationEntry>.from(widget.entries);
  }

  Future<void> _showClearHistoryDialog() async {
    final period = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Bersihkan Notifikasi Lama'),
        content: const Text(
          'Pilih riwayat yang ingin dihapus. Notifikasi yang belum dibaca tetap aman.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Batal'),
          ),
          PopupMenuButton<String>(
            tooltip: 'Pilih periode riwayat',
            onSelected: (value) => Navigator.pop(dialogContext, value),
            itemBuilder: (_) => const [
              PopupMenuItem(value: '7_days', child: Text('Lebih dari 7 hari')),
              PopupMenuItem(
                value: '30_days',
                child: Text('Lebih dari 30 hari'),
              ),
              PopupMenuItem(
                value: '90_days',
                child: Text('Lebih dari 90 hari'),
              ),
              PopupMenuItem(
                value: 'all_read',
                child: Text('Semua yang sudah dibaca'),
              ),
            ],
            child: const Padding(
              padding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              child: Text('Pilih dan Hapus'),
            ),
          ),
        ],
      ),
    );

    if (period == null || !mounted) {
      return;
    }

    setState(() => _isClearingHistory = true);
    try {
      final refreshedEntries = await widget.onClearHistory(period);
      if (mounted) {
        setState(() => _entries = refreshedEntries);
      }
    } catch (_) {
      // Pesan kegagalan ditampilkan halaman induk, daftar lama tetap dipertahankan.
    } finally {
      if (mounted) {
        setState(() => _isClearingHistory = false);
      }
    }
  }

  Future<void> _toggleNotifications(bool value) async {
    setState(() => _isUpdatingPermission = true);
    final saved = await widget.onToggleNotifications(value);
    if (!mounted) {
      return;
    }

    setState(() {
      _notificationsEnabled = saved ? value : false;
      _isUpdatingPermission = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final latestEntries = _entries.where((entry) => entry.isUnread).toList();
    final oldEntries = _entries.where((entry) => !entry.isUnread).toList();

    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: Container(
        decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
          children: [
            _SectionHeader(
              eyebrow: 'Status',
              title: widget.title,
              subtitle: widget.subtitle,
            ),
            const SizedBox(height: 16),
            if (widget.showPermissionSettings) ...[
              _NotificationPermissionCard(
                enabled: _notificationsEnabled,
                isUpdating: _isUpdatingPermission,
                onChanged: _toggleNotifications,
              ),
              const SizedBox(height: 16),
            ],
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: _isClearingHistory ? null : _showClearHistoryDialog,
                icon: _isClearingHistory
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.delete_sweep_outlined),
                label: const Text('Hapus Riwayat'),
              ),
            ),
            const SizedBox(height: 8),
            if (_entries.isEmpty)
              const _EmptyStateCard(
                title: 'Belum ada notifikasi',
                subtitle: 'Informasi terbaru akan muncul di halaman ini.',
              ),
            if (latestEntries.isNotEmpty) ...[
              _NotificationGroupTitle(
                title: 'Notifikasi Terbaru',
                subtitle: '${latestEntries.length} belum dibaca',
              ),
              const SizedBox(height: 10),
              ...latestEntries.map(
                (entry) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _NotificationTile(
                    entry: entry,
                    onTap: () => Navigator.of(context).pop(entry),
                  ),
                ),
              ),
            ],
            if (oldEntries.isNotEmpty) ...[
              if (latestEntries.isNotEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 10),
                  child: Divider(color: TravSTheme.line, thickness: 2),
                ),
              _NotificationGroupTitle(
                title: 'Notifikasi Lama',
                subtitle: '${oldEntries.length} sudah dibaca',
              ),
              const SizedBox(height: 10),
              ...oldEntries.map(
                (entry) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _NotificationTile(
                    entry: entry,
                    onTap: () => Navigator.of(context).pop(entry),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _NotificationGroupTitle extends StatelessWidget {
  const _NotificationGroupTitle({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Text(
            subtitle,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _NotificationPermissionCard extends StatelessWidget {
  const _NotificationPermissionCard({
    required this.enabled,
    required this.isUpdating,
    required this.onChanged,
  });

  final bool enabled;
  final bool isUpdating;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: enabled ? TravSTheme.orangeSoft : TravSTheme.surfaceSoft,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(
              enabled
                  ? Icons.notifications_active_outlined
                  : Icons.notifications_off_outlined,
              color: enabled ? TravSTheme.orange : TravSTheme.navyMuted,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  enabled ? 'Notifikasi Aktif' : 'Notifikasi Mati',
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  enabled
                      ? 'Aplikasi boleh mengirim pengingat penting dari perangkat ini.'
                      : 'Aktifkan untuk meminta izin notifikasi dari perangkat.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          if (isUpdating)
            const SizedBox(
              width: 24,
              height: 24,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          else
            Switch(
              value: enabled,
              activeThumbColor: TravSTheme.orange,
              onChanged: onChanged,
            ),
        ],
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.entry, required this.onTap});

  final _NotificationEntry entry;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final toneColor = switch (entry.tone) {
      _NotificationTone.success => TravSTheme.success,
      _NotificationTone.warning => TravSTheme.warning,
      _NotificationTone.info => TravSTheme.navy,
    };

    final cardColor = entry.isUnread
        ? toneColor.withValues(alpha: 0.08)
        : TravSTheme.surfaceSoft;
    final borderColor = entry.isUnread
        ? toneColor.withValues(alpha: 0.38)
        : TravSTheme.line;

    return Material(
      color: cardColor,
      borderRadius: BorderRadius.circular(24),
      child: InkWell(
        // Notifikasi membawa jenis dan referensi dari backend agar bisa diarahkan
        // ke detail pemesanan, tiket, atau detail perjalanan yang sesuai.
        onTap: onTap,
        borderRadius: BorderRadius.circular(24),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: cardColor,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(
              color: borderColor,
              width: entry.isUnread ? 1.5 : 1,
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: toneColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(Icons.notifications_rounded, color: toneColor),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            entry.title,
                            style: theme.textTheme.titleMedium?.copyWith(
                              color: TravSTheme.navy,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        if (entry.isUnread)
                          Container(
                            width: 10,
                            height: 10,
                            decoration: const BoxDecoration(
                              color: TravSTheme.orange,
                              shape: BoxShape.circle,
                            ),
                          ),
                        const SizedBox(width: 8),
                        const Icon(
                          Icons.chevron_right_rounded,
                          color: TravSTheme.navyMuted,
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      entry.message,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: TravSTheme.navyMuted,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        _StatusPill(
                          label: entry.category,
                          color: toneColor,
                          textColor: toneColor,
                        ),
                        const Spacer(),
                        Text(
                          entry.timeLabel,
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: TravSTheme.navyMuted,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DriverJourneyDetailPage extends StatefulWidget {
  const _DriverJourneyDetailPage({
    required this.record,
    required this.onOpenScanner,
    required this.onStartJourney,
    required this.onRecordExpense,
    required this.onConfirmCash,
    required this.onDropoffPassenger,
    required this.onRefreshJourney,
  });

  final _DriverJourneyRecord record;
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

  Future<void> _handleStartJourney() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text(
          'Mulai Perjalanan',
          style: TextStyle(color: TravSTheme.navy, fontWeight: FontWeight.w800),
        ),
        content: Text(
          'Pastikan kendaraan, rute, dan manifest sudah diperiksa. Mulai perjalanan ${_record.route}?',
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
                  _InfoRowData('No. HP akun', passenger.accountPhone),
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
      appBar: AppBar(title: const Text('Detail Perjalanan')),
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
                    Text(
                      '${_formatDateTime(record.departureAt)}\n${record.vehicle}',
                      style: Theme.of(
                        context,
                      ).textTheme.bodySmall?.copyWith(color: Colors.white70),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: _HeroStatTile(
                            stat: _HeroStat(
                              label: 'Penumpang',
                              value: '${record.passengerCount}',
                            ),
                            dark: true,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: _HeroStatTile(
                            stat: _HeroStat(
                              label: 'Pendapatan',
                              value: _formatCurrency(record.revenue),
                            ),
                            dark: true,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _InfoCard(
                title: 'Rute perjalanan',
                description:
                    'Manifest dan penumpang berikut ini disusun sesuai alur kerja pengemudi.',
                icon: Icons.alt_route_rounded,
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
                      'Manifest Penumpang',
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
                onPressed: widget.onOpenScanner,
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
    required this.onSubmit,
  });

  final String routeLabel;
  final _DriverExpenseSubmitCallback onSubmit;

  @override
  State<_DriverOperationalExpenseSheet> createState() =>
      _DriverOperationalExpenseSheetState();
}

class _DriverOperationalExpenseSheetState
    extends State<_DriverOperationalExpenseSheet> {
  final _formKey = GlobalKey<FormState>();
  final _jenisController = TextEditingController();
  final _nominalController = TextEditingController();
  final _keteranganController = TextEditingController();
  final _imagePicker = ImagePicker();
  DateTime _tanggal = DateTime.now();
  XFile? _fotoBuktiNota;
  bool _isSubmitting = false;
  String? _photoError;

  @override
  void dispose() {
    _jenisController.dispose();
    _nominalController.dispose();
    _keteranganController.dispose();
    super.dispose();
  }

  Future<void> _pickReceipt(ImageSource source) async {
    try {
      final photo = await _imagePicker.pickImage(
        source: source,
        imageQuality: 75,
        maxWidth: 1600,
      );
      if (!mounted || photo == null) {
        return;
      }

      setState(() {
        _fotoBuktiNota = photo;
        _photoError = null;
      });
      _formKey.currentState?.validate();
    } catch (_) {
      if (mounted) {
        setState(() {
          _photoError = 'Foto nota belum dapat dipilih. Coba kembali.';
        });
      }
    }
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

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    if (_fotoBuktiNota == null) {
      setState(() {
        _photoError = 'Foto bukti nota wajib dilampirkan.';
      });
      return;
    }

    final nominal = double.tryParse(_nominalController.text.trim());
    if (nominal == null || nominal <= 0) {
      return;
    }

    setState(() => _isSubmitting = true);
    final saved = await widget.onSubmit(
      jenisBiaya: _jenisController.text.trim(),
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
              TextFormField(
                controller: _jenisController,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'Jenis biaya',
                  hintText: 'Contoh: BBM, parkir, tol, atau makan',
                  prefixIcon: Icon(Icons.category_outlined),
                ),
                validator: (value) => value == null || value.trim().isEmpty
                    ? 'Jenis biaya wajib diisi.'
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
                  prefixIcon: Icon(Icons.payments_outlined),
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
                    prefixIcon: Icon(Icons.calendar_today_outlined),
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
              const SizedBox(height: 16),
              Text(
                'Foto bukti nota (wajib)',
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 10),
              if (_fotoBuktiNota != null) ...[
                Stack(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(14),
                      child: FutureBuilder<Uint8List>(
                        future: _fotoBuktiNota!.readAsBytes(),
                        builder: (context, snapshot) {
                          if (!snapshot.hasData) {
                            return const SizedBox(
                              width: 120,
                              height: 120,
                              child: Center(child: CircularProgressIndicator()),
                            );
                          }
                          return Image.memory(
                            snapshot.data!,
                            width: 120,
                            height: 120,
                            fit: BoxFit.cover,
                          );
                        },
                      ),
                    ),
                    Positioned(
                      right: 4,
                      top: 4,
                      child: IconButton.filled(
                        tooltip: 'Hapus foto nota',
                        onPressed: () {
                          setState(() {
                            _fotoBuktiNota = null;
                            _photoError = 'Foto bukti nota wajib dilampirkan.';
                          });
                          _formKey.currentState?.validate();
                        },
                        icon: const Icon(Icons.close_rounded, size: 18),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
              ],
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _isSubmitting
                          ? null
                          : () => _pickReceipt(ImageSource.camera),
                      icon: const Icon(Icons.photo_camera_outlined),
                      label: const Text('Kamera'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _isSubmitting
                          ? null
                          : () => _pickReceipt(ImageSource.gallery),
                      icon: const Icon(Icons.photo_library_outlined),
                      label: const Text('Galeri'),
                    ),
                  ),
                ],
              ),
              if (_photoError != null) ...[
                const SizedBox(height: 8),
                Text(
                  _photoError!,
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
    this.onConfirmCash,
    this.onDropoff,
    this.onOpenDetail,
  });

  final _ManifestPassengerRecord passenger;
  final bool isConfirming;
  final bool isDroppingOff;
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
                  icon: const Icon(Icons.info_outline_rounded),
                  label: const Text('Detail'),
                ),
              ),
              if (passenger.canDropoff) ...[
                const SizedBox(width: 10),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: isDroppingOff ? null : onDropoff,
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
            icon: const Icon(Icons.map_outlined),
            label: const Text('Buka di Google Maps'),
          ),
        ],
      ),
    );
  }
}

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
            ? 'Tiket cocok dengan manifest dan sudah ditandai hadir.'
            : 'Tiket tidak valid untuk manifest aktif.';
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
      appBar: AppBar(title: const Text('Pindai Tiket')),
      body: Container(
        decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
          children: [
            const _SectionHeader(
              eyebrow: 'Scan',
              title: 'Pindai tiket',
              subtitle: 'Arahkan kamera ke QR tiket penumpang.',
            ),
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

class _RouteBookmark {
  const _RouteBookmark({
    required this.key,
    required this.origin,
    required this.destination,
    required this.path,
    required this.scheduleCount,
    required this.availableSeats,
    required this.startingPrice,
  });

  final String key;
  final String origin;
  final String destination;
  final String path;
  final int scheduleCount;
  final int availableSeats;
  final double startingPrice;

  String get title => '$origin - $destination';
  String get subtitle => '$origin menuju $destination';
}

class _RouteBookmarkCard extends StatelessWidget {
  const _RouteBookmarkCard({required this.route, required this.onTap});

  final _RouteBookmark route;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: TravSTheme.line),
          boxShadow: [
            BoxShadow(
              color: TravSTheme.navy.withValues(alpha: 0.04),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          children: [
            _NetworkThumbnail(
              imageUrl: _destinationImageUrl(route.destination),
              fallbackIcon: Icons.location_city_rounded,
              label: route.destination,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    route.title,
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Rute utama perjalanan',
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    route.path == '-' ? route.subtitle : route.path,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navyMuted,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: [
                      _TinyInfoPill(
                        icon: Icons.event_available_rounded,
                        label: '${route.scheduleCount} jadwal',
                      ),
                      _TinyInfoPill(
                        icon: Icons.event_seat_rounded,
                        label: '${route.availableSeats} kursi',
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            const Icon(Icons.chevron_right_rounded, color: TravSTheme.navy),
          ],
        ),
      ),
    );
  }
}

class _SelectedRouteLabel extends StatelessWidget {
  const _SelectedRouteLabel({required this.route});

  final _RouteBookmark route;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: TravSTheme.orange.withValues(alpha: 0.18)),
      ),
      child: Row(
        children: [
          const Icon(Icons.bookmark_rounded, color: TravSTheme.orange),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              route.title,
              style: theme.textTheme.labelLarge?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Text(
            _formatCurrency(route.startingPrice),
            style: theme.textTheme.labelMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _ScheduleBookmarkCard extends StatelessWidget {
  const _ScheduleBookmarkCard({
    required this.schedule,
    required this.ctaLabel,
    required this.onTap,
  });

  final TravSSchedule schedule;
  final String ctaLabel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: TravSTheme.line),
          boxShadow: [
            BoxShadow(
              color: TravSTheme.navy.withValues(alpha: 0.04),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _NetworkThumbnail(
                  imageUrl:
                      TravSApiService.resolveMediaUrl(
                        schedule.kendaraanFotoUrl,
                      ) ??
                      _vehicleImageUrl(schedule.kendaraan),
                  fallbackIcon: Icons.directions_car_filled_rounded,
                  label: schedule.kendaraan,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _formatScheduleDate(schedule.waktuBerangkat),
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: TravSTheme.navy,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 8),
                      _ScheduleInfoLine(
                        label: 'Rute',
                        value: '${schedule.asal} - ${schedule.tujuan}',
                      ),
                      _ScheduleInfoLine(
                        label: 'Kendaraan',
                        value: schedule.kendaraan,
                      ),
                      _ScheduleInfoLine(
                        label: 'Sisa Kursi',
                        value:
                            '${schedule.sisaKursi} / ${schedule.kapasitas} Kursi',
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  _formatScheduleTime(schedule.waktuBerangkat),
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Harga',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: TravSTheme.navyMuted,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        _formatCurrency(schedule.hargaTiket),
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: TravSTheme.navy,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                ElevatedButton(
                  onPressed: onTap,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: TravSTheme.navy,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 18,
                      vertical: 12,
                    ),
                    minimumSize: const Size(76, 42),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: Text(ctaLabel),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ScheduleInfoLine extends StatelessWidget {
  const _ScheduleInfoLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 76,
            child: Text(
              label,
              style: theme.textTheme.labelSmall?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
          ),
          Text(
            ': ',
            style: theme.textTheme.labelSmall?.copyWith(
              color: TravSTheme.navyMuted,
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: theme.textTheme.labelSmall?.copyWith(
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

class _TinyInfoPill extends StatelessWidget {
  const _TinyInfoPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: TravSTheme.orange),
          const SizedBox(width: 5),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _ScanResultCard extends StatelessWidget {
  const _ScanResultCard({
    required this.title,
    required this.subtitle,
    required this.tone,
    required this.icon,
    required this.detailLines,
    required this.onReset,
    super.key,
  });

  final String title;
  final String subtitle;
  final Color tone;
  final IconData icon;
  final List<String> detailLines;
  final VoidCallback onReset;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          height: 260,
          decoration: BoxDecoration(
            color: tone.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(24),
          ),
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                CircleAvatar(
                  radius: 34,
                  backgroundColor: tone.withValues(alpha: 0.16),
                  child: Icon(icon, color: tone, size: 34),
                ),
                const SizedBox(height: 14),
                Text(
                  title,
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: tone,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          subtitle,
          style: theme.textTheme.bodyMedium?.copyWith(color: TravSTheme.navy),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: TravSTheme.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final line in detailLines) ...[
                Text(
                  line,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
                if (line != detailLines.last) const SizedBox(height: 6),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),
        OutlinedButton(onPressed: onReset, child: const Text('Ulangi Pindai')),
      ],
    );
  }
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({
    required this.schedule,
    required this.onTap,
    required this.ctaLabel,
  });

  final TravSSchedule schedule;
  final VoidCallback onTap;
  final String ctaLabel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(26),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(26),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _NetworkThumbnail(
                  imageUrl:
                      TravSApiService.resolveMediaUrl(
                        schedule.kendaraanFotoUrl,
                      ) ??
                      _vehicleImageUrl(schedule.kendaraan),
                  fallbackIcon: Icons.directions_car_filled_rounded,
                  label: schedule.kendaraan,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${schedule.asal} - ${schedule.tujuan}',
                        style: theme.textTheme.titleMedium?.copyWith(
                          color: TravSTheme.navy,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        schedule.jalur,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: TravSTheme.navyMuted,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    _StatusPill(
                      label: '${schedule.sisaKursi} kursi',
                      color: schedule.sisaKursi > 2
                          ? TravSTheme.success
                          : TravSTheme.warning,
                      textColor: schedule.sisaKursi > 2
                          ? TravSTheme.success
                          : TravSTheme.warning,
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: _MetaColumn(
                    label: 'Berangkat',
                    value: _formatDateTime(schedule.waktuBerangkat),
                  ),
                ),
                Expanded(
                  child: _MetaColumn(
                    label: 'Kendaraan',
                    value: schedule.kendaraan,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: Text(
                    _formatCurrency(schedule.hargaTiket),
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                TextButton(onPressed: onTap, child: Text(ctaLabel)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _MetaColumn extends StatelessWidget {
  const _MetaColumn({required this.label, required this.value});

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
          style: theme.textTheme.labelSmall?.copyWith(
            color: TravSTheme.navyMuted,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: theme.textTheme.bodySmall?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w600,
            height: 1.35,
          ),
        ),
      ],
    );
  }
}

class _BookingCard extends StatelessWidget {
  const _BookingCard({required this.booking, required this.onTap});

  final TravSBookingResult booking;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    booking.rute,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                _StatusPill(
                  label: _humanizeStatus(booking.status),
                  color: booking.isPaid
                      ? TravSTheme.success
                      : TravSTheme.warning,
                  textColor: booking.isPaid
                      ? TravSTheme.success
                      : TravSTheme.warning,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: _MetaColumn(
                    label: 'Tanggal keberangkatan',
                    value: _formatScheduleDate(booking.waktuBerangkat),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _MetaColumn(
                    label: 'Waktu keberangkatan',
                    value: _formatScheduleTime(booking.waktuBerangkat),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _MetaColumn(
                    label: 'Jumlah kursi',
                    value: '${booking.jumlahKursi}',
                  ),
                ),
                Expanded(
                  child: _MetaColumn(
                    label: 'Total',
                    value: _formatCurrency(booking.totalHarga),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _TicketPreviewCard extends StatelessWidget {
  const _TicketPreviewCard({
    required this.booking,
    required this.onTap,
    required this.isCompleted,
  });

  final TravSBookingResult booking;
  final VoidCallback onTap;
  final bool isCompleted;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(26),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: isCompleted ? const Color(0xFFE8EDF5) : TravSTheme.navy,
          borderRadius: BorderRadius.circular(26),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    booking.rute,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: isCompleted ? TravSTheme.navy : Colors.white,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Icon(
                  Icons.qr_code_2_rounded,
                  color: isCompleted ? TravSTheme.navyMuted : Colors.white,
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              isCompleted ? 'Perjalanan selesai' : 'Tiket perjalanan',
              style: theme.textTheme.bodySmall?.copyWith(
                color: isCompleted ? TravSTheme.navy : Colors.white,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _TicketScheduleMeta(
                    label: 'Tanggal keberangkatan',
                    value: _formatScheduleDate(booking.waktuBerangkat),
                    isCompleted: isCompleted,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _TicketScheduleMeta(
                    label: 'Waktu keberangkatan',
                    value: _formatScheduleTime(booking.waktuBerangkat),
                    isCompleted: isCompleted,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: isCompleted
                    ? Colors.white
                    : Colors.white.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.verified_outlined,
                    color: isCompleted ? TravSTheme.success : Colors.white,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      isCompleted
                          ? 'Perjalanan selesai. Tiket ini tersimpan sebagai riwayat.'
                          : booking.isPaid
                          ? 'Buka detail tiket untuk melihat QR unik per kursi penumpang.'
                          : 'Pemesanan ini masih menunggu pembayaran, jadi QR belum aktif.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: isCompleted ? TravSTheme.navy : Colors.white,
                      ),
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

class _TicketScheduleMeta extends StatelessWidget {
  const _TicketScheduleMeta({
    required this.label,
    required this.value,
    required this.isCompleted,
  });

  final String label;
  final String value;
  final bool isCompleted;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final labelColor = isCompleted ? TravSTheme.navyMuted : Colors.white70;
    final valueColor = isCompleted ? TravSTheme.navy : Colors.white;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: theme.textTheme.labelSmall?.copyWith(color: labelColor),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          style: theme.textTheme.bodySmall?.copyWith(
            color: valueColor,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _DriverJourneyCard extends StatelessWidget {
  const _DriverJourneyCard({required this.record, required this.onTap});

  final _DriverJourneyRecord record;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    record.route,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                _StatusPill(
                  label: record.statusLabel,
                  color: record.status == 'selesai'
                      ? TravSTheme.success
                      : TravSTheme.orange,
                  textColor: record.status == 'selesai'
                      ? TravSTheme.success
                      : TravSTheme.orange,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              record.vehicle,
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _MetaColumn(
                    label: 'Waktu berangkat',
                    value: _formatDateTime(record.departureAt),
                  ),
                ),
                Expanded(
                  child: _MetaColumn(
                    label: 'Manifest',
                    value: '${record.passengerCount} penumpang',
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _DriverHistoryCard extends StatelessWidget {
  const _DriverHistoryCard({required this.record, required this.onTap});

  final _DriverJourneyRecord record;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    record.route,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                _StatusPill(
                  label: record.statusLabel,
                  color: record.status == 'selesai'
                      ? TravSTheme.success
                      : TravSTheme.orange,
                  textColor: record.status == 'selesai'
                      ? TravSTheme.success
                      : TravSTheme.orange,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              _formatDateTime(record.departureAt),
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _MetaColumn(
                    label: 'Pendapatan',
                    value: _formatCurrency(record.revenue),
                  ),
                ),
                Expanded(
                  child: _MetaColumn(
                    label: 'Penumpang',
                    value: '${record.passengerCount} orang',
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _DriverHistoryDateFilter extends StatelessWidget {
  const _DriverHistoryDateFilter({
    required this.startDate,
    required this.endDate,
    required this.onPickStart,
    required this.onPickEnd,
    required this.onApply,
    required this.onReset,
  });

  final DateTime? startDate;
  final DateTime? endDate;
  final VoidCallback onPickStart;
  final VoidCallback onPickEnd;
  final VoidCallback onApply;
  final VoidCallback onReset;

  @override
  Widget build(BuildContext context) {
    String label(DateTime? date) => date == null
        ? 'Pilih tanggal'
        : DateFormat('dd MMM yyyy', 'id_ID').format(date);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Filter tanggal perjalanan',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _DateFilterButton(
                  title: 'Tanggal mulai',
                  value: label(startDate),
                  onTap: onPickStart,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _DateFilterButton(
                  title: 'Tanggal selesai',
                  value: label(endDate),
                  onTap: onPickEnd,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: onReset,
                  child: const Text('Reset'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton(
                  onPressed: onApply,
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(56),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(18),
                    ),
                  ),
                  child: const Text(
                    'Terapkan Filter',
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DateFilterButton extends StatelessWidget {
  const _DateFilterButton({
    required this.title,
    required this.value,
    required this.onTap,
  });

  final String title;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        constraints: const BoxConstraints(minHeight: 76),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: TravSTheme.surfaceSoft,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.labelSmall),
            const SizedBox(height: 5),
            Row(
              children: [
                const Icon(Icons.calendar_month_outlined, size: 17),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    value,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _EditProfilePage extends StatefulWidget {
  const _EditProfilePage({
    required this.session,
    required this.apiService,
    required this.authService,
  });

  final TravSUserSession session;
  final TravSApiService apiService;
  final TravSAuthService authService;

  @override
  State<_EditProfilePage> createState() => _EditProfilePageState();
}

class _EditProfilePageState extends State<_EditProfilePage> {
  final _formKey = GlobalKey<FormState>();
  final _imagePicker = ImagePicker();
  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  late final TextEditingController _phoneController;
  late final TextEditingController _driverLicenseController;
  final TextEditingController _currentPasswordController =
      TextEditingController();
  final TextEditingController _newPasswordController = TextEditingController();
  final TextEditingController _confirmPasswordController =
      TextEditingController();
  XFile? _selectedPhoto;
  Uint8List? _selectedPhotoBytes;
  bool _isSaving = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    final user = widget.session.user;
    _nameController = TextEditingController(text: user.nama);
    _emailController = TextEditingController(text: user.email);
    _phoneController = TextEditingController(text: user.noHp);
    _driverLicenseController = TextEditingController(text: user.nomorSim ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _driverLicenseController.dispose();
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _selectPhoto(ImageSource source) async {
    try {
      final photo = await _imagePicker.pickImage(
        source: source,
        imageQuality: 82,
        maxWidth: 1280,
        maxHeight: 1280,
      );

      if (photo == null || !mounted) {
        return;
      }

      final bytes = await photo.readAsBytes();

      setState(() {
        _selectedPhoto = photo;
        _selectedPhotoBytes = bytes;
        _errorMessage = null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _errorMessage =
            'Foto profil belum bisa dipilih. Coba gunakan galeri atau kamera lain.';
      });
    }
  }

  Future<void> _saveProfile() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _isSaving = true;
      _errorMessage = null;
    });

    try {
      String? uploadedProfilePath;
      if (_selectedPhoto != null) {
        final uploadedPhoto = await widget.apiService.uploadProfilePhoto(
          token: widget.session.token,
          photo: _selectedPhoto!,
        );
        uploadedProfilePath = uploadedPhoto.url.isNotEmpty
            ? '${uploadedPhoto.url}?v=${DateTime.now().millisecondsSinceEpoch}'
            : uploadedPhoto.path;
      }

      await widget.apiService.updateProfile(
        token: widget.session.token,
        nama: _nameController.text,
        email: _emailController.text,
        noHp: _phoneController.text,
        nomorSim: widget.session.user.isDriver
            ? _driverLicenseController.text
            : null,
      );

      final shouldChangePassword =
          _currentPasswordController.text.trim().isNotEmpty ||
          _newPasswordController.text.trim().isNotEmpty ||
          _confirmPasswordController.text.trim().isNotEmpty;
      if (shouldChangePassword) {
        await widget.apiService.changePassword(
          token: widget.session.token,
          currentPassword: _currentPasswordController.text,
          password: _newPasswordController.text,
          passwordConfirmation: _confirmPasswordController.text,
        );
      }

      var updatedUser = await widget.apiService.loadCurrentUserProfile(
        token: widget.session.token,
      );
      if (uploadedProfilePath != null && uploadedProfilePath.isNotEmpty) {
        updatedUser = updatedUser.copyWith(fotoProfil: uploadedProfilePath);
      }

      final updatedSession = widget.session.copyWith(user: updatedUser);
      await widget.authService.saveSession(updatedSession);

      if (!mounted) {
        return;
      }

      Navigator.of(context).pop(updatedSession);
    } on TravSApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _errorMessage =
            'Profil belum bisa disimpan. Periksa koneksi lalu coba lagi.';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isSaving = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final user = widget.session.user;
    final avatarUrl = TravSApiService.resolveMediaUrl(user.fotoProfil);
    final photoName = _selectedPhoto?.name;

    return Scaffold(
      appBar: AppBar(title: const Text('Edit Profil')),
      body: Container(
        decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
        child: SafeArea(
          child: Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 28),
              children: [
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(color: TravSTheme.line),
                  ),
                  child: Column(
                    children: [
                      _ProfileAvatarPreview(
                        name: user.nama,
                        imageBytes: _selectedPhotoBytes,
                        imageUrl: avatarUrl,
                        radius: 44,
                        onTap: _selectedPhotoBytes != null || avatarUrl == null
                            ? null
                            : () => _showTravSImagePreview(
                                context,
                                imageUrl: avatarUrl,
                                title: user.nama,
                              ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        photoName == null
                            ? 'Pilih foto profil dari kamera atau galeri.'
                            : 'Dipilih: $photoName',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: TravSTheme.navyMuted,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _isSaving
                                  ? null
                                  : () => _selectPhoto(ImageSource.camera),
                              icon: const Icon(Icons.photo_camera_outlined),
                              label: const Text('Kamera'),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _isSaving
                                  ? null
                                  : () => _selectPhoto(ImageSource.gallery),
                              icon: const Icon(Icons.photo_library_outlined),
                              label: const Text('Galeri'),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                _SectionCard(
                  title: 'Data Akun',
                  subtitle: 'Ubah data yang dipakai untuk identitas akun.',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const _ProfileFieldLabel('Nama lengkap'),
                      TextFormField(
                        controller: _nameController,
                        textCapitalization: TextCapitalization.words,
                        decoration: _profileInputDecoration(
                          hint: 'Masukkan nama lengkap',
                          icon: Icons.person_outline_rounded,
                        ),
                        validator: (value) =>
                            _validateRequiredText(value, 'Nama lengkap'),
                      ),
                      const SizedBox(height: 12),
                      const _ProfileFieldLabel('Email'),
                      TextFormField(
                        controller: _emailController,
                        keyboardType: TextInputType.emailAddress,
                        decoration: _profileInputDecoration(
                          hint: 'contoh@email.com',
                          icon: Icons.mail_outline_rounded,
                        ),
                        validator: (value) =>
                            _validateRequiredText(value, 'Email'),
                      ),
                      const SizedBox(height: 12),
                      _ProfileFieldLabel(
                        user.isDriver ? 'Nomor HP pengemudi' : 'Nomor HP',
                      ),
                      TextFormField(
                        controller: _phoneController,
                        keyboardType: TextInputType.phone,
                        decoration: _profileInputDecoration(
                          hint: '081234567891',
                          icon: Icons.phone_outlined,
                        ),
                        validator: (value) =>
                            _validateRequiredText(value, 'Nomor HP'),
                      ),
                    ],
                  ),
                ),
                if (user.isDriver) ...[
                  const SizedBox(height: 14),
                  _SectionCard(
                    title: 'Data Pengemudi',
                    subtitle:
                        'Lengkapi identitas pengemudi. Data kendaraan dikelola admin dari menu Armada dan Jadwal.',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const _ProfileFieldLabel('Nomor SIM (NIK KTP)'),
                        TextFormField(
                          controller: _driverLicenseController,
                          keyboardType: TextInputType.number,
                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly,
                            LengthLimitingTextInputFormatter(16),
                          ],
                          decoration: _profileInputDecoration(
                            hint: 'Masukkan 16 digit NIK KTP',
                            icon: Icons.badge_outlined,
                          ),
                          validator: _validateDriverLicense,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Kendaraan tidak diedit dari profil pengemudi karena armada ditentukan admin pada jadwal perjalanan.',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: TravSTheme.navyMuted,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                _SectionCard(
                  title: 'Ganti Password',
                  subtitle:
                      'Isi bagian ini hanya jika ingin mengganti password akun.',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const _ProfileFieldLabel('Password lama'),
                      TextFormField(
                        controller: _currentPasswordController,
                        obscureText: true,
                        decoration: _profileInputDecoration(
                          hint: 'Password lama',
                          icon: Icons.lock_outline_rounded,
                        ),
                        validator: _validateOptionalPasswordGroup,
                      ),
                      const SizedBox(height: 12),
                      const _ProfileFieldLabel('Password baru'),
                      TextFormField(
                        controller: _newPasswordController,
                        obscureText: true,
                        decoration: _profileInputDecoration(
                          hint: 'Password baru',
                          icon: Icons.lock_reset_rounded,
                        ),
                        validator: _validateNewPassword,
                      ),
                      const SizedBox(height: 12),
                      const _ProfileFieldLabel('Konfirmasi password baru'),
                      TextFormField(
                        controller: _confirmPasswordController,
                        obscureText: true,
                        decoration: _profileInputDecoration(
                          hint: 'Konfirmasi password baru',
                          icon: Icons.verified_user_outlined,
                        ),
                        validator: _validateConfirmPassword,
                      ),
                    ],
                  ),
                ),
                if (_errorMessage != null) ...[
                  const SizedBox(height: 14),
                  _ErrorBanner(message: _errorMessage!),
                ],
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _isSaving ? null : _saveProfile,
                  icon: _isSaving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.save_outlined),
                  label: Text(_isSaving ? 'Menyimpan...' : 'Simpan Profil'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  InputDecoration _profileInputDecoration({
    required String hint,
    required IconData icon,
  }) {
    return InputDecoration(
      hintText: hint,
      prefixIcon: Icon(icon),
      filled: true,
      fillColor: Colors.white,
      floatingLabelBehavior: FloatingLabelBehavior.never,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: TravSTheme.line),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: TravSTheme.line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: TravSTheme.orange, width: 1.4),
      ),
    );
  }

  String? _validateOptionalPasswordGroup(String? value) {
    final shouldChangePassword =
        value?.trim().isNotEmpty == true ||
        _newPasswordController.text.trim().isNotEmpty ||
        _confirmPasswordController.text.trim().isNotEmpty;
    if (shouldChangePassword && (value == null || value.trim().isEmpty)) {
      return 'Password lama wajib diisi.';
    }
    return null;
  }

  String? _validateDriverLicense(String? value) {
    final licenseNumber = value?.trim() ?? '';
    if (licenseNumber.isEmpty) {
      return 'Nomor SIM wajib diisi.';
    }
    if (licenseNumber.length != 16) {
      return 'Nomor SIM harus menggunakan NIK KTP sebanyak 16 digit.';
    }
    return null;
  }

  String? _validateNewPassword(String? value) {
    final shouldChangePassword =
        _currentPasswordController.text.trim().isNotEmpty ||
        value?.trim().isNotEmpty == true ||
        _confirmPasswordController.text.trim().isNotEmpty;
    if (!shouldChangePassword) {
      return null;
    }
    if (value == null || value.trim().length < 8) {
      return 'Password baru minimal 8 karakter.';
    }
    return null;
  }

  String? _validateConfirmPassword(String? value) {
    if (_newPasswordController.text.trim().isEmpty &&
        (value == null || value.trim().isEmpty)) {
      return null;
    }
    if (value != _newPasswordController.text) {
      return 'Konfirmasi password belum sama.';
    }
    return null;
  }
}

class _ProfileFieldLabel extends StatelessWidget {
  const _ProfileFieldLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 6),
      child: Text(
        text,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: TravSTheme.navy,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _ProfileAvatarPreview extends StatelessWidget {
  const _ProfileAvatarPreview({
    required this.name,
    required this.radius,
    this.imageBytes,
    this.imageUrl,
    this.onTap,
  });

  final String name;
  final double radius;
  final Uint8List? imageBytes;
  final String? imageUrl;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty
        ? 'P'
        : name.trim().substring(0, 1).toUpperCase();
    final size = radius * 2;

    Widget initialAvatar() {
      return CircleAvatar(
        radius: radius,
        backgroundColor: TravSTheme.navy,
        child: Text(
          initial,
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w900,
          ),
        ),
      );
    }

    Widget tappable(Widget child) {
      if (onTap == null) {
        return child;
      }

      return GestureDetector(onTap: onTap, child: child);
    }

    if (imageBytes != null) {
      return tappable(
        CircleAvatar(
          radius: radius,
          backgroundColor: TravSTheme.navy,
          child: ClipOval(
            child: Image.memory(
              imageBytes!,
              width: size,
              height: size,
              fit: BoxFit.cover,
            ),
          ),
        ),
      );
    }

    if (imageUrl != null && imageUrl!.trim().isNotEmpty) {
      return tappable(
        CircleAvatar(
          radius: radius,
          backgroundColor: TravSTheme.navy,
          child: ClipOval(
            child: Image.network(
              imageUrl!,
              width: size,
              height: size,
              fit: BoxFit.cover,
              loadingBuilder: (context, child, progress) {
                if (progress == null) {
                  return child;
                }

                return SizedBox(
                  width: size,
                  height: size,
                  child: const Center(
                    child: SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  ),
                );
              },
              errorBuilder: (context, error, stackTrace) {
                return initialAvatar();
              },
            ),
          ),
        ),
      );
    }

    return tappable(initialAvatar());
  }
}

class _ProfileSummaryCard extends StatelessWidget {
  const _ProfileSummaryCard({required this.session});

  final TravSUserSession session;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final avatarUrl = TravSApiService.resolveMediaUrl(session.user.fotoProfil);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Row(
        children: [
          _ProfileAvatarPreview(
            name: session.user.nama,
            radius: 30,
            imageUrl: avatarUrl,
            onTap: avatarUrl == null
                ? null
                : () => _showTravSImagePreview(
                    context,
                    imageUrl: avatarUrl,
                    title: session.user.nama,
                  ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  session.user.nama,
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  session.user.email,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _humanizeRole(session.user.peran),
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: TravSTheme.orange,
                    fontWeight: FontWeight.w700,
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

class _ProfileMenuTile extends StatelessWidget {
  const _ProfileMenuTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(22),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: TravSTheme.surfaceSoft,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: TravSTheme.navy),
            ),
            const SizedBox(width: 14),
            Expanded(
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
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navyMuted,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.chevron_right_rounded,
              color: TravSTheme.navyMuted,
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
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

    return Container(
      width: double.infinity,
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
            title,
            style: theme.textTheme.titleLarge?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
            ),
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F1),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFFFB7B7)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline_rounded, color: Color(0xFFEF4444)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: const Color(0xFFB91C1C),
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({
    required this.title,
    required this.description,
    required this.icon,
    this.justifyDescription = false,
  });

  final String title;
  final String description;
  final IconData icon;
  final bool justifyDescription;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: TravSTheme.orangeSoft,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(icon, color: TravSTheme.orange),
          ),
          const SizedBox(width: 12),
          Expanded(
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
                const SizedBox(height: 6),
                Text(
                  description,
                  textAlign: justifyDescription
                      ? TextAlign.justify
                      : TextAlign.start,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
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

class _PassengerInfoPage extends StatelessWidget {
  const _PassengerInfoPage();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: TravSTheme.background,
      body: SafeArea(
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          children: [
            _PageTopBar(
              leading: IconButton(
                onPressed: () => Navigator.of(context).maybePop(),
                icon: const Icon(Icons.arrow_back_rounded),
                color: TravSTheme.navy,
              ),
              title: 'Info Pemesanan',
              subtitle: 'Aturan dan tata cara pemesanan penumpang.',
            ),
            const SizedBox(height: 16),
            const _BookingInfoSections(),
          ],
        ),
      ),
    );
  }
}

class _DriverInfoPage extends StatelessWidget {
  const _DriverInfoPage();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
          children: [
            _PageTopBar(
              leading: IconButton(
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.arrow_back_rounded),
              ),
              title: 'Panduan Pengemudi',
              subtitle: 'Tata cara dan aturan kerja pengemudi.',
            ),
            const SizedBox(height: 16),
            const _InfoGroupTitle(
              title: 'Tata Cara Pengemudi',
              subtitle:
                  'Langkah yang perlu dilakukan saat menerima tugas perjalanan.',
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Periksa jadwal tugas',
              description:
                  'Buka halaman Beranda atau Manifest untuk melihat jadwal yang ditugaskan admin. Periksa rute, waktu berangkat, kendaraan, dan jumlah penumpang sebelum perjalanan dimulai.\n\n'
                  '1. Jadwal aktif muncul setelah admin membuat penugasan.\n'
                  '2. Kendaraan mengikuti data jadwal, bukan data profil.\n'
                  '3. Jika jadwal tidak sesuai, hubungi admin sebelum berangkat.',
              icon: Icons.event_available_outlined,
              justifyDescription: true,
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Buka manifest penumpang',
              description:
                  'Manifest berisi daftar penumpang, nomor kursi, titik jemput, titik turun, nomor HP, dan data barang bawaan. Gunakan data ini sebagai acuan kerja saat menjemput penumpang.\n\n'
                  '1. Pastikan nama dan kursi sesuai daftar.\n'
                  '2. Periksa catatan patokan jemput dan turun.\n'
                  '3. Lihat foto barang jika penumpang mengunggah bukti barang bawaan.',
              icon: Icons.assignment_outlined,
              justifyDescription: true,
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Hubungi dan jemput penumpang',
              description:
                  'Gunakan tombol telepon atau WhatsApp jika perlu memastikan titik jemput. Jika tersedia, buka tombol navigasi untuk membantu menuju lokasi penjemputan atau titik turun.\n\n'
                  '1. Nomor penumpang hanya digunakan untuk kebutuhan perjalanan.\n'
                  '2. Pastikan titik jemput sesuai data aplikasi.\n'
                  '3. Konfirmasi ulang jika lokasi sulit ditemukan.',
              icon: Icons.phone_in_talk_outlined,
              justifyDescription: true,
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Pindai QR tiket',
              description:
                  'Saat penumpang naik, pindai QR tiket dari aplikasi penumpang. Pemindaian dipakai untuk mencatat kehadiran dan memastikan tiket yang digunakan sesuai pemesanan.\n\n'
                  '1. Jangan memindai tiket milik penumpang lain.\n'
                  '2. Jika QR tidak terbaca, periksa kembali data manifest.\n'
                  '3. Laporkan kendala tiket kepada admin.',
              icon: Icons.qr_code_scanner_rounded,
              justifyDescription: true,
            ),
            const SizedBox(height: 22),
            const _InfoGroupTitle(
              title: 'Aturan Pengemudi',
              subtitle:
                  'Ketentuan yang perlu dijaga selama menjalankan perjalanan.',
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Ikuti jadwal dan kendaraan tugas',
              description:
                  'Pengemudi menjalankan perjalanan sesuai jadwal yang dibuat admin. Data rute, kendaraan, waktu keberangkatan, dan manifest tidak boleh diubah sendiri melalui aplikasi pengemudi.\n\n'
                  '1. Kendaraan ditentukan dari jadwal.\n'
                  '2. Perubahan rute atau jadwal dilakukan oleh admin.\n'
                  '3. Jika ada kendala kendaraan, segera konfirmasi ke admin.',
              icon: Icons.directions_car_filled_outlined,
              justifyDescription: true,
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Jaga data penumpang',
              description:
                  'Nomor HP, titik jemput, titik turun, dan tiket penumpang hanya digunakan untuk kebutuhan perjalanan. Jangan membagikan data tersebut kepada pihak lain.\n\n'
                  '1. QR tiket bersifat bukti perjalanan.\n'
                  '2. Data lokasi dipakai untuk operasional jemput dan turun.\n'
                  '3. Komunikasi dilakukan secara sopan dan seperlunya.',
              icon: Icons.privacy_tip_outlined,
              justifyDescription: true,
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Catat pembayaran tunai dengan benar',
              description:
                  'Jika penumpang memilih pembayaran tunai, konfirmasi hanya setelah pembayaran benar-benar diterima. Status pembayaran digital tetap mengikuti sistem pembayaran yang digunakan.\n\n'
                  '1. Jangan mengonfirmasi tunai sebelum uang diterima.\n'
                  '2. Pembayaran digital tidak diubah manual oleh pengemudi.\n'
                  '3. Laporkan selisih pembayaran kepada admin.',
              icon: Icons.payments_outlined,
              justifyDescription: true,
            ),
          ],
        ),
      ),
    );
  }
}

class _BookingInfoSections extends StatelessWidget {
  const _BookingInfoSections();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _InfoGroupTitle(
          title: 'Aturan Pemesanan',
          subtitle: 'Ketentuan yang perlu dipahami sebelum membuat pemesanan.',
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Data penumpang wajib benar',
          description:
              'Gunakan nama lengkap dan nomor HP aktif agar admin atau pengemudi bisa menghubungi Anda jika ada perubahan jadwal.\n\n'
              '1. Nama penumpang dipakai pada tiket dan manifest.\n'
              '2. Nomor HP dipakai untuk komunikasi keberangkatan.\n'
              '3. Data yang salah dapat menyulitkan proses penjemputan.',
          icon: Icons.badge_outlined,
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Kursi mengikuti ketersediaan jadwal',
          description:
              'Kursi yang sudah dipilih akan dikunci sementara saat proses pemesanan berjalan. Jika sesi habis atau pemesanan dibatalkan, kursi dilepas kembali agar bisa dipilih penumpang lain.\n\n'
              '1. Pilih kursi sesuai jumlah penumpang.\n'
              '2. Jangan menutup proses pemesanan sebelum data selesai dikirim.\n'
              '3. Kursi baru dianggap aman setelah pemesanan berhasil dibuat.',
          icon: Icons.event_seat_outlined,
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Pembayaran mengikuti batas waktu',
          description:
              'Setelah pemesanan dibuat, pembayaran harus diselesaikan sebelum batas waktu berakhir. Jika melewati batas dan belum berhasil dibayar, pemesanan dapat dibatalkan oleh sistem.\n\n'
              '1. QRIS atau transfer gateway mengikuti status dari sistem pembayaran.\n'
              '2. Pembayaran tunai dikonfirmasi oleh pengemudi atau admin.\n'
              '3. Tiket QR hanya valid setelah pembayaran dinyatakan lunas.',
          icon: Icons.timer_outlined,
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Titik jemput, titik turun, dan barang bawaan',
          description:
              'Isi alamat serta patokan lokasi dengan jelas agar pengemudi mudah menemukan Anda. Barang bawaan biasa dicatat sebagai informasi operasional dan tidak dikenakan biaya tambahan.\n\n'
              '1. Patokan bisa berupa toko, gang, bangunan, atau warna rumah.\n'
              '2. Isi nama atau keterangan barang yang dibawa.\n'
              '3. Foto barang bersifat opsional dan membantu pengecekan.\n'
              '4. Sepeda, sepeda motor, dan barang berukuran besar wajib dikonfirmasi langsung kepada admin.\n'
              '5. Barang besar tidak dihitung atau diterima otomatis oleh aplikasi.',
          icon: Icons.location_on_outlined,
          justifyDescription: true,
        ),
        const SizedBox(height: 22),
        const _InfoGroupTitle(
          title: 'Tata Cara Pemesanan',
          subtitle:
              'Langkah penggunaan aplikasi dari mencari jadwal sampai berangkat.',
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: '1. Pilih rute dan jadwal',
          description:
              'Buka menu Rute, pilih tujuan perjalanan, lalu lihat jadwal yang tersedia. Perhatikan tanggal, jam berangkat, armada, harga tiket, dan jumlah kursi kosong sebelum melanjutkan.',
          icon: Icons.route_rounded,
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: '2. Pilih kursi dan isi data perjalanan',
          description:
              'Pilih kursi yang masih tersedia, lalu isi data penumpang. Setelah itu lengkapi titik jemput dan titik turun dengan alamat serta patokan lokasi yang mudah dipahami pengemudi.',
          icon: Icons.fact_check_outlined,
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: '3. Isi barang bawaan',
          description:
              'Pilih apakah Anda membawa barang, lalu isi nama atau keterangan barang yang dibawa. Foto dapat ditambahkan untuk membantu pengemudi mengenali barang. Untuk sepeda, sepeda motor, atau barang berukuran besar, hubungi admin terlebih dahulu sebelum membuat pemesanan.',
          icon: Icons.luggage_rounded,
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: '4. Selesaikan pembayaran',
          description:
              'Pilih metode pembayaran yang tersedia. Untuk QRIS atau transfer gateway, ikuti instruksi pembayaran sampai berhasil. Untuk tunai, siapkan pembayaran saat bertemu pengemudi sesuai ketentuan.',
          icon: Icons.payments_rounded,
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: '5. Gunakan tiket digital saat keberangkatan',
          description:
              'Setelah pembayaran berhasil, tiket digital dan QR akan tersedia di menu Tiket. Tunjukkan QR kepada pengemudi saat keberangkatan agar tiket dapat dipindai dan status kehadiran tercatat.',
          icon: Icons.qr_code_2_rounded,
          justifyDescription: true,
        ),
        const SizedBox(height: 22),
        const _InfoGroupTitle(
          title: 'Syarat Penggunaan',
          subtitle:
              'Hal yang perlu dijaga agar pemesanan dan perjalanan berjalan lancar.',
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Isi data sesuai kondisi sebenarnya',
          description:
              'Gunakan data penumpang, titik jemput, titik turun, dan barang bawaan yang benar. Data ini dipakai admin dan pengemudi untuk menyiapkan perjalanan, menghubungi penumpang, dan memastikan kapasitas kendaraan tetap aman.',
          icon: Icons.assignment_ind_outlined,
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Simpan dan jaga tiket QR',
          description:
              'Tiket QR adalah bukti pemesanan yang dipakai saat keberangkatan. Jangan membagikan tiket kepada orang lain, karena QR digunakan pengemudi untuk mencatat kehadiran penumpang.',
          icon: Icons.security_rounded,
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Ikuti waktu dan titik keberangkatan',
          description:
              'Datang sesuai jadwal yang tertera pada tiket. Jika titik jemput atau titik turun sulit ditemukan, isi patokan lokasi seperti nama toko, simpang, gang, warna rumah, atau bangunan terdekat.',
          icon: Icons.schedule_rounded,
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Pahami batas waktu dan pembatalan',
          description:
              'Kursi hanya dikunci sementara saat pemesanan dan pembayaran. Jika waktu habis atau pembayaran belum selesai, pemesanan dapat dibatalkan oleh sistem agar kursi tersedia kembali untuk penumpang lain.',
          icon: Icons.event_busy_rounded,
          justifyDescription: true,
        ),
      ],
    );
  }
}

class _InfoGroupTitle extends StatelessWidget {
  const _InfoGroupTitle({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: theme.textTheme.titleLarge?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          subtitle,
          style: theme.textTheme.bodySmall?.copyWith(
            color: TravSTheme.navyMuted,
          ),
        ),
      ],
    );
  }
}

class _EmptyStateCard extends StatelessWidget {
  const _EmptyStateCard({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return _InfoCard(
      title: title,
      description: subtitle,
      icon: Icons.inbox_outlined,
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({
    required this.label,
    required this.color,
    required this.textColor,
    this.solid = false,
  });

  final String label;
  final Color color;
  final Color textColor;
  final bool solid;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: solid ? color : color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: solid ? color : color.withValues(alpha: 0.16),
        ),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: textColor,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

Color _journeyStatusBackground(String status) {
  switch (status) {
    case 'dibuka':
      return TravSTheme.success;
    case 'ditutup':
      return Colors.white;
    case 'berangkat':
      return TravSTheme.orange;
    case 'selesai':
      return TravSTheme.navyMuted;
    case 'dibatalkan':
      return TravSTheme.danger;
    default:
      return Colors.white;
  }
}

Color _journeyStatusTextColor(String status) {
  switch (status) {
    case 'dibuka':
    case 'berangkat':
    case 'selesai':
    case 'dibatalkan':
      return Colors.white;
    default:
      return TravSTheme.navy;
  }
}

String _humanizeRole(String role) {
  switch (role) {
    case 'pengemudi':
      return 'Pengemudi';
    case 'penumpang':
      return 'Penumpang';
    case 'admin':
      return 'Admin';
    default:
      return 'Tamu';
  }
}

String _humanizeStatus(String status) {
  switch (status) {
    case 'terbayar':
    case 'berhasil':
      return 'Berhasil';
    case 'menunggu':
    case 'menunggu_pembayaran':
      return 'Menunggu';
    case 'dibuka':
      return 'Dibuka';
    case 'ditutup':
      return 'Ditutup';
    case 'berangkat':
      return 'Berangkat';
    case 'selesai':
      return 'Selesai';
    default:
      return status.replaceAll('_', ' ');
  }
}

// Daftar rute memakai data jadwal yang sudah ada agar tidak meminta data ulang ke backend.
List<_RouteBookmark> _buildRouteBookmarks(List<TravSSchedule> schedules) {
  final groupedSchedules = <String, List<TravSSchedule>>{};

  for (final schedule in schedules.where((item) => item.sisaKursi > 0)) {
    final key = _routeKeyForSchedule(schedule);
    groupedSchedules.putIfAbsent(key, () => <TravSSchedule>[]).add(schedule);
  }

  return groupedSchedules.entries.map((entry) {
    final routeSchedules = entry.value;
    final firstSchedule = routeSchedules.first;
    var availableSeats = 0;
    var startingPrice = firstSchedule.hargaTiket;

    for (final schedule in routeSchedules) {
      availableSeats += schedule.sisaKursi;
      if (schedule.hargaTiket < startingPrice) {
        startingPrice = schedule.hargaTiket;
      }
    }

    return _RouteBookmark(
      key: entry.key,
      origin: firstSchedule.asal,
      destination: firstSchedule.tujuan,
      path: firstSchedule.jalur,
      scheduleCount: routeSchedules.length,
      availableSeats: availableSeats,
      startingPrice: startingPrice,
    );
  }).toList();
}

String _routeKeyForSchedule(TravSSchedule schedule) {
  final origin = schedule.asal.trim().toLowerCase();
  final destination = schedule.tujuan.trim().toLowerCase();
  return '$origin|$destination';
}

String _destinationImageUrl(String destination) {
  return _unsplashSearchUrl('$destination Indonesia city landscape');
}

String _vehicleImageUrl(String vehicleName) {
  return _unsplashSearchUrl('$vehicleName travel car');
}

String _unsplashSearchUrl(String query) {
  final encodedQuery = Uri.encodeComponent(query);
  return 'https://source.unsplash.com/160x160/?$encodedQuery';
}

_RouteBookmark? _findRouteBookmark(
  List<_RouteBookmark> routes,
  String? selectedKey,
) {
  if (selectedKey == null) {
    return null;
  }

  for (final route in routes) {
    if (route.key == selectedKey) {
      return route;
    }
  }

  return null;
}

// Filter dijalankan di Flutter agar pergantian pilihan terasa cepat dan tidak mengulang query.
List<TravSSchedule> _filterSchedulesForRoute({
  required List<TravSSchedule> schedules,
  required String routeKey,
  required _ScheduleDateFilter filter,
  DateTime? selectedDate,
}) {
  final filtered = schedules
      .where((schedule) => _routeKeyForSchedule(schedule) == routeKey)
      .where((schedule) => schedule.sisaKursi > 0)
      .toList();

  if (filter == _ScheduleDateFilter.tomorrow) {
    final tomorrow = DateTime.now().add(const Duration(days: 1));
    return filtered.where((schedule) {
      final scheduleDate = _parseLocalDateTime(schedule.waktuBerangkat);
      return scheduleDate != null && _isSameDate(scheduleDate, tomorrow);
    }).toList();
  }

  if (filter == _ScheduleDateFilter.byDate && selectedDate != null) {
    return filtered.where((schedule) {
      final scheduleDate = _parseLocalDateTime(schedule.waktuBerangkat);
      return scheduleDate != null && _isSameDate(scheduleDate, selectedDate);
    }).toList()..sort((first, second) {
      return _scheduleDateValue(
        first.waktuBerangkat,
      ).compareTo(_scheduleDateValue(second.waktuBerangkat));
    });
  }

  return filtered..sort((first, second) {
    return _scheduleDateValue(
      first.waktuBerangkat,
    ).compareTo(_scheduleDateValue(second.waktuBerangkat));
  });
}

bool _isSameDate(DateTime first, DateTime second) {
  return first.year == second.year &&
      first.month == second.month &&
      first.day == second.day;
}

TravSBookingResult? _nextPaidUpcomingBooking(
  List<TravSBookingResult> bookings,
) {
  final now = DateTime.now();
  final paidUpcoming =
      bookings.where((booking) {
        final departure = _parseLocalDateTime(booking.waktuBerangkat);
        if (departure == null) {
          return false;
        }

        // Perjalanan berikutnya hanya mengambil pemesanan lunas yang masih aktif,
        // sehingga pesanan batal atau perjalanan selesai tidak ikut tampil.
        return booking.status == 'terbayar' &&
            booking.payment.status == 'berhasil' &&
            !departure.isBefore(now);
      }).toList()..sort((first, second) {
        return _scheduleDateValue(
          first.waktuBerangkat,
        ).compareTo(_scheduleDateValue(second.waktuBerangkat));
      });

  return paidUpcoming.isEmpty ? null : paidUpcoming.first;
}

bool _isCompletedTicket(TravSBookingResult booking) {
  if (booking.status == 'selesai') {
    return true;
  }

  final departure = DateTime.tryParse(booking.waktuBerangkat)?.toLocal();
  return departure != null && departure.isBefore(DateTime.now());
}

bool _isOldBooking(TravSBookingResult booking) {
  return booking.status == 'dibatalkan' || _isCompletedTicket(booking);
}

String? _validateRequiredText(String? value, String label) {
  if ((value ?? '').trim().isEmpty) {
    return '$label wajib diisi.';
  }

  return null;
}

DateTime _scheduleDateValue(String iso) {
  return _parseLocalDateTime(iso) ?? DateTime(9999);
}

DateTime? _parseLocalDateTime(String iso) {
  if (iso.isEmpty) {
    return null;
  }

  // API mengirim waktu jadwal dalam format ISO. Semua tampilan jadwal di mobile
  // harus memakai waktu lokal perangkat agar daftar jadwal dan detail pemesanan
  // tidak berbeda hari/jam ketika response memiliki timezone UTC.
  return DateTime.tryParse(iso)?.toLocal();
}

String _formatScheduleDate(String iso) {
  if (iso.isEmpty) {
    return '-';
  }

  final date = _parseLocalDateTime(iso);
  if (date == null) {
    return iso;
  }

  return DateFormat('EEEE, dd MMM yyyy', 'id_ID').format(date);
}

String _formatScheduleTime(String iso) {
  if (iso.isEmpty) {
    return '-';
  }

  final date = _parseLocalDateTime(iso);
  if (date == null) {
    return iso;
  }

  final hour = date.hour;
  final time = DateFormat('HH.mm').format(date);
  final period = hour < 11
      ? 'Pagi'
      : hour < 15
      ? 'Siang'
      : hour < 18
      ? 'Sore'
      : 'Malam';

  return '$time $period';
}

String _formatDateTime(String iso) {
  if (iso.isEmpty) {
    return '-';
  }

  final date = _parseLocalDateTime(iso);
  if (date == null) {
    return iso;
  }

  return DateFormat('dd MMM yyyy\nHH:mm', 'id_ID').format(date);
}

String _compactRouteName(String route) {
  return route
      .trim()
      .replaceAll(RegExp(r'\s*-\s*'), '-')
      .replaceAll(RegExp(r'\s{2,}'), ' ');
}

String _formatShortDate(String iso) {
  if (iso.isEmpty) {
    return '-';
  }

  final date = _parseLocalDateTime(iso);
  if (date == null) {
    return iso;
  }

  return DateFormat('dd MMM').format(date);
}

String _formatCurrency(double value) {
  return NumberFormat.currency(
    locale: 'id_ID',
    symbol: 'Rp',
    decimalDigits: 0,
  ).format(value);
}
