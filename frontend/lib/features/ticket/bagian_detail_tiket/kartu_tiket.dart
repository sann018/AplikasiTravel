part of '../travs_ticket_detail_page.dart';

class _EmptyTicketCard extends StatelessWidget {
  const _EmptyTicketCard({required this.booking});

  final TravSBookingResult booking;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          children: [
            const Icon(
              Icons.qr_code_2_outlined,
              color: TravSTheme.navyMuted,
              size: 44,
            ),
            const SizedBox(height: 14),
            Text(
              booking.isPaid
                  ? 'Tiket belum diterbitkan untuk pemesanan ini.'
                  : 'QR tiket belum aktif karena pembayaran belum terverifikasi.',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              booking.isPaid
                  ? 'Refresh kembali beberapa saat lagi atau cek detail pemesanan untuk memastikan tiket telah diterbitkan.'
                  : 'Begitu status pembayaran berubah menjadi berhasil, QR unik akan muncul untuk setiap kursi penumpang.',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: TravSTheme.navyMuted),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}

class _TicketCard extends StatelessWidget {
  const _TicketCard({required this.booking, required this.passenger});

  final TravSBookingResult booking;
  final TravSBookingPassengerInfo passenger;

  @override
  Widget build(BuildContext context) {
    final ticket = passenger.ticket!;
    final qrData = ticket.token.isNotEmpty ? ticket.token : ticket.kodeQr;
    final driver = booking.pengemudi;
    final vehicle = booking.kendaraan;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    passenger.namaPenumpang,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: ticket.sudahDipindai
                        ? const Color(0xFFE8EDF5)
                        : TravSTheme.orangeSoft,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    ticket.sudahDipindai ? 'Sudah Dipindai' : 'Belum Dipindai',
                    style: TextStyle(
                      color: ticket.sudahDipindai
                          ? TravSTheme.navyMuted
                          : TravSTheme.orange,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Kursi A${passenger.seatNumber} - ${booking.rute}',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: TravSTheme.navyMuted),
            ),
            const SizedBox(height: 20),
            LayoutBuilder(
              builder: (context, constraints) {
                final qrBox = _TicketQrBox(data: qrData);
                final infoPanel = _TicketTravelInfoPanel(
                  booking: booking,
                  passenger: passenger,
                  driver: driver,
                  vehicle: vehicle,
                );

                if (constraints.maxWidth >= 720) {
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(child: Center(child: qrBox)),
                      const SizedBox(width: 20),
                      Expanded(child: infoPanel),
                    ],
                  );
                }

                return Column(
                  children: [
                    Center(child: qrBox),
                    const SizedBox(height: 18),
                    infoPanel,
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
