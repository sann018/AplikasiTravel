# TASK V2 — Petugas Loket Mobile dan Pemesanan Loket RuslyTravel

## 1. Keputusan Arsitektur Final

1. Pemesanan loket dilakukan melalui aplikasi mobile.
2. Aktornya adalah **Petugas Loket**, bukan Admin Web.
3. Admin Web tetap memakai akun `admin` dan tidak dapat masuk aplikasi mobile.
4. Petugas Loket memakai akun internal dengan peran `petugas_loket`.
5. Akun Petugas Loket dibuat, diaktifkan, dinonaktifkan, dan dipantau oleh Admin melalui web.
6. Registrasi publik tetap hanya membuat akun penumpang.
7. Tampilan mobile Petugas Loket memakai halaman dan komponen yang sama dengan penumpang. Perbedaannya hanya konteks **Mode Petugas Loket**, sumber data, dan hak akses API.
8. Pemesanan loket menggunakan `BookingService`, seat lock, pembayaran, dan penerbitan tiket yang sama dengan pemesanan penumpang.
9. Jangan membuat logika booking kedua.

## 2. Tujuan

Petugas Loket dapat:

- masuk ke aplikasi mobile menggunakan akun internal;
- melihat jadwal yang tersedia;
- mengunci kursi dengan mekanisme yang sama seperti penumpang;
- mengisi data calon penumpang tanpa wajib membuat akun penumpang;
- menghubungkan pemesanan ke akun penumpang existing secara opsional;
- memilih pembayaran tunai atau digital;
- mengonfirmasi pembayaran tunai setelah uang diterima;
- melihat dan mengunduh tiket yang telah diterbitkan;
- melihat riwayat pemesanan loket yang dibuat oleh akunnya sendiri.

## 3. Batas Hak Akses

### Admin Web

- mengelola akun Petugas Loket;
- melihat pemesanan loket pada monitoring dan laporan;
- tetap mengelola data master melalui web;
- tidak menggunakan token mobile loket.

### Petugas Loket

- hanya mengakses endpoint `/api/petugas-loket/*`;
- token hanya memiliki ability `mobile-counter`;
- tidak dapat membuka halaman pengelolaan Admin Web;
- hanya dapat membuka pemesanan loket yang dibuat oleh akunnya;
- tidak dapat menggunakan endpoint penumpang atau pengemudi.

### Penumpang dan Pengemudi

- alur, role, token, dan endpoint existing tetap dipertahankan;
- tidak dapat memakai endpoint Petugas Loket.

## 4. Perubahan Database

### Tabel `pengguna`

Nilai enum `peran` menjadi:

```text
penumpang
pengemudi
petugas_loket
admin
```

Tidak dibuat tabel akun baru karena seluruh jenis akun tetap memakai tabel `pengguna`.

### Tabel `pemesanan`

| Kolom | Fungsi |
|---|---|
| `dibuat_oleh` | ID Petugas Loket yang mencatat pemesanan |
| `sumber_pemesanan` | Membedakan `aplikasi_penumpang` dan `loket` |
| `nama_pemesan` | Snapshot nama kontak pemesan |
| `no_hp_pemesan` | Snapshot nomor HP pemesan |
| `email_pemesan` | Snapshot email pemesan jika tersedia |
| `pengguna_id` | Akun penumpang terkait; boleh null untuk pelanggan tanpa akun |

Pemesanan mandiri:

```text
pengguna_id       = ID penumpang
dibuat_oleh       = null
sumber_pemesanan  = aplikasi_penumpang
```

Pemesanan loket tanpa akun:

```text
pengguna_id       = null
dibuat_oleh       = ID Petugas Loket
sumber_pemesanan  = loket
```

## 5. Pembuatan Akun Petugas

Admin membuat akun dari menu **Petugas Loket** pada web admin dengan data:

- nama lengkap;
- email aktif;
- nomor HP;
- status aktif/nonaktif.

Sistem membuat kata sandi acak yang tidak diketahui pengguna lalu mengirim tautan aktivasi melalui email. Petugas menentukan kata sandi sendiri melalui tautan tersebut. Saat akun dinonaktifkan, seluruh token mobile dicabut. Akun tidak dihapus agar riwayat pembuat pemesanan tetap dapat ditelusuri.

## 6. Autentikasi Mobile

Role yang diterima login mobile:

```text
penumpang      -> mobile-passenger
pengemudi      -> mobile-driver
petugas_loket  -> mobile-counter
```

Role `admin` ditolak oleh login mobile. Registrasi mobile tidak menyediakan pilihan Petugas Loket.

## 7. Endpoint Petugas Loket

