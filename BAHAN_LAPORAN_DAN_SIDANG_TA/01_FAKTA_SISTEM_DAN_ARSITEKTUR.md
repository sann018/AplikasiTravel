# Fakta Sistem dan Arsitektur RuslyTravel

## 1. Gambaran Umum

RuslyTravel merupakan sistem layanan travel antarkota yang terdiri atas aplikasi mobile berbasis
Flutter dan aplikasi web admin berbasis Laravel Blade. Aplikasi mobile dipakai oleh tamu,
penumpang, dan pengemudi, sedangkan web admin digunakan untuk mengelola data operasional dan
memantau transaksi. Kedua antarmuka memakai backend Laravel yang sama sebagai sumber data dan
aturan bisnis.

Sistem menangani proses pencarian jadwal, pemilihan serta penguncian kursi, pencatatan penumpang
dan barang bawaan, pemilihan titik jemput dan turun, pembayaran digital atau tunai, penerbitan
tiket elektronik, pemindaian QR, kegiatan perjalanan pengemudi, biaya operasional, notifikasi,
dan laporan admin.

## 2. Aktor Sistem

| Aktor | Hak Akses Utama |
|---|---|
| Tamu | Melihat beranda, informasi, rute, jadwal, dan detail jadwal tanpa dapat memesan |
| Penumpang | Membuat akun, memilih kursi, membuat pemesanan, membayar, melihat detail, membuka dan mengunduh tiket |
| Pengemudi | Melihat tugas perjalanan, manifes penumpang, memindai tiket, mencatat jemput/turun, mengonfirmasi tunai, dan mencatat biaya |
| Admin | Mengelola master data, jadwal, pemesanan, pembayaran, biaya, notifikasi, pengaturan, tiket, dan laporan |

Pembatasan peran dilakukan pada backend melalui middleware `role`, bukan hanya dengan
menyembunyikan menu di Flutter atau Blade. Route mobile juga memakai autentikasi Sanctum.

Sumber:

