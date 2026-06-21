# Cheatsheet Sidang Tugas Akhir — AplikasiTravel (Rusly Travel)

> **Untuk siapa**: Mahasiswa pengerjaan tugas akhir AplikasiTravel
> **Cara pakai**: Baca berulang sebelum sidang, jangan dihapal mentah, pahami logikanya
> **Tanggal**: 16 Juni 2026
> **Status verifikasi**: 68 backend test PASS · 11 Flutter test PASS · flutter analyze clean

---

## Bagian 1 — Identitas Project Singkat

| Aspek | Jawaban |
|---|---|
| Judul | AplikasiTravel — Sistem Pemesanan Travel Berbasis Mobile dan Web Admin |
| Studi kasus | Rusly Travel (Pontianak — Sanggau, Kalimantan Barat) |
| Tujuan | Mempermudah pemesanan tiket travel, manajemen pengemudi, dan pelaporan operasional |
| Lingkup pengguna | Penumpang (mobile), Pengemudi (mobile), Admin (web) |
| Durasi pengerjaan | ~3 bulan |
| Status saat sidang | Fitur inti selesai, testing lulus, siap demo lokal |

### Statistik Project

| Metrik | Nilai ringkas |
|---|---|
| Endpoint API | 30+ endpoint |
| Backend test | 68 test |
| Flutter test | 11 test |
| Tabel utama | 14 tabel |

---

## Bagian 2 — Tech Stack Ringkas (HAPAL INI)

```
Backend:
- PHP 8.3
- Laravel 13 (framework backend, include Blade template engine)
- SQLite (database)
- Sanctum (API authentication)
- Pakasir (payment gateway integration)

Web Admin:
- Laravel + Blade (server-side rendering)
- Bootstrap 5 (CSS framework)

Mobile App:
- Flutter SDK + Dart
- flutter_local_notifications (notifikasi)
- mobile_scanner (scan QR)
- google_maps_flutter (peta + GPS)
- image_picker (upload foto bagasi)
- http (REST API client)
```

**Saat sidang cukup bilang**: "Backend pakai Laravel, web admin pakai Bootstrap 5, mobile pakai Flutter."

---

## Bagian 3 — Flow Bisnis Utama (WAJIB PAHAM)

### 3.1 Flow Pemesanan Penumpang

```
1. User buka app → login / lanjut sebagai tamu
2. Pilih rute (Pontianak → Sanggau) di tab Pilih Jadwal
3. Pilih tanggal & jam dari date/time picker
4. Pilih jadwal yang tersedia → halaman Pemesanan Jadwal
5. Pilih kursi di denah kursi mobil → kursi terkunci 30 menit
6. Isi biodata + jumlah bagasi + foto bagasi + titik jemput (GPS/peta)
7. Submit → masuk halaman Pembayaran (status: Menunggu Pembayaran)
8. Pilih metode (QRIS / Bank VA / Tunai)
9. Bayar lewat aplikasi bank (manual transfer ke VA) ATAU klik Simulasi Bayar
10. Setelah terbayar → tiket QR otomatis terbit
11. Driver scan QR saat penumpang naik → status tiket "digunakan"
```

### 3.2 Konsep Kunci yang Wajib Dipahami

#### A. Seat Lock 2 Tahap

```
Tahap 1: Form Lock (30 menit)
  - User pilih kursi → kursi dikunci untuk user tersebut
  - User punya waktu 30 menit isi form & submit
  - Kalau timeout → kursi dilepas otomatis lewat scheduler

Tahap 2: Payment Lock (5 menit)
  - User submit booking → kursi di-lock untuk pembayaran
  - User punya 5 menit bayar
  - Kalau timeout → booking dibatalkan, kursi dilepas

Kenapa perlu? Mencegah double booking saat 2 user pesan kursi yang sama bersamaan.
File: backend/app/Services/BookingService.php
```

#### B. Payment Lifecycle

```
menunggu_pembayaran → terbayar → tiket terbit → digunakan
       ↓
   kedaluwarsa (kalau tidak bayar dalam 5 menit)
       ↓
   gagal (kalau Pakasir webhook kasih status gagal)
       ↓
   dibatalkan (admin batalin manual)
```

