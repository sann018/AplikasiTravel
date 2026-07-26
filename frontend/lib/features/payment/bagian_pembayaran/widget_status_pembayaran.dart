part of '../travs_payment_preview_page.dart';

class _PaymentStatusBanner extends StatelessWidget {
  const _PaymentStatusBanner({
    required this.status,
    required this.isCashPayment,
    required this.deadlineIso,
    required this.onExpired,
  });

  final _PaymentDisplayStatus status;
  final bool isCashPayment;
  final String deadlineIso;
  final VoidCallback onExpired;

  @override
  Widget build(BuildContext context) {
    final isCashPending =
        status == _PaymentDisplayStatus.pending && isCashPayment;
    final (title, message, icon, colors) = isCashPending
        ? (
            'Pembayaran Tunai',
            'Bayarkan kepada pengemudi setelah dijemput dan sebelum naik kendaraan.',
            Icons.payments_rounded,
            const [TravSTheme.navy, TravSTheme.navySoft],
          )
        : switch (status) {
            _PaymentDisplayStatus.paid => (
              'Pembayaran Berhasil',
              'Transaksi telah dikonfirmasi. Tiket elektronik siap dibuka.',
              Icons.check_circle_rounded,
              const [Color(0xFF1F9D67), Color(0xFF176D4A)],
            ),
            _PaymentDisplayStatus.expired => (
              'Pembayaran Kedaluwarsa',
              'Batas waktu pembayaran telah berakhir.',
              Icons.timer_off_rounded,
              const [Color(0xFF9F2F38), Color(0xFF691E27)],
            ),
            _PaymentDisplayStatus.failed => (
              'Pembayaran Gagal',
              'Pembayaran tidak dapat diproses. Silakan periksa kembali detail pemesanan.',
              Icons.error_rounded,
              const [Color(0xFF9F2F38), Color(0xFF691E27)],
            ),
            _PaymentDisplayStatus.cancelled => (
              'Pembayaran Dibatalkan',
              'Pemesanan telah dibatalkan dan pembayaran tidak dapat dilanjutkan.',
              Icons.cancel_rounded,
              const [Color(0xFF7C3740), Color(0xFF51242A)],
            ),
            _PaymentDisplayStatus.pending => (
              'Menunggu Pembayaran',
              'Segera selesaikan pembayaran agar pemesanan tidak dibatalkan.',
              Icons.schedule_rounded,
              const [TravSTheme.navy, TravSTheme.navySoft],
            ),
          };

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: colors,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(28),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: Colors.white, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            message,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Colors.white.withValues(alpha: 0.9),
              height: 1.45,
            ),
          ),
          if (status == _PaymentDisplayStatus.pending &&
              !isCashPending &&
              deadlineIso.isNotEmpty) ...[
            const SizedBox(height: 16),
            _CountdownPanel(
              title: _formatPaymentDeadlineSentence(deadlineIso),
              deadlineIso: deadlineIso,
              icon: Icons.timer_outlined,
              onDark: true,
              showStateLabel: false,
              onExpired: onExpired,
            ),
          ],
        ],
      ),
    );
  }
}

class _CheckoutStatusBanner extends StatelessWidget {
  const _CheckoutStatusBanner({
    required this.deadlineIso,
    required this.maxDurationMinutes,
    required this.seatLabel,
    required this.onExpired,
  });

  final String deadlineIso;
  final int maxDurationMinutes;
  final String seatLabel;
  final VoidCallback onExpired;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: TravSTheme.headerGradient,
        borderRadius: BorderRadius.circular(32),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(
                Icons.edit_note_rounded,
                color: Colors.white,
                size: 30,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Lengkapi Data Pemesanan',
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Kursi $seatLabel sudah dikunci sementara. Lengkapi data sebelum waktu habis.',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: Colors.white.withValues(alpha: 0.9),
              height: 1.45,
            ),
          ),
          const SizedBox(height: 18),
          _CountdownPanel(
            title: _formatCheckoutDeadlineSentence(deadlineIso),
            deadlineIso: deadlineIso,
            maxDurationMinutes: maxDurationMinutes,
            icon: Icons.timer_outlined,
            onDark: true,
            showStateLabel: false,
            onExpired: onExpired,
          ),
        ],
      ),
    );
  }
}
