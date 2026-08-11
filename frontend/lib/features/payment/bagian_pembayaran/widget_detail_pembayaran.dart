part of '../travs_payment_preview_page.dart';

class _CompactTravelRow extends StatelessWidget {
  const _CompactTravelRow({required this.icon, required this.value});

  final IconData icon;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 21, color: TravSTheme.navyMuted),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
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
            flex: 5,
            child: Text(
              label,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: emphasize ? TravSTheme.navy : TravSTheme.navyMuted,
                fontWeight: emphasize ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
          ),
          const SizedBox(width: 20),
          Expanded(
            flex: 5,
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: emphasize ? FontWeight.w800 : FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PaymentDataError extends StatelessWidget {
  const _PaymentDataError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        message,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          color: TravSTheme.orange,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _MessageBanner extends StatelessWidget {
  const _MessageBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F1),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFF3B6B6)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.only(top: 2),
            child: Icon(Icons.error_outline_rounded, color: TravSTheme.danger),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: TravSTheme.danger,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PaymentStatusCheckBanner extends StatelessWidget {
  const _PaymentStatusCheckBanner({required this.feedback});

  final _PaymentCheckFeedback feedback;

  @override
  Widget build(BuildContext context) {
    final (background, border, foreground, icon) = switch (feedback.tone) {
      _PaymentCheckFeedbackTone.success => (
        const Color(0xFFEAF8F1),
        const Color(0xFFBCE5D2),
        TravSTheme.success,
        Icons.check_circle_outline_rounded,
      ),
      _PaymentCheckFeedbackTone.warning => (
        const Color(0xFFFFF7E8),
        const Color(0xFFF2D59D),
        const Color(0xFF9A6200),
        Icons.info_outline_rounded,
      ),
      _PaymentCheckFeedbackTone.error => (
        const Color(0xFFFFF1F1),
        const Color(0xFFF3B6B6),
        TravSTheme.danger,
        Icons.error_outline_rounded,
      ),
      _PaymentCheckFeedbackTone.info => (
        const Color(0xFFEEF5FF),
        const Color(0xFFC8D9EE),
        TravSTheme.navySoft,
        Icons.sync_rounded,
      ),
    };

    return Container(
      key: const Key('payment-status-feedback'),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: foreground, size: 22),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              feedback.message,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: foreground,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
