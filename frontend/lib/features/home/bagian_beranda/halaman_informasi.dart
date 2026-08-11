part of '../travs_home_page.dart';

class _PassengerInfoPage extends StatelessWidget {
  const _PassengerInfoPage();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: TravSTheme.background,
      body: SafeArea(
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          children: [
            _PageTopBar(
              leading: IconButton(
                onPressed: () => Navigator.of(context).maybePop(),
                icon: const Icon(Icons.arrow_back_rounded),
                color: TravSTheme.navy,
              ),
              title: 'Info Pemesanan',
            ),
            const SizedBox(height: 16),
            const _BookingInfoSections(),
          ],
        ),
      ),
    );
  }
}

class _DriverInfoPage extends StatelessWidget {
  const _DriverInfoPage();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
          children: [
            _PageTopBar(
              leading: IconButton(
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.arrow_back_rounded),
              ),
              title: 'Panduan Pengemudi',
              subtitle: 'Tata cara dan aturan kerja pengemudi.',
            ),
            const SizedBox(height: 16),
            const _InfoGroupTitle(
              title: 'Tata Cara Pengemudi',
              subtitle:
                  'Langkah yang perlu dilakukan saat menerima tugas perjalanan.',
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Periksa jadwal tugas',
              description:
                  'Buka halaman Beranda atau Perjalanan untuk melihat jadwal tugas Anda. Periksa rute, waktu berangkat, kendaraan, dan jumlah penumpang sebelum perjalanan dimulai.\n\n'
                  '1. Jadwal aktif muncul setelah tugas perjalanan tersedia.\n'
                  '2. Kendaraan mengikuti data jadwal, bukan data profil.\n'
                  '3. Jika jadwal tidak sesuai, hubungi petugas operasional sebelum berangkat.',
              justifyDescription: true,
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Buka manifes penumpang',
              description:
                  'Manifes berisi daftar penumpang, nomor kursi, titik jemput, titik turun, nomor HP, dan data barang bawaan. Gunakan data ini sebagai acuan kerja saat menjemput penumpang.\n\n'
                  '1. Pastikan nama dan kursi sesuai daftar.\n'
                  '2. Periksa catatan patokan jemput dan turun.\n'
                  '3. Lihat foto barang jika penumpang mengunggah bukti barang bawaan.',
              justifyDescription: true,
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Hubungi dan jemput penumpang',
              description:
                  'Gunakan tombol telepon atau WhatsApp jika perlu memastikan titik jemput. Jika tersedia, buka tombol navigasi untuk membantu menuju lokasi penjemputan atau titik turun.\n\n'
                  '1. Nomor penumpang hanya digunakan untuk kebutuhan perjalanan.\n'
                  '2. Pastikan titik jemput sesuai data aplikasi.\n'
                  '3. Konfirmasi ulang jika lokasi sulit ditemukan.',
              justifyDescription: true,
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Pindai QR tiket',
              description:
                  'Saat penumpang naik, pindai QR tiket dari aplikasi penumpang. Pemindaian dipakai untuk mencatat kehadiran dan memastikan tiket yang digunakan sesuai pemesanan.\n\n'
                  '1. Jangan memindai tiket milik penumpang lain.\n'
                  '2. Jika QR tidak terbaca, periksa kembali data manifes.\n'
                  '3. Laporkan kendala tiket kepada petugas operasional.',
              justifyDescription: true,
            ),
            const SizedBox(height: 22),
            const _InfoGroupTitle(
              title: 'Aturan Pengemudi',
              subtitle:
                  'Ketentuan yang perlu dijaga selama menjalankan perjalanan.',
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Ikuti jadwal dan kendaraan tugas',
              description:
                  'Pengemudi menjalankan perjalanan sesuai jadwal operasional. Data rute, kendaraan, waktu keberangkatan, dan manifes tidak dapat diubah melalui aplikasi pengemudi.\n\n'
                  '1. Kendaraan ditentukan dari jadwal.\n'
                  '2. Perubahan rute atau jadwal dilakukan oleh petugas operasional.\n'
                  '3. Jika ada kendala kendaraan, segera hubungi petugas operasional.',
              justifyDescription: true,
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Jaga data penumpang',
              description:
                  'Nomor HP, titik jemput, titik turun, dan tiket penumpang hanya digunakan untuk kebutuhan perjalanan. Jangan membagikan data tersebut kepada pihak lain.\n\n'
                  '1. QR tiket bersifat bukti perjalanan.\n'
                  '2. Data lokasi dipakai untuk operasional jemput dan turun.\n'
                  '3. Komunikasi dilakukan secara sopan dan seperlunya.',
              justifyDescription: true,
            ),
            const SizedBox(height: 12),
            const _InfoCard(
              title: 'Catat pembayaran tunai dengan benar',
              description:
                  'Jika penumpang memilih pembayaran tunai, konfirmasi hanya setelah pembayaran benar-benar diterima. Status pembayaran digital tetap mengikuti penyedia pembayaran yang digunakan.\n\n'
                  '1. Jangan mengonfirmasi tunai sebelum uang diterima.\n'
                  '2. Pembayaran digital tidak diubah manual oleh pengemudi.\n'
                  '3. Laporkan selisih pembayaran kepada petugas operasional.',
              justifyDescription: true,
            ),
          ],
        ),
      ),
    );
  }
}

