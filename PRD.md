# PRD Final, Audit Teknis, dan Spesifikasi Produk

Status: Revisi scope final tanpa titipan dan carter
Tanggal pembaruan: 27 April 2026
Proyek: Rancang Bangun Aplikasi Pemesanan Jasa Transportasi Umum Lintas Kota pada Rusly Travel Berbasis Mobile

Dokumen ini menggantikan versi sebelumnya. Revisi ini menetapkan bahwa scope akhir difokuskan pada layanan travel penumpang lintas kota saja. Fitur tambahan seperti titipan barang, paket, dokumen, hewan, kendaraan, dan carter kendaraan dikeluarkan dari batas proyek.

Dokumen ini disusun berdasarkan:
- kondisi repository yang benar-benar ada pada workspace saat ini
- wireframe Figma mobile dan web admin yang dibagikan user sebagai acuan layout
- keputusan scope akhir yang memfokuskan sistem pada pemesanan penumpang, operasional pengemudi, operasional admin, pembayaran, QR ticket, bagasi, dan laporan

## 1. Audit Repository

### 1.1 Ringkasan struktur project

Repository terdiri dari tiga area utama:

- `backend/`
  Laravel untuk API mobile dan dashboard admin web berbasis Blade.
- `frontend/`
  Flutter untuk aplikasi mobile guest, penumpang, dan pengemudi.
- root docs
  `PRD.md` dan `task.md` sebagai dokumen kerja proyek.

Struktur aktual yang penting:

- `backend/app/Models`
  model inti untuk pengguna, pengemudi, kendaraan, rute, jadwal, kursi, pemesanan, bagasi, pembayaran, tiket, notifikasi, biaya operasional, dan pengaturan sistem.
- `backend/app/Http/Controllers/Api`
  controller auth, bootstrap mobile, guest schedule, booking penumpang, dan dashboard pengemudi.
- `backend/app/Http/Controllers/Web/Admin`
  controller dashboard, kendaraan, pengemudi, rute, jadwal, pemesanan, biaya operasional, laporan, notifikasi, dan pengaturan.
- `backend/app/Services`
  `BookingService` dan `BookingCalculator`.
- `backend/database/migrations`
  skema inti untuk operasional travel penumpang.
- `frontend/lib`
  fondasi app flow, auth page, home shell, schedule detail, payment preview, theme, dan API service.

### 1.2 Teknologi yang benar-benar dipakai saat ini

Backend:

- PHP `^8.3`
- Laravel `^13`
- Laravel Sanctum
- Blade
- Vite
- SQLite untuk dev/test saat ini
- PHPUnit

Frontend:

- Flutter dengan Dart `^3.11.5`
- `http`
- `intl`
- `google_fonts`
- `shared_preferences`

Teknologi target yang belum benar-benar terpasang:

- MySQL untuk deployment akhir
- Firebase Cloud Messaging
- geolocator / map picker / reverse geocoding
- payment gateway QRIS atau Virtual Account
- QR scanner package
- storage upload package yang matang

### 1.3 Kesehatan build saat audit

Verifikasi lokal yang sudah dilakukan:

- `php artisan test` lulus
- `flutter analyze` lulus
- `flutter test` lulus

Kesimpulan:

- fondasi project masih buildable
- tetapi sebagian besar flow bisnis inti masih belum end-to-end
- test coverage masih dangkal dan belum menyentuh operasi berisiko tinggi

### 1.4 Modul atau fitur yang sudah diimplementasikan

Yang sudah ada dan benar-benar punya fondasi kode:

- auth API penumpang dengan Sanctum
- guest bisa melihat daftar jadwal aktif
- guest bisa melihat detail jadwal
- booking draft penumpang di backend
- simulasi pembayaran lokal untuk testing
- penerbitan tiket elektronik dasar
- dashboard admin web berbasis Blade
- CRUD kendaraan
- CRUD pengemudi
- CRUD rute
- CRUD jadwal
- monitoring pemesanan
- biaya operasional
- laporan dasar
- notifikasi database dasar
- pengaturan sistem key-value
- dashboard pengemudi API sederhana
- flow mobile baru: splash, login/register, guest mode, home shell role-based

### 1.5 Modul atau fitur yang setengah jadi

- seat lock dua tahap
- bagasi dan aturan biaya tambahan
- tiket elektronik dan QR
- dashboard pengemudi mobile
- dashboard penumpang mobile
- notifikasi event-based
- pembayaran digital
- pembayaran tunai dengan verifikasi admin
- history booking live
- submit booking live dari Flutter

### 1.6 Modul atau fitur yang belum ada

- forgot password penumpang
- flow booking live dari Flutter sampai create booking final
- form lock yang benar-benar aktif
- cleanup expired lock terjadwal
- auto cancel payment expired
- payment gateway nyata
- webhook payment
- ticket QR scan satu kali pakai
- manifest penumpang live untuk pengemudi
- action pickup, hadir, drop-off, selesai untuk pengemudi
- GPS permission
- map picker
- reverse geocoding
- deep link Google Maps
- FCM push notification
- dashboard pembayaran admin yang lebih fokus
- audit trail status
- black box test suite formal

### 1.7 Model, tabel, dan endpoint yang sudah ada

Tabel domain yang sudah ada:

