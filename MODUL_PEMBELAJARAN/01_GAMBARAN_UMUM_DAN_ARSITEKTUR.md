# 01 — Gambaran Umum & Arsitektur Sistem

> Tujuan bab ini: kamu paham **apa** yang dibangun, **siapa** penggunanya, **teknologi** apa
> yang dipakai, dan **bagaimana** semua bagian saling terhubung. Ini fondasi sebelum masuk ke kode.

---

## 1.1 Apa Itu RuslyTravel?

RuslyTravel adalah **aplikasi pemesanan jasa transportasi umum travel lintas kota**. Bayangkan
sebuah perusahaan travel yang melayani rute antar kota (misalnya Pontianak - Sintang). Sebelum ada
aplikasi ini, pemesanan biasanya lewat telепон atau catat manual. Aplikasi ini memindahkan seluruh
proses itu ke digital:

- Penumpang bisa **lihat jadwal, pilih kursi, dan bayar** lewat HP.
- Pengemudi bisa **melihat daftar penumpang (Manifes) dan memvalidasi tiket** lewat HP.
- Admin bisa **mengelola rute, armada, jadwal, dan laporan** lewat komputer.

Di dalam kode, aplikasi ini diberi nama merek internal **"TravS"**. Jadi kalau kamu lihat kata
`TravS`, `travs`, atau `travs-` di kode, itu maksudnya aplikasi ini. Nama yang tampil ke pengguna
adalah "RuslyTravel" (bisa diatur admin lewat menu Pengaturan).

---

## 1.2 Empat Peran Pengguna (Sangat Penting)

Sistem ini punya **4 peran**. Kamu harus hafal ini karena sering ditanya saat sidang.

### a. Tamu (Guest)
Pengguna yang **belum login**. Bisa membuka aplikasi mobile dan **melihat-lihat rute serta jadwal**,
tapi **tidak bisa memesan**. Begitu mau memesan, dia diminta login/daftar dulu.

- Bukti kode: di Flutter ada `AppRolePreview.guest` (file `frontend/lib/core/models/app_role_preview.dart`).
- Backend menyediakan endpoint publik `/api/guest/jadwal` (lihat `GuestController.php`).

### b. Penumpang (Passenger)
Pengguna terdaftar yang **memesan tiket**. Fitur: lihat jadwal, pilih kursi, isi data penumpang &
titik jemput/turun, bayar, lihat tiket QR, lihat riwayat, kelola profil.

- Peran ini disimpan di kolom `peran` = `'penumpang'` pada tabel `pengguna`.
- Saat daftar lewat mobile, akun **selalu** dibuat sebagai penumpang (lihat `AuthController.php`).

### c. Pengemudi (Driver)
Sopir yang menjalankan perjalanan. Fitur: lihat perjalanan yang ditugaskan, lihat Manifes
(daftar penumpang), scan QR tiket, tandai penumpang dijemput/turun, konfirmasi pembayaran tunai,
catat biaya operasional.

- Peran `peran` = `'pengemudi'`. Data tambahan (nomor SIM, dll) ada di tabel `pengemudi`.
- Akun pengemudi **dibuat oleh admin**, bukan daftar sendiri.

### d. Admin
Pengelola sistem. Fitur: kelola rute, armada, pengemudi, jadwal, pemesanan, pembayaran, biaya
operasional, laporan, notifikasi, banner, dan pengaturan sistem.

- Peran `peran` = `'admin'`.
- Admin **hanya bisa lewat web**. Kalau akun admin coba login di aplikasi mobile, **ditolak**
  (lihat `travs_auth_service.dart` — ada pengecekan `session.user.isAdmin`).

> **Poin sidang:** kalau ditanya "kenapa admin tidak bisa login di mobile?", jawabannya: karena
> secara desain admin bekerja di panel web yang layarnya lebih luas untuk mengelola data, dan
> aplikasi mobile sengaja dibatasi hanya untuk penumpang & pengemudi.

---

## 1.3 Teknologi yang Dipakai

| Bagian | Teknologi | Penjelasan singkat |
|--------|-----------|--------------------|
| Backend (otak sistem) | **Laravel 13** (PHP 8.3) | Mengurus database, aturan bisnis, dan menyediakan API |
| Autentikasi API | **Laravel Sanctum** | Memberi "token" ke aplikasi mobile setelah login |
| Database | **MySQL** | Tempat semua data disimpan (nama database: `aplikasi_travel`) |
| Frontend mobile | **Flutter** (Dart) | Aplikasi HP untuk penumpang & pengemudi |
| Web admin | **Blade + CSS custom + Bootstrap** | Halaman web yang dirender server untuk admin |
| Payment gateway | **Pakasir** (mode sandbox) | Layanan pembayaran QRIS/virtual account online |

