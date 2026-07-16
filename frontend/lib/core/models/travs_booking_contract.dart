class TravSSeatLockResult {
  const TravSSeatLockResult({
    required this.jadwalId,
    required this.seatNumbers,
    required this.lockExpiresAt,
    required this.lockMinutes,
  });

  final int jadwalId;
  final List<int> seatNumbers;
  final String lockExpiresAt;
  final int lockMinutes;

  factory TravSSeatLockResult.fromJson(Map<String, dynamic> json) {
    return TravSSeatLockResult(
      jadwalId: _readInt(json['jadwal_id']),
      seatNumbers: (json['seat_numbers'] as List<dynamic>? ?? <dynamic>[])
          .map(_readInt)
          .toList(),
      lockExpiresAt: json['lock_expires_at'] as String? ?? '',
      lockMinutes: _readInt(json['lock_minutes']),
    );
  }
}

class TravSPassengerPayload {
  const TravSPassengerPayload({
    required this.seatNumber,
    required this.namaPenumpang,
    required this.noHpPenumpang,
  });

  final int seatNumber;
  final String namaPenumpang;
  final String noHpPenumpang;

  Map<String, dynamic> toJson() {
    return {
      'seat_number': seatNumber,
      'nama_penumpang': namaPenumpang,
      'no_hp_penumpang': noHpPenumpang,
    };
  }
}

class TravSBaggagePayload {
  const TravSBaggagePayload({
    required this.deskripsiItem,
    this.metodeHitung,
    this.posisiBarang,
    this.kategoriUkuran,
    this.jenisBarangBesar,
    this.beratKg,
    this.ukuranCm,
    this.panjangCm,
    this.lebarCm,
    this.jumlahItem,
    this.fotoUrl,
    this.fotoUrls = const <String>[],
  });

  final String deskripsiItem;
  final String? metodeHitung;
  final String? posisiBarang;
  final String? kategoriUkuran;
  final String? jenisBarangBesar;
  final double? beratKg;
  final int? ukuranCm;
  final int? panjangCm;
  final int? lebarCm;
  final int? jumlahItem;
  final String? fotoUrl;
  final List<String> fotoUrls;

  Map<String, dynamic> toJson() {
    return {
      'deskripsi_item': deskripsiItem,
      'metode_hitung': metodeHitung,
      if (posisiBarang != null) 'posisi_barang': posisiBarang,
      if (kategoriUkuran != null) 'kategori_ukuran': kategoriUkuran,
      if (jenisBarangBesar != null) 'jenis_barang_besar': jenisBarangBesar,
      'berat_kg': beratKg,
      'ukuran_cm': ukuranCm,
      'panjang_cm': panjangCm,
      'lebar_cm': lebarCm,
      if (jumlahItem != null) 'jumlah_item': jumlahItem,
      'foto_url': fotoUrl,
      if (fotoUrls.isNotEmpty) 'foto_urls': fotoUrls,
    };
  }
}

class TravSCreateBookingPayload {
  const TravSCreateBookingPayload({
    required this.jadwalId,
    required this.seatNumbers,
    required this.pickupLatitude,
    required this.pickupLongitude,
    required this.pickupAddress,
    this.pickupNote,
    required this.dropoffLatitude,
    required this.dropoffLongitude,
    required this.dropoffAddress,
    this.dropoffNote,
    required this.paymentMethod,
    required this.passengers,
    required this.baggageItems,
  });

  final int jadwalId;
  final List<int> seatNumbers;
  final double pickupLatitude;
  final double pickupLongitude;
  final String pickupAddress;
  final String? pickupNote;
  final double dropoffLatitude;
  final double dropoffLongitude;
  final String dropoffAddress;
  final String? dropoffNote;
  final String paymentMethod;
  final List<TravSPassengerPayload> passengers;
  final List<TravSBaggagePayload> baggageItems;

  Map<String, dynamic> toJson() {
    return {
      'jadwal_id': jadwalId,
      'seat_numbers': seatNumbers,
      'pickup': {
        'latitude': pickupLatitude,
        'longitude': pickupLongitude,
        'address': pickupAddress,
        'note': pickupNote,
      },
      'dropoff': {
        'latitude': dropoffLatitude,
        'longitude': dropoffLongitude,
        'address': dropoffAddress,
        'note': dropoffNote,
      },
      'payment_method': paymentMethod,
      'passengers': passengers.map((item) => item.toJson()).toList(),
      'baggage_items': baggageItems.map((item) => item.toJson()).toList(),
    };
  }
}