- `pengguna`
- `pengemudi`
- `kendaraan`
- `rute`
- `jadwal`
- `kursi_jadwal`
- `pemesanan`
- `detail_pemesanan`
- `item_bagasi`
- `pembayaran`
- `tiket_elektronik`
- `notifikasi`
- `biaya_operasional_perjalanan`
- `pengaturan_sistem`

Endpoint API yang sudah ada:

- `GET /api/ping`
- `GET /api/mobile/bootstrap`
- `GET /api/guest/jadwal`
- `GET /api/guest/jadwal/{jadwal}`
- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout`
- `GET /api/penumpang/dashboard`
- `GET /api/penumpang/bookings`
- `GET /api/penumpang/bookings/{booking}`
- `POST /api/penumpang/bookings/estimate`
- `POST /api/penumpang/bookings`
- `POST /api/penumpang/bookings/{booking}/simulate-payment`
- `GET /api/pengemudi/dashboard`

### 1.8 Temuan arsitektur, code smell, dan technical debt

Temuan paling penting:

| Level | Temuan | Bukti kode | Dampak |
|---|---|---|---|
| Kritis | Edit jadwal bisa mereset kursi existing menjadi tersedia | `backend/app/Http/Controllers/Web/Admin/JadwalController.php` pada sinkronisasi seat map | Berisiko membuka kursi yang sudah terkunci atau terpesan. |
| Kritis | Booking draft belum aman terhadap race condition | `backend/app/Services/BookingService.php` melakukan cek sebelum transaction dan belum memakai row lock | Kursi berpotensi dipesan ganda pada request bersamaan. |
| Tinggi | Form lock belum benar-benar hidup | setting `seat_lock_form_minutes` baru ada di response dan seed | Aturan bisnis dua tahap seat lock belum terpenuhi. |
| Tinggi | Tidak ada scheduler cleanup expired lock | `backend/routes/console.php` masih default | Kursi terkunci bisa menggantung terlalu lama. |
| Tinggi | Mobile masih fallback ke mock saat API gagal | `frontend/lib/core/services/travs_api_service.dart` | Tester bisa salah mengira fitur hidup padahal API gagal. |
| Tinggi | Integrasi payment, maps, FCM, file upload belum punya fondasi service nyata | config dan service layer belum ada | Scope inti belum siap dihubungkan ke layanan eksternal. |
| Menengah | Controller admin masih memegang logika transisi status secara langsung | beberapa controller web admin | Sulit dirawat dan rawan status loncat. |
| Menengah | Driver dashboard masih summary-only | `DriverController` | Belum cocok untuk operasional lapangan. |
| Menengah | Halaman Flutter besar dan masih banyak placeholder | terutama detail jadwal dan payment preview | Sulit dites dan rawan regressi UI. |

### 1.9 Masalah UI/UX yang terlihat saat ini

Mobile:

- sebagian flow masih berupa shell dan preview
- belum semua state live seperti empty booking, payment pending, atau QR aktif
- belum ada UX permission untuk lokasi dan kamera
- belum ada onboarding pickup point yang utuh

Admin web:

- data-heavy tetapi guard operasional masih kurang
- warning untuk aksi berisiko belum cukup kuat
- dashboard pembayaran sudah dipisah dari monitoring booking, masih perlu hardening warning operasional lanjutan

Driver:

- manifest live dan scan QR live sudah tersedia
- tombol aksi berangkat dan selesai sudah tersedia
- action pickup masih perlu difinalkan agar alur lapangan lebih lengkap

### 1.10 Gap antara kode saat ini dengan kebutuhan produk final

Setelah titipan dan carter dikeluarkan, gap utama sekarang adalah:

- core booking penumpang belum benar-benar live dari mobile ke backend
- seat lock aman belum selesai
- payment digital sudah punya scaffold Pakasir dan webhook, tetapi transaksi nyata Pakasir masih perlu uji callback publik
- pembayaran tunai sudah punya verifikasi admin dasar, audit trail, tiket, dan notifikasi
- QR ticket sudah bisa diverifikasi satu kali pakai, masih perlu monitoring admin yang lebih lengkap
- pengemudi sudah punya manifest, scan live, dan aksi berangkat/selesai; action pickup belum final
- admin sudah punya guard lifecycle dan dashboard pembayaran dasar, masih perlu warning operasional yang lebih kuat
- pickup point belum didukung map picker dan reverse geocoding
- push notification belum ada

## 2. Pemahaman Produk

### 2.1 Ringkasan produk final

Produk akhir adalah sistem pemesanan travel lintas kota Rusly Travel yang terdiri dari:

- aplikasi mobile untuk guest, penumpang, dan pengemudi
- backend API Laravel
- web admin operasional
- database relasional

Produk ini hanya mencakup layanan penumpang lintas kota, termasuk:

- lihat jadwal
- booking kursi
- input pickup point
- input data penumpang
- input bagasi
- pembayaran
- tiket digital dengan QR
- riwayat booking
- operasional pengemudi
- operasional admin

### 2.2 Problem statement

Rusly Travel masih sangat bergantung pada proses manual melalui WhatsApp, telepon, tiket kertas, dan pencatatan manual. Dampaknya:

- rawan salah catat
- rawan miskomunikasi
- kursi bisa terpesan ganda
- pelanggan tidak mendapat status yang jelas
- pengemudi tidak memiliki manifest digital
- admin tidak memiliki pusat kontrol operasional yang rapi

### 2.3 Target pengguna

- Guest
  ingin melihat jadwal, harga, kendaraan, dan kursi tanpa harus login.
- Penumpang
  ingin booking cepat, aman, jelas, dan punya bukti digital.
- Pengemudi
  ingin tugas lapangan yang ringkas, jelas, dan mudah dioperasikan.
- Admin
  ingin data operasional yang rapi, cepat diolah, dan mudah diaudit.

### 2.4 Scope in

- guest read-only jadwal
- auth penumpang
- booking penumpang
- seat lock dua tahap
- pickup point berbasis lokasi
- bagasi dan foto bagasi
- payment digital dan tunai
- tiket QR
- history booking
- mobile pengemudi
- web admin
- notifikasi event penting
- laporan dan pengaturan sistem

### 2.5 Scope out

- titipan barang, paket, dokumen
- titipan hewan
- titipan kendaraan
- carter kendaraan
- live tracking kendaraan di dalam aplikasi
- in-app navigation turn-by-turn
- chat internal
- rating dan review

### 2.6 Assumptions

- guest tidak memiliki akun khusus
- penumpang memakai akun Sanctum mobile
- pengemudi memakai akun mobile dengan role khusus
- admin tetap bekerja dari web admin
- navigasi hanya membuka Google Maps eksternal
- QR dipakai untuk tiket penumpang
- deployment akhir diarahkan ke MySQL walau dev saat ini masih SQLite

### 2.7 Open questions

- apakah cash dianggap valid saat booking dibuat atau setelah diverifikasi admin
- apakah semua jadwal selalu memiliki seat map sesuai kapasitas kendaraan
- apakah foto bagasi wajib untuk semua booking atau hanya kondisi tertentu
- apakah perubahan jadwal setelah payment sukses diizinkan atau harus dibatasi ketat

### 2.8 Saran rollout bertahap

1. stabilisasi engine seat dan lifecycle booking
2. hidupkan flow mobile penumpang end-to-end
3. hidupkan operasional pengemudi
4. matangkan dashboard admin pembayaran, laporan, dan pengaturan
5. hardening, black box test, dan release prep

## 3. PRD Final

### 3.1 Product overview

Rusly Travel membutuhkan sistem terpusat untuk memesan, membayar, mengelola, memverifikasi, dan melaporkan perjalanan penumpang lintas kota. Sistem harus sederhana untuk pengguna umum dan efisien untuk admin serta pengemudi.

### 3.2 Business goals

- mengurangi pemesanan manual
- mencegah double booking
- menyediakan tiket digital yang sah
- meningkatkan transparansi status booking
- mempercepat kerja admin
- mempermudah operasional pengemudi

### 3.3 User personas

Persona 1 - Penumpang rutin:

- sering bepergian antarkota
- ingin lihat jadwal dan kursi dengan cepat
- ingin bayar dengan cara yang jelas
- ingin tiket digital yang mudah ditunjukkan

Persona 2 - Pengemudi lapangan:

- bekerja dengan waktu terbatas
- butuh manifest yang ringkas
- butuh scan tiket yang mudah
- butuh akses cepat ke titik jemput

Persona 3 - Admin operasional:

- mengelola banyak data sekaligus
- perlu memantau exception dengan cepat
- perlu kontrol status, jadwal, armada, driver, dan pembayaran

### 3.4 Roles and permissions

Guest:

- lihat jadwal
- lihat detail jadwal
- tidak bisa checkout

Penumpang:

- register dan login
- membuat booking
- memilih kursi
- mengisi pickup point
- mengisi data penumpang
- mengisi bagasi
- membayar
- melihat riwayat
- melihat tiket digital

Pengemudi:

- login
- melihat tugas aktif
- melihat manifest penumpang
- scan QR tiket
- membuka Google Maps eksternal
- update status perjalanan

Admin:

- mengelola pengguna, pengemudi, kendaraan, rute, jadwal
- mengelola pemesanan
- mengelola pembayaran
- memverifikasi pembayaran tunai
- mengelola biaya operasional
- mengelola notifikasi dan pengaturan
- membuat laporan

### 3.5 Service matrix

| Area | Guest | Penumpang | Pengemudi | Admin |
|---|---|---|---|---|
| Jadwal | lihat | lihat | lihat trip assigned | kelola |
| Booking kursi | tidak | buat dan lihat | lihat manifest | kelola |
| Pembayaran | tidak | bayar | lihat status | kelola dan verifikasi |
| Tiket QR | tidak | lihat | scan | monitor |
| Operasional trip | tidak | lihat status | jalankan trip | monitor dan atur |

### 3.6 User journeys

Journey guest:

1. guest buka aplikasi
2. guest melihat daftar jadwal
3. guest membuka detail jadwal
4. guest diminta login saat ingin lanjut booking

Journey penumpang:

1. penumpang login
2. penumpang pilih jadwal
3. penumpang pilih kursi
4. penumpang isi titik jemput
5. penumpang isi data penumpang
6. penumpang isi bagasi
7. penumpang memilih metode bayar
8. sistem membuat booking dan payment draft
9. penumpang membayar
10. sistem menerbitkan tiket QR
11. penumpang melihat riwayat dan tiket

Journey pengemudi:

1. pengemudi login
2. pengemudi melihat trip aktif
3. pengemudi membuka manifest penumpang
4. pengemudi membuka titik jemput di Google Maps
5. pengemudi scan tiket saat boarding
6. pengemudi update status pickup, berangkat, selesai

Journey admin:

1. admin login web
2. admin mengelola jadwal, armada, dan pengemudi
3. admin memantau booking dan pembayaran
4. admin memverifikasi cash bila perlu
5. admin melihat laporan dan biaya operasional

### 3.7 Detailed functional requirements

#### FR-01 Auth

- penumpang bisa register
- penumpang bisa login dengan email atau nomor HP
- penumpang bisa logout
- pengemudi bisa login
- admin login melalui web

#### FR-02 Guest schedule

- guest dapat melihat jadwal aktif
- guest dapat melihat detail jadwal
- guest hanya read-only

#### FR-03 Booking penumpang

- penumpang dapat memilih jadwal
- penumpang dapat memilih satu atau beberapa kursi
- sistem menampilkan seat map
- penumpang dapat mengisi biodata penumpang per kursi

#### FR-04 Pickup point

- pickup point menyimpan latitude
- pickup point menyimpan longitude
- pickup point menyimpan alamat hasil reverse geocoding

#### FR-05 Bagasi

- penumpang dapat mengisi barang bawaan
- penumpang dapat memilih hitung berdasarkan berat + item atau ukuran + item
- input berat, panjang, dan lebar menggunakan angka, bukan slider
- sistem menghitung aturan bagasi sesuai setting
- ringkasan checkout menampilkan rincian biaya tambahan bagasi
- foto bagasi dapat diunggah bila diwajibkan sistem

#### FR-06 Payment

- sistem mendukung QRIS
- sistem mendukung transfer atau VA bila diaktifkan
- sistem mendukung tunai bila diaktifkan
- status payment harus terlihat jelas

#### FR-07 Ticket and QR

- tiket digital diterbitkan setelah payment valid
- QR harus unik
- QR hanya bisa dipindai satu kali

#### FR-08 History and notifications

- penumpang dapat melihat riwayat booking
- penumpang mendapat notifikasi event penting

#### FR-09 Driver operations

- pengemudi dapat melihat dashboard tugas
- pengemudi dapat melihat manifest penumpang
- pengemudi dapat scan QR
- pengemudi dapat membuka pickup point di Google Maps
- pengemudi dapat update status perjalanan

#### FR-10 Admin operations

- admin dapat CRUD pengguna
- admin dapat CRUD pengemudi
- admin dapat CRUD kendaraan
- admin dapat CRUD rute
- admin dapat CRUD jadwal
- admin dapat memantau dan mengelola booking
- admin dapat mengelola pembayaran
- admin dapat mengelola biaya operasional
- admin dapat mengelola pengaturan sistem
- admin dapat melihat statistik dan laporan

### 3.8 Business rules

1. satu kursi pada satu jadwal tidak boleh dibooking dua kali
2. seat lock harus aman terhadap request bersamaan
3. titik jemput harus menyimpan latitude, longitude, dan alamat
4. navigasi hanya membuka Google Maps eksternal
5. QR tiket unik dan hanya valid satu kali scan
6. booking code unik
7. payment status dan booking status tidak boleh loncat sembarangan
8. file upload bagasi harus divalidasi tipe dan ukuran
9. admin memiliki pengaturan global sistem
10. sistem tidak boleh over-engineered

### 3.9 Non-functional requirements

- response API konsisten
- validasi server-side wajib
- authorization berbasis role wajib
- operasi kritis memakai transaction
- UI mobile harus ringan dan mudah dipahami
- web admin harus efisien untuk data-heavy flow
- sistem harus tetap buildable dan realistis untuk TA

### 3.10 Edge cases and error cases

- seat sudah diambil user lain saat checkout
- payment callback datang dua kali
- jadwal penuh
- lock kursi expired saat user masih membuka form
- QR dipindai dua kali
- admin mengubah jadwal yang sudah memiliki booking
- pengemudi mencoba scan tiket yang bukan milik trip-nya
- file bagasi terlalu besar atau format tidak valid

### 3.11 Payment flow

1. user memilih metode bayar
2. sistem membuat record pembayaran draft
3. sistem menahan kursi sampai batas waktu payment
4. jika payment sukses, booking menjadi terbayar dan tiket diterbitkan
5. jika payment expired, booking dibatalkan dan kursi dilepas
6. jika cash dipilih, admin memverifikasi sesuai aturan sistem

### 3.12 QR / verification flow

1. tiket diterbitkan per detail penumpang
2. token QR unik dibuat oleh backend
3. pengemudi scan QR
4. backend memverifikasi token, jadwal, dan status scan
5. jika valid, status tiket menjadi sudah dipindai
6. scan ulang harus ditolak

### 3.13 Manifest flow

Manifest pengemudi minimal berisi:

- kode booking
- nama penumpang
- kursi
- status hadir
- titik jemput
- catatan bagasi ringkas

### 3.14 Notification flow

Event minimal:

- booking dibuat
- payment berhasil
- payment akan kedaluwarsa
- jadwal berubah
- tiket aktif
- status perjalanan diperbarui

### 3.15 Reporting requirements

Laporan minimal:

- jumlah booking per periode
- pendapatan per periode
- booking per rute
- payment per status
- okupansi kursi per jadwal
- biaya operasional perjalanan

### 3.16 Security and access control

- semua request sensitif wajib auth
- role wajib divalidasi server-side
- ownership booking wajib diverifikasi
- file upload tidak boleh percaya path dari client
- payment webhook wajib verifikasi signature
- scan QR wajib dicek server-side

### 3.17 Acceptance criteria

- guest bisa melihat jadwal tanpa login
- penumpang bisa login dan membuat booking
- seat lock aman dan tidak double booking
- payment status jelas
- tiket QR terbit setelah payment valid
- pengemudi bisa melihat manifest dan scan tiket
- admin bisa mengelola jadwal, booking, payment, dan laporan
- build backend dan frontend tetap hijau

## 4. UX / Frontend Spec

### 4.1 Mobile guest / penumpang

#### MGP-01 Splash

- Tujuan: memberi identitas aplikasi dan transisi masuk yang jelas.
- Komponen utama: logo, nama aplikasi, progress indicator.
- Data yang ditampilkan: branding.
- CTA utama: tidak ada.
- CTA sekunder: tidak ada.
- Validasi: tidak ada.
- State: loading only.
- Catatan UX: singkat, tidak lebih dari beberapa detik.
- Rekomendasi layout: full-screen navy gradient.

#### MGP-02 Login / Register

- Tujuan: autentikasi penumpang dengan friction rendah.
- Komponen utama: form login, form register, error message, guest button.
- Data yang ditampilkan: email/no HP, password, nama.
- CTA utama: `Masuk`, `Daftar Sekarang`.
- CTA sekunder: `Masuk Sebagai Tamu`.
- Validasi: email/no HP wajib, password minimal 8 karakter, confirmation match.
- State: loading, validation error, auth error, success.
- Catatan UX: guest mode tetap tersedia.
- Rekomendasi layout: hero sederhana di atas, kartu form putih di bawah.

#### MGP-03 Home guest

- Tujuan: menampilkan rute cepat ke jadwal dan ringkasan layanan inti.
- Komponen utama: hero, route finder card, quick grid, list jadwal populer.
- Data yang ditampilkan: source label, rute populer, harga, kursi, kendaraan.
- CTA utama: `Masuk / Daftar`, `Lihat Jadwal`.
- CTA sekunder: kartu quick info.
- Validasi: tidak ada.
- State: loading list, empty jadwal, error API.
- Catatan UX: fokus ke kejelasan, bukan dekorasi.
- Rekomendasi layout: header navy, kartu putih, bottom nav.

#### MGP-04 Daftar jadwal

- Tujuan: memudahkan guest dan penumpang memindai pilihan trip.
- Komponen utama: list card jadwal, status kursi, harga, waktu.
- Data yang ditampilkan: asal, tujuan, jalur, harga, kursi, kendaraan.
- CTA utama: `Lihat Detail`.
- CTA sekunder: refresh.
- Validasi: tidak ada.
- State: loading, empty, error.
- Catatan UX: sorting waktu berangkat paling dekat.
- Rekomendasi layout: vertical list.

#### MGP-05 Detail jadwal

- Tujuan: membuka informasi lengkap sebelum booking.
- Komponen utama: hero jadwal, info kendaraan, info driver, seat map, ringkasan aturan.
- Data yang ditampilkan: seat map, fasilitas, harga, durasi, aturan bagasi, pilihan metode hitung bagasi.
- CTA utama: `Login Untuk Booking` atau `Lanjut ke Checkout`.
- CTA sekunder: refresh.
- Validasi: batas kursi per booking, kursi tersedia, bagasi.
- State: loading, sold out, error.
- Catatan UX: seat map harus mudah dipindai.
- Rekomendasi layout: kartu bertingkat dengan CTA tetap jelas.

#### MGP-06 Checkout / Payment

- Tujuan: merangkum biaya dan memilih metode bayar.
- Komponen utama: summary, passenger form, bagasi, metode bayar.
- Data yang ditampilkan: subtotal tiket, admin fee, rincian biaya bagasi, total, countdown form dan pembayaran.
- CTA utama: `Bayar`.
- CTA sekunder: kembali ke detail.
- Validasi: field penumpang, pickup point, method payment, foto bagasi bila wajib.
- State: loading, validation error, payment pending, success, expired.
- Catatan UX: total harus selalu terlihat jelas.
- Rekomendasi layout: stacked cards dengan summary kuat di bawah.

#### MGP-07 Riwayat booking

- Tujuan: menampilkan status order penumpang.
- Komponen utama: filter status, list booking, quick status pill.
- Data yang ditampilkan: kode booking, rute, waktu, status, total.
- CTA utama: `Lihat Detail`.
- CTA sekunder: filter.
- Validasi: ownership.
- State: empty, loading, error.
- Catatan UX: tampilkan booking aktif di urutan atas.
- Rekomendasi layout: list card.

#### MGP-08 Tiket QR

- Tujuan: menampilkan tiket digital aktif.
- Komponen utama: card ticket, QR placeholder, ringkasan trip.
- Data yang ditampilkan: kode booking, rute, waktu, status scan.
- CTA utama: `Tampilkan QR`.
- CTA sekunder: `Lihat Jadwal`.
- Validasi: hanya booking terbayar.
- State: empty, loading, invalid ticket.
- Catatan UX: QR harus mudah diakses tanpa banyak tap.
- Rekomendasi layout: kartu kontras tinggi.

### 4.2 Mobile pengemudi

#### MGD-01 Dashboard

- Tujuan: ringkasan tugas harian.
- Komponen utama: summary card, list trip berikutnya.
- Data yang ditampilkan: jumlah jadwal aktif, jumlah penumpang, trip list.
- CTA utama: `Lihat Manifest`.
- CTA sekunder: `Buka Scan`.
- Validasi: role pengemudi.
- State: empty trip, loading, error.
- Catatan UX: tampilkan info minimum yang cepat dibaca.
- Rekomendasi layout: summary di atas, trip list di bawah.

#### MGD-02 Manifest

- Tujuan: melihat daftar penumpang per trip.
- Komponen utama: trip card, list penumpang, kursi, pickup.
- Data yang ditampilkan: nama, kursi, status hadir, pickup point.
- CTA utama: `Buka Trip`.
- CTA sekunder: refresh.
- Validasi: hanya trip assigned ke pengemudi.
- State: empty, loading, error.
- Catatan UX: jangan campur data tidak penting.
- Rekomendasi layout: list per trip.

#### MGD-03 Scan tiket

- Tujuan: verifikasi boarding penumpang.
- Komponen utama: kamera scanner, hasil validasi, info trip.
- Data yang ditampilkan: hasil scan, nama penumpang, kursi, status.
- CTA utama: `Aktifkan Scanner`.
- CTA sekunder: `Cek Manual`.
- Validasi: QR valid, belum dipindai, milik trip yang benar.
- State: camera denied, scan success, already used, invalid, offline.
- Catatan UX: hasil scan harus tegas dan cepat.
- Rekomendasi layout: layar kamera dominan.

### 4.3 Web admin

#### WAD-01 Dashboard

- Tujuan: memantau ringkasan operasional.
- Komponen utama: summary card, table booking terbaru, chart sederhana.
- Data yang ditampilkan: booking hari ini, revenue, pending payment, driver aktif.
- CTA utama: `Buka Pemesanan`.
- CTA sekunder: `Pengaturan Sistem`.
- Validasi: role admin.
- State: partial data, empty, error.
- Catatan UX: exception harus lebih menonjol dari dekorasi.
- Rekomendasi layout: cards + table + chart.

#### WAD-02 Pengemudi

- Tujuan: mengelola akun dan status verifikasi pengemudi.
- Komponen utama: form, table, filter, status pill.
- Data yang ditampilkan: identitas, no SIM, status aktif, status verifikasi.
- CTA utama: `Tambah`, `Simpan`.
- CTA sekunder: `Edit`, `Hapus`.
- Validasi: email unik, no HP unik, no SIM unik.
- State: empty list, validation error.
- Catatan UX: bedakan status nonaktif dan belum verified.
- Rekomendasi layout: split form dan table.

#### WAD-03 Kendaraan

- Tujuan: mengelola armada.
- Komponen utama: form, table, filter status.
- Data yang ditampilkan: plat, merk, tipe, kapasitas, status.
- CTA utama: `Tambah`.
- CTA sekunder: `Edit`, `Hapus`.
- Validasi: plat unik, kapasitas logis.
- State: empty list.
- Catatan UX: tandai unit yang sedang dipakai.
- Rekomendasi layout: data table dengan ringkasan.

#### WAD-04 Rute dan Jadwal

- Tujuan: mengelola lintasan dan keberangkatan.
- Komponen utama: form rute, form jadwal, list jadwal, status.
- Data yang ditampilkan: asal, tujuan, harga, armada, pengemudi, waktu, status.
- CTA utama: `Tambah Jadwal`.
- CTA sekunder: `Edit`, `Hapus`.
- Validasi: driver aktif, estimasi tiba > berangkat.
- State: empty, conflict, validation error.
- Catatan UX: edit jadwal existing harus punya warning bila sudah ada booking.
- Rekomendasi layout: split form + table.

#### WAD-05 Monitoring pemesanan

- Tujuan: mengelola booking penumpang.
- Komponen utama: filter, table, detail booking.
- Data yang ditampilkan: kode booking, pelanggan, rute, kursi, payment status, total.
- CTA utama: `Lihat Detail`.
- CTA sekunder: filter/reset.
- Validasi: transisi status harus aman.
- State: empty filter result, loading, error.
- Catatan UX: sediakan quick action untuk pending payment dan expired booking.
- Rekomendasi layout: filter bar + data table.

#### WAD-06 Pembayaran, laporan, pengaturan

- Tujuan: memusatkan transaksi dan konfigurasi.
- Komponen utama: table transaksi, cash verify, report filters, grouped settings.
- Data yang ditampilkan: metode, nominal, status, waktu bayar, summary report.
- CTA utama: `Verifikasi Cash`, `Export`, `Simpan`.
- CTA sekunder: `Reset Filter`.
- Validasi: tidak boleh double confirm, nominal logis.
- State: empty report, pending, failed.
- Catatan UX: pembayaran exception jangan bercampur dengan booking exception.
- Rekomendasi layout: tabbed settings dan report table.

## 5. Arsitektur Teknis

### 5.1 High-level architecture

- Flutter mobile berkomunikasi ke Laravel API melalui HTTPS.
- Laravel API menangani auth, booking, payment orchestration, QR verification, notification event, dan persistence.
- Admin web tetap satu aplikasi Laravel + Blade agar deployment TA sederhana.
- Database relasional menyimpan transaksi, operasional, dan konfigurasi.
- Layanan eksternal: payment gateway, reverse geocoding, Google Maps deep link, FCM.

### 5.2 Batas modul

- Auth dan role
- Master operasional
- Passenger booking
- Payment
- Ticket and QR
- Driver operations
- Admin operations
- Notification
- Maps and location
- Reporting and settings

### 5.3 Arsitektur mobile

Target struktur:

- `core/`
  config, constants, HTTP client, auth storage, shared widgets, theme.
- `features/auth`
- `features/home`
- `features/passenger_booking`
- `features/passenger_history`
- `features/passenger_ticket`
- `features/driver_operations`
- `features/notifications`

### 5.4 Arsitektur backend

Rekomendasi:

- controller tipis
- FormRequest untuk validasi
- service layer untuk business logic kritis
- job dan event untuk notification dan cleanup
- policy atau explicit role guard
- resource atau transformer untuk response konsisten

### 5.5 Arsitektur admin web

- tetap Blade
- pecah partial form dan table agar tidak duplikatif
- tambahkan guard untuk aksi berisiko
- buat area pembayaran lebih fokus

### 5.6 Strategi database

- target akhir MySQL
- SQLite masih boleh untuk test dan dev ringan
- index wajib di foreign key, status, dan waktu
- operasi seat lock, payment success, dan scan QR harus atomik
- pertimbangkan `status_logs` untuk audit trail

### 5.7 Strategi auth dan role

- mobile memakai Sanctum token
- admin web memakai session auth
- role minimal: `penumpang`, `pengemudi`, `admin`
- guest adalah mode tanpa auth, bukan row database

### 5.8 Strategi notifikasi

- log notifikasi disimpan ke database
- push notification memakai FCM
- pengiriman memakai queue job
- template diambil dari pengaturan sistem bila memungkinkan

### 5.9 Strategi pembayaran

- buat payment service abstraction
- metode: QRIS, transfer atau VA, cash
- semua callback payment masuk ke webhook controller khusus
- update payment dan booking dalam satu transaction
- simpan raw payload callback

### 5.10 Strategi file upload

- validasi mime, ukuran, dan jumlah file
- generate nama file aman
- simpan metadata file, bukan path mentah dari client
- gunakan disk yang dikontrol server

### 5.11 Strategi QR

- QR berbasis token unik atau signed payload
- verifikasi dilakukan di backend
- satu token satu kali pakai
- hasil scan disimpan lengkap dengan waktu scan dan pengemudi

### 5.12 Strategi concurrency seat lock

Desain target:

1. request pemilihan kursi masuk
2. backend membuka transaction
3. row kursi diambil dengan row lock
4. sistem memeriksa status kursi dan expiry lock
5. sistem memperbarui lock owner dan expiry
6. transaction commit

Tambahan penting:

- scheduler membersihkan lock kadaluarsa
- booking payment expired membatalkan order dan melepas kursi
- admin tidak boleh mereset seat map existing sembarangan

## 6. Garis Besar API dan Data Contract

### 6.1 Prinsip response

Success response target:

```json
{
  "success": true,
  "message": "Pesan singkat",
  "data": {}
}
```

Error response target:

```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "field_name": [
      "Pesan error"
    ]
  }
}
```

### 6.2 Endpoint penting

Guest:

- `GET /api/mobile/bootstrap`
- `GET /api/guest/jadwal`
- `GET /api/guest/jadwal/{id}`

Auth:

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/auth/me`
- `POST /api/auth/logout`

