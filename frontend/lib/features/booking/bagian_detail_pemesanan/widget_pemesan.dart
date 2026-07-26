part of '../travs_booking_detail_page.dart';

class _RequesterDetails extends StatelessWidget {
  const _RequesterDetails({required this.requester});

  final TravSBookingRequesterInfo requester;

  @override
  Widget build(BuildContext context) {
    final imageUrl = TravSApiService.resolveMediaUrl(requester.fotoUrl);

    return Row(
      children: [
        _RequesterAvatar(name: requester.nama, imageUrl: imageUrl),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                requester.nama,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                requester.email,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: TravSTheme.navyMuted),
              ),
              const SizedBox(height: 2),
              Text(
                requester.noHp,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: TravSTheme.navyMuted),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _RequesterAvatar extends StatelessWidget {
  const _RequesterAvatar({required this.name, this.imageUrl});

  final String name;
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty
        ? 'P'
        : name.trim().substring(0, 1).toUpperCase();

    return ClipOval(
      child: Container(
        width: 54,
        height: 54,
        color: TravSTheme.navy,
        child: imageUrl == null
            ? _RequesterInitial(initial: initial)
            : Image.network(
                imageUrl!,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) =>
                    _RequesterInitial(initial: initial),
              ),
      ),
    );
  }
}

class _RequesterInitial extends StatelessWidget {
  const _RequesterInitial({required this.initial});

  final String initial;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Text(
        initial,
        style: Theme.of(context).textTheme.titleLarge?.copyWith(
          color: Colors.white,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}