class TravSBookingPaymentInfo {
  const TravSBookingPaymentInfo({
    required this.status,
    required this.method,
    required this.expiredAt,
    this.paidAt = '',
    required this.canSimulate,
    required this.usesGateway,
    required this.provider,
    required this.project,
    required this.amount,
    required this.fee,
    required this.totalPayment,
    required this.paymentNumber,
    required this.checkoutUrl,
    required this.gatewayStatus,
  });

  final String status;
  final String method;
  final String expiredAt;
  final String paidAt;
  final bool canSimulate;
  final bool usesGateway;
  final String provider;
  final String project;
  final double amount;
  final double fee;
  final double totalPayment;
  final String paymentNumber;

  /// Tautan halaman Pakasir yang dibuka penumpang untuk menyelesaikan pembayaran.
  final String checkoutUrl;

  final String gatewayStatus;

  bool get hasPaymentNumber => paymentNumber.trim().isNotEmpty;

  /// Menandai apakah tautan pembayaran dari Pakasir tersedia dan bisa dibuka.
  bool get hasCheckoutUrl => checkoutUrl.trim().isNotEmpty;

  factory TravSBookingPaymentInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingPaymentInfo(
      status: json['status'] as String? ?? '-',
      method: json['method'] as String? ?? '-',
      expiredAt: json['expired_at'] as String? ?? '',
      paidAt: json['paid_at'] as String? ?? '',
      canSimulate: json['can_simulate'] as bool? ?? false,
      usesGateway: json['uses_gateway'] as bool? ?? false,
      provider: json['provider'] as String? ?? '',
      project: json['project'] as String? ?? '',
      amount: _readDouble(json['amount']),
      fee: _readDouble(json['fee']),
      totalPayment: _readDouble(json['total_payment']),
      paymentNumber: json['payment_number'] as String? ?? '',
      checkoutUrl: json['checkout_url'] as String? ?? '',
      gatewayStatus: json['gateway_status'] as String? ?? '',
    );
  }
}

class TravSBookingPickupInfo {
  const TravSBookingPickupInfo({
    required this.latitude,
    required this.longitude,
    required this.address,
    this.note,
  });

  final double latitude;
  final double longitude;
  final String address;
  final String? note;

  factory TravSBookingPickupInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingPickupInfo(
      latitude: _readDouble(json['latitude']),
      longitude: _readDouble(json['longitude']),
      address: json['address'] as String? ?? '-',
      note: json['note'] as String?,
    );
  }
}

class TravSBookingVehicleInfo {
  const TravSBookingVehicleInfo({
    required this.merk,
    required this.plat,
    required this.warna,
    required this.tipe,
    required this.kapasitas,
    required this.fasilitas,
    this.fotoUrl,
  });

  final String merk;
  final String plat;
  final String warna;
  final String tipe;
  final int kapasitas;
  final String fasilitas;
  final String? fotoUrl;

  factory TravSBookingVehicleInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingVehicleInfo(
      merk: json['merk'] as String? ?? '-',
      plat: json['plat'] as String? ?? '-',
      warna: json['warna'] as String? ?? '-',
      tipe: json['tipe'] as String? ?? '-',
      kapasitas: _readInt(json['kapasitas']),
      fasilitas: json['fasilitas'] as String? ?? '-',
      fotoUrl: json['foto_url'] as String?,
    );
  }
}

class TravSBookingDriverInfo {
  const TravSBookingDriverInfo({
    required this.nama,
    required this.email,
    required this.noHp,
    required this.nomorSim,
    required this.status,
    this.fotoUrl,
  });

  final String nama;
  final String email;
  final String noHp;
  final String nomorSim;
  final String status;
  final String? fotoUrl;

  factory TravSBookingDriverInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingDriverInfo(
      nama: json['nama'] as String? ?? '-',
      email: json['email'] as String? ?? '-',
      noHp: json['no_hp'] as String? ?? '-',
      nomorSim: json['nomor_sim'] as String? ?? '-',
      status: json['status'] as String? ?? '-',
      fotoUrl: json['foto_url'] as String?,
    );
  }
}

class TravSBookingTicketInfo {
  const TravSBookingTicketInfo({
    required this.kodeQr,
    required this.token,
    required this.sudahDipindai,
  });

  final String kodeQr;
  final String token;
  final bool sudahDipindai;

  factory TravSBookingTicketInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingTicketInfo(
      kodeQr: json['kode_qr'] as String? ?? '-',
      token: json['token'] as String? ?? '',
      sudahDipindai: json['sudah_dipindai'] as bool? ?? false,
    );
  }
}