- [`backend/routes/api.php`](../backend/routes/api.php#L50)
- [`backend/routes/web.php`](../backend/routes/web.php#L42)
- [`backend/bootstrap/app.php`](../backend/bootstrap/app.php#L22)

## 3. Arsitektur Sistem

```text
┌──────────────────────┐       HTTPS/JSON API       ┌─────────────────────────┐
│ Aplikasi Flutter     │ ─────────────────────────> │ Backend Laravel         │
│ Tamu/Penumpang/Driver│ <───────────────────────── │ Route, Controller,      │
└──────────────────────┘                            │ Service, Model           │
                                                    └────────────┬────────────┘
┌──────────────────────┐       Web/Session          │            │
│ Web Admin Blade      │ ─────────────────────────> │            │
│ Bootstrap + travs-*  │ <───────────────────────── │            │
└──────────────────────┘                            │            ▼
                                                    │   Database relasional
                                                    │
                                                    ├── Pakasir API
                                                    ├── OpenStreetMap/Nominatim
                                                    └── Penyimpanan berkas
```

### 3.1 Lapisan Backend

1. **Route** menentukan endpoint dan middleware.
2. **Controller** memvalidasi request serta membentuk response.
3. **Service** menjalankan aturan bisnis yang dapat dipakai ulang, misalnya `BookingService`,
   `PakasirService`, dan `DriverAvailabilityService`.
4. **Model Eloquent** memetakan tabel dan relasi.
5. **Migration** menjadi sumber struktur database.
6. **Scheduler** menjalankan pembatalan otomatis, penutupan jadwal, dan pengingat keberangkatan.

Pemisahan tersebut dapat dijelaskan sebagai penerapan arsitektur berlapis. Logika transaksi
pemesanan tidak diletakkan di Flutter karena request mobile dapat dimodifikasi. Backend menghitung
ulang harga, memeriksa kursi, serta mengunci baris database sebelum menyimpan perubahan.

### 3.2 Lapisan Mobile

Flutter mengelompokkan kode berdasarkan fitur: autentikasi, beranda, jadwal, pemesanan,
pembayaran, lokasi, dan tiket. Kelas `TravSApiService` menangani komunikasi HTTP dan mengirim
token melalui header `Authorization: Bearer ...`.

Sumber:

- [`frontend/lib/core/services/travs_api_service.dart`](../frontend/lib/core/services/travs_api_service.dart#L29)
- [`frontend/lib/features/`](../frontend/lib/features/)

### 3.3 Lapisan Web Admin

Web admin menggunakan Laravel Blade, Vite, Bootstrap 5.3.3, Feather Icons, dan CSS `travs-*`.
Bootstrap menjadi fondasi grid, form, tombol, card, tabel, badge, dan responsivitas. Class
`travs-*` dipertahankan untuk identitas visual navy-oranye serta layout khusus RuslyTravel.

Sumber:

- [`backend/package.json`](../backend/package.json)
- [`backend/resources/css/app.css`](../backend/resources/css/app.css#L1)
- [`backend/resources/js/app.js`](../backend/resources/js/app.js#L1)
- [`backend/vite.config.js`](../backend/vite.config.js)

## 4. Teknologi yang Digunakan

| Komponen | Teknologi |
|---|---|
| Mobile | Flutter, Dart SDK 3.11.5 |
| Backend/API | PHP 8.3, Laravel 13 |
| Autentikasi API | Laravel Sanctum 4.3 |
| Web admin | Blade, Bootstrap 5.3.3, CSS kustom, JavaScript ringan |
| Build web | Vite 8 |
| Peta | `flutter_map`, OpenStreetMap, ArcGIS imagery |
| Lokasi | `geolocator`, `geocoding`, Nominatim |
| QR | `qr_flutter` untuk tampilan, `mobile_scanner` untuk pemindaian |
| Payment gateway | Pakasir Transaction API dan Transaction Detail API |
| Ekspor | CSV dan SpreadsheetML `.xls` |
| Database | Relasional melalui Eloquent/migration; konfigurasi contoh repository memakai SQLite |

Catatan akademik: jangan menulis bahwa database produksi pasti MySQL apabila deployment belum
diverifikasi. Repository saat ini menggunakan `DB_CONNECTION=sqlite` pada `.env.example`, walau
Laravel mendukung MySQL melalui konfigurasi.

## 5. Modul Aktif

### 5.1 Mobile Tamu

- bootstrap aplikasi dan branding;
- banner/informasi penting;
- daftar rute dan jadwal;
- detail jadwal serta informasi kursi;
- diarahkan masuk/daftar ketika ingin memesan.

### 5.2 Mobile Penumpang

- registrasi, verifikasi email, login, lupa kata sandi, profil;
- pencarian jadwal dan detail jadwal;
- pilih kursi dan kunci form;
- data penumpang, barang bawaan, titik jemput, serta titik turun;
- pembayaran QRIS, Virtual Account, atau tunai;
- daftar dan detail pemesanan;
- tiket per penumpang, QR, dan unduh gambar tiket;
- notifikasi dan informasi perjalanan.

### 5.3 Mobile Pengemudi

- halaman **Perjalanan** sebagai daftar tugas;
- detail perjalanan dan **Manifes Penumpang**;
- pemindaian tiket;
- penandaan jemput, hadir, turun, dan tidak hadir;
- konfirmasi pembayaran tunai;
- perubahan status berangkat dan selesai;
- pencatatan biaya operasional;
- pembaruan foto profil dan kendaraan yang ditugaskan.

### 5.4 Web Admin

- dasbor statistik dan ringkasan operasional;
- armada, pengemudi, rute, dan jadwal;
- detail jadwal dan pemesanan berhasil;
- monitoring pemesanan serta pembayaran;
- biaya operasional dan rekap;
- laporan pemesanan dan transaksi beserta ekspor;
- notifikasi admin dan pengguna;
- pengaturan sistem, akun, pembayaran, serta konten beranda;
- Monitor Tiket untuk audit QR.

## 6. Keamanan dan Konsistensi Data

- Input penting divalidasi di backend.
- Kata sandi disimpan dengan mekanisme hash Laravel.
- Route dilindungi autentikasi dan peran.
- Endpoint memakai rate limiter yang berbeda untuk read, action, upload, auth, dan webhook.
- Operasi yang mengubah beberapa tabel menggunakan `DB::transaction()`.
- Baris jadwal, kursi, pemesanan, pembayaran, dan tiket dikunci dengan `lockForUpdate()` ketika
  berisiko diproses bersamaan.
- Response error API distandarkan menjadi `success`, `message`, dan `errors`.
- API key Pakasir diambil dari konfigurasi environment dan tidak dikirim ke Flutter.

## 7. Batas Implementasi yang Harus Ditulis Jujur

1. **Pakasir masih bergantung pada konfigurasi proyek provider.** Nilai fee sandbox atau siapa
   penanggung fee tidak dapat ditetapkan hanya dari kode repository.
2. **Barang bawaan belum menambah tarif.** Data barang dan foto dicatat untuk operasional; nilai
   `biaya_item` saat pemesanan baru adalah nol.
3. **Laporan okupansi tidak aktif.** View dan fungsi controller lama masih ada, tetapi route serta
   menu tidak diaktifkan karena tidak termasuk proposal.
4. **`seat_layout` masih disimpan untuk kompatibilitas.** Pengaturan kursi aktif dilakukan pada
   jadwal, sedangkan model dapat membuat pola awal dari kapasitas kendaraan.
5. **Notifikasi bersifat internal database.** Kolom `token_push` tersedia, tetapi pengiriman push
   eksternal belum menjadi alur runtime utama.
6. **Target pengujian utama mobile adalah Android.** Dukungan platform lain berasal dari Flutter,
   tetapi tidak otomatis berarti sudah diuji setara.
7. **Migration backfill tunai lama masih pending.** Transaksi baru telah menyimpan snapshot dengan
   struktur baru, tetapi data tunai historis memerlukan migrasi terkontrol.

## 8. Narasi Akademik Siap Pakai

> Sistem RuslyTravel dirancang menggunakan arsitektur client-server. Aplikasi mobile Flutter dan
> web admin Laravel Blade berperan sebagai antarmuka, sedangkan backend Laravel menjadi pusat
> validasi, pengolahan transaksi, dan akses data. Pemisahan tersebut bertujuan menjaga konsistensi
> aturan bisnis karena harga, ketersediaan kursi, batas waktu, status pembayaran, dan penerbitan
> tiket tidak ditentukan oleh perangkat pengguna. Integrasi eksternal digunakan secara terbatas
> untuk pembayaran melalui Pakasir dan pemetaan melalui OpenStreetMap/Nominatim. Proses kritis
> dijalankan dalam transaksi database serta penguncian baris untuk mencegah data ganda ketika
> beberapa request berlangsung secara bersamaan.

## Pembaruan Arsitektur Real-Time — 27 Juli 2026

RuslyTravel kini menggunakan Laravel Reverb sebagai server WebSocket pada port lokal `6001`.
Perubahan model `KursiJadwal` diamati oleh `KursiJadwalObserver` setelah transaksi database
selesai. Observer memanggil `SeatAvailabilityRealtimeService`, kemudian event
`SeatAvailabilityChanged` dikirim ke channel publik `jadwal.{id}`.

Payload event hanya memuat ID jadwal dan waktu perubahan. Event tidak membawa nama, nomor HP,
kursi milik pengguna, atau data pribadi lainnya. Flutter berlangganan menggunakan
`web_socket_channel`; event hanya menjadi sinyal untuk mengambil seat map terbaru melalui API.
Dengan demikian API dan validasi backend tetap menjadi sumber kebenaran.

```text
perubahan kursi selesai di database
→ observer
→ event Reverb pada channel jadwal
→ Flutter menerima sinyal
→ Flutter memuat ulang kursi dari API
```

Kegagalan Reverb ditangkap oleh service dan dicatat sebagai peringatan. Booking tidak dibatalkan
hanya karena koneksi realtime gagal, sebab pengamanan utama tetap berada pada transaksi database,
locking, dan validasi status kursi.
