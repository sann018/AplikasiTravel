# Rekap Sistem dan Bahan Laporan Tugas Akhir RuslyTravel

Tanggal penyusunan awal: 24 Juni 2026  
Pembaruan terakhir: 8 Juli 2026

Dokumen ini dibuat sebagai bahan utama penyusunan Laporan Tugas Akhir berdasarkan:

- Analisis struktur project `backend` Laravel dan `frontend` Flutter.
- Dokumen proposal: `Proposal_TA_Ihsan_REVISI Vx.docx`.
- Template laporan: `Draft Template Penulisan Laporan Tugas Akhir (TA) 2025.docx`.
- Dokumen markdown project: `PRD.md`, `task.md`, `STATUS_FITUR_PROJECT.md`, `NARASI_ALUR_SISTEM_APLIKASI.md`, `CHEATSHEET_SIDANG.md`, `RANGKUMAN_BELAJAR_SIDANG.md`, `PANDUAN_DEMO_LOKAL.md`, `AUDIT_DOKUMENTASI_FITUR.md`, dan `Fitur yang perlu dipertimbangkan.md`.

## 1. Identitas Sistem

Judul proposal:

**Rancang Bangun Aplikasi Pemesanan Jasa Transportasi Umum Lintas Kota Pada Rusly Travel Berbasis Mobile**

Nama sistem yang berjalan di project:

**RuslyTravel / TravS**

Jenis sistem:

- Aplikasi mobile untuk penumpang, tamu, dan pengemudi.
- Web admin untuk pengelolaan operasional travel.
- Backend API sebagai pusat data dan logika bisnis.

Tujuan utama sistem:

Membantu proses pemesanan travel lintas kota pada Rusly Travel agar lebih terstruktur, mulai dari pengelolaan rute, jadwal, armada, pengemudi, kursi, pemesanan, pembayaran, tiket digital, manifest penumpang, sampai laporan operasional.

## 2. Kesesuaian dengan Template Laporan TA

Berdasarkan template laporan, struktur laporan akhir dapat diarahkan sebagai berikut.

### BAB I - Pendahuluan

Bagian ini dapat menjelaskan:

- Latar belakang masih adanya proses pemesanan travel yang rawan dilakukan secara manual.
- Masalah utama seperti pencatatan pemesanan, ketersediaan kursi, status pembayaran, data penumpang, dan koordinasi pengemudi.
- Kebutuhan sistem mobile dan web admin untuk membantu penumpang, pengemudi, dan admin.
- Tujuan membuat aplikasi pemesanan travel lintas kota berbasis mobile dengan backend terpusat.

### BAB II - Dasar Teori

Bagian ini dapat diisi teori terkait:

- Sistem informasi pemesanan tiket/travel.
- Mobile application.
- Web admin.
- REST API.
- Laravel.
- Flutter.
- Database relasional.
- Payment gateway simulasi.
- QR Code.
- Black box testing.
- Rate limiting.
- Role-based access control.

### BAB III - Perancangan Sistem

Bagian ini dapat memuat:

- Analisis kebutuhan pengguna: admin, penumpang, pengemudi, tamu.
- Arsitektur sistem mobile, backend API, dan web admin.
- Rancangan database berdasarkan tabel yang sudah ada.
- Rancangan alur pemesanan, pembayaran, tiket, notifikasi, dan manifest.
- Rancangan antarmuka aplikasi mobile dan web admin.

### BAB IV - Hasil dan Pembahasan

Bagian ini dapat memuat:

- Implementasi backend Laravel.
- Implementasi frontend Flutter.
- Implementasi web admin Blade Laravel.
- Pengujian fitur utama.
- Hasil pengujian backend dan Flutter.
- Pembahasan keamanan, validasi, rate limit, dan integrasi Pakasir simulasi.

### BAB V - Penutup

Bagian ini dapat memuat:

- Kesimpulan bahwa sistem berhasil membantu pemesanan, pengelolaan operasional, pembayaran simulasi, dan tiket digital.
- Saran pengembangan seperti push notification Firebase, production payment gateway, deployment HTTPS, backup otomatis, export laporan lebih lanjut, dan audit sistem.

## 3. Gambaran Umum Arsitektur Sistem

RuslyTravel memakai arsitektur client-server.

Frontend mobile Flutter digunakan oleh:

- Tamu: melihat informasi awal, rute, dan jadwal terbatas.
- Penumpang: membuat pemesanan, memilih kursi, mengatur titik jemput/turun, membayar, melihat tiket, dan membaca notifikasi.
- Pengemudi: melihat jadwal tugas, manifest penumpang, scan QR tiket, validasi kehadiran, validasi turun, dan melihat riwayat perjalanan.

Backend Laravel digunakan untuk:

- Menyediakan REST API mobile.
- Mengelola web admin.
- Menyimpan data ke database.
- Menjalankan validasi bisnis.
- Mengatur booking, seat lock, pembayaran, tiket, notifikasi, dan laporan.

Web admin Laravel Blade digunakan oleh admin untuk:

- Mengelola armada, pengemudi, rute, jadwal, pemesanan, pembayaran, biaya operasional, laporan, banner, pengaturan sistem, dan notifikasi.

## 4. Statistik Project Saat Dianalisis

Statistik berdasarkan struktur repository saat dokumen ini dibuat:

| Komponen | Jumlah |
|---|---:|
| File test backend Laravel | 21 |
| File test Flutter | 6 |
| Migration database | 23 |
| Model Laravel | 16 |
| Controller Laravel | 26 |
| Service Laravel | 12 |
| Baris definisi route API | 49 |
| Baris definisi route web admin | 65 |

Catatan:

- Angka route adalah jumlah baris definisi route, bukan jumlah endpoint unik final setelah group middleware dihitung.
- Project sudah memiliki pemisahan controller, model, service, migration, view, dan test.

## 5. Teknologi yang Digunakan

### Backend

- PHP 8.3.
- Laravel 13.
- Laravel Sanctum untuk autentikasi API mobile.
- Blade Laravel untuk web admin.
- Bootstrap 5 dan CSS custom untuk tampilan admin.
- Migration dan seeder untuk struktur database dan data awal.
- PHPUnit untuk pengujian backend.

### Frontend Mobile

- Flutter 3.41.7.
- Dart 3.11.5.
- Package utama:
  - `http` untuk komunikasi API.
  - `shared_preferences` untuk penyimpanan session lokal.
  - `geolocator` dan `geocoding` untuk lokasi.
  - `flutter_map` dan `latlong2` untuk peta.
  - `qr_flutter` untuk QR tiket.
  - `mobile_scanner` untuk scan tiket.
  - `image_picker` untuk upload foto.
  - `permission_handler` untuk izin perangkat.
  - `google_fonts` untuk tipografi.

## 6. Role dan Hak Akses

### 6.1 Tamu

Tamu dapat:

- Melihat halaman awal.
- Melihat preview rute dan jadwal.
- Melihat informasi layanan.
- Masuk ke aplikasi tanpa akun, tetapi fitur pemesanan tetap dikunci.

Tamu tidak dapat:

- Membuat pemesanan.
- Memilih kursi.
- Melakukan pembayaran.
- Melihat tiket.
- Mengakses data pribadi penumpang.

### 6.2 Penumpang

Penumpang dapat:

- Register dan login.
- Melihat rute dan jadwal.
- Melihat detail jadwal.
- Memilih kursi.
- Mengisi data penumpang.
- Mengisi titik jemput dan titik turun.
- Mengisi patokan jemput dan patokan turun.
- Mengatur barang bawaan.
- Upload foto barang bawaan.
- Memilih metode pembayaran.
- Melihat status pemesanan.
- Melihat tiket digital dan QR.
- Melihat notifikasi.
- Mengelola profil.

### 6.3 Pengemudi

Pengemudi dapat:

- Login sebagai pengemudi.
- Melihat dashboard tugas.
- Melihat jadwal dan manifest penumpang.
- Melihat data penumpang yang sudah resmi masuk manifest.
- Scan QR tiket penumpang.
- Menandai penumpang hadir.
- Konfirmasi pembayaran tunai.
- Menandai penumpang turun.
- Memastikan barang bawaan aman.
- Melihat riwayat perjalanan.
- Mengelola profil pengemudi.

### 6.4 Admin

Admin dapat:

- Login ke web admin.
- Mengelola armada kendaraan.
- Mengelola pengemudi.
- Mengelola rute perjalanan.
- Mengelola jadwal perjalanan.
- Memantau pemesanan.
- Memantau pembayaran.
- Konfirmasi pembayaran tunai.
- Mengelola biaya operasional.
- Melihat laporan pemesanan dan transaksi.
- Mengelola banner beranda.
- Mengelola pengaturan sistem.
- Melihat log notifikasi admin dan user.

## 7. Modul Backend Laravel

### 7.1 Controller API

Controller API yang penting:

- `AuthController`: register, login, logout, profil, upload foto, lupa password, reset password.
- `GuestController`: jadwal untuk tamu.
- `PassengerController`: dashboard penumpang.
- `BookingController`: detail jadwal, lock kursi, estimasi biaya, upload foto bagasi, pembuatan pemesanan, detail pemesanan, simulasi pembayaran.
- `DriverController`: dashboard pengemudi, manifest, scan tiket, pickup, dropoff, konfirmasi tunai, upload foto pengemudi/kendaraan.
- `NotificationController`: daftar notifikasi, jumlah unread, tandai baca, hapus riwayat.
- `PaymentGatewayController`: webhook Pakasir.
- `MobileSettingsController`: pengaturan mobile.
- `HomeBannerController`: banner beranda mobile.
- `AppBootstrapController`: bootstrap data awal aplikasi mobile.

### 7.2 Controller Web Admin

Controller web admin yang penting:

- `DashboardController`: ringkasan operasional.
- `KendaraanController`: armada kendaraan.
- `PengemudiController`: data pengemudi.
- `RuteController`: rute perjalanan.
- `JadwalController`: jadwal keberangkatan dan validasi pengemudi.
- `PemesananController`: monitoring pemesanan.
- `PembayaranController`: monitoring pembayaran dan konfirmasi tunai.
- `BiayaOperasionalController`: catat biaya dan akumulasi biaya operasional.
- `LaporanController`: laporan pemesanan dan transaksi.
- `SettingsController`: pengaturan sistem.
- `NotifikasiController`: notifikasi admin dan user.
- `HomeBannerController`: banner/promo beranda.
- `TiketMonitorController`: monitoring tiket.

### 7.3 Service Backend

Service utama:

- `BookingService`: proses inti pemesanan, seat lock, pembayaran, status booking, tiket, dan integrasi Pakasir.
- `BookingCalculator`: hitung total tiket dan bagasi.
- `BaggageCalculatorService`: aturan biaya bagasi berdasarkan berat, ukuran, dan jumlah item.
- `PakasirService`: komunikasi dengan Pakasir dalam mode simulasi/lokal.
- `SettingService`: membaca pengaturan sistem dengan cache.
- `ScheduleLifecycleService`: mengatur status jadwal berdasarkan waktu.
- `DriverAvailabilityService`: mencegah pengemudi mendapat jadwal bentrok.
- `NotificationHistoryService`: menandai notifikasi terbaca dan hapus riwayat lama.
- `NotificationTemplateService`: membuat pesan notifikasi dari template standar.
- `AdminOperationalNotificationService`: membuat notifikasi penting untuk admin.
- `DepartureReminderService`: pengingat keberangkatan.
- `LogoImageService`: resize logo aplikasi.

## 8. Modul Frontend Flutter

File utama Flutter:

- `main.dart`: entry aplikasi.
- `app.dart`: konfigurasi aplikasi.
- `features/auth/travs_auth_page.dart`: login, register, tamu, lupa password.
- `features/home/travs_home_page.dart`: halaman utama untuk role tamu, penumpang, dan pengemudi.
- `features/schedule/travs_schedule_detail_page.dart`: detail jadwal.
- `features/booking/travs_booking_detail_page.dart`: detail pemesanan.
- `features/payment/travs_payment_preview_page.dart`: pembayaran.
- `features/ticket/travs_ticket_detail_page.dart`: tiket dan QR.
- `features/pickup/travs_pickup_map_picker_page.dart`: pilih lokasi.
- `core/services/travs_api_service.dart`: akses API.
- `core/services/travs_auth_service.dart`: session auth.
- `core/services/settings_api_service.dart`: pengaturan mobile.
- `core/models/*`: model data aplikasi.
- `core/widgets/*`: widget umum.
- `core/theme/travs_theme.dart`: warna dan tema UI.

## 9. Database dan Entitas Utama

Model utama yang tersedia:

- `User`: akun admin, penumpang, dan pengemudi.
- `Pengemudi`: profil pengemudi.
- `Kendaraan`: data armada.
- `Rute`: data rute perjalanan.
- `Jadwal`: jadwal keberangkatan.
- `KursiJadwal`: status kursi per jadwal.
- `Pemesanan`: data transaksi pemesanan.
- `DetailPemesanan`: penumpang per kursi.
- `Pembayaran`: data pembayaran.
- `TiketElektronik`: tiket digital dan QR.
- `ItemBagasi`: data barang bawaan.
- `Notifikasi`: notifikasi user dan admin.
- `BiayaOperasionalPerjalanan`: biaya operasional per perjalanan.
- `SystemSetting`: konfigurasi sistem.
- `HomeBanner`: banner beranda.
- `PengaturanSistem`: model pengaturan lama/kompatibilitas.

Relasi inti:

- Satu rute memiliki banyak jadwal.
- Satu jadwal terhubung ke satu rute, satu kendaraan, dan satu pengemudi.
- Satu jadwal memiliki banyak kursi jadwal.
- Satu pemesanan dibuat oleh satu user penumpang.
- Satu pemesanan memiliki banyak detail penumpang.
- Satu pemesanan memiliki pembayaran dan tiket.
- Satu detail pemesanan dapat memiliki data kursi, pickup, dropoff, dan status hadir/turun.
- Satu pemesanan dapat memiliki item bagasi.

## 10. Alur Utama Sistem

### 10.1 Alur Penumpang Membuat Pemesanan

1. Penumpang login.
2. Penumpang membuka rute dan jadwal.
3. Sistem menampilkan jadwal yang masih terbuka.
4. Penumpang memilih jadwal.
5. Penumpang memilih kursi.
6. Sistem mengunci kursi sementara pada tahap form.
7. Penumpang mengisi biodata penumpang.
8. Penumpang memilih titik jemput dan titik turun.
9. Penumpang mengisi patokan jemput/turun.
10. Penumpang mengisi data barang bawaan.
11. Penumpang upload foto barang bawaan jika diwajibkan.
12. Backend menghitung estimasi biaya.
13. Penumpang membuat pemesanan.
14. Sistem mengunci kursi selama masa pembayaran.
15. Penumpang memilih metode pembayaran.
16. Setelah pembayaran berhasil, status pemesanan berubah menjadi terbayar.
17. Sistem menerbitkan tiket digital dan QR.

### 10.2 Alur Pembayaran

Metode pembayaran yang tersedia:

- QRIS Pakasir.
- Transfer/Virtual Account Pakasir.
- Tunai kepada pengemudi/admin jika diaktifkan.

Catatan penting:

- Admin fee RuslyTravel sudah dihapus.
- Biaya layanan mengikuti kanal pembayaran Pakasir.
- Mode Pakasir yang dipakai untuk demo adalah simulasi lokal, bukan transaksi nyata.
- Flow tetap menunjukkan konsep payment gateway: request pembayaran, status menunggu, pembayaran berhasil/gagal, lalu tiket diterbitkan.

### 10.3 Alur Pengemudi

1. Pengemudi login.
2. Pengemudi melihat dashboard tugas.
3. Pengemudi membuka manifest.
4. Pengemudi melihat daftar penumpang resmi.
5. Pengemudi melihat titik jemput, titik turun, dan patokan lokasi.
6. Pengemudi scan QR tiket penumpang.
7. Sistem menandai penumpang hadir.
8. Jika penumpang bayar tunai, pengemudi dapat konfirmasi pembayaran tunai.
9. Setelah sampai tujuan, pengemudi menandai penumpang turun.
10. Sistem mencatat status turun dan barang aman.
11. Riwayat perjalanan tersimpan.

### 10.4 Alur Admin

1. Admin login web.
2. Admin mengelola armada dan pengemudi.
3. Admin membuat rute dan jadwal.
4. Sistem memvalidasi agar pengemudi tidak bentrok jadwal.
5. Admin memantau pemesanan.
6. Admin memantau pembayaran.
7. Admin mengelola biaya operasional.
8. Admin melihat laporan dan notifikasi.

## 11. Fitur Utama yang Sudah Ada

### 11.1 Autentikasi

- Login penumpang.
- Login pengemudi.
- Login admin.
- Register penumpang.
- Masuk sebagai tamu.
- Lupa password memakai email/token OTP.
- Update profil.
- Upload foto profil.
- Ganti password.
- Role-based access.