Penumpang:

- `GET /api/penumpang/dashboard`
- `POST /api/penumpang/bookings/lock-form`
- `POST /api/penumpang/bookings/estimate`
- `POST /api/penumpang/bookings`
- `GET /api/penumpang/bookings`
- `GET /api/penumpang/bookings/{id}`
- `GET /api/penumpang/tickets/{id}`
- `GET /api/penumpang/notifications`

Pengemudi:

- `GET /api/pengemudi/dashboard`
- `GET /api/pengemudi/trips`
- `GET /api/pengemudi/trips/{jadwal}/manifest`
- `POST /api/pengemudi/tickets/scan`
- `POST /api/pengemudi/trips/{jadwal}/status`

Payment:

- `POST /api/payments/pakasir/webhook`
- `POST /api/penumpang/bookings/{booking}/refresh-payment-status`
- `GET /admin/pembayaran`
- `POST /admin/pembayaran/{pemesanan}/confirm-cash`

### 6.3 Request/response tingkat tinggi

Create passenger booking:

```json
{
  "jadwal_id": 12,
  "seat_numbers": [3, 4],
  "pickup": {
    "latitude": -0.0225,
    "longitude": 109.3425,
    "address": "Jalan Ahmad Yani, Pontianak"
  },
  "payment_method": "qris",
  "passengers": [
    {
      "seat_number": 3,
      "nama_penumpang": "Nama 1",
      "no_hp_penumpang": "0812..."
    }
  ],
  "baggage_items": []
}
```

