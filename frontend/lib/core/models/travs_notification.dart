// Model notifikasi yang dipakai aplikasi mobile.
//
// KONSEP:
// Setiap kali terjadi event penting di backend (pembayaran berhasil, tiket terbit,
// jadwal berubah), backend menyimpan satu baris ke tabel `notifikasi`. Mobile
// kemudian mengambil data ini lewat polling endpoint `/api/v1/notifications`.

class TravSNotification {
  const TravSNotification({
    required this.id,
    required this.judul,
    required this.pesan,
    required this.jenis,
    required this.referensiId,
    required this.sudahDibaca,
    required this.createdAt,
  });

  /// ID unik notifikasi dari database backend (auto-increment).
  /// Dipakai untuk menentukan mana notifikasi yang "baru" vs "sudah pernah dilihat".
  final int id;

  /// Judul singkat notifikasi, contoh: "Pembayaran Berhasil", "Manifest Baru".
  final String judul;

  /// Isi pesan lengkap notifikasi yang ditampilkan di body notif sistem Android.
  final String pesan;

  /// Kategori notifikasi: 'pembayaran', 'perjalanan', atau 'sistem'.
  /// Dipakai untuk menentukan warna dan icon di UI.
  final String jenis;

  /// ID referensi ke entitas terkait (misal ID pemesanan atau ID jadwal).
  /// Nullable karena tidak semua notifikasi punya referensi spesifik.
  final int? referensiId;

  /// Apakah notifikasi ini sudah dibaca oleh user.
  /// Dipakai untuk menampilkan dot/badge "belum dibaca" di UI.
  final bool sudahDibaca;

  /// Waktu notifikasi dibuat di backend.
  /// Ditampilkan sebagai label waktu di UI (contoh: "2 jam lalu").
  final DateTime? createdAt;

  /// Factory constructor untuk mengubah JSON dari API menjadi object Dart.
  ///
  /// POLA DEFENSIVE PARSING:
  /// Setiap field di-cast dengan null-safety (`as num?`, `as String?`) dan
  /// diberi default value (`?? 0`, `?? ''`). Ini mencegah crash jika backend
  /// mengirim data yang tidak lengkap atau format berubah.
  factory TravSNotification.fromJson(Map<String, dynamic> json) {
    final createdRaw = json['created_at'];
    return TravSNotification(
      id: (json['id'] as num?)?.toInt() ?? 0,
      judul: (json['judul'] as String?) ?? '',
      pesan: (json['pesan'] as String?) ?? '',
      jenis: (json['jenis'] as String?) ?? 'sistem',
      referensiId: (json['referensi_id'] as num?)?.toInt(),
      sudahDibaca: (json['sudah_dibaca'] as bool?) ?? false,
      // DateTime.tryParse aman — mengembalikan null jika format string tidak valid.
      createdAt: createdRaw is String ? DateTime.tryParse(createdRaw) : null,
    );
  }
}

/// Hasil polling endpoint notifikasi — berisi daftar item + jumlah belum dibaca.
///
/// KENAPA DIPISAH DARI TravSNotification:
/// Endpoint `/api/v1/notifications` mengembalikan object dengan 2 field:
/// - `items`: array notifikasi
/// - `unread_count`: angka untuk badge
///
/// Class ini membungkus keduanya agar mudah dipakai di UI tanpa parsing manual.
class TravSNotificationFeed {
  const TravSNotificationFeed({
    required this.items,
    required this.unreadCount,
  });

  /// Daftar notifikasi terbaru (sudah diurutkan dari yang paling baru oleh backend).
  final List<TravSNotification> items;

  /// Jumlah total notifikasi yang belum dibaca — untuk badge angka di icon lonceng.
  final int unreadCount;

  /// Factory constructor untuk parsing response JSON dari backend.
  factory TravSNotificationFeed.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    // whereType<Map<String, dynamic>> memfilter item yang bukan Map (safety).
    // Ini mencegah crash jika backend mengirim null atau format aneh di array.
    final list = rawItems is List
        ? rawItems
              .whereType<Map<String, dynamic>>()
              .map(TravSNotification.fromJson)
              .toList()
        : <TravSNotification>[];

    return TravSNotificationFeed(
      items: list,
      unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
    );
  }

  /// Konstanta untuk state awal sebelum polling pertama selesai.
  static const empty = TravSNotificationFeed(items: [], unreadCount: 0);
}