class TravSBookingPassengerInfo {
  const TravSBookingPassengerInfo({
    required this.seatNumber,
    required this.namaPenumpang,
    required this.noHpPenumpang,
    required this.statusKehadiran,
    this.ticket,
  });

  final int seatNumber;
  final String namaPenumpang;
  final String noHpPenumpang;
  final String statusKehadiran;
  final TravSBookingTicketInfo? ticket;

  bool get hasTicket => ticket != null;

  factory TravSBookingPassengerInfo.fromJson(Map<String, dynamic> json) {
    final ticketJson = _asMapOrNull(json['ticket']);

    return TravSBookingPassengerInfo(
      seatNumber: _readInt(json['seat_number']),
      namaPenumpang: json['nama_penumpang'] as String? ?? '-',
      noHpPenumpang: json['no_hp_penumpang'] as String? ?? '-',
      statusKehadiran: json['status_kehadiran'] as String? ?? '-',
      ticket: ticketJson == null
          ? null
          : TravSBookingTicketInfo.fromJson(ticketJson),
    );
  }
}

class TravSBookingBaggageInfo {
  const TravSBookingBaggageInfo({
    required this.deskripsiItem,
    required this.metodeHitung,
    required this.posisiBarang,
    required this.kategoriUkuran,
    required this.jenisBarangBesar,
    required this.beratKg,
    required this.ukuranCm,
    required this.panjangCm,
    required this.lebarCm,
    required this.jumlahItem,
    required this.fotoUrl,
    required this.fotoUrls,
  });

  final String deskripsiItem;
  final String? metodeHitung;
  final String posisiBarang;
  final String kategoriUkuran;
  final String? jenisBarangBesar;
  final double? beratKg;
  final int? ukuranCm;
  final int? panjangCm;
  final int? lebarCm;
  final int jumlahItem;
  final String? fotoUrl;
  final List<String> fotoUrls;

  factory TravSBookingBaggageInfo.fromJson(Map<String, dynamic> json) {
    final rawPhotoUrls = json['foto_urls'] as List<dynamic>? ?? <dynamic>[];
    final fotoUrls = rawPhotoUrls
        .whereType<String>()
        .where((url) => url.trim().isNotEmpty)
        .toList();
    final legacyPhotoUrl = json['foto_url'] as String?;
    if (legacyPhotoUrl != null &&
        legacyPhotoUrl.trim().isNotEmpty &&
        !fotoUrls.contains(legacyPhotoUrl)) {
      fotoUrls.insert(0, legacyPhotoUrl);
    }

    return TravSBookingBaggageInfo(
      deskripsiItem: json['deskripsi_item'] as String? ?? '-',
      metodeHitung: json['metode_hitung'] as String?,
      posisiBarang: json['posisi_barang'] as String? ?? 'masuk_bagasi',
      kategoriUkuran: json['kategori_ukuran'] as String? ?? 'besar',
      jenisBarangBesar: json['jenis_barang_besar'] as String?,
      beratKg: json['berat_kg'] == null ? null : _readDouble(json['berat_kg']),
      ukuranCm: json['ukuran_cm'] == null ? null : _readInt(json['ukuran_cm']),
      panjangCm: json['panjang_cm'] == null
          ? null
          : _readInt(json['panjang_cm']),
      lebarCm: json['lebar_cm'] == null ? null : _readInt(json['lebar_cm']),
      jumlahItem: _readInt(json['jumlah_item'], fallback: 1),
      fotoUrl: fotoUrls.isNotEmpty ? fotoUrls.first : legacyPhotoUrl,
      fotoUrls: fotoUrls.take(3).toList(),
    );
  }
}

class TravSBookingRequesterInfo {
  const TravSBookingRequesterInfo({
    required this.nama,
    required this.email,
    required this.noHp,
    this.fotoUrl,
  });

  final String nama;
  final String email;
  final String noHp;
  final String? fotoUrl;

  factory TravSBookingRequesterInfo.fromJson(Map<String, dynamic> json) {
    return TravSBookingRequesterInfo(
      nama: json['nama'] as String? ?? '-',
      email: json['email'] as String? ?? '-',
      noHp: json['no_hp'] as String? ?? '-',
      fotoUrl: json['foto_url'] as String?,
    );
  }
}

