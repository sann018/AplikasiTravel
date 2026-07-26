part of '../travs_payment_preview_page.dart';

class _CheckoutFormView extends StatelessWidget {
  const _CheckoutFormView({
    required this.formKey,
    required this.header,
    required this.locationCard,
    required this.passengerCard,
    required this.baggageCard,
    required this.summaryCard,
    required this.errorMessage,
    required this.isSubmitting,
    required this.onSubmit,
  });

  final GlobalKey<FormState> formKey;
  final Widget header;
  final Widget locationCard;
  final Widget passengerCard;
  final Widget baggageCard;
  final Widget summaryCard;
  final String? errorMessage;
  final bool isSubmitting;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        children: [
          header,
          const SizedBox(height: 16),
          locationCard,
          const SizedBox(height: 16),
          passengerCard,
          const SizedBox(height: 16),
          baggageCard,
          const SizedBox(height: 16),
          summaryCard,
          if (errorMessage != null) ...[
            const SizedBox(height: 16),
            _MessageBanner(message: errorMessage!),
          ],
          const SizedBox(height: 18),
          ElevatedButton.icon(
            onPressed: isSubmitting ? null : onSubmit,
            icon: Icon(
              isSubmitting ? Icons.sync_rounded : Icons.lock_clock_outlined,
            ),
            label: Text(
              isSubmitting
                  ? 'Menyimpan Pemesanan...'
                  : 'Buat Pemesanan Sekarang',
            ),
          ),
        ],
      ),
    );
  }
}

class _ResponsiveCoordinateFields extends StatelessWidget {
  const _ResponsiveCoordinateFields({
    required this.first,
    required this.second,
  });

  final Widget first;
  final Widget second;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 380) {
          return Column(children: [first, const SizedBox(height: 12), second]);
        }

        return Row(
          children: [
            Expanded(child: first),
            const SizedBox(width: 12),
            Expanded(child: second),
          ],
        );
      },
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

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
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
            if (subtitle.trim().isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                subtitle,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
            ],
            const SizedBox(height: 18),
            child,
          ],
        ),
      ),
    );
  }
}