### 11.2 Rute dan Jadwal

- Daftar rute.
- Daftar jadwal.
- Detail jadwal.
- Filter tanggal.
- Status jadwal terbuka, ditutup, berangkat, selesai.
- Jadwal otomatis tidak tampil jika sudah melewati batas operasional.
- Pengemudi dikunci jika masih memiliki jadwal aktif/bentrok.

### 11.3 Pemesanan dan Kursi

- Pilih kursi visual.
- Seat lock tahap form.
- Seat lock tahap pembayaran.
- Pencegahan double booking.
- Validasi kursi terkunci/terisi.
- Batas maksimal kursi per booking mengikuti pengaturan.
- Popup konfirmasi saat user akan keluar dari alur pemesanan.

### 11.4 Titik Jemput dan Titik Turun

- Pilih titik GPS/peta.
- Reverse geocoding koordinat menjadi alamat.
- Field patokan jemput.
- Field patokan turun.
- Link arah ke Google Maps pada informasi lokasi.

### 11.5 Barang Bawaan

- Hitung berdasarkan berat + item.
- Hitung berdasarkan ukuran + item.
- Batas gratis berat.
- Batas gratis ukuran.
- Batas gratis jumlah item.
- Biaya tambahan otomatis.
- Upload foto barang bawaan.
- Foto bagasi tampil di detail pemesanan.

### 11.6 Pembayaran

- QRIS Pakasir.
- Transfer/VA Pakasir.
- Tunai.
- Simulasi pembayaran Pakasir untuk demo lokal.
- Konfirmasi tunai oleh admin/pengemudi.
- Status pembayaran otomatis memperbarui pemesanan.
- Tiket muncul setelah pembayaran valid.

### 11.7 Tiket dan QR

- Tiket digital.
- QR unik per tiket/kursi.
- Scan QR oleh pengemudi.
- Status hadir setelah scan.
- Validasi agar QR tidak digunakan sembarangan.
- Tampilan tiket aktif dan tiket selesai dipisahkan.

### 11.8 Notifikasi

- Notifikasi untuk penumpang, pengemudi, dan admin.
- Badge notifikasi belum dibaca.
- Notifikasi otomatis terbaca saat halaman dibuka.
- Hapus riwayat notifikasi lama yang sudah dibaca.
- Notifikasi admin untuk pemesanan baru, pembayaran tunai, pembayaran berhasil/gagal/kedaluwarsa, jadwal mendekati keberangkatan, dan kapasitas hampir penuh.

### 11.9 Web Admin

- Dashboard minimalis.
- Statistik dapat diklik menuju halaman terkait.
- Filter memakai tombol Terapkan Filter agar request tidak berlebihan.
- Section collapsible untuk halaman tertentu.
- Manajemen armada kendaraan.
- Manajemen pengemudi.
- Manajemen rute.
- Manajemen jadwal.
- Monitoring pemesanan.
- Monitoring pembayaran.
- Biaya operasional dan akumulasi per rute.
- Laporan pemesanan dan transaksi.
- Pengaturan sistem.
- Upload logo.
- Upload banner beranda.
- Notifikasi admin dan log notifikasi user.

## 12. Keamanan dan Validasi

Keamanan yang sudah diterapkan atau disiapkan:

- Query memakai Eloquent/Query Builder, bukan SQL mentah dari input user.
- Validasi backend untuk input penting.
- Laravel Sanctum untuk token API.
- Role middleware untuk membatasi akses.
- Rate limit pada endpoint API dan aksi admin.
- Transaksi database untuk operasi penting seperti booking, pembayaran, dan update status.
- Password di-hash.
- File upload divalidasi tipe dan ukuran.
- Data sensitif disimpan di `.env`.
- Error API dibuat lebih konsisten.
- Tombol submit admin dikunci setelah submit untuk mencegah klik ganda.

Contoh rate limit:

- Auth: login/register/reset password.
- Read API: endpoint data umum.
- Action API: booking, update status, notifikasi.
- Upload: foto profil, foto bagasi, foto kendaraan.
- Admin action: create/update/delete data.

## 13. Pengujian

Pengujian yang tersedia:

- Feature test backend untuk auth, booking, payment gateway, notifikasi, settings, driver operations, admin operations, home banner, dan security.
- Unit/feature test Flutter untuk app settings, async state, data pengemudi, notifikasi, data UI penumpang, dan widget dasar.

Command pengujian:

```bash
cd backend
php artisan test
```

```bash
cd frontend
flutter analyze
flutter test
```

Catatan dari dokumentasi lama:

- Pernah tercatat statistik 68 backend tests dan 11 Flutter tests.
- Berdasarkan struktur file saat rekap ini dibuat, terdapat 21 file test backend dan 6 file test Flutter. Jumlah assertion/test case aktual dapat berbeda dari jumlah file.

## 14. Mode Simulasi Pakasir

Mode Pakasir pada project ini dipakai sebagai simulasi lokal untuk kebutuhan demo dan sidang.

Alasan memakai simulasi:

- Lebih aman untuk tugas akhir.
- Tidak memakai transaksi uang nyata.
- Tidak bergantung penuh pada callback publik production.
- Memudahkan demo alur pembayaran berhasil/gagal.
- Tetap menunjukkan konsep integrasi payment gateway.

Yang tidak diwajibkan pada mode simulasi:

- Webhook production.
- Signature verification production.
- Uji transaksi nyata.
- Settlement bank nyata.

Yang tetap bisa dijelaskan:

- Sistem membuat transaksi pembayaran.
- Sistem menyimpan status menunggu.
- Setelah simulasi berhasil, status berubah menjadi berhasil.
- Tiket digital diterbitkan.
- Penumpang dapat melihat QR tiket.

## 15. Integrasi Pengaturan Sistem

Pengaturan sistem digunakan agar beberapa aturan tidak perlu selalu di-hardcode.

Contoh pengaturan:

- Nama aplikasi.
- Logo aplikasi.
- Kontak admin.
- WhatsApp layanan.
- Email sistem.
- Maksimal kursi per booking.
- Durasi lock form.
- Durasi lock pembayaran.
- Status pembayaran tunai.
- Aturan bagasi.
- Template notifikasi standar.

Catatan:

- Admin fee RuslyTravel sudah dihapus karena biaya admin mengikuti kanal Pakasir.
- Logo sudah memakai upload image dan di-resize ke WEBP maksimal 512x512 px.
- Logo dipakai pada web admin dan mobile.
- Service `SettingService` dipakai agar modul lain membaca pengaturan dari satu tempat.

## 16. Ringkasan Gabungan Dokumen Markdown

### 16.1 PRD.md

Isi utama PRD:

- Audit repository.
- Pemahaman produk.
- Scope fitur.
- Roles and permissions.
- Functional requirements.
- Business rules.
- UX spec mobile dan web admin.
- Arsitektur teknis.
- API contract.
- Database notes.
- Quality control.

Nilai penting untuk laporan:

- PRD bisa dijadikan dasar BAB III perancangan sistem.
- Bagian functional requirements dapat diubah menjadi kebutuhan fungsional.
- Bagian non-functional requirements dapat diubah menjadi kebutuhan non-fungsional.
- Bagian business rules dapat dijelaskan sebagai aturan sistem.

### 16.2 task.md

Isi utama:

- Dashboard checklist progres.
- Gap analysis.
- Breakdown epic implementasi.
- Roadmap fase 0 sampai 5.
- Quality control summary.
- Model testing.
- Bug monitoring board.

Nilai penting untuk laporan:

- Bisa dipakai untuk menjelaskan metode pengembangan dan tahapan pengerjaan.
- Fase 0-3 sudah selesai.
- Fase 4-5 sebagian selesai dan menjadi backlog/hardening.

### 16.3 STATUS_FITUR_PROJECT.md

Isi utama:

- Snapshot fitur terbaru.
- Fitur selesai.
- Perubahan database dan API.
- Perubahan tampilan.
- Hasil pengujian.
- Risiko dan catatan.

Nilai penting untuk laporan:

- Cocok untuk BAB IV bagian hasil implementasi.
- Cocok untuk daftar fitur yang berhasil dibuat.

### 16.4 NARASI_ALUR_SISTEM_APLIKASI.md

Isi utama:

- Gambaran umum sistem.
- Hak akses.
- Alur autentikasi.
- Alur tamu.
- Alur penumpang.
- Alur pengemudi.
- Alur web admin.
- Sinkronisasi akun lama.
- Keamanan dan konsistensi data.