Lifecycle ini **idempotent** — webhook Pakasir bisa dipanggil berulang tanpa duplikasi tiket.

#### B.1 Mode Simulasi Pakasir

Untuk demo sidang, sistem memakai **mode simulasi Pakasir lokal**, bukan transaksi nyata dengan callback publik.

Alasannya:

- demo bisa berjalan stabil tanpa menunggu server publik, domain, atau tunnel
- tidak bergantung kondisi internet dan dashboard payment gateway saat sidang
- flow bisnis tetap sama: booking dibuat, pembayaran diproses, status menjadi terbayar, lalu tiket QR diterbitkan
- credential Pakasir tetap tidak pernah ditaruh di Flutter
- webhook signature verification belum diperlukan karena mode demo tidak menerima callback publik dari pihak luar

Kalimat aman saat sidang:

> "Pada demo ini saya memakai simulasi pembayaran lokal agar alur bisa diuji stabil di laptop. Konsep integrasinya tetap sama: backend menjadi penghubung payment gateway, lalu setelah pembayaran valid sistem menerbitkan tiket digital."

#### C. QR Ticket One-Time Use

```
- Setiap kursi yang sudah dibayar → terbit 1 tiket QR
- QR berisi token random panjang (bukan kode booking biasa)
- Driver scan QR → status tiket berubah "dipindai"
- Tiket yang sudah dipindai TIDAK BISA dipakai 2x (one-time use)
- Mencegah penumpang share tiket ke orang lain
```

#### D. Role-Based Access

```
Penumpang  → bisa pesan, bayar, lihat tiket
Pengemudi  → bisa lihat manifest, scan QR, update status perjalanan
Admin      → CRUD kendaraan/pengemudi/rute/jadwal, verifikasi cash, laporan

API guard: middleware('role:penumpang|pengemudi|admin')
```

#### E. Polling Notifikasi (pengganti FCM)

```
- Mobile app polling ke backend tiap 30 detik
- Cek tabel notifikasi user yang sedang login
- Kalau ada notif baru → tampilkan via flutter_local_notifications
- Kelebihan: tidak butuh Firebase, tidak butuh credential berbayar
- Kekurangan: notif tidak masuk saat app tertutup penuh
```

---

## Bagian 4 — Pertanyaan Umum + Jawaban Siap Pakai

### 4.1 Pertanyaan Tools & Framework

#### Q: "Framework apa yang kamu pakai untuk web admin?"
> **A**: "Web admin saya pakai Laravel sebagai backend framework dan Bootstrap 5 untuk styling komponen UI."

#### Q: "Kenapa pilih Laravel, bukan Spring Boot atau Express?"
> **A**: "Laravel cocok untuk skala bisnis kecil-menengah seperti Rusly Travel. Punya ecosystem yang lengkap (Eloquent ORM, Sanctum auth, Blade), dokumentasi bahasa Indonesia banyak, dan saya sudah familiar."

#### Q: "Kenapa Flutter, bukan native Android?"
> **A**: "Flutter mendukung cross-platform (Android + iOS) dengan satu codebase. Untuk skala UMKM seperti Rusly Travel, ini lebih efisien. Kalau pakai native Android saja, suatu saat butuh iOS harus mulai dari nol."

#### Q: "Kenapa SQLite, bukan MySQL/PostgreSQL?"
> **A**: "Untuk skala bisnis Rusly Travel saat ini, SQLite cukup. SQLite zero-config, file-based, mudah di-backup. Migrasi ke MySQL/PostgreSQL bisa dilakukan kapan saja karena Eloquent ORM bersifat database-agnostic."

#### Q: "Apakah pakai theme jadi atau bikin sendiri?"
> **A**: "Untuk web admin saya pakai Mazer template open-source berlisensi MIT sebagai starting point, lalu kustomisasi warna brand TravS. Untuk mobile saya pakai Material Design dari Flutter dengan tema warna sendiri."

### 4.2 Pertanyaan Logic Bisnis