Scan QR:

```json
{
  "token_qr": "opaque-or-signed-token",
  "jadwal_id": 12
}
```

### 6.4 Error responses

- `400 bad_request`
- `401 unauthorized`
- `403 forbidden`
- `404 not_found`
- `409 conflict`
- `422 validation_error`
- `500 internal_server_error`

### 6.5 Alur webhook payment

1. gateway mengirim callback ke webhook
2. backend memverifikasi signature
3. backend mencocokkan invoice reference
4. backend membuka transaction
5. update pembayaran
6. update booking
7. terbitkan tiket bila valid
8. simpan payload callback
9. trigger notifikasi

### 6.6 Trigger notifikasi

- booking created
- payment success
- payment expiring
- jadwal changed
- ticket activated
- trip status changed

## 7. Catatan Database

### 7.1 Entitas inti

- pengguna
- pengemudi
- kendaraan
- rute
- jadwal
- kursi_jadwal
- pemesanan
- detail_pemesanan
- item_bagasi
- pembayaran
- tiket_elektronik
- notifikasi
- biaya_operasional_perjalanan
- pengaturan_sistem

### 7.2 Relasi inti

- `pengguna` has many `pemesanan`
- `pengguna` has one `pengemudi`
- `pengemudi` belongs to `pengguna`
- `jadwal` belongs to `rute`, `kendaraan`, `pengemudi`
- `kursi_jadwal` belongs to `jadwal`
- `detail_pemesanan` belongs to `pemesanan` dan `kursi_jadwal`
- `tiket_elektronik` belongs to `detail_pemesanan`
- `pembayaran` belongs to `pemesanan`