Nilai penting untuk laporan:

- Dapat langsung dijadikan narasi BAB IV.
- Cocok untuk menjelaskan alur end-to-end sistem saat sidang.

### 16.5 CHEATSHEET_SIDANG.md

Isi utama:

- Identitas project singkat.
- Tech stack.
- Flow bisnis utama.
- Konsep seat lock, payment lifecycle, QR ticket, role access, polling notifikasi.
- Pertanyaan umum sidang dan jawaban.
- Checklist persiapan sidang.

Nilai penting untuk laporan:

- Cocok untuk bahan tanya jawab.
- Cocok untuk membuat slide presentasi.

### 16.6 RANGKUMAN_BELAJAR_SIDANG.md

Isi utama:

- Gambaran project.
- File frontend/backend penting.
- Alur pemesanan.
- Titik jemput.
- Pembayaran Pakasir.
- Pengemudi.
- Rate limit.
- Service class.
- Bagasi.
- Booking dan seat lock.
- Tiket dan QR.
- Role user.
- Upload foto.

Nilai penting untuk laporan:

- Cocok sebagai catatan pemahaman kode.
- Bisa membantu menjawab pertanyaan dosen tentang struktur project.

### 16.7 PANDUAN_DEMO_LOKAL.md

Isi utama:

- Menjalankan backend Laravel.
- Menjalankan Flutter ke HP fisik.
- Menjalankan Flutter ke emulator.
- Mode simulasi Pakasir.
- Push notification tanpa Firebase.
- Backup database.
- HTTPS production.
- Environment dev/staging/production.

Nilai penting untuk laporan:

- Cocok untuk lampiran panduan demo.
- Bisa dipakai saat persiapan sidang.

### 16.8 AUDIT_DOKUMENTASI_FITUR.md

Isi utama:

- Kesimpulan dokumentasi.
- Matriks perubahan.
- Dokumen yang sudah diperbarui.
- Fitur yang perlu uji nyata.
- Catatan sidang.

Nilai penting untuk laporan:

- Cocok sebagai catatan validasi dokumentasi.
- Membantu membedakan fitur selesai, sebagian, dan backlog.

### 16.9 Fitur yang perlu dipertimbangkan.md

Isi utama:

- Reset password memakai email/token.
- SMTP Gmail.
- Login Google.
- SMS OTP.
- Rekomendasi keamanan reset password.

Nilai penting untuk laporan:

- Cocok untuk bagian saran pengembangan.
- Menjelaskan bahwa SMS OTP dan login Google dapat menjadi fitur lanjutan.

## 17. Bahan Kebutuhan Fungsional

Kebutuhan fungsional yang dapat ditulis pada laporan:

1. Sistem dapat melakukan autentikasi pengguna berdasarkan role.
2. Sistem dapat menampilkan rute dan jadwal perjalanan.
3. Sistem dapat menampilkan detail jadwal beserta armada, pengemudi, harga, dan kursi tersedia.
4. Sistem dapat mengunci kursi saat proses pemesanan berlangsung.
5. Sistem dapat menerima input data penumpang.
6. Sistem dapat menerima titik jemput dan titik turun.
7. Sistem dapat menghitung biaya tambahan bagasi.
8. Sistem dapat menerima upload foto barang bawaan.
9. Sistem dapat membuat pemesanan.
10. Sistem dapat memproses pembayaran simulasi Pakasir.
11. Sistem dapat memproses pembayaran tunai.
12. Sistem dapat menerbitkan tiket digital QR setelah pembayaran valid.
13. Sistem dapat melakukan scan tiket oleh pengemudi.
14. Sistem dapat mencatat kehadiran penumpang.
15. Sistem dapat mencatat status penumpang turun.
16. Sistem dapat mengelola data armada.
17. Sistem dapat mengelola data pengemudi.
18. Sistem dapat mengelola data rute dan jadwal.
19. Sistem dapat menampilkan laporan pemesanan dan transaksi.
20. Sistem dapat mengirim dan menampilkan notifikasi.

## 18. Bahan Kebutuhan Non-Fungsional

Kebutuhan non-fungsional:

1. Sistem memiliki validasi input pada backend.
2. Sistem memiliki pembatasan request/rate limit.
3. Sistem menggunakan autentikasi token untuk API mobile.
4. Sistem membatasi akses berdasarkan role.
5. Sistem menggunakan transaksi database pada operasi penting.
6. Sistem menampilkan error dengan bahasa yang lebih mudah dipahami.
7. Sistem dapat dijalankan pada lingkungan lokal untuk demo.
8. Sistem mendukung tampilan mobile yang responsif.
9. Web admin mendukung filter dengan tombol Terapkan Filter agar request tidak berlebihan.
10. Sistem menyimpan file upload pada storage publik yang terkontrol.

## 19. Bahan Batasan Masalah

Batasan masalah yang dapat dipakai:

1. Sistem dibuat untuk studi kasus Rusly Travel.
2. Aplikasi mobile difokuskan pada Android.
3. Payment gateway Pakasir digunakan dalam mode simulasi untuk kebutuhan demo tugas akhir.
4. Sistem belum menggunakan transaksi uang nyata.
5. Push notification menggunakan polling, belum memakai Firebase Cloud Messaging.
6. Sistem dijalankan pada environment lokal untuk pengujian.
7. Integrasi peta menggunakan fitur lokasi dan reverse geocoding sesuai dukungan perangkat.
8. Pengelolaan laporan masih difokuskan pada laporan pemesanan, transaksi, dan biaya operasional.

## 20. Bahan Rumusan Masalah

Rumusan masalah yang bisa disesuaikan:

1. Bagaimana merancang aplikasi pemesanan jasa transportasi umum lintas kota pada Rusly Travel berbasis mobile?
2. Bagaimana sistem dapat membantu penumpang memilih jadwal, kursi, titik jemput, titik turun, dan melakukan pemesanan?
3. Bagaimana sistem dapat membantu pengemudi melihat manifest penumpang dan melakukan validasi tiket?
4. Bagaimana web admin dapat membantu pengelolaan rute, jadwal, armada, pengemudi, pemesanan, pembayaran, dan laporan?
5. Bagaimana sistem menjaga konsistensi data kursi agar tidak terjadi pemesanan ganda?

## 21. Bahan Tujuan Tugas Akhir

Tujuan yang dapat ditulis:

1. Merancang dan membangun aplikasi pemesanan travel lintas kota berbasis mobile untuk Rusly Travel.
2. Menyediakan fitur pemesanan jadwal, pemilihan kursi, titik jemput, titik turun, dan barang bawaan.
3. Menyediakan fitur pembayaran simulasi dan pembayaran tunai.
4. Menyediakan tiket digital berbasis QR Code.
5. Menyediakan fitur manifest dan validasi penumpang untuk pengemudi.
6. Menyediakan web admin untuk mengelola data operasional dan laporan.

## 22. Bahan Manfaat Tugas Akhir

Manfaat untuk penumpang:

- Memudahkan melihat jadwal dan rute.
- Memudahkan pemesanan tanpa harus datang langsung.
- Memudahkan pemantauan status pembayaran dan tiket.

Manfaat untuk pengemudi:

- Memudahkan melihat daftar penumpang.
- Memudahkan validasi tiket.
- Memudahkan mengetahui titik jemput dan titik turun.

Manfaat untuk admin:

- Memudahkan pengelolaan data operasional.
- Memudahkan monitoring pemesanan dan pembayaran.
- Memudahkan pembuatan laporan.

Manfaat akademik:

- Menjadi contoh penerapan aplikasi mobile, backend API, web admin, database, dan pengujian pada studi kasus transportasi.

## 23. Bahan Metodologi

Metode yang sesuai dengan proposal adalah Waterfall.

Tahapan Waterfall yang dapat dijelaskan:

1. Analisis kebutuhan: mengidentifikasi kebutuhan penumpang, pengemudi, dan admin.
2. Perancangan sistem: membuat rancangan database, alur sistem, API, dan UI.
3. Implementasi: membangun backend Laravel, frontend Flutter, dan web admin Blade.
4. Pengujian: melakukan pengujian fitur menggunakan black box dan automated test.
5. Pemeliharaan/evaluasi: memperbaiki bug, menyempurnakan validasi, dan menambah dokumentasi.

## 24. Bahan Pengujian Black Box

Contoh skenario pengujian:

| No | Fitur | Skenario | Hasil yang Diharapkan |
|---:|---|---|---|
| 1 | Login | User memasukkan email/nomor HP dan password benar | User masuk sesuai role |
| 2 | Register | Penumpang mengisi data valid | Akun penumpang berhasil dibuat |
| 3 | Pilih kursi | Penumpang memilih kursi tersedia | Kursi terkunci sementara |
| 4 | Double booking | User lain memilih kursi terkunci | Sistem menolak pemilihan |
| 5 | Bagasi | Berat/item melebihi batas gratis | Sistem menghitung biaya tambahan |
| 6 | Pembayaran | Simulasi Pakasir berhasil | Status menjadi terbayar dan tiket muncul |
| 7 | QR tiket | Pengemudi scan QR valid | Status penumpang menjadi hadir |
| 8 | Pembayaran tunai | Pengemudi/admin konfirmasi tunai | Status pembayaran menjadi lunas |
| 9 | Notifikasi | User membuka halaman notifikasi | Notifikasi menjadi terbaca |
| 10 | Jadwal bentrok | Admin memilih pengemudi dengan jadwal aktif | Sistem menolak atau mengunci pilihan |

## 25. Risiko dan Backlog

Risiko yang masih perlu diperhatikan:

- Mode Pakasir masih simulasi, bukan production.
- Push notification belum memakai FCM.
- Deployment HTTPS production belum menjadi fokus utama.
- Backup database otomatis perlu disiapkan saat production.
- Export laporan Excel/CSV dapat dikembangkan lebih lanjut.
- Audit sistem dapat ditambahkan untuk mencatat aktivitas admin lebih lengkap.
- Penghapusan data lama perlu mekanisme arsip agar tidak merusak relasi historis.

Backlog yang tercatat dari dokumentasi:

- Notifikasi otomatis yang lebih matang untuk seluruh role.
- Hapus riwayat notifikasi lama.
- Rekap biaya menjadi laporan dengan export Excel/CSV.
- Penambahan menu Audit Sistem.
- Fitur arsip/hapus data lama untuk pemesanan, pembayaran, dan biaya operasional.
- Pengembangan SMS OTP atau login Google jika dibutuhkan.

## 26. Catatan Penting untuk Sidang

Poin yang perlu dipahami:

1. Seat lock dipakai untuk mencegah kursi dipesan ganda.
2. Perhitungan harga dilakukan di backend agar tidak bisa dimanipulasi dari frontend.
3. Payment gateway diproses lewat backend agar status pembayaran tetap konsisten.
4. QR tiket dibuat unik agar dapat divalidasi oleh pengemudi.
5. Role digunakan agar data admin, pengemudi, penumpang, dan tamu tidak tercampur.
6. Rate limit digunakan untuk mencegah spam request.
7. Service class digunakan agar controller tidak terlalu penuh dan logika bisnis lebih mudah dipahami.
8. Validasi backend tetap wajib walaupun frontend sudah memiliki form validation.
9. File upload divalidasi agar tidak menerima file berbahaya.
10. Mode simulasi Pakasir dipakai agar demo aman dan tidak memakai uang nyata.

## 27. Kesimpulan Sementara

Berdasarkan analisis frontend, backend, dan dokumentasi project, sistem RuslyTravel sudah berkembang dari rancangan proposal menjadi aplikasi yang memiliki tiga bagian utama: aplikasi mobile, backend API, dan web admin. Sistem telah mencakup proses utama bisnis travel lintas kota, mulai dari pengelolaan rute dan jadwal, pemilihan kursi, pemesanan, pembayaran, tiket QR, manifest pengemudi, sampai laporan admin.

Untuk laporan TA, dokumen ini dapat dijadikan bahan dasar BAB I sampai BAB IV. Bagian yang paling perlu dirapikan selanjutnya adalah penyusunan narasi akademik sesuai template, penyisipan gambar antarmuka, diagram arsitektur, relasi database, serta tabel hasil pengujian black box.

## 28. Addendum Sinkronisasi BAB IV dan BAB V - 28 Juni 2026

Bagian ini menyatukan hasil analisis proposal, template laporan, kode backend Laravel, kode frontend Flutter, dan dokumen markdown project agar bahan BAB IV dan BAB V tidak saling bertentangan.

Catatan penting yang perlu dijaga konsisten dalam laporan:

- Sistem autentikasi yang berjalan saat ini adalah autentikasi lokal menggunakan akun, email/nomor HP, password, token API, dan role.
- Login Google/OAuth belum menjadi implementasi sistem.
- Reset password sudah disiapkan dengan alur token/email-ready, tetapi SMS OTP belum diterapkan karena membutuhkan layanan SMS gateway berbayar.
- Tracking GPS real-time kendaraan belum menjadi implementasi sistem.
- WebSocket realtime penuh untuk notifikasi belum diterapkan. Notifikasi yang ada lebih tepat dijelaskan sebagai notifikasi berbasis API/polling/refresh data.
- Payment gateway Pakasir pada kebutuhan demo dijelaskan sebagai mode simulasi atau integrasi terbatas, bukan klaim transaksi production penuh.
- Fitur yang belum diterapkan tetap boleh ditulis pada bagian saran pengembangan, bukan sebagai fitur yang sudah selesai.

### 28.1 Kesesuaian Rancangan Proposal dan Implementasi

| Rancangan pada Proposal | Status pada Kode Project | Bukti Implementasi |
|---|---|---|
| Aplikasi mobile untuk penumpang dan pengemudi | Sudah ada. Implementasi juga menambahkan mode tamu. | `frontend/lib/features/home/travs_home_page.dart`, `frontend/lib/features/auth/travs_auth_page.dart` |
| Backend API Laravel | Sudah ada. API mobile didefinisikan pada route API. | `backend/routes/api.php`, `backend/app/Http/Controllers/Api` |
| Web admin | Sudah ada untuk pengelolaan operasional. | `backend/routes/web.php`, `backend/resources/views/admin` |
| Pengelolaan rute | Sudah ada. | `backend/app/Http/Controllers/Web/Admin/RuteController.php`, `backend/app/Models/Rute.php` |
| Pengelolaan armada kendaraan | Sudah ada. | `backend/app/Http/Controllers/Web/Admin/KendaraanController.php`, `backend/app/Models/Kendaraan.php` |
| Pengelolaan pengemudi | Sudah ada. | `backend/app/Http/Controllers/Web/Admin/PengemudiController.php`, `backend/app/Models/Pengemudi.php` |
| Pengelolaan jadwal perjalanan | Sudah ada, termasuk relasi rute, kendaraan, dan pengemudi. | `backend/app/Http/Controllers/Web/Admin/JadwalController.php`, `backend/app/Models/Jadwal.php` |
| Pemesanan tiket travel | Sudah ada. | `backend/app/Http/Controllers/Api/BookingController.php`, `backend/app/Services/BookingService.php` |
| Penguncian kursi sementara | Sudah ada melalui data kursi jadwal dan service booking. | `backend/app/Models/KursiJadwal.php`, `backend/app/Services/BookingService.php` |
| Pembayaran digital dan tunai | Sudah ada. Digital melalui Pakasir/simulasi, tunai melalui konfirmasi manual. | `backend/app/Services/PakasirService.php`, `backend/app/Models/Pembayaran.php` |
| Tiket elektronik QR Code | Sudah ada. | `backend/app/Models/TiketElektronik.php`, `frontend/lib/features/ticket/travs_ticket_detail_page.dart` |
| Manifest pengemudi dan scan QR | Sudah ada. | `backend/app/Http/Controllers/Api/DriverController.php`, `frontend/lib/features/home/travs_home_page.dart` |
| Pencatatan barang bawaan | Sudah ada, termasuk perhitungan biaya bagasi dan foto barang. | `backend/app/Services/BaggageCalculatorService.php`, `backend/app/Models/ItemBagasi.php` |
| Notifikasi | Sudah ada, tetapi bukan WebSocket realtime penuh. | `backend/app/Http/Controllers/Api/NotificationController.php`, `backend/app/Models/Notifikasi.php` |
| Laporan admin | Sudah ada. | `backend/app/Http/Controllers/Web/Admin/LaporanController.php`, `backend/resources/views/admin/laporan` |

### 28.2 Fitur Tambahan yang Perlu Dijelaskan pada BAB IV

Beberapa fitur berkembang dari rancangan awal proposal dan perlu dijelaskan sebagai hasil pengembangan implementasi:

- Mode tamu untuk melihat informasi awal tanpa login.
- Banner beranda yang dikelola admin.
- Pengaturan sistem untuk beberapa konfigurasi aplikasi.
- Rate limit untuk mengurangi spam request.
- Notifikasi operasional admin.
- Upload logo aplikasi, foto profil, foto kendaraan, dan foto barang bawaan.
- Pengajuan biaya operasional dari pengemudi dengan status verifikasi.
- Validasi bentrok jadwal pengemudi dan kendaraan.

### 28.3 Fitur yang Sebaiknya Masuk Saran, Bukan Klaim Implementasi

Fitur berikut tidak dijadikan klaim implementasi utama:

- Login Google/OAuth.
- SMS OTP produksi.
- Tracking GPS real-time kendaraan.
- WebSocket realtime penuh.
- Refund otomatis.
- Komplain otomatis.
- Reschedule mandiri penumpang.
- Deployment production penuh dengan transaksi payment gateway nyata.

## 29. Draft Narasi BAB IV Hasil dan Pembahasan

# BAB IV HASIL DAN PEMBAHASAN

## 4.1 Gambaran Umum Sistem

RuslyTravel merupakan aplikasi pemesanan jasa transportasi umum lintas kota berbasis mobile yang dikembangkan untuk membantu proses operasional Rusly Travel. Sistem ini dibangun menggunakan aplikasi mobile Flutter, backend Laravel, database relasional, dan web admin berbasis Laravel Blade. Aplikasi mobile digunakan oleh tamu, penumpang, dan pengemudi, sedangkan web admin digunakan oleh pihak admin untuk mengelola data operasional.

Sistem ini mendukung proses pengelolaan rute, armada kendaraan, pengemudi, jadwal perjalanan, pemesanan tiket, pembayaran, pencatatan barang bawaan, penerbitan tiket elektronik QR Code, manifest pengemudi, notifikasi, biaya operasional, laporan, banner beranda, dan pengaturan sistem. Dengan adanya sistem ini, proses pemesanan yang sebelumnya dilakukan secara manual dapat dibuat lebih terstruktur karena data pemesanan, kursi, pembayaran, dan tiket tersimpan dalam satu sistem.

[Masukkan Gambar: Arsitektur Sistem RuslyTravel]

Hak akses pengguna dibagi menjadi empat role utama:

1. Tamu, yaitu pengguna yang belum login dan dapat melihat informasi awal seperti rute dan jadwal terbatas.
2. Penumpang, yaitu pengguna yang dapat melakukan pemesanan, pembayaran, melihat tiket, dan mengelola profil.
3. Pengemudi, yaitu pengguna yang dapat melihat jadwal tugas, manifest penumpang, scan QR tiket, serta melihat riwayat perjalanan.
4. Admin, yaitu pengguna web admin yang mengelola data operasional sistem.

## 4.2 Implementasi Sistem

### 4.2.1 Implementasi Backend Laravel

Backend sistem dikembangkan menggunakan Laravel. Backend menyediakan API untuk aplikasi mobile dan route web untuk dashboard admin. Route API dapat ditemukan pada `backend/routes/api.php`, sedangkan route web admin terdapat pada `backend/routes/web.php`.

Controller API yang digunakan antara lain `AuthController`, `BookingController`, `DriverController`, `GuestController`, `PassengerController`, `NotificationController`, dan `PaymentGatewayController`. Controller web admin berada pada folder `backend/app/Http/Controllers/Web/Admin`, seperti `DashboardController`, `KendaraanController`, `PengemudiController`, `RuteController`, `JadwalController`, `PemesananController`, `PembayaranController`, `BiayaOperasionalController`, `LaporanController`, `HomeBannerController`, `NotifikasiController`, dan `SettingsController`.

Backend juga menggunakan service class untuk memisahkan logika bisnis agar controller tidak terlalu penuh. Beberapa service penting adalah `BookingService`, `BaggageCalculatorService`, `PakasirService`, `SettingService`, `DriverAvailabilityService`, `ScheduleLifecycleService`, dan `AdminOperationalNotificationService`.

### 4.2.2 Implementasi Aplikasi Mobile Flutter

Aplikasi mobile dikembangkan menggunakan Flutter. Struktur aplikasi berada pada folder `frontend/lib`. Halaman autentikasi terdapat pada `frontend/lib/features/auth/travs_auth_page.dart`, sedangkan halaman utama role pengguna banyak diatur pada `frontend/lib/features/home/travs_home_page.dart`.

Aplikasi mobile memiliki beberapa halaman utama, yaitu halaman login/register, beranda tamu, beranda penumpang, daftar rute, detail jadwal, form pemesanan, pembayaran, tiket digital, profil, beranda pengemudi, manifest pengemudi, scan QR, dan riwayat perjalanan. Komunikasi antara Flutter dan backend dilakukan melalui service API pada `frontend/lib/core/services/travs_api_service.dart`.

[Masukkan Gambar: Halaman Login Mobile]  
[Masukkan Gambar: Beranda Penumpang]  
[Masukkan Gambar: Beranda Pengemudi]

### 4.2.3 Implementasi Web Admin

Web admin dikembangkan menggunakan Laravel Blade dan Bootstrap. View admin berada pada folder `backend/resources/views/admin`. Web admin digunakan untuk mengelola data operasional seperti kendaraan, pengemudi, rute, jadwal, pemesanan, pembayaran, biaya operasional, laporan, notifikasi, banner, dan pengaturan sistem.

Dashboard admin menampilkan ringkasan operasional. Halaman armada digunakan untuk mengelola kendaraan. Halaman pengemudi digunakan untuk mengelola akun dan identitas pengemudi. Halaman jadwal digunakan untuk memasangkan rute, kendaraan, dan pengemudi dalam satu jadwal perjalanan.

[Masukkan Gambar: Dashboard Admin]  
[Masukkan Gambar: Halaman Armada Kendaraan]  
[Masukkan Gambar: Halaman Jadwal Perjalanan]

### 4.2.4 Implementasi Database

Database sistem dibuat menggunakan migration Laravel. Entitas utama yang digunakan antara lain `users`, `rute`, `kendaraan`, `pengemudi`, `jadwal`, `kursi_jadwal`, `pemesanan`, `detail_pemesanan`, `pembayaran`, `tiket_elektronik`, `item_bagasi`, `notifikasi`, `biaya_operasional_perjalanan`, dan `system_settings`.

Relasi utama sistem adalah satu jadwal memiliki satu rute, satu kendaraan, dan satu pengemudi. Satu pemesanan terhubung dengan satu jadwal dan memiliki detail pemesanan per kursi. Pembayaran terhubung dengan pemesanan, sedangkan tiket elektronik terhubung dengan detail pemesanan.

[Masukkan Gambar: Relasi Database]

### 4.2.5 Implementasi Pemesanan dan Seat Lock

Pemesanan ditangani oleh `BookingController` dan `BookingService`. Sistem memiliki mekanisme penguncian kursi sementara melalui tabel `kursi_jadwal`. Saat penumpang memilih kursi, kursi dapat dikunci dalam waktu tertentu agar tidak dipilih oleh penumpang lain secara bersamaan.

Seat lock membantu mengurangi risiko pemesanan ganda. Jika waktu pemesanan atau pembayaran habis, sistem dapat melepas kursi agar kembali tersedia.

### 4.2.6 Implementasi Pembayaran

Pembayaran dikelola melalui model `Pembayaran`, controller `PaymentGatewayController`, dan service `PakasirService`. Sistem mendukung pembayaran digital melalui Pakasir/simulasi dan pembayaran tunai. Pembayaran digital mengikuti status dari gateway atau proses sinkronisasi sistem, sedangkan pembayaran tunai dapat dikonfirmasi oleh admin atau pengemudi sesuai alur sistem.

Pada laporan TA, Pakasir sebaiknya dijelaskan sebagai mode simulasi atau integrasi terbatas untuk kebutuhan demo lokal. Jika sistem akan digunakan secara production, maka pengujian transaksi nyata dan konfigurasi payment gateway production perlu dilakukan kembali.

### 4.2.7 Implementasi Tiket Elektronik QR Code

Tiket elektronik disimpan pada model `TiketElektronik`. Tiket berisi kode QR yang digunakan sebagai bukti keberangkatan. Penumpang dapat melihat tiket digital pada aplikasi mobile, sedangkan pengemudi dapat melakukan scan QR untuk memvalidasi kehadiran penumpang.

Fitur ini membantu menggantikan tiket kertas yang mudah hilang atau rusak.

### 4.2.8 Implementasi Manifest Pengemudi

