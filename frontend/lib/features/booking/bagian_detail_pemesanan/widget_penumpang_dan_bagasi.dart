part of '../travs_booking_detail_page.dart';

class _PassengerCard extends StatelessWidget {
  const _PassengerCard({required this.passenger});

  final TravSBookingPassengerInfo passenger;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Kursi A${passenger.seatNumber}',
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              _StatusBadge(
                label: _humanizeStatus(passenger.statusKehadiran),
                background: Colors.white,
                foreground: TravSTheme.navy,
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            passenger.namaPenumpang,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            passenger.noHpPenumpang,
            style: theme.textTheme.bodySmall?.copyWith(
              color: TravSTheme.navyMuted,
            ),
          ),
        ],
      ),
    );
  }
}

class _BaggageCard extends StatelessWidget {
  const _BaggageCard({required this.item});

  final TravSBookingBaggageInfo item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final baggagePhotoUrls = _uniquePhotoUrls(
      item.fotoUrls.map(TravSApiService.resolveMediaUrl).whereType<String>(),
    ).take(3).toList();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            item.deskripsiItem,
            style: theme.textTheme.titleSmall?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _MiniBadge(
                label: item.posisiBarang == 'dipegang_sendiri'
                    ? 'Dipegang sendiri'
                    : 'Masuk bagasi',
              ),
            ],
          ),
          if (baggagePhotoUrls.isNotEmpty) ...[
            const SizedBox(height: 14),
            GridView.builder(
              itemCount: baggagePhotoUrls.length,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                crossAxisSpacing: 8,
                mainAxisSpacing: 8,
              ),
              itemBuilder: (context, index) {
                final photoUrl = baggagePhotoUrls[index];
                return GestureDetector(
                  onTap: () => _showPhotoPreview(context, photoUrl),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(14),
                    child: Image.network(
                      photoUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) {
                        return Container(
                          color: Colors.white,
                          alignment: Alignment.center,
                          child: Text(
                            'Foto tidak tersedia',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: TravSTheme.navyMuted,
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                );
              },
            ),
          ] else ...[
            const SizedBox(height: 14),
            Text(
              'Foto barang belum tersedia.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
