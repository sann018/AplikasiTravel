// Model notifikasi yang dipakai aplikasi mobile.
//
// KONSEP:
// Setiap kali terjadi kejadian penting di backend (pembayaran berhasil, tiket terbit,
// jadwal berubah), backend menyimpan satu baris ke tabel `notifikasi`. Mobile
// mengambil data ini secara berkala lewat endpoint `/api/v1/notifications`.

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

  /// ID unik yang dibuat otomatis oleh database.
  /// Dipakai untuk menentukan mana notifikasi yang "baru" vs "sudah pernah dilihat".
  final int id;

  /// Judul singkat notifikasi, contoh: "Pembayaran Berhasil", "Manifest Baru".
  final String judul;

  /// Isi lengkap yang ditampilkan pada notifikasi sistem Android.
  final String pesan;

  /// Kategori notifikasi: 'pembayaran', 'perjalanan', atau 'sistem'.
  /// Dipakai untuk menentukan warna dan ikon di tampilan aplikasi.
  final String jenis;

  /// ID referensi ke entitas terkait (misal ID pemesanan atau ID jadwal).
  /// Boleh kosong karena tidak semua notifikasi terkait dengan data tertentu.
  final int? referensiId;

  /// Menandai apakah notifikasi sudah dibaca oleh pengguna.
  /// Dipakai untuk menampilkan titik atau angka pada ikon notifikasi.
  final bool sudahDibaca;

  /// Waktu notifikasi dibuat di backend.
  /// Ditampilkan sebagai label waktu di UI (contoh: "2 jam lalu").
  final DateTime? createdAt;

  /// Mengubah JSON dari API menjadi objek Dart yang siap dipakai tampilan.
  ///
  /// Pembacaan dibuat hati-hati karena data dari backend bisa saja kosong
  /// atau berubah format. Nilai awal dipakai agar aplikasi tidak berhenti.
  factory TravSNotification.fromJson(Map<String, dynamic> json) {
    final createdRaw = json['created_at'];
    return TravSNotification(
      id: (json['id'] as num?)?.toInt() ?? 0,
      judul: (json['judul'] as String?) ?? '',
      pesan: (json['pesan'] as String?) ?? '',
      jenis: (json['jenis'] as String?) ?? 'sistem',
      referensiId: (json['referensi_id'] as num?)?.toInt(),
      sudahDibaca: (json['sudah_dibaca'] as bool?) ?? false,
      // Kalau format tanggal tidak valid, nilainya dibuat null agar UI tetap aman.
      createdAt: createdRaw is String ? DateTime.tryParse(createdRaw) : null,
    );
  }
}

/// Hasil pengambilan notifikasi berisi daftar pesan dan jumlah yang belum dibaca.
///
/// Data ini dipisahkan dari satu notifikasi karena endpoint mengirim dua bagian:
/// daftar notifikasi pada `items` dan jumlah belum dibaca pada `unread_count`.
/// Kelas ini menyatukan keduanya agar halaman tidak perlu membaca JSON manual.
class TravSNotificationFeed {
  const TravSNotificationFeed({
    required this.items,
    required this.unreadCount,
  });

  /// Daftar notifikasi terbaru (sudah diurutkan dari yang paling baru oleh backend).
  final List<TravSNotification> items;

  /// Jumlah notifikasi belum dibaca untuk angka pada ikon lonceng.
  final int unreadCount;

  /// Mengubah data balikan backend menjadi objek yang mudah dipakai.
  factory TravSNotificationFeed.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    // Hanya item berbentuk Map yang diproses, supaya data kosong/aneh tidak bikin crash.
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

  /// Nilai awal sebelum aplikasi selesai mengambil notifikasi pertama kali.
  static const empty = TravSNotificationFeed(items: [], unreadCount: 0);
}