### Library/package penting

**Backend (`backend/composer.json`):**
- `laravel/framework` ^13.0 — kerangka utama
- `laravel/sanctum` ^4.3 — token login API
- `laravel/tinker` — alat uji coba kode di terminal

**Frontend (`frontend/pubspec.yaml`):**
- `http` — memanggil API backend
- `shared_preferences` — menyimpan sesi login & draft form di HP
- `qr_flutter` — menggambar QR code tiket
- `mobile_scanner` — memindai QR (dipakai pengemudi)
- `flutter_map` + `latlong2` + `geolocator` + `geocoding` — peta & lokasi jemput/turun
- `image_picker` — ambil/pilih foto (bagasi, profil)
- `google_fonts` — font Plus Jakarta Sans
- `url_launcher` — buka Google Maps / link checkout pembayaran
- `permission_handler` — minta izin kamera/notifikasi
- `intl` — format tanggal & angka gaya Indonesia

> **Catatan penting:** aplikasi Flutter ini **tidak memakai** package state management seperti
> Provider/Bloc/Riverpod. Ia memakai cara paling dasar Flutter yaitu `StatefulWidget` + `setState`.
> Ini bagus untuk dijelaskan sebagai pilihan yang sederhana dan mudah dipahami.

---

## 1.4 Arsitektur Besar (Bagaimana Semua Terhubung)

Bayangkan sistem ini seperti restoran:

```
        ┌───────────────────────┐          ┌───────────────────────┐
        │   APLIKASI MOBILE      │          │     WEB ADMIN          │
        │   (Flutter / Dart)     │          │   (Blade + CSS)        │
        │  Penumpang & Pengemudi │          │       Admin            │
        └───────────┬───────────┘          └───────────┬───────────┘
                    │  minta data lewat                 │  buka halaman
                    │  REST API (JSON)                  │  web biasa
                    ▼                                   ▼
        ┌───────────────────────────────────────────────────────────┐
        │                    BACKEND LARAVEL                          │
        │  Route → Controller → Service → Model → Database            │
        │  (aturan bisnis, validasi, perhitungan, keamanan)           │
        └───────────────────────────┬───────────────────────────────┘
                                     ▼
                          ┌────────────────────┐
                          │   DATABASE MySQL    │
                          │  (semua data travel)│
                          └────────────────────┘
```

Penjelasan dengan analogi restoran:
- **Aplikasi mobile & web admin** = pelanggan/pelayan yang memesan.
- **API** = kertas pesanan yang dikirim ke dapur.
- **Backend Laravel** = dapur yang memasak (mengolah aturan bisnis).
- **Database** = gudang bahan (tempat data disimpan).

### Alur satu permintaan (contoh: penumpang membuka daftar jadwal)

1. Penumpang buka halaman di aplikasi Flutter.
2. Flutter memanggil API: `GET /api/guest/jadwal` (lewat file `travs_api_service.dart`).
3. Laravel menerima di **Route** (`backend/routes/api.php`).
4. Route mengarahkan ke **Controller** (`GuestController@...`).
5. Controller mengambil data lewat **Model** (`Jadwal`) dari **Database**.
6. Controller membungkus jadi JSON dan mengirim balik.
7. Flutter menerima JSON, mengubahnya jadi objek Dart, lalu menampilkan di layar.

> **Poin sidang:** ini pola **MVC + Service Layer**. Route menerima permintaan, Controller
> mengatur alur, Service menampung logika bisnis yang rumit, Model mewakili tabel database.

---

## 1.5 Konsep Pola MVC + Service Layer (Backend)

Laravel memakai pola **MVC** (Model-View-Controller), dan project ini menambahkan **Service Layer**:

- **Model** (`backend/app/Models/`) — mewakili satu tabel database. Contoh: `Pemesanan.php`
  mewakili tabel `pemesanan`. Di sini juga ditulis relasi antar tabel.
- **View** (`backend/resources/views/`) — tampilan HTML untuk admin (file `.blade.php`). Aplikasi
  mobile TIDAK memakai View ini; mobile hanya menerima JSON.
- **Controller** (`backend/app/Http/Controllers/`) — penerima permintaan, pengatur alur.
- **Service** (`backend/app/Services/`) — "otak" logika bisnis yang rumit, dipisah agar Controller
  tidak terlalu gemuk. Contoh: `BookingService.php` berisi seluruh logika pemesanan & pembayaran.

