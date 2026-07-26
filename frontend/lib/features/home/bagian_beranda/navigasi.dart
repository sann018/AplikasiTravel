part of '../travs_home_page.dart';

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
    this.subtitle,
    this.leading,
    this.trailing,
  });

  final String title;
  final String? subtitle;
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
              if (subtitle != null) ...[
                const SizedBox(height: 4),
                Text(
                  subtitle!,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
              ],
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
          icon: const Icon(Icons.notifications_rounded),
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
  const _SectionHeader({this.eyebrow, required this.title});

  final String? eyebrow;
  final String title;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (eyebrow != null) ...[
          Text(
            eyebrow!.toUpperCase(),
            style: theme.textTheme.labelMedium?.copyWith(
              color: TravSTheme.orange,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.8,
            ),
          ),
          const SizedBox(height: 6),
        ],
        Text(
          title,
          style: theme.textTheme.titleMedium?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}