Manifest pengemudi ditangani oleh `DriverController`. Pengemudi dapat melihat daftar penumpang berdasarkan jadwal yang ditugaskan. Data manifest berisi informasi penumpang, nomor kursi, status pembayaran, status kehadiran, titik jemput, titik turun, dan informasi barang bawaan jika tersedia.

Pengemudi juga dapat melakukan scan tiket QR untuk mengubah status kehadiran penumpang. Setelah perjalanan selesai, jadwal dapat masuk ke riwayat perjalanan pengemudi.

### 4.2.9 Implementasi Laporan dan Pengaturan Sistem

Laporan admin dikelola melalui `LaporanController` dan view pada folder `backend/resources/views/admin/laporan`. Laporan yang tersedia meliputi laporan pemesanan dan laporan transaksi. Biaya operasional juga dikelola pada modul tersendiri agar admin dapat mencatat pengeluaran perjalanan.

Pengaturan sistem dikelola melalui `SettingsController`, `SettingService`, dan model `SystemSetting`. Modul ini digunakan untuk menyimpan konfigurasi sistem seperti identitas aplikasi, aturan pemesanan, pembayaran tunai, dan aturan bagasi.

## 4.3 Pengujian Sistem

Pengujian sistem diarahkan menggunakan metode Black Box Testing. Pengujian dilakukan dengan mengamati input, proses, dan output sistem tanpa membahas kode program secara langsung.

[Masukkan Tabel: Pengujian Black Box]

| No | Fitur yang Diuji | Skenario Pengujian | Data Uji | Hasil yang Diharapkan | Hasil Aktual | Status |
|---:|---|---|---|---|---|---|
| 1 | Login | User memasukkan akun valid | Email/no HP dan password benar | User masuk sesuai role | Perlu diuji langsung | Perlu diuji |
| 2 | Register penumpang | Penumpang mengisi form daftar | Nama, email, no HP, password | Akun penumpang berhasil dibuat | Perlu diuji langsung | Perlu diuji |
| 3 | Mode tamu | User memilih lanjut sebagai tamu | Tanpa akun | Beranda tamu tampil | Perlu diuji langsung | Perlu diuji |
| 4 | Melihat rute dan jadwal | User membuka daftar rute | Rute aktif | Jadwal tersedia tampil | Perlu diuji langsung | Perlu diuji |
| 5 | Memilih kursi | Penumpang memilih kursi tersedia | Nomor kursi | Kursi terkunci sementara | Perlu diuji langsung | Perlu diuji |
| 6 | Pemesanan | Penumpang mengisi data pemesanan | Data penumpang, kursi, lokasi | Pemesanan berhasil dibuat | Perlu diuji langsung | Perlu diuji |
| 7 | Titik jemput/turun | Penumpang mengisi lokasi | Alamat/koordinat/patokan | Lokasi tersimpan pada pemesanan | Perlu diuji langsung | Perlu diuji |
| 8 | Barang bawaan | Penumpang mengisi data bagasi | Berat, item, foto | Biaya bagasi dihitung | Perlu diuji langsung | Perlu diuji |
| 9 | Pembayaran digital | Penumpang memilih QRIS/transfer | Metode digital | Status pembayaran diproses | Perlu diuji langsung | Perlu diuji |
| 10 | Pembayaran tunai | Penumpang memilih tunai | Metode tunai | Status menunggu konfirmasi | Perlu diuji langsung | Perlu diuji |
| 11 | Tiket digital | Penumpang membuka tiket | Pemesanan terbayar | QR tiket tampil | Perlu diuji langsung | Perlu diuji |
| 12 | Scan QR | Pengemudi scan tiket valid | QR tiket | Status hadir berubah | Perlu diuji langsung | Perlu diuji |
| 13 | Kelola kendaraan | Admin tambah/edit kendaraan | Data kendaraan | Kendaraan tersimpan | Perlu diuji langsung | Perlu diuji |
| 14 | Kelola pengemudi | Admin tambah/edit pengemudi | Data pengemudi | Pengemudi tersimpan | Perlu diuji langsung | Perlu diuji |
| 15 | Kelola rute | Admin tambah/edit rute | Data rute | Rute tersimpan | Perlu diuji langsung | Perlu diuji |
| 16 | Kelola jadwal | Admin membuat jadwal | Rute, kendaraan, pengemudi, waktu | Jadwal tersimpan | Perlu diuji langsung | Perlu diuji |
| 17 | Detail pemesanan admin | Admin membuka detail pemesanan | Kode pemesanan | Detail lengkap tampil | Perlu diuji langsung | Perlu diuji |
| 18 | Laporan admin | Admin membuka laporan | Filter periode | Laporan tampil sesuai filter | Perlu diuji langsung | Perlu diuji |

Project juga memiliki test otomatis pada backend dan Flutter. Namun untuk laporan, hasil aktual pengujian tetap perlu diisi berdasarkan pengujian langsung pada aplikasi yang berjalan.

## 4.4 Analisis Hasil dan Evaluasi

Berdasarkan implementasi kode, sistem RuslyTravel telah menjawab masalah utama pada proses pemesanan manual. Sistem membantu penumpang melihat jadwal, memilih kursi, membuat pemesanan, melakukan pembayaran, dan memperoleh tiket digital. Sistem juga membantu mengurangi risiko pemesanan ganda melalui mekanisme seat lock.

Bagi pengemudi, sistem membantu melihat jadwal tugas, manifest penumpang, titik jemput, titik turun, status pembayaran, dan validasi tiket QR. Bagi admin, sistem membantu mengelola data operasional dan memantau pemesanan, pembayaran, biaya operasional, notifikasi, dan laporan.

Keterbatasan sistem yang perlu ditulis secara jujur adalah belum adanya Login Google/OAuth, OTP SMS produksi, tracking GPS real-time kendaraan, WebSocket realtime penuh, refund otomatis, dan komplain otomatis. Fitur-fitur tersebut tidak mengurangi fungsi utama sistem, tetapi dapat menjadi arah pengembangan selanjutnya.

## 30. Draft Narasi BAB V Penutup

# BAB V PENUTUP

## 5.1 Kesimpulan

Berdasarkan hasil implementasi dan pembahasan, dapat disimpulkan bahwa:

1. Sistem RuslyTravel berhasil dirancang sebagai aplikasi pemesanan jasa transportasi umum lintas kota berbasis mobile dengan dukungan backend Laravel dan web admin.
2. Sistem menyediakan fitur utama untuk penumpang, pengemudi, tamu, dan admin, seperti pengelolaan rute, armada, pengemudi, jadwal perjalanan, pemesanan, pembayaran, tiket elektronik QR Code, manifest pengemudi, notifikasi, biaya operasional, laporan, banner, dan pengaturan sistem.
3. Mekanisme pemilihan kursi dan seat lock membantu mengurangi risiko kursi dipesan oleh lebih dari satu penumpang pada waktu yang bersamaan.
4. Tiket elektronik berbasis QR Code membantu menggantikan bukti pemesanan kertas sehingga lebih mudah ditampilkan, disimpan, dan diverifikasi.
5. Web admin membantu pihak Rusly Travel mengelola data operasional dan memantau aktivitas pemesanan secara lebih terstruktur.
6. Sistem belum mencakup beberapa fitur lanjutan seperti Login Google/OAuth, OTP SMS produksi, tracking GPS real-time kendaraan, dan WebSocket realtime penuh, sehingga fitur tersebut dapat dijadikan saran pengembangan.

## 5.2 Saran

Saran pengembangan sistem RuslyTravel ke depan adalah:

1. Mengintegrasikan payment gateway production secara penuh jika sistem akan digunakan untuk transaksi nyata.
2. Menambahkan push notification menggunakan Firebase Cloud Messaging atau layanan serupa agar notifikasi dapat diterima pengguna tanpa harus membuka aplikasi.
3. Menambahkan tracking GPS real-time kendaraan jika kebutuhan operasional travel sudah memerlukan pemantauan posisi kendaraan.
4. Menambahkan Login Google/OAuth atau OTP SMS apabila tersedia kebutuhan dan anggaran layanan pihak ketiga.
5. Menyempurnakan export laporan Excel/CSV agar laporan lebih siap digunakan untuk administrasi perusahaan.
6. Menambahkan backup database berkala untuk menjaga keamanan data operasional.
7. Menambahkan audit log yang lebih lengkap untuk mencatat aktivitas penting admin.
8. Melakukan pengujian langsung bersama penumpang, pengemudi, dan admin Rusly Travel agar sistem dapat dievaluasi berdasarkan kondisi penggunaan nyata.

## 31. Checklist Sinkronisasi Dokumen Laporan