#### Q: "Tantangan terbesar saat development?"
> **A**: "Implementasi seat locking. Awalnya saya hanya pakai 1 lock, ternyata bisa terjadi race condition kalau 2 user submit form bersamaan. Akhirnya saya bagi 2 tahap: form lock 30 menit dan payment lock 5 menit. Sekarang sudah aman dari double booking."

#### Q: "Bagaimana mencegah double booking?"
> **A**: "Pakai database transaction + row-level lock di tabel kursi_jadwal. Saat user submit, status kursi di-update dengan kondisi `WHERE status = 'tersedia'`. Kalau ada user lain yang sudah pesan duluan, query akan return 0 affected rows dan booking ditolak."

#### Q: "Bagaimana flow payment dengan Pakasir?"
> **A**: "Untuk demo sidang saya pakai mode simulasi Pakasir lokal. User klik bayar → backend membuat data pembayaran → tombol simulasi memvalidasi pembayaran → sistem update status menjadi terbayar dan menerbitkan tiket QR. Kalau nanti production, konsepnya tetap sama, hanya sumber validasinya dari callback/payment gateway."

#### Q: "Kalau Pakasir down/error, bagaimana?"
> **A**: "Ada 3 mekanisme fallback:
> 1. Retry otomatis saat user klik Cek Status Pembayaran
> 2. Admin bisa verifikasi manual untuk pembayaran tunai
> 3. Mode simulasi lokal untuk testing tanpa Pakasir"

#### Q: "Kenapa tidak langsung transaksi nyata Pakasir?"
> **A**: "Karena fokus tugas akhir adalah membuktikan alur sistem pemesanan sampai tiket digital. Transaksi nyata membutuhkan callback publik, domain/tunnel yang stabil, dan konfigurasi production. Untuk demo lokal, simulasi lebih aman dan konsisten, tetapi flow backend tetap menunjukkan konsep payment gateway."

#### Q: "Apakah perlu webhook signature verification?"
> **A**: "Untuk mode simulasi lokal tidak perlu, karena sistem tidak menerima callback publik dari luar. Signature verification baru relevan saat masuk production webhook sungguhan."

#### Q: "Kenapa tiket QR satu kali pakai?"
> **A**: "Mencegah penumpang share tiket ke orang lain. Tanpa one-time use, satu QR bisa dipakai 5 orang naik mobil yang sama padahal cuma bayar 1 kursi."

### 4.3 Pertanyaan AI & Pengerjaan

#### Q: "Apakah pakai AI dalam pengerjaan?"
> **A**: "Iya, saya pakai AI sebagai tools bantu untuk hal-hal seperti boilerplate code, debug error message, saran best practice, dan generate test case. Tapi logic bisnis utama seperti seat locking, payment lifecycle, dan QR ticket flow saya rancang sendiri berdasarkan analisis kebutuhan Rusly Travel. AI saya pakai sebagai asisten coding sama seperti Stack Overflow dan dokumentasi resmi."

#### Q: "Tampilan terlalu bagus untuk 3 bulan, jangan-jangan kerjaan AI?"
> **A**: "Tampilan admin saya pakai template open-source Mazer yang sudah punya 90% styling. Tampilan mobile pakai Material Design bawaan Flutter. Untuk development saya kombinasikan tutorial Laravel/Flutter, dokumentasi resmi, Stack Overflow, dan AI assistant. Kontribusi murni saya di logic bisnis dan integrasi sistem. Saya bisa demonstrasikan flow-nya jika diminta."

#### Q: "Kenapa kerjaan kamu lengkap padahal cuma 3 bulan?"
> **A**: "Saya banyak pakai library/package yang sudah jadi: Laravel Sanctum untuk auth, mobile_scanner untuk scan QR, google_maps_flutter untuk peta, dll. Kalau bikin semua dari nol memang tidak cukup. Tapi pilih library yang tepat dan integrasikan dengan benar adalah skill tersendiri."

### 4.4 Pertanyaan Batasan & Saran Pengembangan

#### Q: "Kenapa tidak pakai push notification (FCM)?"
> **A**: "Untuk skala bisnis Rusly Travel sebagai bisnis lokal, polling cukup memadai. Notifikasi yang penting (pembayaran berhasil, tiket terbit) muncul saat user aktif buka app. Polling juga lebih hemat: tidak butuh layanan eksternal Google FCM, tidak butuh credential berbayar. Migrasi ke FCM gampang nanti karena event trigger di backend sudah dipisah ke tabel notifikasi, tinggal tambah listener yang kirim ke FCM saat deployment produksi."

