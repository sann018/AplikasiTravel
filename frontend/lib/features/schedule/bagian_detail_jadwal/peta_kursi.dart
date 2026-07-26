part of '../travs_schedule_detail_page.dart';

class _SeatMapCard extends StatelessWidget {
  const _SeatMapCard({
    required this.detail,
    required this.selectedSeatNumbers,
    required this.maxSeatCount,
    required this.canSelectSeats,
    required this.lockMessage,
    required this.onLockedTap,
    required this.onSeatTap,
  });

  final TravSScheduleDetail detail;
  final Set<int> selectedSeatNumbers;
  final int maxSeatCount;
  final bool canSelectSeats;
  final String lockMessage;
  final VoidCallback onLockedTap;
  final ValueChanged<TravSSeatItem> onSeatTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Pilih Kursi',
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                if (!canSelectSeats) const _BookingLockBadge(label: 'Terkunci'),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              canSelectSeats
                  ? 'Pilih kursi langsung dari denah mobil. Kursi akan dikunci saat lanjut ke konfirmasi pemesanan.'
                  : lockMessage,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 16),
            const _SeatLegend(),
            const SizedBox(height: 18),
            _VehicleSeatDeck(
              seats: detail.seatMap,
              seatLayout: detail.seatLayout,
              selectedSeatNumbers: selectedSeatNumbers,
              canSelectSeats: canSelectSeats,
              onLockedTap: onLockedTap,
              onSeatTap: onSeatTap,
            ),
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: TravSTheme.surfaceSoft,
                borderRadius: BorderRadius.circular(22),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: _SeatSummaryItem(
                      label: 'Dipilih',
                      value: '${selectedSeatNumbers.length}/$maxSeatCount',
                    ),
                  ),
                  Expanded(
                    child: _SeatSummaryItem(
                      label: 'Tersedia',
                      value: '${detail.schedule.sisaKursi}',
                    ),
                  ),
                  Expanded(
                    child: _SeatSummaryItem(
                      label: 'Terkunci',
                      value: '${detail.lockedSeatCount}',
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

class _CheckoutPreviewCard extends StatelessWidget {
  const _CheckoutPreviewCard({
    required this.estimate,
    required this.selectedSeatNumbers,
    required this.maxSeatCount,
    required this.isSoldOut,
    required this.canEditBooking,
    required this.lockMessage,
    required this.isPreparingCheckout,
    required this.onClearSeatSelection,
    required this.onPrimaryAction,
  });

  final TravSCheckoutDraft estimate;
  final List<int> selectedSeatNumbers;
  final int maxSeatCount;
  final bool isSoldOut;
  final bool canEditBooking;
  final String lockMessage;
  final bool isPreparingCheckout;
  final VoidCallback onClearSeatSelection;
  final VoidCallback onPrimaryAction;

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
              'Ringkasan Pemesanan',
              style: theme.textTheme.titleLarge?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              canEditBooking
                  ? 'Pilih kursi dulu, lalu isi data penumpang, pickup, dan metode pembayaran di langkah berikutnya.'
                  : 'Bagian pemesanan dikunci agar kursi tidak terpilih oleh pengguna yang belum login sebagai penumpang.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 18),
            if (!canEditBooking) ...[
              _LockedBookingNotice(message: lockMessage),
              const SizedBox(height: 18),
            ],
            Container(
              key: const Key('seat-choice-summary'),
              width: double.infinity,
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: TravSTheme.surfaceSoft,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Pilihan kursi',
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Maksimal $maxSeatCount kursi per transaksi.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navyMuted,
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (selectedSeatNumbers.isEmpty)
                    Text(
                      'Belum ada kursi yang dipilih.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: TravSTheme.navyMuted,
                        fontWeight: FontWeight.w700,
                      ),
                    )
                  else
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: selectedSeatNumbers
                              .map(
                                (seatNumber) =>
                                    Chip(label: Text('Kursi A$seatNumber')),
                              )
                              .toList(),
                        ),
                        const SizedBox(height: 10),
                        TextButton.icon(
                          onPressed: canEditBooking
                              ? onClearSeatSelection
                              : null,
                          icon: const Icon(Icons.restart_alt_rounded),
                          label: const Text('Reset pilihan kursi'),
                        ),
                      ],
                    ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            Container(
              key: const Key('ticket-price-summary'),
              width: double.infinity,
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: TravSTheme.surfaceSoft,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Column(
                children: [
                  _PriceRow(
                    label: 'Subtotal tiket',
                    value: 'Rp${_formatCurrency(estimate.ticketSubtotal)}',
                  ),
                  const Divider(height: 24),
                  _PriceRow(
                    label: 'Estimasi total',
                    value: 'Rp${_formatCurrency(estimate.total)}',
                    emphasize: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: isSoldOut || isPreparingCheckout
                    ? null
                    : onPrimaryAction,
                icon: Icon(
                  !canEditBooking
                      ? Icons.login_rounded
                      : isPreparingCheckout
                      ? Icons.sync_rounded
                      : Icons.receipt_long_rounded,
                ),
                label: Text(
                  isSoldOut
                      ? 'Jadwal penuh'
                      : isPreparingCheckout
                      ? 'Mengunci Kursi...'
                      : !canEditBooking
                      ? 'Masuk untuk Memesan'
                      : 'Lanjut ke Konfirmasi',
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