Gunakan checklist ini agar isi laporan tidak bertentangan dengan kondisi project:

- [ ] BAB IV menyebut sistem memakai auth lokal, bukan Google/OAuth.
- [ ] BAB IV menyebut SMS OTP belum diterapkan dan dapat menjadi saran pengembangan.
- [ ] BAB IV menyebut notifikasi tersedia, tetapi bukan WebSocket realtime penuh.
- [ ] BAB IV menyebut GPS dipakai untuk titik jemput/turun, bukan tracking kendaraan realtime.
- [ ] BAB IV menyebut Pakasir sebagai simulasi/integrasi terbatas jika belum diuji production.
- [ ] BAB IV menampilkan bukti implementasi dari file controller, model, service, route, dan view.
- [ ] Tabel pengujian tidak mengarang hasil aktual; gunakan “Perlu diuji langsung” jika belum diuji.
- [ ] BAB V hanya menyimpulkan fitur yang benar-benar dibahas pada BAB IV.
- [ ] Saran BAB V berisi fitur lanjutan yang memang belum menjadi implementasi utama.

## 32. File Bukti Implementasi yang Bisa Dirujuk

Backend Laravel:

- `backend/routes/api.php`
- `backend/routes/web.php`
- `backend/app/Http/Controllers/Api/AuthController.php`
- `backend/app/Http/Controllers/Api/BookingController.php`
- `backend/app/Http/Controllers/Api/DriverController.php`
- `backend/app/Http/Controllers/Api/NotificationController.php`
- `backend/app/Http/Controllers/Api/PaymentGatewayController.php`
- `backend/app/Http/Controllers/Web/Admin/JadwalController.php`
- `backend/app/Http/Controllers/Web/Admin/PemesananController.php`
- `backend/app/Http/Controllers/Web/Admin/KendaraanController.php`
- `backend/app/Http/Controllers/Web/Admin/PengemudiController.php`
- `backend/app/Services/BookingService.php`
- `backend/app/Services/BaggageCalculatorService.php`
- `backend/app/Services/PakasirService.php`
- `backend/app/Services/SettingService.php`

Frontend Flutter:

- `frontend/lib/features/auth/travs_auth_page.dart`
- `frontend/lib/features/home/travs_home_page.dart`
- `frontend/lib/features/schedule/travs_schedule_detail_page.dart`
- `frontend/lib/features/booking/travs_booking_detail_page.dart`
- `frontend/lib/features/payment/travs_payment_preview_page.dart`
- `frontend/lib/features/ticket/travs_ticket_detail_page.dart`
- `frontend/lib/features/pickup/travs_pickup_map_picker_page.dart`
- `frontend/lib/core/services/travs_api_service.dart`
- `frontend/lib/core/theme/travs_theme.dart`

Database dan model:

- `backend/database/migrations`
- `backend/app/Models/Pemesanan.php`
- `backend/app/Models/Pembayaran.php`
- `backend/app/Models/TiketElektronik.php`
- `backend/app/Models/Jadwal.php`
- `backend/app/Models/Kendaraan.php`
- `backend/app/Models/Pengemudi.php`
- `backend/app/Models/ItemBagasi.php`
- `backend/app/Models/Notifikasi.php`

Pengujian:

- `backend/tests/Feature/BookingFlowTest.php`
- `backend/tests/Feature/PaymentGatewayTest.php`
- `backend/tests/Feature/DriverOperationsTest.php`
- `backend/tests/Feature/AdminOperationsTest.php`
- `backend/tests/Feature/NotificationApiTest.php`
- `frontend/test`

## 33. Pembaruan Sistem per 8 Juli 2026

Bagian ini mencatat perubahan terbaru yang perlu ikut dipertimbangkan saat menyusun BAB IV dan BAB V laporan tugas akhir.

### 33.1 Validasi dan Manajemen Pengemudi

Menu Pengemudi pada web admin sudah disederhanakan karena akun pengemudi dibuat langsung oleh admin. Fitur verifikasi manual tidak lagi ditampilkan pada daftar, filter, maupun form tambah/edit pengemudi. Field lama terkait verifikasi tetap dipertahankan pada database untuk menjaga kompatibilitas data lama, tetapi proses admin sekarang otomatis menyiapkan pengemudi agar siap dipilih pada jadwal.

Pesan validasi form pengemudi juga sudah menggunakan Bahasa Indonesia yang lebih ramah. Contohnya, email duplikat tidak lagi menampilkan pesan bawaan framework, tetapi menampilkan keterangan bahwa email sudah digunakan akun lain.

File terkait:

- `backend/app/Http/Controllers/Web/Admin/PengemudiController.php`
- `backend/resources/views/admin/pengemudi/index.blade.php`
- `backend/resources/views/admin/pengemudi/create.blade.php`
- `backend/resources/views/admin/pengemudi/edit.blade.php`

### 33.2 Detail Pemesanan dan Pembayaran

Teks pada halaman Detail Pemesanan admin sudah dibuat lebih ramah. Istilah teknis seperti gateway dan status digital manual tidak ditonjolkan kepada admin. Penjelasan pembayaran digital diarahkan menjadi bahwa status pembayaran diperbarui otomatis setelah konfirmasi dari layanan pembayaran, sedangkan pembayaran tunai tetap dapat dikonfirmasi sesuai alur sistem.

File terkait:

- `backend/resources/views/admin/pemesanan/show.blade.php`
- `backend/app/Http/Controllers/Web/Admin/PemesananController.php`
- `backend/app/Http/Controllers/Web/Admin/PembayaranController.php`

### 33.3 Export Laporan

Export Laporan Pemesanan dan Laporan Transaksi sudah dirapikan. Format utama yang disediakan adalah:

- `Export Excel` untuk laporan rapi dalam format `.xls`.
- `Export CSV` untuk data mentah.

Format `.xlsx` tidak ditampilkan pada laporan utama agar fitur lebih sederhana dan tidak membutuhkan dependency tambahan. Export `.xls` dibuat lebih formal dengan identitas `RUSLY TRAVEL`, judul laporan, periode, pencetak, tanggal cetak, header berwarna navy, border tabel, alignment, wrap text, format tanggal, format Rupiah, dan ringkasan total jika tersedia.

File terkait:

- `backend/app/Http/Controllers/Web/Admin/LaporanController.php`
- `backend/resources/views/admin/laporan/pemesanan.blade.php`
- `backend/resources/views/admin/laporan/transaksi.blade.php`

### 33.4 Banner Beranda

Pengelolaan Banner Beranda sudah dipindahkan ke halaman Pengaturan Sistem sebagai tab `Banner Beranda`. Menu sidebar khusus Banner Beranda tidak lagi ditampilkan agar struktur menu lebih ringkas. Route dan API banner tetap dipertahankan agar aplikasi mobile tamu dan penumpang tetap membaca data banner dari endpoint yang sama.

Tampilan daftar banner di web admin juga diperbaiki. Pada desktop, area daftar banner memakai sekitar dua pertiga lebar konten agar ukuran foto tidak melebar berlebihan. Gambar banner ditampilkan dengan rasio 16:9, ukuran responsif, dan preview tetap dapat dibuka. Jarak toggle dengan teks `Tampilkan di aplikasi` juga dirapikan agar mudah dibaca.

File terkait:

- `backend/app/Http/Controllers/Web/Admin/HomeBannerController.php`
- `backend/app/Http/Controllers/Web/Admin/SettingsController.php`
- `backend/resources/views/admin/pengaturan/index.blade.php`
- `backend/resources/views/admin/layouts/app.blade.php`
- `backend/resources/css/app.css`

### 33.5 Notifikasi

Teks ringkasan yang tidak terlalu penting pada halaman Notifikasi admin dan Log Notifikasi User sudah disederhanakan. Fungsi notifikasi tidak diubah; perubahan hanya pada teks tampilan agar halaman lebih bersih.

File terkait:

- `backend/resources/views/admin/notifikasi/index.blade.php`

### 33.6 Pengujian Terbaru

Pengujian backend terakhir yang dijalankan:

- `php -l` pada controller admin yang diubah: berhasil tanpa error sintaks.
- `php artisan test --filter=AdminOperationsTest`: 19 test berhasil.
- `php artisan test`: 164 test berhasil dengan 886 assertion.

Pengujian Flutter tidak dijalankan pada pembaruan ini karena perubahan utama sesi ini berada pada web admin dan backend. Sinkronisasi banner ke aplikasi mobile tetap dijaga melalui API yang sama dan telah tercakup oleh test backend `HomeBannerFeatureTest`.