#### Q: "Bagaimana keamanan data saat transit?"
> **A**: "Untuk produksi, HTTPS diaktifkan via reverse proxy Nginx + Let's Encrypt. Kode aplikasi tidak perlu perubahan karena mengikuti konfigurasi APP_URL di environment. Untuk demo sidang ini saya jalankan via HTTP lokal."

#### Q: "Backup database?"
> **A**: "Sudah disiapkan command artisan `travs:backup-database` yang dijadwalkan harian via Laravel Scheduler. Setiap backup disimpan ke folder storage/app/backups/ dengan retention 7 file terbaru. Untuk produksi tinggal pasang cron job di server."

#### Q: "Apa saran pengembangan ke depan?"
> **A**: "Yang sudah saya tulis di laporan:
> 1. Push notification FCM untuk perangkat real saat app tertutup
> 2. Verifikasi nomor HP via OTP SMS
> 3. Email verification saat registrasi
> 4. Two-factor authentication untuk admin
> 5. Integrasi WhatsApp Business API sebagai fallback channel
> 6. Real-time dashboard monitoring untuk admin"

### 4.5 Pertanyaan Demo

#### Q: "Tunjukkan flow pemesanan dari awal sampai tiket terbit."
> **Demo step**:
> 1. Buka app di emulator → login sebagai penumpang
> 2. Pilih jadwal Pontianak → Sanggau
> 3. Pilih kursi (tunjukkan denah kursi mobil)
> 4. Isi biodata + foto bagasi + pickup
> 5. Pilih metode QRIS
> 6. Klik **Simulasi Bayar** (jelaskan: ini untuk demo, di produksi user transfer manual)
> 7. Tampilkan tiket QR
> 8. Switch ke akun pengemudi → scan QR → tiket "digunakan"

#### Q: "Tunjukkan handling error saat user salah input."
> **Demo step**:
> 1. Login dengan password salah → tampil pesan error
> 2. Submit booking tanpa pilih kursi → tampil validasi
> 3. Submit booking tanpa upload foto bagasi → tampil validasi

---

## Bagian 5 — Persiapan Mental Sidang

### 5.1 Yang HARUS Kamu Hindari

| Don't | Kenapa |
|---|---|
| ❌ "Saya buat semua dari nol" | Bohong, dan ketahuan saat ditanya detail |
| ❌ "AI tidak saya pakai sama sekali" | Bohong kalau memang pakai |
| ❌ "Mazer ini saya yang bikin" | Bohong fatal, MIT license bisa dicek |
| ❌ "Bootstrap saya yang bikin" | Bohong fatal, framework open-source terkenal |
| ❌ Defensif saat ditanya | Penguji jadi curiga |
| ❌ Sok tahu jawab pertanyaan yang tidak yakin | Mending jujur "saya kurang yakin, akan saya pelajari lagi" |

### 5.2 Yang AMAN Kamu Lakukan

| Do | Kenapa |
|---|---|
| ✅ Akui pakai template/library jadi | Standar industri, tidak pelanggaran |
| ✅ Akui pakai AI sebagai tools | Norma baru 2026, asal paham yang dibuat |
| ✅ Highlight kontribusi di logic bisnis | Itu yang dinilai |
| ✅ Demo dengan percaya diri | Latihan dulu di rumah |
| ✅ Jelaskan trade-off setiap keputusan | Tunjukkan kamu paham consequence |
| ✅ Bawa catatan kecil flow penting | Tidak masalah, asal tidak baca semua |

### 5.3 Saat Ditanya yang Kamu Tidak Tahu

Jangan panik. Jawaban template:

> "Untuk pertanyaan ini saya kurang yakin Pak/Bu. Yang saya pahami adalah [jelasin sebatas yang kamu tahu]. Untuk detail lebih lanjut saya akan pelajari lagi setelah sidang."

Penguji **lebih suka** jawaban jujur daripada karangan yang ketahuan bohong.

---