Kenapa dipisah Service? Supaya logika rumit (misalnya mengunci kursi, menghitung harga, memproses
pembayaran) tidak menumpuk di Controller. Ini membuat kode lebih rapi dan mudah diuji.

---

## 1.6 Ringkasan Alur Bisnis Utama (End-to-End)

Ini "cerita besar" aplikasi. Hafalkan alurnya, detail kodenya ada di file `04` dan `05`.

1. **Tamu melihat jadwal** — buka aplikasi, lihat daftar rute & jadwal tanpa login.
2. **Daftar / Login** — untuk memesan, tamu jadi penumpang lewat registrasi (dengan verifikasi
   email OTP) atau login.
3. **Pilih jadwal & kursi** — penumpang pilih jadwal, lalu memilih kursi. Kursi yang dipilih
   **dikunci sementara** (seat lock) supaya tidak direbut orang lain saat mengisi form.
4. **Isi data** — nama penumpang tiap kursi, titik jemput & titik turun (pakai peta), barang bawaan.
5. **Pilih metode bayar** — QRIS, virtual account (lewat Pakasir), atau Tunai (bayar di kendaraan).
6. **Bayar** — kalau QRIS/virtual account, penumpang diarahkan ke halaman pembayaran Pakasir. Kalau Tunai,
   pembayaran dikonfirmasi nanti oleh pengemudi/admin.
7. **Tiket terbit** — begitu pembayaran berhasil, sistem menerbitkan **tiket elektronik ber-QR**
   untuk tiap kursi.
8. **Hari keberangkatan** — pengemudi membuka Manifes, memanggil penumpang, **scan QR** untuk
   validasi, menandai dijemput & diturunkan.
9. **Selesai** — setelah semua turun/perjalanan selesai, status jadwal & pemesanan jadi "selesai".
10. **Laporan** — admin melihat laporan pemesanan & transaksi, serta mencatat biaya operasional.

---

## 1.7 Istilah Penting (Kamus Cepat)

| Istilah Indonesia (di kode) | Arti |
|------------------------------|------|
| `pengguna` | User / akun |
| `peran` | Role (tamu/penumpang/pengemudi/admin) |
| `rute` | Trayek antar kota (asal → tujuan) |
| `kendaraan` | Armada / mobil |
| `pengemudi` | Sopir |
| `jadwal` | Jadwal keberangkatan (gabungan rute + armada + sopir + waktu) |
| `kursi_jadwal` | Kursi untuk satu jadwal tertentu |
| `pemesanan` | Booking / order |
| `detail_pemesanan` | Data tiap penumpang dalam satu pemesanan |
| `pembayaran` | Transaksi pembayaran |
| `tiket_elektronik` | E-ticket ber-QR |
| `item_bagasi` | Barang bawaan |
| `biaya_operasional_perjalanan` | Pengeluaran sopir (bensin, tol, dll) |
| `notifikasi` | Pemberitahuan |
| `seat lock` | Penguncian kursi sementara agar tak direbut |
| `manifes` | Daftar penumpang yang harus dilayani sopir dalam satu trip |

---

## 1.8 Pembaruan Arsitektur per 25 Juli 2026

Alur transaksi sekarang mempunyai dua fase waktu: lock kursi ketika form diisi dan lock pembayaran setelah draft booking dibuat. Keduanya diproses oleh `BookingService` di backend agar deadline tidak hanya bergantung pada timer Flutter. Scheduler Laravel membatalkan booking kedaluwarsa setiap menit serta menutup jadwal H-1 jam sebelum berangkat.

Pembayaran digital melalui Pakasir menyimpan snapshot nominal transaksi, biaya admin provider, total yang dibayar penumpang, serta nomor pembayaran bila tersedia. Dengan demikian, perubahan harga rute atau biaya provider tidak mengubah laporan transaksi lama.

Notifikasi admin mencakup pengingat jadwal, kapasitas hampir penuh, dan event armada telah berangkat. Referensi notifikasi dibedakan antara booking dan jadwal sebelum tombol detail ditampilkan.

> **Catatan jujur:** Sanctum digunakan untuk token API dan token dihapus saat logout/reset password. Masa kedaluwarsa token eksplisit belum tampak pada source yang diaudit, jadi jangan menyebut token otomatis expired tanpa konfigurasi tambahan.

Lanjut ke `02_STRUKTUR_DAN_SETUP.md` untuk mengenal struktur folder dan cara menjalankan project.
