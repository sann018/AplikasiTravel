# Indeks Bahan Laporan dan Sidang RuslyTravel

Dokumen dalam folder ini disusun dari implementasi aktif proyek **AplikasiTravel/RuslyTravel** pada
27 Juli 2026. Sumber utama yang diperiksa adalah migration, model, service, controller, route,
Blade, kode Flutter, pengaturan paket, dan pengujian otomatis. Proposal dan laporan Tugas Akhir
dipakai untuk menyelaraskan urutan pembahasan, tetapi fakta teknis tetap mengikuti kode program.

## Tujuan

Paket ini dapat digunakan untuk:

1. melengkapi penjelasan BAB III, terutama perancangan basis data, UI/UX, algoritma, dan proses
   bisnis;
2. menjadi bahan penulisan BAB IV tentang implementasi dan pengujian;
3. mempelajari hubungan antarmodul sebelum sidang;
4. melacak potongan kode penting sampai ke file dan baris sumber;
5. membedakan fitur yang benar-benar aktif, fitur kompatibilitas, dan batas implementasi.

## Daftar Dokumen

| No. | Dokumen | Isi Utama |
|---:|---|---|
| 1 | [01_FAKTA_SISTEM_DAN_ARSITEKTUR.md](01_FAKTA_SISTEM_DAN_ARSITEKTUR.md) | Ruang lingkup, aktor, teknologi, arsitektur, dan batas implementasi |
| 2 | [02_PERANCANGAN_BASIS_DATA.md](02_PERANCANGAN_BASIS_DATA.md) | Penjelasan ERD, kardinalitas, struktur tabel, dan perubahan terhadap rancangan lama |
| 3 | [03_PERANCANGAN_ANTARMUKA_UI_UX.md](03_PERANCANGAN_ANTARMUKA_UI_UX.md) | Narasi akademik UI/UX tamu, penumpang, pengemudi, dan admin |
| 4 | [04_ALUR_KERJA_PENGGUNA.md](04_ALUR_KERJA_PENGGUNA.md) | Alur tamu, pemesanan penumpang, tugas pengemudi, dan pengelolaan admin |
| 5 | [05_LOGIKA_PERHITUNGAN_MATEMATIKA.md](05_LOGIKA_PERHITUNGAN_MATEMATIKA.md) | Rumus harga, fee, pendapatan, estimasi waktu, tarif historis, dan statistik |
| 6 | [06_POTONGAN_KODE_FITUR_UTAMA.md](06_POTONGAN_KODE_FITUR_UTAMA.md) | Penguncian kursi, batas waktu, lokasi, konflik jadwal, dan status perjalanan |
| 7 | [07_PEMBAYARAN_QR_TIKET_DAN_EXPORT.md](07_PEMBAYARAN_QR_TIKET_DAN_EXPORT.md) | Integrasi Pakasir, webhook, tiket QR, unduh tiket, dan ekspor laporan |
| 8 | [08_PENGUJIAN_TEMUAN_DAN_LATIHAN_SIDANG.md](08_PENGUJIAN_TEMUAN_DAN_LATIHAN_SIDANG.md) | Matriks uji, temuan audit, dan pertanyaan-jawaban sidang |
| 9 | [09_BATASAN_MASALAH_DAN_SARAN_PENGEMBANGAN.md](09_BATASAN_MASALAH_DAN_SARAN_PENGEMBANGAN.md) | Batas lokal, layanan eksternal, Reverb, FCM, lokasi, SMTP, dan saran pengembangan |
| 10 | [10_STATISTIK_DASBOR_DAN_LAPORAN.md](10_STATISTIK_DASBOR_DAN_LAPORAN.md) | Rumus, query, dan potongan kode seluruh card statistik yang aktif |
| 11 | [11_AUDIT_DEBUG_DAN_BLACKBOX_TESTING.md](11_AUDIT_DEBUG_DAN_BLACKBOX_TESTING.md) | Hasil audit, temuan sinkronisasi, dan pengujian black box sistem |
| 12 | [12_USE_CASE_DIAGRAM_DAN_DESKRIPSI_AKTOR.md](12_USE_CASE_DIAGRAM_DAN_DESKRIPSI_AKTOR.md) | Audit diagram, relasi use case, serta deskripsi Tamu, Penumpang, Pengemudi, dan Admin |
| 13 | [13_PANDUAN_KODE_SEMBILAN_FITUR_UTAMA.md](13_PANDUAN_KODE_SEMBILAN_FITUR_UTAMA.md) | Panduan kode aktual sembilan fitur utama, validasi penting, alur data, dan jawaban sidang |

## Cara Membaca

Urutan belajar yang disarankan:

1. baca arsitektur dan batas implementasi;
2. pahami ERD serta status data;
3. ikuti alur kerja setiap aktor;
4. pelajari rumus sebelum membaca potongan kode;
5. baca integrasi pembayaran dan tiket;
6. tutup dengan pengujian serta latihan jawaban sidang.

## Aturan Kebenaran Data

Jika terdapat perbedaan antara proposal, gambar ERD, dokumentasi lama, dan kode, gunakan urutan
prioritas berikut:

1. migration yang berstatus `Ran`;
2. model dan service yang dipanggil route aktif;
3. controller dan response API;
4. tampilan Flutter/Blade;
5. test otomatis;
6. dokumentasi atau proposal.

Urutan tersebut digunakan karena laporan harus menggambarkan sistem yang benar-benar
diimplementasikan.

## Arti Penanda

- **Aktif**: mempunyai route/alur pemanggilan dan digunakan aplikasi.
- **Kompatibilitas**: masih dipertahankan agar data atau client lama tidak rusak.
- **Belum terverifikasi eksternal**: bergantung pada layanan di luar repository, misalnya nilai fee
  dari proyek Pakasir.
- **Temuan audit**: kondisi nyata yang perlu dijelaskan atau diuji lagi, bukan fakta yang boleh
  ditutupi.

## Catatan Migration

Hasil `php artisan migrate:status` menunjukkan seluruh migration struktur pembayaran terbaru
sudah `Ran`, termasuk kolom snapshot nominal, fee, dan total. Migration
`2026_07_25_000100_backfill_cash_payment_snapshots.php` masih `Pending`. Migration tersebut hanya
mengisi snapshot transaksi tunai lama dan tidak diperlukan untuk transaksi baru, tetapi perlu
backup serta persetujuan sebelum diterapkan pada data lama.

## Pembaruan 27 Juli 2026

- Laravel Reverb aktif pada port lokal `6001` untuk memberi sinyal perubahan kursi.
- Flutter mengambil ulang data kursi dari API setelah menerima event dan tetap memakai polling
  sebagai fallback.
- Pada Windows, `composer run dev` tidak lagi menjalankan Laravel Pail karena ekstensi `pcntl`
  tidak tersedia.
- Card rute terpopuler membedakan jumlah pemesanan selesai dan jumlah penumpang tanpa menambah
  kolom database.
- Penjelasan lengkap statistik admin tersedia pada dokumen nomor 10.