## Bagian 6 — Checklist Sebelum Sidang

### 6.1 Latihan Mandiri

- [ ] Demo flow pemesanan dari awal sampai tiket terbit (target: < 5 menit)
- [ ] Demo flow scan QR pengemudi
- [ ] Demo flow admin verifikasi cash payment
- [ ] Latihan modify code live (tambah field, ubah warna, hapus field)
- [ ] Hafal istilah teknis di tabel database (pemesanan, detail_pemesanan, kursi_jadwal, dll)

### 6.2 Persiapan Teknis

- [ ] Backend Laravel jalan tanpa error (`php artisan serve`)
- [ ] Mobile app build sukses di emulator/HP fisik
- [ ] Test akun siap pakai:
    - Admin: dari `DatabaseSeeder.php`
    - Penumpang: bisa registrasi baru
    - Pengemudi: dari seeder
- [ ] Database fresh dengan data dummy yang masuk akal
- [ ] Slide presentasi siap (minimal 10 slide)
- [ ] PANDUAN_DEMO_LOKAL.md dipelajari (untuk troubleshoot saat demo)

### 6.3 Persiapan Mental

- [ ] Tidur cukup malam sebelumnya
- [ ] Pakaian rapi sesuai aturan kampus
- [ ] Datang lebih awal 30 menit sebelum jadwal
- [ ] Bawa cadangan laptop/charger
- [ ] Bawa kabel HDMI/adapter untuk proyektor
- [ ] Bernapas dalam-dalam sebelum mulai

---

## Bagian 7 — Statistik Project untuk Slide Presentasi

| Metrik | Nilai |
|---|---|
| Total file backend (PHP) | ~50 file |
| Total controller backend | 19 controller (7 API + 12 admin web) |
| Total model | 14 model Eloquent |
| Total migration | 12+ migration |
| Total tabel utama | 14 tabel |
| Total endpoint API | 30+ endpoint |
| Total halaman admin web | 12 halaman utama |
| Total layar mobile | 8 fitur (auth, home, schedule, booking, payment, pickup, ticket, launch) |
| Backend test | 68 test, 355 assertions, semua PASS |
| Flutter test | 11 test, semua PASS |
| Static analysis | flutter analyze: 0 error |

---

## Bagian 8 — File Penting untuk Referensi Cepat

| Pertanyaan | Buka File Ini |
|---|---|
| Flow pemesanan | `backend/app/Services/BookingService.php` |
| Payment integration | `backend/app/Services/PakasirService.php` |
| Seat lock mechanism | `backend/app/Services/BookingService.php` (method `lockSeats`, `releaseExpiredResources`) |
| QR ticket generation | `backend/app/Models/TiketElektronik.php` |
| Notification polling | `frontend/lib/core/services/travs_notification_polling_service.dart` |
| Admin dashboard | `backend/app/Http/Controllers/Web/Admin/DashboardController.php` |
| Konfigurasi project | `backend/.env`, `backend/config/*.php` |
| Database schema | `backend/database/migrations/*.php` |

---

## Bagian 9 — Penutup

Bro, ingat **3 hal penting** ini saat sidang:

### 1. JUJUR > BOHONG
Pakai AI itu sah. Pakai template itu sah. Pakai library itu sah. Yang **TIDAK** sah adalah klaim semua kerjaan sendiri padahal bukan.

### 2. PAHAM > HAFAL
Penguji bisa modify pertanyaan, tapi flow logic tetap sama. Kalau paham flow, kamu aman dari pertanyaan apapun.

### 3. SANTAI > NERVOUS
Kamu sudah kerja 3 bulan untuk ini. Penguji bukan musuh, mereka mau memastikan kamu paham yang dibangun. Jawab dengan tenang.

> **Kamu sudah punya project lengkap dengan 68 backend test dan 11 Flutter test, dokumentasi rapi, dan flow bisnis yang masuk akal. Tinggal latihan presentasi dan jaga kesehatan. Selamat sidang!**

---

*Dokumen ini disusun untuk persiapan sidang tugas akhir. Pelajari dengan teliti, jangan dihafal mentah. Yang penting kamu paham logikanya, bukan kata-katanya.*
