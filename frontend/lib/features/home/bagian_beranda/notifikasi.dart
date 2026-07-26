part of '../travs_home_page.dart';

class _NotificationPage extends StatefulWidget {
  const _NotificationPage({
    required this.title,
    required this.entries,
    required this.notificationsEnabled,
    required this.showPermissionSettings,
    required this.onToggleNotifications,
    required this.onClearHistory,
  });

  final String title;
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
        content: const Text('Pilih notifikasi yang ingin dihapus.'),
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
      appBar: AppBar(
        leading: IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: Text(widget.title),
      ),
      body: Container(
        decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
          children: [
            _SectionHeader(title: widget.title),
            const SizedBox(height: 14),
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
              child: FilledButton.icon(
                onPressed: _isClearingHistory ? null : _showClearHistoryDialog,
                style: FilledButton.styleFrom(
                  backgroundColor: TravSTheme.navy,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                icon: _isClearingHistory
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.delete_sweep_outlined),
                label: const Text('Hapus Notifikasi'),
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
              Icons.notifications_rounded,
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
                    Wrap(
                      spacing: 12,
                      runSpacing: 8,
                      alignment: WrapAlignment.spaceBetween,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        _StatusPill(
                          label: entry.category,
                          color: toneColor,
                          textColor: toneColor,
                        ),
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