class TravSBookingResult {
  const TravSBookingResult({
    required this.id,
    required this.kodeBooking,
    required this.status,
    required this.jumlahKursi,
    required this.batasPembayaran,
    required this.subtotalTiket,
    required this.biayaBagasi,
    required this.biayaAdmin,
    required this.diskon,
    required this.totalHarga,
    required this.rute,
    required this.waktuBerangkat,
    required this.payment,
    this.pemesan,
    this.pickup,
    this.dropoff,
    this.kendaraan,
    this.pengemudi,
    this.passengers = const <TravSBookingPassengerInfo>[],
    this.baggageItems = const <TravSBookingBaggageInfo>[],
  });

  final int id;
  final String kodeBooking;
  final String status;
  final int jumlahKursi;
  final String batasPembayaran;
  final double subtotalTiket;
  final double biayaBagasi;
  final double biayaAdmin;
  final double diskon;
  final double totalHarga;
  final String rute;
  final String waktuBerangkat;
  final TravSBookingPaymentInfo payment;
  final TravSBookingRequesterInfo? pemesan;
  final TravSBookingPickupInfo? pickup;
  final TravSBookingPickupInfo? dropoff;
  final TravSBookingVehicleInfo? kendaraan;
  final TravSBookingDriverInfo? pengemudi;
  final List<TravSBookingPassengerInfo> passengers;
  final List<TravSBookingBaggageInfo> baggageItems;

  bool get isPaid => status == 'terbayar' || payment.status == 'berhasil';
  int get activeTicketCount =>
      passengers.where((item) => item.hasTicket).length;

  factory TravSBookingResult.fromJson(Map<String, dynamic> json) {
    final pickupJson = _asMapOrNull(json['pickup']);
    final dropoffJson = _asMapOrNull(json['dropoff']);
    final vehicleJson = _asMapOrNull(json['kendaraan']);
    final driverJson = _asMapOrNull(json['pengemudi']);
    final requesterJson = _asMapOrNull(json['pemesan']);

    return TravSBookingResult(
      id: _readInt(json['id']),
      kodeBooking: json['kode_booking'] as String? ?? '-',
      status: json['status'] as String? ?? '-',
      jumlahKursi: _readInt(json['jumlah_kursi']),
      batasPembayaran: json['batas_pembayaran'] as String? ?? '',
      subtotalTiket: _readDouble(json['subtotal_tiket']),
      biayaBagasi: _readDouble(json['biaya_bagasi']),
      biayaAdmin: _readDouble(json['biaya_admin']),
      diskon: _readDouble(json['diskon']),
      totalHarga: _readDouble(json['total_harga']),
      rute: json['rute'] as String? ?? '-',
      waktuBerangkat: json['waktu_berangkat'] as String? ?? '',
      payment: TravSBookingPaymentInfo.fromJson(_asMap(json['payment'])),
      pemesan: requesterJson == null
          ? null
          : TravSBookingRequesterInfo.fromJson(requesterJson),
      pickup: pickupJson == null
          ? null
          : TravSBookingPickupInfo.fromJson(pickupJson),
      dropoff: dropoffJson == null
          ? null
          : TravSBookingPickupInfo.fromJson(dropoffJson),
      kendaraan: vehicleJson == null
          ? null
          : TravSBookingVehicleInfo.fromJson(vehicleJson),
      pengemudi: driverJson == null
          ? null
          : TravSBookingDriverInfo.fromJson(driverJson),
      passengers: (json['passengers'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) => TravSBookingPassengerInfo.fromJson(
              item as Map<String, dynamic>,
            ),
          )
          .toList(),
      baggageItems: (json['baggage_items'] as List<dynamic>? ?? <dynamic>[])
          .map(
            (item) =>
                TravSBookingBaggageInfo.fromJson(item as Map<String, dynamic>),
          )
          .toList(),
    );
  }
}

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map<String, dynamic>) {
    return value;
  }

  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }

  return <String, dynamic>{};
}

Map<String, dynamic>? _asMapOrNull(dynamic value) {
  if (value == null) {
    return null;
  }

  if (value is Map<String, dynamic>) {
    return value;
  }

  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }

  return null;
}

int _readInt(dynamic value, {int fallback = 0}) {
  if (value == null) {
    return fallback;
  }

  if (value is int) {
    return value;
  }

  if (value is num) {
    return value.toInt();
  }

  return int.tryParse(value.toString()) ?? fallback;
}

double _readDouble(dynamic value, {double fallback = 0}) {
  if (value == null) {
    return fallback;
  }

  if (value is double) {
    return value;
  }

  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value.toString()) ?? fallback;
}