class _BookingInfoSections extends StatelessWidget {
  const _BookingInfoSections();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _InfoGroupTitle(title: 'Aturan Pemesanan'),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Data penumpang wajib benar',
          description:
              'Gunakan nama lengkap dan nomor HP aktif agar Anda dapat dihubungi jika ada perubahan jadwal.\n\n'
              '1. Nama penumpang dipakai pada tiket dan manifes.\n'
              '2. Nomor HP dipakai untuk komunikasi keberangkatan.\n'
              '3. Data yang salah dapat menyulitkan proses penjemputan.',
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Kursi mengikuti ketersediaan jadwal',
          description:
              'Kursi yang sudah dipilih akan dikunci sementara saat proses pemesanan berjalan. Jika sesi habis atau pemesanan dibatalkan, kursi dilepas kembali agar bisa dipilih penumpang lain.\n\n'
              '1. Pilih kursi sesuai jumlah penumpang.\n'
              '2. Jangan menutup proses pemesanan sebelum data selesai dikirim.\n'
              '3. Kursi baru dianggap aman setelah pemesanan berhasil dibuat.',
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Pembayaran mengikuti batas waktu',
          description:
              'Setelah pemesanan dibuat, pembayaran harus diselesaikan sebelum batas waktu berakhir. Jika melewati batas dan belum berhasil dibayar, pemesanan dapat dibatalkan secara otomatis.\n\n'
              '1. Status QRIS atau Virtual Account mengikuti penyedia pembayaran.\n'
              '2. Pembayaran tunai dikonfirmasi setelah uang diterima petugas.\n'
              '3. Tiket QR hanya valid setelah pembayaran dinyatakan lunas.',
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Titik jemput, titik turun, dan barang bawaan',
          description:
              'Isi alamat serta patokan lokasi dengan jelas agar pengemudi mudah menemukan Anda. Barang bawaan biasa dicatat sebagai informasi operasional dan tidak dikenakan biaya tambahan.\n\n'
              '1. Patokan bisa berupa toko, gang, bangunan, atau warna rumah.\n'
              '2. Isi nama atau keterangan barang yang dibawa.\n'
              '3. Foto barang bersifat opsional dan membantu pengecekan.\n'
              '4. Sepeda, sepeda motor, dan barang berukuran besar wajib dikonfirmasi melalui layanan RuslyTravel.\n'
              '5. Barang besar tidak dihitung atau diterima otomatis oleh aplikasi.',
          justifyDescription: true,
        ),
        const SizedBox(height: 22),
        const _InfoGroupTitle(
          title: 'Tata Cara Pemesanan',
          subtitle:
              'Langkah penggunaan aplikasi dari mencari jadwal sampai berangkat.',
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: '1. Pilih rute dan jadwal',
          description:
              'Buka menu Rute, pilih tujuan perjalanan, lalu lihat jadwal yang tersedia. Perhatikan tanggal, jam berangkat, armada, harga tiket, dan jumlah kursi kosong sebelum melanjutkan.',
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: '2. Pilih kursi dan isi data perjalanan',
          description:
              'Pilih kursi yang masih tersedia, lalu isi data penumpang. Setelah itu lengkapi titik jemput dan titik turun dengan alamat serta patokan lokasi yang mudah dipahami pengemudi.',
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: '3. Isi barang bawaan',
          description:
              'Pilih apakah Anda membawa barang, lalu isi nama atau keterangan barang yang dibawa. Foto dapat ditambahkan untuk membantu pengemudi mengenali barang. Untuk sepeda, sepeda motor, atau barang berukuran besar, hubungi layanan RuslyTravel terlebih dahulu sebelum membuat pemesanan.',
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: '4. Selesaikan pembayaran',
          description:
              'Pilih metode pembayaran yang tersedia. Untuk QRIS atau Virtual Account, ikuti instruksi pembayaran sampai berhasil. Untuk tunai, siapkan pembayaran saat bertemu pengemudi sesuai ketentuan.',
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: '5. Gunakan tiket digital saat keberangkatan',
          description:
              'Setelah pembayaran berhasil, tiket digital dan QR akan tersedia di menu Tiket. Tunjukkan QR kepada pengemudi saat keberangkatan agar tiket dapat dipindai dan status kehadiran tercatat.',
          justifyDescription: true,
        ),
        const SizedBox(height: 22),
        const _InfoGroupTitle(
          title: 'Syarat Penggunaan',
          subtitle:
              'Hal yang perlu dijaga agar pemesanan dan perjalanan berjalan lancar.',
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Isi data sesuai kondisi sebenarnya',
          description:
              'Gunakan data penumpang, titik jemput, titik turun, dan barang bawaan yang benar agar penjemputan dapat disiapkan dengan aman dan tepat.',
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Simpan dan jaga tiket QR',
          description:
              'Tiket QR adalah bukti pemesanan yang dipakai saat keberangkatan. Jangan membagikan tiket kepada orang lain, karena QR digunakan pengemudi untuk mencatat kehadiran penumpang.',
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Ikuti waktu dan titik keberangkatan',
          description:
              'Datang sesuai jadwal yang tertera pada tiket. Jika titik jemput atau titik turun sulit ditemukan, isi patokan lokasi seperti nama toko, simpang, gang, warna rumah, atau bangunan terdekat.',
          justifyDescription: true,
        ),
        const SizedBox(height: 12),
        const _InfoCard(
          title: 'Pahami batas waktu dan pembatalan',
          description:
              'Kursi hanya dikunci sementara saat pemesanan dan pembayaran. Jika waktu habis atau pembayaran belum selesai, pemesanan dapat dibatalkan secara otomatis agar kursi tersedia kembali untuk penumpang lain.',
          justifyDescription: true,
        ),
      ],
    );
  }
}