```text
GET    /api/petugas-loket/dashboard
GET    /api/petugas-loket/jadwal
GET    /api/petugas-loket/jadwal/{jadwal}
GET    /api/petugas-loket/penumpang/search
POST   /api/petugas-loket/pemesanan/lock-form
POST   /api/petugas-loket/pemesanan/release-form-lock
POST   /api/petugas-loket/pemesanan/estimate
POST   /api/petugas-loket/pemesanan/baggage-photo
POST   /api/petugas-loket/pemesanan
GET    /api/petugas-loket/pemesanan
GET    /api/petugas-loket/pemesanan/{pemesanan}
PUT    /api/petugas-loket/pemesanan/{pemesanan}/payment
POST   /api/petugas-loket/pemesanan/{pemesanan}/confirm-cash
POST   /api/petugas-loket/pemesanan/{pemesanan}/refresh-payment-status
```

Semua endpoint dilindungi oleh:

```text
auth:sanctum
role:petugas_loket
token.ability:mobile-counter
throttle
```

## 8. UI Mobile

Petugas Loket menggunakan struktur halaman penumpang yang sama:

1. Beranda.
2. Rute dan jadwal.
3. Pemesanan.
4. Tiket.
5. Profil.

Perbedaan konteks yang wajib terlihat:

- label **Mode Petugas Loket**;
- statistik memakai pemesanan loket milik petugas;
- daftar pemesanan dan tiket hanya berasal dari transaksi loket milik petugas;
- form konfirmasi meminta data pemesan utama;
- payload memakai `counterMode` dan endpoint Petugas Loket.

Komponen detail jadwal, seat map, lokasi, bagasi, pembayaran, detail pemesanan, dan tiket tidak diduplikasi.

## 9. Aturan Booking

```text
Penumpang -----------┐
                     ├── BookingService dan transaksi database yang sama
Petugas Loket -------┘
```

Keduanya memakai validasi yang sama untuk:

- jadwal masih dapat dipesan;
- batas waktu form;
- kursi tersedia;
- kepemilikan seat lock;
- harga snapshot;
- titik jemput dan turun;
- barang bawaan;
- pembayaran;
- penerbitan tiket.

Untuk booking loket, `dikunci_oleh` dan `dibuat_oleh` berisi ID Petugas Loket. ID petugas tidak boleh disimpan sebagai `pengguna_id` karena petugas bukan penumpang.

## 10. Pembayaran dan Tiket

- Tunai tidak memanggil Pakasir, fee bernilai nol, dan tidak langsung dianggap lunas.
- Konfirmasi tunai hanya dapat dilakukan Petugas Loket pembuat transaksi dan harus idempotent.
- QRIS dan Virtual Account tetap memakai integrasi Pakasir existing.
- Tiket hanya diterbitkan setelah pembayaran berhasil.
- Satu detail penumpang menghasilkan satu tiket dan satu token QR unik.

## 11. Use Case yang Perlu Diperbarui

Tambahkan aktor **Petugas Loket** dengan use case:

- Masuk;
- Cari dan Pilih Jadwal;
- Buat Pemesanan Loket;
- Pilih dan Kunci Kursi;
- Isi Data Pemesan dan Penumpang;
- Pilih Metode Pembayaran;
- Konfirmasi Pembayaran Tunai;
- Cek Status Pembayaran Digital;
- Lihat dan Unduh Tiket Loket;
- Lihat Riwayat Pemesanan Loket;
- Kelola Profil dan Kata Sandi.

Pada aktor Admin tambahkan **Kelola Akun Petugas Loket**. Diagram pengemudi tidak memerlukan perubahan khusus karena booking loket masuk ke manifes yang sama.

## 12. Pengujian Wajib

- Petugas aktif dapat login mobile dan mendapat ability `mobile-counter`.
- Admin Web ditolak oleh login mobile.
- Penumpang/pengemudi ditolak dari endpoint loket.
- Petugas tidak dapat membuka web admin.
- Petugas tidak dapat membuka booking loket milik petugas lain.
- Booking tanpa akun dan dengan akun penumpang existing berhasil.
- Seat lock saling sinkron dengan alur penumpang.
- Konfirmasi tunai berulang tidak menggandakan tiket.
- Menonaktifkan akun mencabut seluruh token.
- Flutter analyze dan seluruh automated test lulus.
- Migrasi produksi hanya dijalankan setelah backup database.

## 13. Kriteria Selesai

Fitur selesai jika role dan hak akses terpisah dengan aman, UI mobile memakai komponen penumpang yang sama, booking loket tercatat dengan sumber serta petugas pembuat, pembayaran dan tiket tetap memakai logika existing, dan data historis tidak rusak.