### 7.3 Constraint penting

- unique `pengguna.email`
- unique `pengguna.no_hp`
- unique `pengemudi.nomor_sim`
- unique `kendaraan.nomor_plat`
- unique `kursi_jadwal (jadwal_id, nomor_kursi)`
- unique `detail_pemesanan.kursi_jadwal_id`
- unique `pemesanan.kode_booking`
- unique `tiket_elektronik.detail_pemesanan_id`
- unique `tiket_elektronik.token_unik_qr`

### 7.4 Enum atau lifecycle status

Yang sudah ada:

- `pengguna.peran`
- `pengemudi.status`
- `pengemudi.status_verifikasi`
- `kendaraan.status_kendaraan`
- `jadwal.status`
- `kursi_jadwal.status_kursi`
- `pemesanan.status_pemesanan`
- `detail_pemesanan.status_kehadiran`
- `pembayaran.status_bayar`

Lifecycle target booking:

- `draft`
- `form_locked`
- `menunggu_pembayaran`
- `terbayar`
- `hadir`
- `selesai`
- `dibatalkan`
- `kedaluwarsa`

### 7.5 Index yang dibutuhkan

- `jadwal (status, waktu_berangkat)`
- `kursi_jadwal (jadwal_id, status_kursi, lock_expires_at)`
- `pemesanan (pengguna_id, status_pemesanan, created_at)`
- `pembayaran (status_bayar, metode_bayar, expired_at, gateway_reference)`
- `tiket_elektronik (token_unik_qr, sudah_dipindai)`
- `notifikasi (pengguna_id, sudah_dibaca, created_at)`

