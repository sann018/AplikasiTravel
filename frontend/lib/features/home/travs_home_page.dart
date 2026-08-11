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

// Bagian besar dipisah per fungsi agar file utama lebih mudah dipelajari.
part 'bagian_beranda/navigasi.dart';
part 'bagian_beranda/bagian_dasbor.dart';
part 'bagian_beranda/model_perjalanan.dart';
part 'bagian_beranda/notifikasi.dart';
part 'bagian_beranda/detail_perjalanan_pengemudi.dart';
part 'bagian_beranda/pemindai_tiket.dart';
part 'bagian_beranda/kartu_beranda.dart';
part 'bagian_beranda/profil.dart';
part 'bagian_beranda/halaman_informasi.dart';

enum _ScheduleDateFilter { all, byDate }

typedef TravSDateRangePicker =
    Future<DateTimeRange?> Function(
      BuildContext context,
      DateTime? startDate,
      DateTime? endDate,
    );

const String _notificationsEnabledPrefsKey = 'travs_notifications_enabled';
@visibleForTesting
void handleDriverTicketScanRequest({
  required BuildContext context,
  required String scheduleStatus,
  required VoidCallback onAllowed,
}) {
  // Jadwal selesai tidak boleh membuka kamera maupun memulai request scan.
  if (scheduleStatus == 'selesai') {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Maaf, pemindaian tidak dapat dilakukan karena jadwal perjalanan ini sudah selesai.',
        ),
        behavior: SnackBarBehavior.floating,
      ),
    );
    return;
  }

  onAllowed();
}

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
                tooltip: 'Tutup pratinjau',
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.close_rounded),
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
    this.apiService,
    this.settingsApiService,
    this.authService,
    this.dateRangePicker,
    super.key,
  });

  final TravSUserSession? session;
  final VoidCallback onOpenAuth;
  final Future<void> Function()? onLogout;
  final ValueChanged<TravSUserSession>? onSessionUpdated;
  final TravSApiService? apiService;
  final SettingsApiService? settingsApiService;
  final TravSAuthService? authService;
  final TravSDateRangePicker? dateRangePicker;

  @override
  State<TravSHomePage> createState() => _TravSHomePageState();
}

class _TravSHomePageState extends State<TravSHomePage> {
  late final TravSApiService _apiService;
  late final SettingsApiService _settingsApiService;
  late final TravSAuthService _authService;

  late Future<_HomeBundle> _future;
  int _selectedTabIndex = 0;
  String? _selectedRouteKey;
  _ScheduleDateFilter _selectedScheduleFilter = _ScheduleDateFilter.all;
  DateTime? _selectedScheduleDate;
  DateTime? _appliedDriverHistoryStartDate;
  DateTime? _appliedDriverHistoryEndDate;
  DateTime? _oldBookingStartDate;
  DateTime? _oldBookingEndDate;
  bool _hasSyncedSessionProfile = false;
  bool _notificationsEnabled = false;
  // Waktu terakhir membuka halaman. Dipakai untuk mengabaikan klik beruntun
  // yang terlalu cepat agar halaman tidak terbuka berlapis (double-push),
  // tetapi jedanya singkat sehingga membuka halaman lain dari dalam halaman
  // yang baru dibuka (misalnya scanner dari detail perjalanan) tetap berfungsi.
  DateTime? _lastPagePushedAt;
  // Penjaga khusus tombol notifikasi karena ada proses memuat data (jaringan)
  // sebelum halaman notifikasi dibuka, sehingga tombol masih bisa ditekan
  // berkali-kali selama menunggu.
  bool _isOpeningNotifications = false;
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
    _apiService = widget.apiService ?? TravSApiService();
    _settingsApiService =
        widget.settingsApiService ??
        SettingsApiService(apiService: _apiService);
    _authService = widget.authService ?? TravSAuthService();
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
      _appliedDriverHistoryStartDate = null;
      _appliedDriverHistoryEndDate = null;
      _oldBookingStartDate = null;
      _oldBookingEndDate = null;
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

