part of '../travs_payment_preview_page.dart';

class _CountdownPanel extends StatefulWidget {
  const _CountdownPanel({
    required this.title,
    required this.deadlineIso,
    required this.icon,
    this.onDark = false,
    this.showStateLabel = true,
    this.maxDurationMinutes,
    this.onExpired,
  });

  final String title;
  final String deadlineIso;
  final IconData icon;
  final bool onDark;
  final bool showStateLabel;
  final int? maxDurationMinutes;
  final VoidCallback? onExpired;

  @override
  State<_CountdownPanel> createState() => _CountdownPanelState();
}

class _CountdownPanelState extends State<_CountdownPanel>
    with WidgetsBindingObserver {
  Timer? _timer;
  late DateTime? _effectiveDeadline;
  late Duration _remaining;
  bool _expiredCallbackSent = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _effectiveDeadline = _resolveEffectiveDeadline();
    _remaining = _calculateRemaining();
    _notifyExpiredIfNeeded();
    _startTimer();
  }

  @override
  void didUpdateWidget(covariant _CountdownPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.deadlineIso != widget.deadlineIso ||
        oldWidget.maxDurationMinutes != widget.maxDurationMinutes) {
      _expiredCallbackSent = false;
      _effectiveDeadline = _resolveEffectiveDeadline();
      _remaining = _calculateRemaining();
      _notifyExpiredIfNeeded();
      _startTimer();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Saat aplikasi kembali aktif, hitung lagi dari timestamp API.
      _updateRemaining();
      _startTimer();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  void _startTimer() {
    _timer?.cancel();
    if (_remaining == Duration.zero) return;

    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      _updateRemaining();
    });
  }

  void _updateRemaining() {
    if (!mounted) return;

    final latest = _calculateRemaining();
    setState(() {
      _remaining = latest;
    });
    if (latest == Duration.zero) {
      // Timer berhenti agar tidak terus membangun angka 00:00.
      _timer?.cancel();
    }
    _notifyExpiredIfNeeded();
  }

  Duration _calculateRemaining() {
    final deadline = _effectiveDeadline;
    if (deadline == null) {
      return Duration.zero;
    }

    final difference = deadline.difference(DateTime.now());
    return difference.isNegative ? Duration.zero : difference;
  }

  DateTime? _resolveEffectiveDeadline() {
    final parsedDeadline = DateTime.tryParse(widget.deadlineIso)?.toLocal();
    final maxMinutes = widget.maxDurationMinutes;
    if (parsedDeadline == null) {
      return null;
    }

    if (maxMinutes == null || maxMinutes <= 0) {
      return parsedDeadline;
    }

    final now = DateTime.now();
    final maxDeadline = now.add(Duration(minutes: maxMinutes));
    // Countdown memakai durasi dari Pengaturan Sistem agar tampilan mobile tidak
    // lebih panjang dari aturan admin jika nilai deadline lama/berbeda timezone terbawa.
    return parsedDeadline.isAfter(maxDeadline) ? maxDeadline : parsedDeadline;
  }

  void _notifyExpiredIfNeeded() {
    if (_expiredCallbackSent || _remaining != Duration.zero) {
      return;
    }

    _expiredCallbackSent = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        widget.onExpired?.call();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final background = widget.onDark
        ? Colors.white.withValues(alpha: 0.12)
        : TravSTheme.surfaceSoft;
    final foreground = widget.onDark ? Colors.white : TravSTheme.navy;
    final muted = widget.onDark
        ? Colors.white.withValues(alpha: 0.76)
        : TravSTheme.navyMuted;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(20),
        border: widget.onDark ? null : Border.all(color: TravSTheme.line),
      ),
      child: Row(
        children: [
          Icon(
            widget.icon,
            color: widget.onDark ? Colors.white : TravSTheme.orange,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.title,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: muted,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _formatDuration(_remaining),
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: foreground,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
          ),
          if (widget.showStateLabel)
            Text(
              _remaining == Duration.zero ? 'Berakhir' : 'Aktif',
              style: theme.textTheme.labelLarge?.copyWith(
                color: _remaining == Duration.zero
                    ? TravSTheme.danger
                    : (widget.onDark ? Colors.white : TravSTheme.success),
                fontWeight: FontWeight.w800,
              ),
            ),
        ],
      ),
    );
  }
}
