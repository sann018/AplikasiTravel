part of '../travs_schedule_detail_page.dart';

class _VehicleSeatDeck extends StatelessWidget {
  const _VehicleSeatDeck({
    required this.seats,
    required this.seatLayout,
    required this.selectedSeatNumbers,
    required this.canSelectSeats,
    required this.onLockedTap,
    required this.onSeatTap,
  });

  final List<TravSSeatItem> seats;

  /// Konfigurasi baris kursi dari admin (per kendaraan).
  /// Contoh: [1, 3, 2] = baris depan 1 kursi, tengah 3 kursi, belakang 2 kursi.
  final List<int> seatLayout;

  final Set<int> selectedSeatNumbers;
  final bool canSelectSeats;
  final VoidCallback onLockedTap;
  final ValueChanged<TravSSeatItem> onSeatTap;

  @override
  Widget build(BuildContext context) {
    final sortedSeats = seats.toList()
      ..sort((first, second) => first.seatNumber.compareTo(second.seatNumber));

    if (sortedSeats.isEmpty) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: TravSTheme.surfaceSoft,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: TravSTheme.line),
        ),
        child: Text(
          'Denah kursi belum tersedia untuk jadwal ini.',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: TravSTheme.navyMuted,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    final layout = _buildVehicleSeatLayout(sortedSeats, seatLayout);
    const deckAspectRatio = 2 / 3;

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 390),
        child: AspectRatio(
          aspectRatio: deckAspectRatio,
          child: LayoutBuilder(
            builder: (context, constraints) {
              final width = constraints.maxWidth;
              final height = constraints.maxHeight;
              final seatSize = width < 340 ? 46.0 : 52.0;

              return Stack(
                clipBehavior: Clip.none,
                children: [
                  Positioned.fill(
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(28),
                      ),
                    ),
                  ),

                  // Gambar mobil menjadi dasar denah, sedangkan tombol kursi
                  // tetap dibangun dari data backend agar fleksibel per armada.
                  Positioned.fill(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      child: Image.asset(
                        'assets/images/seat_taksi_top.png',
                        fit: BoxFit.contain,
                        errorBuilder: (context, error, stackTrace) {
                          return CustomPaint(painter: _VehicleCabinPainter());
                        },
                      ),
                    ),
                  ),
                  Positioned(
                    left: width * 0.62 - seatSize / 2,
                    top: height * 0.36 - seatSize / 2,
                    child: _VehicleSeatButton(
                      size: seatSize,
                      label: 'P',
                      status: 'driver',
                      selected: false,
                      enabled: false,
                      onTap: null,
                    ),
                  ),
                  for (final item in layout)
                    Positioned(
                      left: width * item.x - seatSize / 2,
                      top: height * item.y - seatSize / 2,
                      child: _VehicleSeatButton(
                        size: seatSize,
                        label: item.seat.seatNumber.toString().padLeft(2, '0'),
                        status: item.seat.status,
                        selected: selectedSeatNumbers.contains(
                          item.seat.seatNumber,
                        ),
                        enabled: item.seat.status == 'tersedia',
                        onTap: item.seat.status == 'tersedia'
                            ? () {
                                if (canSelectSeats) {
                                  onSeatTap(item.seat);
                                } else {
                                  onLockedTap();
                                }
                              }
                            : null,
                      ),
                    ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _VehicleSeatLayoutItem {
  const _VehicleSeatLayoutItem({
    required this.seat,
    required this.x,
    required this.y,
  });

  final TravSSeatItem seat;
  final double x;
  final double y;
}

/// Menyusun posisi tombol kursi berdasarkan pengaturan armada dari admin.
/// Jika belum ada pengaturan, susunan dibuat dari jumlah kursi yang tersedia.
List<_VehicleSeatLayoutItem> _buildVehicleSeatLayout(
  List<TravSSeatItem> seats,
  List<int> seatLayout,
) {
  final seatByNumber = <int, TravSSeatItem>{
    for (final seat in seats) seat.seatNumber: seat,
  };
  final maxSeatNumber = seats.isEmpty
      ? 0
      : seats.map((seat) => seat.seatNumber).reduce(math.max);
  final configuredSeatTotal = seatLayout.fold<int>(
    0,
    (total, rowSize) => total + rowSize,
  );
  final fixedSeatTotal = math.min(
    6,
    math.max(maxSeatNumber, configuredSeatTotal),
  );

  if (fixedSeatTotal > 0 && fixedSeatTotal <= 6) {
    return List<_VehicleSeatLayoutItem>.generate(fixedSeatTotal, (index) {
      final seatNumber = index + 1;
      final position = _fixedVehicleSeatPosition(seatNumber);

      return _VehicleSeatLayoutItem(
        seat:
            seatByNumber[seatNumber] ??
            TravSSeatItem(
              id: -seatNumber,
              seatNumber: seatNumber,
              status: 'nonaktif',
            ),
        x: position.dx,
        y: position.dy,
      );
    });
  }

  final rowPattern = seatLayout.isNotEmpty
      ? seatLayout
      : _seatRowPattern(seats.length);
  final rowCount = math.max(1, rowPattern.length);
  final items = <_VehicleSeatLayoutItem>[];
  var seatIndex = 0;

  for (var rowIndex = 0; rowIndex < rowPattern.length; rowIndex++) {
    final rowSize = rowPattern[rowIndex];
    final y = _seatYPosition(rowCount, rowIndex);

    for (var columnIndex = 0; columnIndex < rowSize; columnIndex++) {
      if (seatIndex >= seats.length) {
        break;
      }

      items.add(
        _VehicleSeatLayoutItem(
          seat: seats[seatIndex],
          x: _seatXPosition(rowSize, columnIndex, rowIndex),
          y: y,
        ),
      );
      seatIndex++;
    }
  }

  return items;
}

Offset _fixedVehicleSeatPosition(int seatNumber) {
  // Nomor kursi dibuat tetap mengikuti denah mobil agar kursi yang ditutup
  // tetap terlihat di posisi aslinya, hanya berubah warna menjadi terkunci.
  const positions = <int, Offset>{
    1: Offset(0.38, 0.38),
    2: Offset(0.70, 0.57),
    3: Offset(0.50, 0.57),
    4: Offset(0.30, 0.57),
    5: Offset(0.38, 0.74),
    6: Offset(0.62, 0.74),
  };

  return positions[seatNumber] ?? const Offset(0.50, 0.55);
}

List<int> _seatRowPattern(int count) {
  if (count <= 0) {
    return const [];
  }
  if (count == 1) {
    return const [1];
  }
  if (count == 2) {
    return const [1, 1];
  }
  if (count == 3) {
    return const [1, 2];
  }
  if (count == 4) {
    return const [2, 2];
  }
  if (count == 5) {
    return const [1, 2, 2];
  }
  if (count == 6) {
    return const [1, 3, 2];
  }
  if (count == 7) {
    return const [1, 3, 3];
  }

  final rows = <int>[];
  var remaining = count;
  while (remaining > 3) {
    rows.add(2);
    remaining -= 2;
  }
  if (remaining > 0) {
    rows.add(remaining);
  }
  return rows;
}

double _seatXPosition(int rowSize, int columnIndex, int rowIndex) {
  if (rowSize == 1) {
    return rowIndex == 0 ? 0.38 : 0.50;
  }

  if (rowSize == 2) {
    return columnIndex == 0 ? 0.32 : 0.68;
  }

  const positions = [0.28, 0.50, 0.72];
  final safeIndex = columnIndex.clamp(0, positions.length - 1).toInt();
  return positions[safeIndex];
}

double _seatYPosition(int rowCount, int rowIndex) {
  if (rowCount <= 1) {
    return 0.55;
  }

  if (rowCount == 2) {
    const positions = [0.43, 0.67];
    return positions[rowIndex.clamp(0, positions.length - 1).toInt()];
  }

  if (rowCount == 3) {
    const positions = [0.39, 0.57, 0.73];
    return positions[rowIndex.clamp(0, positions.length - 1).toInt()];
  }

  const start = 0.36;
  const end = 0.82;
  return start + (rowIndex * ((end - start) / (rowCount - 1)));
}

class _VehicleCabinPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final bodyPaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.fill;
    final linePaint = Paint()
      ..color = TravSTheme.line
      ..strokeWidth = 1.2
      ..style = PaintingStyle.stroke;
    final softPaint = Paint()
      ..color = TravSTheme.surfaceSoft
      ..style = PaintingStyle.fill;

    final body = RRect.fromRectAndRadius(
      Rect.fromLTWH(
        size.width * 0.18,
        size.height * 0.06,
        size.width * 0.64,
        size.height * 0.88,
      ),
      Radius.circular(size.width * 0.20),
    );
    canvas.drawRRect(body, bodyPaint);
    canvas.drawRRect(body, linePaint);

    final hood = RRect.fromRectAndRadius(
      Rect.fromLTWH(
        size.width * 0.28,
        size.height * 0.09,
        size.width * 0.44,
        size.height * 0.15,
      ),
      Radius.circular(size.width * 0.10),
    );
    canvas.drawRRect(hood, softPaint);
    canvas.drawRRect(hood, linePaint);

    final cabin = RRect.fromRectAndRadius(
      Rect.fromLTWH(
        size.width * 0.25,
        size.height * 0.25,
        size.width * 0.50,
        size.height * 0.47,
      ),
      Radius.circular(size.width * 0.08),
    );
    canvas.drawRRect(cabin, softPaint);
    canvas.drawRRect(cabin, linePaint);

    canvas.drawLine(
      Offset(size.width * 0.24, size.height * 0.46),
      Offset(size.width * 0.76, size.height * 0.46),
      linePaint,
    );
    canvas.drawLine(
      Offset(size.width * 0.24, size.height * 0.66),
      Offset(size.width * 0.76, size.height * 0.66),
      linePaint,
    );
    canvas.drawLine(
      Offset(size.width * 0.50, size.height * 0.25),
      Offset(size.width * 0.50, size.height * 0.88),
      linePaint..color = TravSTheme.line.withValues(alpha: 0.7),
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
