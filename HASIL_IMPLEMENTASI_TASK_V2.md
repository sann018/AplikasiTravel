# Hasil Implementasi Task V2 — Petugas Loket Mobile

## 1. Keputusan Final

- Admin tetap khusus web dan tidak diterima oleh login mobile.
- Dibuat peran internal `petugas_loket` untuk pelayanan pemesanan di kantor.
- Akun Petugas Loket dibuat dan dikendalikan Admin melalui web.
- Petugas Loket memakai halaman serta komponen mobile penumpang yang sama.
- Perbedaannya berada pada penanda **Mode Petugas Loket**, hak akses, endpoint, data pemesan, dan sumber transaksi.
- Tidak dibuat salinan logika booking; semua transaksi tetap memakai `BookingService`.

## 2. Perubahan Database

Migration role:

```text
backend/database/migrations/2026_08_10_000200_add_counter_staff_role_to_pengguna_table.php
```

Kolom `pengguna.peran` sekarang:

```text
enum('penumpang','pengemudi','petugas_loket','admin')
```

Migration dijalankan pada database lokal tanggal 10 Agustus 2026 sebagai batch 10. Sebelum migration dibuat backup:

```text
backupdatabase/aplikasi_travel_before_counter_staff_role_20260810_230659.sql
SHA-256: CF50302834545BF68F305D5CDDED044850DC01C465BFCC3859E565D013481003
```

Migration pemesanan loket `2026_08_09_000100_add_counter_booking_fields_to_pemesanan_table.php` sudah berstatus Ran dari implementasi sebelumnya. Dua migration lama yang tidak terkait tetap Pending dan tidak dijalankan:

```text
2026_07_25_000100_backfill_cash_payment_snapshots.php
2026_07_29_000100_add_cleanup_index_to_pemesanan_table.php
```

## 3. Pengelolaan Akun

Web admin memperoleh menu **Petugas Loket** untuk:

- menambah akun;
- mencari dan memfilter akun;
- mengubah nama, email, nomor HP, dan status;
- mengirim tautan aktivasi;
- menonaktifkan atau mengaktifkan akun.

Sistem tidak meminta Admin menentukan kata sandi petugas. Kata sandi awal diacak, lalu Petugas membuat kata sandinya sendiri melalui tautan aktivasi email. Penonaktifan akun mencabut semua token perangkat. Tidak tersedia aksi hapus agar identitas pembuat transaksi historis tetap tersimpan.

## 4. Autentikasi dan Authorization

```text
penumpang      -> mobile-passenger
pengemudi      -> mobile-driver
petugas_loket  -> mobile-counter
```

Route Petugas Loket dilindungi oleh Sanctum, `role:petugas_loket`, ability `mobile-counter`, dan throttle. Petugas hanya dapat membaca serta memproses booking loket yang dibuat oleh akunnya. Admin Web, penumpang, dan pengemudi tidak dapat memakai endpoint tersebut.

## 5. Endpoint

Prefix final:

```text
/api/petugas-loket
```

Endpoint menyediakan dashboard ringkas, jadwal, pencarian akun penumpang yang exact, seat lock, estimate, upload foto bagasi, create/list/detail booking, pemilihan metode, pemeriksaan status digital, serta konfirmasi tunai.

## 6. UI Flutter

Petugas Loket sekarang dirender melalui `TravSHomePage`, sama seperti penumpang. Halaman khusus Admin Mobile yang sebelumnya dibuat sementara telah dihapus.

Struktur halaman:

1. Beranda.
2. Rute.
3. Pesanan.
4. Tiket.
5. Profil.

Pada role Petugas Loket, UI menampilkan:

- label `Mode Petugas Loket`;
- statistik pemesanan loket;
- daftar pemesanan dan tiket milik petugas;
- judul kontekstual seperti `Pemesanan Loket`, `Tiket Loket`, dan `Profil Petugas`.

Detail jadwal, denah kursi, lokasi, barang bawaan, pembayaran, detail booking, dan tiket memakai komponen yang sama dengan penumpang. Nilai `counterMode` hanya menentukan endpoint serta data tambahan yang harus dikirim.

## 7. Pembayaran Tunai

Konfirmasi tunai tetap tersedia bagi:

- Petugas Loket melalui aplikasi mobile;
- Admin melalui web admin;
- Pengemudi melalui alur manifes yang sudah ada.

Setiap jalur menyimpan sumber konfirmasi berbeda. Proses bersifat idempoten sehingga klik ulang tidak menerbitkan tiket kedua. Pembayaran digital tetap menggunakan alur Pakasir existing.

## 8. Hasil Pengujian

```text
Backend Laravel : 310 test lulus, 1.999 assertion
Flutter test    : 84 test lulus
Dart analyze    : tidak ada masalah
Vite build      : berhasil
Composer        : composer.json valid
APK debug       : berhasil dibuat
```

APK:

```text
frontend/build/app/outputs/flutter-apk/app-debug.apk
```

Pengujian terfokus membuktikan:

- Petugas dapat login dengan ability yang benar;
- Admin ditolak oleh login mobile;
- Petugas tidak dapat membuka web admin;
- akun nonaktif kehilangan seluruh token;
- booking tanpa akun dan dengan akun penumpang existing berhasil;
- pemesanan petugas lain tidak dapat dibuka;
- konfirmasi tunai tidak menggandakan tiket;
- Flutter menampilkan Petugas melalui halaman penumpang dengan konteks mode berbeda.

## 9. Pengujian Manual yang Masih Diperlukan

Kontrol browser interaktif tidak tersedia pada sesi ini, sehingga tampilan web belum diklaim sudah diperiksa langsung di browser. Sebelum sidang lakukan uji manual pada:

- halaman Admin Web `Petugas Loket` pada zoom 100%;
- pembuatan akun dan penerimaan email aktivasi SMTP nyata;
- login Petugas pada LDPlayer;
- seat lock dua perangkat;
- pembayaran Pakasir sandbox;
- unduh/bagikan tiket pada perangkat Android.
