part of '../travs_ticket_detail_page.dart';

class _TicketOwnerSelector extends StatelessWidget {
  const _TicketOwnerSelector({
    required this.passengers,
    required this.selectedIndex,
    required this.onSelected,
  });

  final List<TravSBookingPassengerInfo> passengers;
  final int selectedIndex;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 4),
          child: Text(
            'Nama Pemilik Tiket',
            style: TextStyle(
              color: TravSTheme.navy,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        const SizedBox(height: 10),
        LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              key: const Key('ticket-owner-selector'),
              scrollDirection: Axis.horizontal,
              child: ConstrainedBox(
                constraints: BoxConstraints(minWidth: constraints.maxWidth),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(passengers.length, (index) {
                    final passenger = passengers[index];
                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 5),
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(
                          minWidth: 132,
                          minHeight: 52,
                        ),
                        child: ChoiceChip(
                          label: Text(
                            passenger.namaPenumpang,
                            textAlign: TextAlign.center,
                          ),
                          labelPadding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 7,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                          selected: index == selectedIndex,
                          onSelected: (_) => onSelected(index),
                        ),
                      ),
                    );
                  }),
                ),
              ),
            );
          },
        ),
      ],
    );
  }
}