class _InfoGroupTitle extends StatelessWidget {
  const _InfoGroupTitle({required this.title, this.subtitle});

  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: theme.textTheme.titleLarge?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w900,
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
    );
  }
}

class _EmptyStateCard extends StatelessWidget {
  const _EmptyStateCard({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return _InfoCard(
      title: title,
      description: subtitle,
      icon: Icons.inbox_outlined,
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({
    required this.label,
    required this.color,
    required this.textColor,
    this.solid = false,
  });

  final String label;
  final Color color;
  final Color textColor;
  final bool solid;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: solid ? color : color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: solid ? color : color.withValues(alpha: 0.16),
        ),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: textColor,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

Color _journeyStatusBackground(String status) {
  switch (status) {
    case 'dibuka':
      return TravSTheme.success;
    case 'ditutup':
      return Colors.white;
    case 'berangkat':
      return TravSTheme.orange;
    case 'selesai':
      return TravSTheme.navyMuted;
    case 'dibatalkan':
      return TravSTheme.danger;
    default:
      return Colors.white;
  }
}

Color _journeyStatusTextColor(String status) {
  switch (status) {
    case 'dibuka':
    case 'berangkat':
    case 'selesai':
    case 'dibatalkan':
      return Colors.white;
    default:
      return TravSTheme.navy;
  }
}

String _humanizeRole(String role) {
  switch (role) {
    case 'pengemudi':
      return 'Pengemudi';
    case 'penumpang':
      return 'Penumpang';
    case 'admin':
      return 'Admin';
    default:
      return 'Tamu';
  }
}

String _humanizeStatus(String status) {
  switch (status) {
    case 'terbayar':
    case 'berhasil':
      return 'Berhasil';
    case 'menunggu':
    case 'menunggu_pembayaran':
      return 'Menunggu';
    case 'dibuka':
      return 'Dibuka';
    case 'ditutup':
      return 'Ditutup';
    case 'berangkat':
      return 'Berangkat';
    case 'selesai':
      return 'Selesai';
    case 'dibatalkan':
      return 'Dibatalkan';
    default:
      return status.replaceAll('_', ' ');
  }
}

// Daftar rute memakai data jadwal yang sudah ada agar tidak meminta data ulang ke backend.
List<TravSSchedule> _availablePublicSchedules(List<TravSSchedule> schedules) {
  // Kursi nol berarti jadwal tidak dapat lagi dipilih penumpang atau tamu.
  return schedules
      .where((schedule) => schedule.sisaKursi > 0)
      .toList(growable: false);
}

List<_RouteBookmark> _buildRouteBookmarks(List<TravSSchedule> schedules) {
  final groupedSchedules = <String, List<TravSSchedule>>{};

  for (final schedule in schedules.where((item) => item.sisaKursi > 0)) {
    final key = _routeKeyForSchedule(schedule);
    groupedSchedules.putIfAbsent(key, () => <TravSSchedule>[]).add(schedule);
  }

  return groupedSchedules.entries.map((entry) {
    final routeSchedules = entry.value;
    final firstSchedule = routeSchedules.first;
    var availableSeats = 0;
    var startingPrice = firstSchedule.hargaTiket;

    for (final schedule in routeSchedules) {
      availableSeats += schedule.sisaKursi;
      if (schedule.hargaTiket < startingPrice) {
        startingPrice = schedule.hargaTiket;
      }
    }

    return _RouteBookmark(
      key: entry.key,
      origin: firstSchedule.asal,
      destination: firstSchedule.tujuan,
      path: firstSchedule.jalur,
      scheduleCount: routeSchedules.length,
      availableSeats: availableSeats,
      startingPrice: startingPrice,
    );
  }).toList();
}

String _routeKeyForSchedule(TravSSchedule schedule) {
  final origin = schedule.asal.trim().toLowerCase();
  final destination = schedule.tujuan.trim().toLowerCase();
  return '$origin|$destination';
}

String _destinationImageUrl(String destination) {
  return _unsplashSearchUrl('$destination Indonesia city landscape');
}

String _vehicleImageUrl(String vehicleName) {
  return _unsplashSearchUrl('$vehicleName travel car');
}

String _unsplashSearchUrl(String query) {
  final encodedQuery = Uri.encodeComponent(query);
  return 'https://source.unsplash.com/160x160/?$encodedQuery';
}

_RouteBookmark? _findRouteBookmark(
  List<_RouteBookmark> routes,
  String? selectedKey,
) {
  if (selectedKey == null) {
    return null;
  }

  for (final route in routes) {
    if (route.key == selectedKey) {
      return route;
    }
  }

  return null;
}

// Filter dijalankan di Flutter agar pergantian pilihan terasa cepat dan tidak mengulang query.
List<TravSSchedule> _filterSchedulesForRoute({
  required List<TravSSchedule> schedules,
  required String routeKey,
  required _ScheduleDateFilter filter,
  DateTime? selectedDate,
}) {
  final filtered = schedules
      .where((schedule) => _routeKeyForSchedule(schedule) == routeKey)
      .where((schedule) => schedule.sisaKursi > 0)
      .toList();

  if (filter == _ScheduleDateFilter.byDate && selectedDate != null) {
    return filtered.where((schedule) {
      final scheduleDate = _parseLocalDateTime(schedule.waktuBerangkat);
      return scheduleDate != null && _isSameDate(scheduleDate, selectedDate);
    }).toList()..sort((first, second) {
      return _scheduleDateValue(
        first.waktuBerangkat,
      ).compareTo(_scheduleDateValue(second.waktuBerangkat));
    });
  }

  return filtered..sort((first, second) {
    return _scheduleDateValue(
      first.waktuBerangkat,
    ).compareTo(_scheduleDateValue(second.waktuBerangkat));
  });
}

bool _isSameDate(DateTime first, DateTime second) {
  return first.year == second.year &&
      first.month == second.month &&
      first.day == second.day;
}

TravSBookingResult? _nextPaidUpcomingBooking(
  List<TravSBookingResult> bookings,
) {
  final now = DateTime.now();
  final paidUpcoming =
      bookings.where((booking) {
        final departure = _parseLocalDateTime(booking.waktuBerangkat);
        if (departure == null) {
          return false;
        }

        // Perjalanan berikutnya hanya mengambil pemesanan lunas yang masih aktif,
        // sehingga pesanan batal atau perjalanan selesai tidak ikut tampil.
        return booking.status == 'terbayar' &&
            booking.payment.status == 'berhasil' &&
            !departure.isBefore(now);
      }).toList()..sort((first, second) {
        return _scheduleDateValue(
          first.waktuBerangkat,
        ).compareTo(_scheduleDateValue(second.waktuBerangkat));
      });

  return paidUpcoming.isEmpty ? null : paidUpcoming.first;
}

bool _isCompletedTicket(TravSBookingResult booking) {
  if (booking.status == 'selesai') {
    return true;
  }

  final departure = DateTime.tryParse(booking.waktuBerangkat)?.toLocal();
  return departure != null && departure.isBefore(DateTime.now());
}

bool _isOldBooking(TravSBookingResult booking) {
  // Pesanan batal yang jadwalnya masih akan datang tetap termasuk terbaru.
  // Riwayat lama hanya berisi perjalanan selesai atau jadwal yang telah lewat.
  return _isCompletedTicket(booking);
}

DateTime _scheduleDateValue(String iso) {
  return _parseLocalDateTime(iso) ?? DateTime(9999);
}

DateTime? _parseLocalDateTime(String iso) {
  if (iso.isEmpty) {
    return null;
  }

  // API mengirim waktu jadwal dalam format ISO. Semua tampilan jadwal di mobile
  // harus memakai waktu lokal perangkat agar daftar jadwal dan detail pemesanan
  // tidak berbeda hari/jam ketika response memiliki timezone UTC.
  return DateTime.tryParse(iso)?.toLocal();
}

String _formatScheduleDate(String iso) {
  if (iso.isEmpty) {
    return '-';
  }

  final date = _parseLocalDateTime(iso);
  if (date == null) {
    return iso;
  }

  return DateFormat('EEEE, dd MMM yyyy', 'id_ID').format(date);
}

String _formatScheduleTime(String iso) {
  if (iso.isEmpty) {
    return '-';
  }

  final date = _parseLocalDateTime(iso);
  if (date == null) {
    return iso;
  }

  final hour = date.hour;
  final time = DateFormat('HH.mm').format(date);
  final period = hour < 11
      ? 'Pagi'
      : hour < 15
      ? 'Siang'
      : hour < 18
      ? 'Sore'
      : 'Malam';

  return '$time $period';
}

String _formatDateTime(String iso) {
  if (iso.isEmpty) {
    return '-';
  }

  final date = _parseLocalDateTime(iso);
  if (date == null) {
    return iso;
  }

  return DateFormat('dd MMM yyyy\nHH:mm', 'id_ID').format(date);
}

String _compactRouteName(String route) {
  return route
      .trim()
      .replaceAll(RegExp(r'\s*-\s*'), '-')
      .replaceAll(RegExp(r'\s{2,}'), ' ');
}

String _formatShortDate(String iso) {
  if (iso.isEmpty) {
    return '-';
  }

  final date = _parseLocalDateTime(iso);
  if (date == null) {
    return iso;
  }

  return DateFormat('dd MMM').format(date);
}

String _formatCurrency(num value) {
  return NumberFormat.currency(
    locale: 'id_ID',
    symbol: 'Rp',
    decimalDigits: 0,
  ).format(value);
}