### 7.6 Catatan transaction untuk operasi kritis

Wajib transaction:

- lock kursi
- create booking beserta detail dan pembayaran draft
- confirm payment dan issue ticket
- scan QR satu kali pakai
- update status penting yang mempengaruhi kursi atau manifest

## 8. Quality Control

### 8.1 Bug risks

- double booking akibat race condition
- reset kursi saat edit jadwal
- lock tidak dibersihkan
- tiket stale saat status booking diubah manual
- jumlah manifest pengemudi tidak akurat

### 8.2 Architecture risks

- logic terlalu banyak di controller
- mobile screen terlalu besar dan sulit dirawat
- fallback mock menutup kegagalan API
- integrasi payment, maps, dan FCM belum punya abstraction final

### 8.3 UX risks

- pengguna mengira preview adalah flow live
- tidak ada feedback jelas saat lock expired
- pengemudi belum punya layar lapangan yang benar-benar operasional
- admin dapat melakukan aksi berisiko tanpa warning kuat

### 8.4 Data integrity risks

- seat status dan detail booking tidak sinkron
- bagasi lintas booking per jadwal belum dihitung ketat
- payment status dan booking status bisa tidak konsisten
- file upload bagasi masih perlu hardening akses publik dan retensi file

### 8.5 Operational risks

- tanpa scheduler, booking expired menumpuk
- tanpa webhook verification, payment mudah salah status
- tanpa manifest live, pengemudi rawan salah info saat boarding

### 8.6 Mitigations

- perbaiki seat engine dengan row lock dan scheduler cleanup
- cegah reset seat map existing saat edit jadwal
- pecah Flutter shell menjadi modul fitur yang lebih kecil
- tambahkan payment service dan webhook controller
- tambahkan QR verification backend-side
- tambahkan status guard dan audit trail

## 9. Kesimpulan Audit

Codebase saat ini layak dijadikan fondasi dan tidak perlu ditulis ulang total. Arah yang paling sehat adalah:

- pertahankan model inti penumpang, admin CRUD, pengaturan sistem, dan fondasi tiket yang sudah ada
- fokus memperbaiki seat engine dan lifecycle booking
- hidupkan flow mobile penumpang sampai benar-benar selesai
- lengkapi operasional pengemudi
- matangkan dashboard admin pembayaran, laporan, dan pengaturan

Dengan scope yang sudah disederhanakan ini, proyek menjadi lebih fokus, lebih realistis untuk Tugas Akhir, dan lebih mudah diselesaikan dengan kualitas yang baik.