    if (_role == AppRolePreview.counterStaff && widget.session != null) {
      final dashboardFuture = _apiService.loadCounterStaffDashboard(
        widget.session!.token,
      );
      final bookingsFuture = _apiService.loadCounterBookings(
        widget.session!.token,
      );
      final notificationsFuture = _loadNotificationFeedSafely();
      final dashboard = await dashboardFuture;
      final bookings = await bookingsFuture;
      final notifications = await notificationsFuture;
      return _HomeBundle(
        payload: payload,
        appSettings: appSettings,
        passengerBookings: bookings,
        counterDashboard: dashboard,
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

  // Membuka halaman baru dengan penjaga anti-spam klik berbasis jeda waktu.
  // Dua klik yang terjadi sangat berdekatan (< 600 ms) hanya membuka satu
  // halaman, sehingga tombol yang ditekan berulang cepat tidak menumpuk
  // halaman berlapis (double-push). Jeda dibuat singkat agar membuka halaman
  // lain dari dalam halaman yang baru dibuka (misalnya tombol Pindai Tiket di
  // halaman detail perjalanan) tetap berfungsi normal.
  Future<T?> _pushPage<T>(Route<T> route) async {
    final now = DateTime.now();
    final lastPush = _lastPagePushedAt;
    if (lastPush != null &&
        now.difference(lastPush) < const Duration(milliseconds: 600)) {
      return null;
    }

    _lastPagePushedAt = now;
    return Navigator.of(context).push<T>(route);
  }

  Future<void> _openScheduleDetail(TravSSchedule schedule) async {
    final result = await _pushPage<Object>(
      MaterialPageRoute<Object>(
        builder: (_) => TravSScheduleDetailPage(
          schedule: schedule,
          selectedRole: _role,
          session: widget.session,
          appSettings: _appSettings,
          onRequireAuthentication: widget.onOpenAuth,
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

    if (result == 'open_driver_manifest') {
      // ID jadwal yang sama dipakai untuk memuat manifes perjalanan pengemudi.
      await _openDriverJourneyById(schedule.id);
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

    final shouldRefresh = await _pushPage<bool>(
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

    final shouldRefresh = await _pushPage<bool>(
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
    final updatedSession = await _pushPage<TravSUserSession>(
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
    return _pushPage<void>(
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

  Future<DateTimeRange?> _pickDateRange({
    DateTime? startDate,
    DateTime? endDate,
  }) {
    final picker = widget.dateRangePicker;
    if (picker != null) {
      return picker(context, startDate, endDate);
    }

    final now = DateTime.now();
    return showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year - 5),
      lastDate: DateTime(now.year + 2),
      initialDateRange: startDate == null || endDate == null
          ? null
          : DateTimeRange(start: startDate, end: endDate),
    );
  }

  Future<void> _pickDriverHistoryRange() async {
    final selected = await _pickDateRange(
      startDate: _appliedDriverHistoryStartDate,
      endDate: _appliedDriverHistoryEndDate,
    );

    if (selected == null || !mounted) return;

    setState(() {
      _appliedDriverHistoryStartDate = DateUtils.dateOnly(selected.start);
      _appliedDriverHistoryEndDate = DateUtils.dateOnly(selected.end);
    });
  }

  Future<void> _pickOldBookingRange() async {
    final selected = await _pickDateRange(
      startDate: _oldBookingStartDate,
      endDate: _oldBookingEndDate,
    );

    if (selected == null || !mounted) return;

    setState(() {
      _oldBookingStartDate = DateUtils.dateOnly(selected.start);
      _oldBookingEndDate = DateUtils.dateOnly(selected.end);
    });
  }

  void _resetDriverHistoryFilter() {
    setState(() {
      _appliedDriverHistoryStartDate = null;
      _appliedDriverHistoryEndDate = null;
    });
  }

  void _resetOldBookingFilter() {
    setState(() {
      _oldBookingStartDate = null;
      _oldBookingEndDate = null;
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

  List<TravSBookingResult> _filterOldBookings(
    Iterable<TravSBookingResult> bookings,
  ) {
    final start = _oldBookingStartDate;
    final end = _oldBookingEndDate;

    return bookings.where((booking) {
      final departure = _parseLocalDateTime(booking.waktuBerangkat);
      if (departure == null) return start == null && end == null;

      final date = DateUtils.dateOnly(departure);
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
    // Cegah tombol notifikasi dispam saat data notifikasi masih dimuat, agar
    // halaman notifikasi tidak terbuka berlapis-lapis.
    if (_isOpeningNotifications) {
      return;
    }

    _isOpeningNotifications = true;
    try {
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

      final selectedEntry = await Navigator.of(context)
          .push<_NotificationEntry>(
            MaterialPageRoute<_NotificationEntry>(
              builder: (_) => _NotificationPage(
                title: 'Notifikasi',
                entries: _buildApiNotifications(feed.items, bundle),
                notificationsEnabled: _notificationsEnabled,
                showPermissionSettings: false,
                onToggleNotifications: _setNotificationsEnabled,
                onClearHistory: (period) =>
                    _clearNotificationHistory(period, bundle),
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
    } finally {
      _isOpeningNotifications = false;
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

    if (_role == AppRolePreview.passenger ||
        _role == AppRolePreview.counterStaff) {
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
    _HomeBundle bundle,
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
      return _buildApiNotifications(refreshed.items, bundle);
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
    await _pushPage<void>(
      MaterialPageRoute<void>(
        builder: (_) => _DriverJourneyDetailPage(
          record: record,
          expenseCategories: _appSettings.operationalExpenseCategories,
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

    await _pushPage<void>(
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
    _HomeBundle bundle,
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
        message: _notificationDisplayMessage(notification, bundle),
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

  String _notificationDisplayMessage(
    TravSNotification notification,
    _HomeBundle bundle,
  ) {
    final referenceId = notification.referensiId;
    if (referenceId == null) return notification.pesan;

    final booking = bundle.passengerBookings
        ?.where((item) => item.id == referenceId)
        .firstOrNull;
    if (booking != null && notification.pesan.contains(booking.kodeBooking)) {
      final departureLabel =
          'jadwal keberangkatan ${_formatDateTime(booking.waktuBerangkat)}';
      final withoutBookingPhrase = notification.pesan.replaceAll(
        RegExp(
          'pemesanan\\s+${RegExp.escape(booking.kodeBooking)}',
          caseSensitive: false,
        ),
        departureLabel,
      );
      return withoutBookingPhrase.replaceAll(
        booking.kodeBooking,
        departureLabel,
      );
    }

    return notification.pesan;
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
      case AppRolePreview.counterStaff:
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
    final isCounterStaff = _role == AppRolePreview.counterStaff;
    final counterDashboard = bundle.counterDashboard;
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
        .where(
          (booking) =>
              booking.status == 'menunggu_pembayaran' &&
              (booking.payment.canChoose ||
                  booking.payment.status == 'menunggu'),
        )
        .toList();
    // Jadwal penuh tidak ditawarkan lagi pada area publik penumpang.
    final availableSchedules = _availablePublicSchedules(
      bundle.payload.featuredSchedules,
    );
    final routes = _buildRouteBookmarks(availableSchedules);
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
          subtitle: isCounterStaff ? 'Mode Petugas Loket' : null,
          trailing: _notificationButton(bundle),
        ),
        const SizedBox(height: 16),
        _PassengerDashboardHero(
          name: _displayName,
          profileImageUrl: TravSApiService.resolveMediaUrl(
            widget.session?.user.fotoProfil,
          ),
          banners: bundle.payload.banners,
          subtitle: isCounterStaff
              ? 'Bantu calon penumpang membuat pemesanan di loket.'
              : 'Mau pergi ke mana hari ini?',
          stats: [
            _HeroStat(
              label: isCounterStaff ? 'Pemesanan loket' : 'Pemesanan aktif',
              value: isCounterStaff
                  ? '${counterDashboard?['total_pemesanan'] ?? bookings.length}'
                  : '${dashboard.bookingAktif}',
            ),
            _HeroStat(
              label: isCounterStaff ? 'Menunggu bayar' : 'Tiket aktif',
              value: isCounterStaff
                  ? '${counterDashboard?['menunggu_pembayaran'] ?? waitingPayment.length}'
                  : '$activeTicketCount',
            ),
            _HeroStat(
              label: isCounterStaff ? 'Tiket terbit' : 'Total pemesanan',
              value: isCounterStaff
                  ? '${counterDashboard?['terbayar'] ?? activeTicketCount}'
                  : '${dashboard.totalBooking}',
            ),
          ],
        ),
        if (bundle.payload.importantInformation.isNotEmpty) ...[
          const SizedBox(height: 16),
          _ImportantInformationSection(
            items: bundle.payload.importantInformation,
            onShowMore: _openPassengerInfoPage,
          ),
        ],
        const SizedBox(height: 18),
        if (!hasHomeTripContent) ...[
          _EmptyStateCard(
            title: 'Tidak ada perjalanan yang tersedia saat ini.',
            subtitle:
                'Jadwal perjalanan baru belum tersedia. Silakan periksa kembali nanti.',
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
            title: isCounterStaff
                ? 'Pembayaran loket menunggu'
                : 'Pembayaran menunggu',
            description:
                '${waitingPayment.first.rute} - ${_formatShortDate(waitingPayment.first.waktuBerangkat)}',
            actionLabel: 'Bayar',
            icon: Icons.account_balance_wallet_rounded,
            onTap: () => _openBookingDetail(waitingPayment.first.id),
          ),
          const SizedBox(height: 18),
        ],
        if (bookings.isEmpty && routes.isNotEmpty)
          _EmptyStateCard(
            title: isCounterStaff
                ? 'Belum ada pemesanan loket.'
                : 'Belum ada pemesanan.',
            subtitle: isCounterStaff
                ? 'Pilih rute dan jadwal untuk membantu calon penumpang.'
                : 'Mulai dari menu rute dan jadwal untuk membuat pemesanan pertama.',
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
      ctaLabel: 'Pesan',
      routeTrailing: IconButton(
        onPressed: () => _openNotifications(bundle),
        icon: const Icon(Icons.notifications_rounded),
      ),
    );
  }

  Widget _buildPassengerOrders(_HomeBundle bundle) {
    final isCounterStaff = _role == AppRolePreview.counterStaff;
    final bookings = bundle.passengerBookings ?? const [];
    final latestBookings = bookings
        .where((item) => !_isOldBooking(item))
        .toList();
    final oldBookings = bookings.where(_isOldBooking).toList();
    final filteredOldBookings = _filterOldBookings(oldBookings);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          leading: _backToHomeButton(),
          title: isCounterStaff ? 'Pemesanan Loket' : 'Daftar Pemesanan',
          trailing: _notificationButton(bundle),
        ),
        const SizedBox(height: 16),
        if (bookings.isEmpty)
          _EmptyStateCard(
            title: isCounterStaff
                ? 'Belum ada pemesanan loket.'
                : 'Belum ada riwayat pemesanan.',
            subtitle:
                'Setelah pemesanan pertama dibuat, daftar pemesanan akan muncul di halaman ini.',
          )
        else ...[
          const _SectionHeader(
            eyebrow: 'Pemesanan',
            title: 'Pemesanan terbaru',
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
            ),
            const SizedBox(height: 12),
            _DateRangeFilterChips(
              startDate: _oldBookingStartDate,
              endDate: _oldBookingEndDate,
              onSelectRange: _pickOldBookingRange,
              onReset: _resetOldBookingFilter,
            ),
            const SizedBox(height: 12),
            if (filteredOldBookings.isEmpty)
              const _EmptyStateCard(
                title: 'Pemesanan lama tidak ditemukan.',
                subtitle:
                    'Coba pilih rentang tanggal lain atau tampilkan semua.',
              )
            else
              ...filteredOldBookings.map(
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
    final isCounterStaff = _role == AppRolePreview.counterStaff;
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
          title: isCounterStaff ? 'Tiket Loket' : 'Daftar Tiket',
          trailing: IconButton(
            onPressed: () => _openNotifications(bundle),
            icon: const Icon(Icons.notifications_rounded),
          ),
        ),
        const SizedBox(height: 16),
        const _SectionHeader(eyebrow: 'Tiket', title: 'Tiket aktif'),
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
          title: _role == AppRolePreview.counterStaff
              ? 'Profil Petugas'
              : 'Profil',
          subtitle: _role == AppRolePreview.counterStaff
              ? 'Mode Petugas Loket'
              : null,
        ),
        const SizedBox(height: 16),
        _ProfileMainCard(
          profile: _ProfileSummaryContent(session: session),
          actions: [
            _ProfileMenuAction(
              icon: Icons.edit_outlined,
              title: 'Edit Profil',
              onTap: () => _openEditProfile(session),
            ),
            _ProfileMenuAction(
              icon: Icons.info_rounded,
              title: 'Info',
              onTap: _openPassengerInfoPage,
            ),
          ],
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
          title: bundle.appSettings.appName,
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
            _HeroStat(label: 'Perjalanan', value: '${records.length}'),
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
            actionLabel: 'Lihat Perjalanan',
            icon: Icons.assignment_rounded,
            onTap: () => _openDriverJourney(nextTrip),
          ),
        if (nextTrip != null) const SizedBox(height: 16),
        const _SectionHeader(eyebrow: 'Perjalanan', title: 'Perjalanan aktif'),
        const SizedBox(height: 12),
        if (records.isEmpty)
          const _EmptyStateCard(
            title: 'Tidak ada perjalanan yang tersedia hari ini.',
            subtitle:
                'Perjalanan aktif yang ditugaskan kepada Anda akan muncul di sini.',
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
          title: 'Perjalanan',
          trailing: _notificationButton(bundle),
        ),
        const SizedBox(height: 16),
        if (records.isEmpty)
          const _EmptyStateCard(
            title: 'Belum ada perjalanan aktif.',
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
        _PageTopBar(leading: _backToHomeButton(), title: 'Riwayat Perjalanan'),
        const SizedBox(height: 16),
        _DateRangeFilterChips(
          startDate: _appliedDriverHistoryStartDate,
          endDate: _appliedDriverHistoryEndDate,
          onSelectRange: _pickDriverHistoryRange,
          onReset: _resetDriverHistoryFilter,
        ),
        const SizedBox(height: 14),
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
        _PageTopBar(leading: _backToHomeButton(), title: 'Profil'),
        const SizedBox(height: 16),
        _DriverProfileWorkCard(
          session: session,
          onEditProfile: () => _openEditProfile(session),
          onOpenGuide: () => _pushPage<void>(
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
    // Tamu hanya melihat jadwal yang masih dapat dipesan.
    final availableSchedules = _availablePublicSchedules(
      bundle.payload.featuredSchedules,
    );

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
      children: [
        _PageTopBar(
          title: bundle.appSettings.appName,
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
          subtitle: null,
          stats: const [],
        ),
        if (bundle.payload.importantInformation.isNotEmpty) ...[
          const SizedBox(height: 16),
          _ImportantInformationSection(
            items: bundle.payload.importantInformation,
            onShowMore: () => setState(() => _selectedTabIndex = 2),
          ),
        ],
        if (availableSchedules.isEmpty) ...[
          const SizedBox(height: 16),
          const _EmptyStateCard(
            title: 'Tidak ada perjalanan yang tersedia saat ini.',
            subtitle:
                'Belum ada jadwal perjalanan yang dapat dipesan. Silakan periksa kembali nanti.',
          ),
        ],
        if (availableSchedules.isNotEmpty) ...[
          const SizedBox(height: 18),
          const _SectionHeader(
            eyebrow: 'Jadwal populer',
            title: 'Rute siap dicek',
          ),
          const SizedBox(height: 12),
          ...availableSchedules
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
      ctaLabel: 'Detail',
      routeTrailing: IconButton(
        onPressed: widget.onOpenAuth,
        icon: const Icon(Icons.login_rounded),
      ),
    );
  }

  Widget _buildRouteBookmarkFlow({
    required _HomeBundle bundle,
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
            trailing: routeTrailing,
          ),
          const SizedBox(height: 18),
          if (routes.isEmpty)
            const _EmptyStateCard(
              title: 'Belum ada rute tersedia.',
              subtitle:
                  'Rute akan tersedia ketika jadwal perjalanan baru telah dibuka.',
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
          trailing: routeTrailing,
        ),
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
              label: _selectedScheduleDate == null
                  ? 'Pilih Tanggal'
                  : DateFormat(
                      'dd MMM yyyy',
                      'id_ID',
                    ).format(_selectedScheduleDate!),
              icon: Icons.calendar_month_rounded,
              selected: _selectedScheduleFilter == _ScheduleDateFilter.byDate,
              onTap: _pickScheduleDate,
            ),
          ],
        ),
        const SizedBox(height: 16),
        if (filteredSchedules.isEmpty)
          const _EmptyStateCard(
            title: 'Jadwal belum tersedia.',
            subtitle:
                'Coba pilih rute lain atau tarik halaman untuk memuat ulang.',
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
        _PageTopBar(leading: _backToHomeButton(), title: 'Info Pemesanan'),
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
        _PageTopBar(leading: _backToHomeButton(), title: 'Akun'),
        const SizedBox(height: 16),
        _GuestProfileCard(
          onOpenAuth: widget.onOpenAuth,
          onOpenInfo: () {
            setState(() {
              _selectedTabIndex = 2;
            });
          },
        ),
      ],
    );
  }

  List<_NavItem> _navItemsForRole(AppRolePreview role) {
    switch (role) {
      case AppRolePreview.driver:
        return const [
          _NavItem(
            label: 'Beranda',
            icon: Icons.home_rounded,
            selectedIcon: Icons.home_rounded,
          ),
          _NavItem(
            label: 'Perjalanan',
            icon: Icons.assignment_rounded,
            selectedIcon: Icons.assignment_rounded,
          ),
          _NavItem(
            label: 'Riwayat',
            icon: Icons.history_rounded,
            selectedIcon: Icons.history_rounded,
          ),
          _NavItem(
            label: 'Profil',
            icon: Icons.person_rounded,
            selectedIcon: Icons.person_rounded,
          ),
        ];
      case AppRolePreview.passenger:
      case AppRolePreview.counterStaff:
        return const [
          _NavItem(
            label: 'Beranda',
            icon: Icons.home_rounded,
            selectedIcon: Icons.home_rounded,
          ),
          _NavItem(
            label: 'Rute',
            icon: Icons.route_rounded,
            selectedIcon: Icons.route_rounded,
          ),
          _NavItem(
            label: 'Pesanan',
            icon: Icons.receipt_long_rounded,
            selectedIcon: Icons.receipt_long_rounded,
          ),
          _NavItem(
            label: 'Tiket',
            icon: Icons.confirmation_number_rounded,
            selectedIcon: Icons.confirmation_number_rounded,
          ),
          _NavItem(
            label: 'Profil',
            icon: Icons.person_rounded,
            selectedIcon: Icons.person_rounded,
          ),
        ];
      case AppRolePreview.guest:
        return const [
          _NavItem(
            label: 'Beranda',
            icon: Icons.home_rounded,
            selectedIcon: Icons.home_rounded,
          ),
          _NavItem(
            label: 'Rute',
            icon: Icons.route_rounded,
            selectedIcon: Icons.route_rounded,
          ),
          _NavItem(
            label: 'Info',
            icon: Icons.info_rounded,
            selectedIcon: Icons.info_rounded,
          ),
          _NavItem(
            label: 'Akun',
            icon: Icons.person_rounded,
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
