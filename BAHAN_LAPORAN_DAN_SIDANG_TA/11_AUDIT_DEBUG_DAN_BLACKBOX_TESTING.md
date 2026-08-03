# Audit Debug dan Black-Box Testing RuslyTravel

**Tanggal pengujian:** 27 Juli 2026  
**Ruang lingkup:** web admin Laravel, API Laravel, aplikasi mobile penumpang, pengemudi, dan mode tamu.

## 1. Metode Pengujian

Audit dilakukan dengan empat cara:

1. membaca route, controller, service, model response, Blade, dan widget Flutter;
2. menguji endpoint yang sedang berjalan pada `http://127.0.0.1:8000`;
3. menjalankan seluruh pengujian otomatis Laravel dan Flutter;
4. menjalankan build produksi web admin dan Flutter Web.

Pengujian tidak menjalankan migration, seeder, penghapusan data, konfirmasi pembayaran, pembuatan pemesanan nyata, atau aksi lain yang dapat mengubah data utama.

Kontrol browser interaktif tidak tersedia pada sesi audit ini. Oleh karena itu, pengujian web langsung dilakukan melalui HTTP, sedangkan render dan interaksi komponen diperiksa melalui Feature Test dan Widget Test. Aplikasi juga tidak diklik langsung melalui LDPlayer pada sesi ini. Hasil tidak mengklaim pengujian visual manual pada seluruh ukuran layar; pengujian visual manual tetap perlu dilakukan sebelum sidang.

### Ringkasan hasil teknis

| Pemeriksaan | Hasil |
|---|---|
| Laravel test | **279 lulus**, 1.757 assertion |
| Flutter test | **79 lulus** |
| Flutter analyze | **Tidak ada masalah** |
| Build Vite web admin | **Berhasil** |
| Build Flutter Web | **Berhasil** |
| API ping, bootstrap, dan jadwal tamu | **Berhasil diakses langsung** |
| Login penumpang data demo | **Berhasil diakses langsung** |
| Laravel Reverb | **Aktif pada port 6001** |
| Migration | Satu migration snapshot tunai lama masih **Pending** |

## 2. Tabel Black-Box Testing

Keterangan status:

- **Lulus langsung**: diuji pada aplikasi/API lokal yang sedang berjalan.
- **Lulus otomatis**: diuji melalui Laravel Feature Test atau Flutter Widget/Unit Test dengan basis data pengujian terisolasi.
- **Temuan**: fungsi berjalan sebagian, tetapi ditemukan perilaku yang perlu diperbaiki.
- **Terhalang data lokal**: tidak dapat diuji langsung tanpa mengetahui atau mengubah kredensial data lokal; bukan otomatis berarti kode gagal.

| No | Fitur yang Diuji | Tujuan Pengujian | Langkah Pengujian | Output yang Diharapkan | Hasil Aktual | Status |
|---:|---|---|---|---|---|---|
| 1 | Ping API | Memastikan Laravel API aktif | Akses `GET /api/ping` | HTTP 200 dan `success=true` | Respons sesuai | Lulus langsung |
| 2 | Bootstrap mobile | Memastikan konfigurasi awal mobile tersedia | Akses `GET /api/mobile/bootstrap` | Branding, metode pembayaran, informasi penting, dan jadwal tersedia | Respons dapat dibaca | Lulus langsung |
| 3 | Daftar jadwal tamu | Memastikan tamu hanya menerima jadwal yang dapat dipesan | Akses `GET /api/guest/jadwal` tanpa token | Jadwal penuh tidak dikirim | Setelah diperbaiki, jadwal `sisa_kursi=0` tidak lagi dikirim | Lulus langsung |
| 4 | Penyaringan jadwal penuh di mobile | Memastikan jadwal dengan kursi habis tidak tampil | Muat beranda tamu dengan jadwal `sisa_kursi=0` | Card jadwal penuh tidak tampil | Widget menyaring jadwal penuh | Lulus otomatis |
| 5 | Detail jadwal tamu | Memastikan tamu dapat melihat informasi tanpa memesan | Buka detail jadwal publik | Detail rute, armada, kursi, dan aturan tersedia | Struktur response dan render detail lulus | Lulus otomatis |
| 6 | Akses API tanpa login | Melindungi data pengguna | Akses dashboard penumpang tanpa token | HTTP 401 dengan pesan konsisten | Respons 401 sesuai | Lulus langsung |
| 7 | Login salah | Memastikan kredensial salah ditolak | Kirim `login` dan kata sandi salah | HTTP 422 tanpa membocorkan data | Respons 422 sesuai | Lulus langsung |
| 8 | Registrasi tidak valid | Memastikan validasi akun berjalan | Kirim email, HP, dan password tidak sesuai | HTTP 422 dan error berada pada field terkait | Respons 422 sesuai | Lulus langsung |
| 9 | Login penumpang | Memastikan akun penumpang dapat masuk | Login memakai akun demo penumpang yang masih aktif | Token dan role penumpang diterima | Login berhasil | Lulus langsung |
| 10 | Profil penumpang | Memastikan data sesi dapat dimuat ulang | Akses `GET /api/auth/me` dengan token penumpang | Data pengguna aktif dikembalikan | Respons sesuai | Lulus langsung |
| 11 | Dashboard penumpang | Memastikan ringkasan penumpang dapat dibuka | Akses dashboard dengan token penumpang | Ringkasan pemesanan dan tiket tersedia | Respons sesuai | Lulus langsung |
| 12 | Daftar pemesanan penumpang | Memastikan daftar aman saat kosong | Akses daftar booking akun demo | Daftar atau empty state dikembalikan | Daftar kosong dikembalikan tanpa error | Lulus langsung |
| 13 | Pemisahan role | Mencegah penumpang membuka fitur pengemudi | Akses dashboard pengemudi memakai token penumpang | HTTP 403 | Respons 403 sesuai | Lulus langsung |
| 14 | Validasi payload booking | Mencegah booking tanpa data wajib | Kirim payload pemesanan kosong | HTTP 422 | Respons 422 sesuai | Lulus langsung |
| 15 | Verifikasi email | Memastikan akun baru belum langsung mendapat akses | Registrasi lalu uji OTP benar, salah, dan kedaluwarsa | Token hanya diberikan setelah verifikasi berhasil | Seluruh skenario lulus | Lulus otomatis |
| 16 | Validasi email dan nomor HP | Memastikan profil memakai Gmail dan 11–12 digit HP | Kirim email non-Gmail dan nomor HP tidak sesuai | Perubahan ditolak | Validasi backend lulus | Lulus otomatis |
| 17 | Validasi password | Memastikan password minimal 6 karakter dan memiliki angka/simbol | Kirim password lemah, sama dengan password lama, password lebih dari 8 karakter, dan password valid | Hanya password yang memenuhi aturan diterima | Seluruh skenario lulus | Lulus otomatis |
| 18 | Penguncian kursi | Mencegah dua penumpang memakai kursi sama | Simulasikan lock dan double submit | Satu pengguna mendapat kursi, request lain ditolak | Transaksi dan locking lulus | Lulus otomatis |
| 19 | Realtime kursi | Memastikan perubahan kursi dapat menjadi sinyal antardevice | Periksa konfigurasi Reverb, channel, event, dan port | Event hanya membawa ID jadwal tanpa data pribadi | Port 6001 aktif dan test event lulus | Lulus langsung dan otomatis |
| 20 | Batas pengisian form | Mencegah booking dibuat setelah sesi form habis | Majukan waktu melewati batas lock form | Booking ditolak dan kursi dilepas | Skenario lulus | Lulus otomatis |
| 21 | Double submit booking | Mencegah pemesanan ganda | Kirim request booking yang sama berulang | Tidak tercipta booking duplikat | Skenario lulus | Lulus otomatis |
| 22 | Pemilihan metode setelah booking | Memastikan metode belum dipilih saat konfirmasi | Buat booking lalu periksa state pembayaran | Booking menunggu pembayaran dan metode dapat dipilih | Kontrak backend dan model Flutter lulus | Lulus otomatis |
| 23 | Pembayaran QRIS | Memastikan metadata Pakasir dibaca dari provider | Simulasikan Transaction Create QRIS | Nominal, fee, total, nomor pembayaran, dan kedaluwarsa tersimpan | Skenario lulus | Lulus otomatis |
| 24 | Pembayaran Virtual Account | Memastikan kode VA spesifik didukung | Uji seluruh metode VA yang didokumentasikan | Metode valid diterima dan snapshot tersimpan | Seluruh metode lulus | Lulus otomatis |
| 25 | Pembayaran tunai | Memastikan tunai tidak memakai Pakasir | Pilih metode tunai | Fee nol, total sama dengan nominal, tidak ada QR/VA | Skenario lulus | Lulus otomatis |
| 26 | Countdown pembayaran tunai | Memastikan tunai tidak memiliki batas bayar digital | Render pembayaran tunai | Countdown dan “Bayar sebelum” tidak tampil | Widget lulus | Lulus otomatis |
| 27 | Total pembayaran digital | Memastikan total tidak memakai nominal tiket saja | Pakasir mengirim amount, fee, dan total | Total penumpang mengikuti snapshot `total_payment` | Kontrak integer dan UI lulus | Lulus otomatis |
| 28 | Fee sandbox nol | Mencegah fee buatan/hardcode | Pakasir mengirim `fee=0` | Nilai nol disimpan apa adanya | Skenario lulus | Lulus otomatis |
| 29 | Webhook Pakasir | Memastikan pembayaran diverifikasi dan idempotent | Kirim webhook benar, duplikat, salah nominal, dan salah order | Hanya webhook valid diproses satu kali | Seluruh skenario lulus | Lulus otomatis |
| 30 | Tiket elektronik | Memastikan tiket hanya terbit setelah pembayaran valid | Selesaikan pembayaran pada database test | Tiket QR dibuat satu kali | Skenario lulus | Lulus otomatis |
| 31 | Unduh tiket | Memastikan tiket dapat disimpan tanpa ketergantungan izin galeri lama | Render tiket lalu jalankan aksi unduh | File tiket dibentuk dan pesan hasil tampil | Widget test lulus | Lulus otomatis |
| 32 | Detail pembayaran mobile | Memastikan halaman tidak kosong dan tidak mengulang nominal | Render pending, berhasil, kedaluwarsa, dan tunai | Banner, detail perjalanan, rincian pembayaran, dan aksi sesuai status | Seluruh render lulus | Lulus otomatis |
| 33 | Riwayat pemesanan | Memastikan filter lama tidak mengubah daftar terbaru | Terapkan filter tanggal pada “Pemesanan Lama” | Daftar terbaru tetap berdasarkan waktu, bukan status berhasil saja | Widget lulus | Lulus otomatis |
| 34 | Manifes pengemudi | Memastikan hanya booking sah/tunai pending yang diperlukan muncul | Muat manifes perjalanan milik pengemudi | Daftar penumpang sesuai perjalanan | Skenario lulus | Lulus otomatis |
| 35 | Mulai perjalanan | Memastikan hanya pengemudi yang ditugaskan dapat memulai | Ubah status perjalanan milik sendiri dan milik orang lain | Milik sendiri berhasil, milik orang lain ditolak | Skenario lulus | Lulus otomatis |
| 36 | Konfirmasi tunai pengemudi | Memastikan tiket terbit setelah uang diterima | Konfirmasi penumpang tunai pada perjalanan aktif | Pembayaran berhasil dan tiket diterbitkan satu kali | Skenario lulus | Lulus otomatis |
| 37 | Pemindaian tiket | Mencegah QR palsu, beda perjalanan, dan scan ganda | Scan tiket valid, palsu, lintas jadwal, dan dua kali | Hanya scan pertama yang sah diterima | Seluruh skenario lulus | Lulus otomatis |
| 38 | Status jemput dan turun | Menjaga urutan operasional penumpang | Tandai turun sebelum hadir, lalu jalankan urutan benar | Turun sebelum hadir ditolak | Skenario lulus | Lulus otomatis |
| 39 | Biaya operasional pengemudi | Memastikan biaya perlu bukti dan perjalanan aktif | Kirim biaya valid, tanpa bukti, dan untuk trip tidak aktif | Hanya biaya valid diterima | Skenario lulus | Lulus otomatis |
| 40 | Login pengemudi pada database aktif | Memastikan pengujian langsung dapat diulang | Login dengan kredensial yang tertulis pada seeder | Token pengemudi diterima | Kredensial seeder tidak sama dengan akun pengemudi pada database aktif | **Terhalang data lokal** |
| 41 | Proteksi halaman admin | Mencegah tamu membuka dashboard admin | Buka `/admin/dashboard` tanpa sesi | Dialihkan ke login admin | Berakhir pada `/admin/login` | Lulus langsung |
| 42 | Login admin pada database aktif | Memastikan pengujian UI admin langsung dapat dilakukan | Login dengan kredensial seeder | Masuk dashboard | Database aktif memakai akun admin yang sudah diubah; kredensial seeder tidak berlaku | **Terhalang data lokal** |
| 43 | Dasbor admin | Memastikan card statistik mengambil query backend | Jalankan Feature Test dasbor | Pendapatan, rute terpopuler, menunggu pembayaran, dan operasional tampil | Test lulus | Lulus otomatis |
| 44 | Detail jadwal admin | Memastikan hanya pemesanan berhasil ditampilkan | Buka detail jadwal berisi status campuran | Hanya pemesanan berhasil masuk ringkasan | Test lulus | Lulus otomatis |
| 45 | Tarif historis | Memastikan perubahan harga rute tidak mengubah booking lama | Ubah harga rute setelah booking dibuat | Detail memakai subtotal tiket historis | Test lulus | Lulus otomatis |
| 46 | Filter web admin | Memastikan tanggal/status tidak valid ditolak | Kirim tanggal salah, rentang terbalik, dan status tidak dikenal | Pesan validasi tampil | Seluruh test lulus | Lulus otomatis |
| 47 | Export laporan | Memastikan rentang tanggal ikut ke file dan filter Excel tersedia | Export pemesanan/transaksi dengan filter aktif | File mengikuti rentang dan memiliki AutoFilter | Test lulus | Lulus otomatis |
| 48 | Pemisahan pendapatan dan fee | Memastikan biaya provider tidak masuk omzet | Buka laporan transaksi dengan pembayaran digital | Pendapatan memakai nominal transaksi | Test lulus | Lulus otomatis |
| 49 | Notifikasi admin | Memastikan detail jadwal dan booking tidak tertukar | Buat referensi jadwal dan booking dengan ID sama | Tombol menuju jenis detail yang benar | Test lulus untuk judul notifikasi yang dikenal | Lulus otomatis |
| 50 | Toggle password admin | Memastikan tombol tampil/sembunyikan konsisten | Render login, pengaturan akun, tambah/edit pengemudi | Input dan ikon berubah tanpa submit form | Test lulus | Lulus otomatis |
| 51 | Penghapusan pemesanan lama | Memastikan Admin hanya menghapus riwayat terminal pada rentang lampau | Kirim rentang valid, rentang terbalik, konfirmasi salah, dan data status aktif | Hanya pemesanan Selesai/Dibatalkan dalam rentang yang dihapus beserta relasinya | Seluruh skenario lulus | Lulus otomatis |
| 51 | Monitor tiket admin | Memastikan route, empty state, filter, dan proteksi tersedia | Buka sebagai admin, buka sebagai tamu, lalu kirim rentang tanggal terbalik | Admin dapat membuka; tamu dan tanggal terbalik ditolak | Tiga Feature Test baru lulus | Lulus otomatis |
| 52 | Tombol “Buka Manifes” pada detail jadwal mobile | Memastikan tombol membuka fitur yang dijanjikan | Tekan tombol pada ringkasan pengemudi | Berpindah ke manifes perjalanan dengan ID jadwal yang sama | Navigasi kini diteruskan ke detail perjalanan/manifes | Lulus otomatis |
| 53 | Migration snapshot tunai lama | Memastikan data transaksi lama memiliki snapshot nominal | Jalankan `php artisan migrate:status` | Semua migration deployment sudah Ran | `2026_07_25_000100_backfill_cash_payment_snapshots` masih Pending | **Temuan deployment** |
| 54 | Bentrok jadwal pengemudi dan kendaraan | Memastikan aturan ketersediaan sesuai kebutuhan operasional | Audit query bentrok jadwal aktif | Jadwal yang benar-benar bertabrakan ditolak | Kode saat ini menolak seluruh jadwal aktif pada tanggal yang sama, walaupun jam tidak bertabrakan | **Perlu keputusan aturan bisnis** |

## 3. Temuan Bug dan Ketidaksinkronan

### 3.1 Temuan yang sudah diperbaiki

#### A. Tombol “Buka Manifes” sebelumnya belum menjalankan fungsi

Pada detail jadwal role pengemudi sebelumnya terdapat tombol **Buka Manifes** yang hanya menampilkan pesan bahwa fitur akan tersedia. Tombol sekarang mengembalikan ID jadwal ke halaman induk, kemudian membuka detail perjalanan/manifes yang sudah tersedia.

Lokasi terkait:

- `frontend/lib/features/schedule/bagian_detail_jadwal/widget_pengemudi_dan_metrik.dart`
- `frontend/lib/features/schedule/travs_schedule_detail_page.dart`

Pengujian regresi baru memastikan placeholder tidak kembali dan ID jadwal yang sama digunakan untuk membuka manifes.

#### B. Backend sebelumnya masih mengirim jadwal dengan kursi habis

Pada awal audit, `GET /api/guest/jadwal` mengirim jadwal dengan `sisa_kursi=0`. Flutter memang sudah menyaring data ini, tetapi kontrak backend dan UI belum konsisten.

Query endpoint jadwal tamu dan bootstrap sekarang memeriksa keberadaan kursi berstatus `tersedia`. Filter bootstrap ditempatkan sebelum `limit(4)` agar jadwal penuh tidak menutup posisi jadwal berikutnya yang masih dapat dipesan.

Lokasi terkait:

- `backend/app/Http/Controllers/Api/GuestController.php`
- `backend/app/Http/Controllers/Api/AppBootstrapController.php`
- `frontend/lib/features/home/bagian_beranda/halaman_informasi.dart`

Pengujian langsung dan Feature Test baru memastikan jadwal penuh tidak dikirim lagi.

#### C. Bahasa mobile yang terlalu teknis sudah dinetralkan

Teks kosong dan error yang sebelumnya menyebut proses admin, server, `refresh`, `Trip aktif`, dan `Brief Pengemudi` telah diganti menjadi bahasa yang berorientasi kepada pengguna.

### 3.2 Prioritas sedang yang belum diubah

#### D. Kredensial seeder dapat berbeda dari database aktif

Database aktif dapat berisi akun yang sudah diubah setelah seeding, sedangkan form akun terbaru mensyaratkan:

- email `@gmail.com`;
- password minimal 6 karakter dengan batas teknis 72 karakter;
- password berisi huruf dan minimal satu angka atau simbol.

Seeder dapat memasukkan data tanpa melewati Request Validation. Karena itu, kredensial database aktif tetap perlu diperiksa sebelum pengujian langsung dan tidak boleh diasumsikan selalu sama dengan nilai awal seeder.

Lokasi terkait:

- `backend/database/seeders/TravSDemoSeeder.php`
- `backend/app/Http/Controllers/Api/AuthController.php`
- `backend/app/Http/Controllers/Web/Admin/PengemudiController.php`

Rekomendasi: buat kredensial demo khusus yang mengikuti aturan terbaru dan dokumentasikan akun sidang. Jangan menjalankan seeder pada database utama tanpa backup.

#### E. Migration snapshot tunai lama belum dijalankan

Migration `2026_07_25_000100_backfill_cash_payment_snapshots.php` masih Pending. Transaksi baru tetap menggunakan kode terbaru, tetapi transaksi tunai lama dapat memiliki snapshot nominal yang belum lengkap pada laporan.

Rekomendasi: backup database terlebih dahulu, periksa data sasaran, lalu jalankan migration hanya setelah disetujui.

#### F. Referensi notifikasi masih bergantung pada judul

Kolom `referensi_id` dapat menunjuk ke pemesanan atau jadwal. Model membedakan sebagian referensi jadwal melalui daftar judul notifikasi. Skenario yang dikenal sudah dilindungi test, tetapi penambahan judul notifikasi baru berisiko salah arah jika daftar judul tidak ikut diperbarui.

Rekomendasi jangka panjang: tambahkan tipe referensi eksplisit, misalnya `referensi_tipe`, tanpa mengubah data lama secara mendadak.

#### G. Aturan bentrok jadwal berlaku untuk satu hari penuh

Validasi pengemudi dan kendaraan saat ini memakai `whereDate(waktu_berangkat, tanggal)` dan status aktif. Artinya satu pengemudi atau kendaraan tidak dapat menerima dua perjalanan aktif pada hari yang sama, walaupun perjalanan pertama selesai pagi dan perjalanan kedua dimulai malam.

Aturan ini aman untuk mencegah bentrok, tetapi lebih ketat daripada aturan “tanggal dan jam yang sama”. Sebelum diubah menjadi pemeriksaan rentang waktu, perlu keputusan apakah kebijakan RuslyTravel memang satu armada/satu pengemudi maksimal satu perjalanan per hari. Jika bukan, pemeriksaan sebaiknya membandingkan interval `waktu_berangkat` sampai `estimasi_tiba`.

### 3.3 Perubahan bahasa dan pengalaman pengguna

Beberapa teks mobile sebelumnya menjelaskan proses internal. Perubahan berikut sudah diterapkan:

| Area | Teks saat ini | Masalah | Saran netral |
|---|---|---|---|
| Beranda penumpang kosong | “Jadwal akan tampil setelah admin membuka perjalanan baru...” | Menjelaskan proses kerja admin | “Jadwal perjalanan baru belum tersedia. Silakan periksa kembali nanti.” |
| Beranda tamu kosong | “Jadwal akan tampil setelah admin membuka perjalanan baru.” | Menyebut admin kepada tamu | “Belum ada jadwal perjalanan yang dapat dipesan.” |
| Daftar rute tamu | “Data rute akan muncul setelah jadwal dibuka admin.” | Menjelaskan menu internal | “Rute akan tersedia ketika jadwal perjalanan baru telah dibuka.” |
| Informasi penumpang | “Data ini dipakai admin dan pengemudi...” | Menjelaskan lokasi/pemakai data | “Pastikan seluruh data perjalanan benar agar proses penjemputan berjalan lancar.” |
| Error format data | “Server mengirim data yang tidak dapat dibaca. Hubungi admin...” | Terlalu teknis | “Data belum dapat ditampilkan. Silakan coba beberapa saat lagi.” |
| Pemindai pengemudi | “Memvalidasi tiket ke server...” | Menyebut proses teknis | “Memeriksa tiket...” |
| Perjalanan pengemudi | “Trip aktif” | Campuran bahasa | “Perjalanan aktif” |
| Detail jadwal pengemudi | “Brief Pengemudi” | Campuran bahasa | “Ringkasan Pengemudi” |
| Empty state jadwal | “tarik halaman untuk refresh” | Campuran bahasa | “tarik halaman untuk memuat ulang” |
| Pratinjau gambar | “Tutup preview” | Campuran bahasa | “Tutup pratinjau” |

Catatan:

- istilah **Manifes** tepat digunakan pada halaman pengemudi yang berisi daftar penumpang, kursi, lokasi, dan barang;
- kata **sistem** pada penjelasan pembatalan otomatis masih wajar karena menjelaskan bahwa pembatalan dilakukan otomatis, bukan oleh pengguna;
- penyebutan **admin** pada panduan operasional pengemudi masih relevan ketika pengemudi memang harus menghubungi penanggung jawab;
- pesan penolakan akun admin pada aplikasi mobile tetap perlu menjelaskan kanal akses yang benar, tetapi dapat dipersingkat agar tidak terasa seperti pesan teknis.

## 4. Kode Tidak Aktif dan Cakupan Test yang Kurang

1. Route Laporan Okupansi sudah dinonaktifkan sesuai proposal, tetapi method controller dan Blade `admin/laporan/okupansi.blade.php` masih tersisa.
2. View rekap biaya lama masih ada, sedangkan route lama sekarang hanya menjadi jalur kompatibilitas menuju halaman biaya operasional.
3. Monitor Tiket sekarang memiliki test untuk render, empty state, rentang tanggal, dan proteksi login. Pengujian pencarian dengan data tiket nyata masih dapat ditambah.
4. Logika bentrok pengemudi/kendaraan belum memiliki test regresi khusus yang membandingkan perjalanan tumpang tindih dan perjalanan tidak tumpang tindih.

Kode tidak aktif tersebut tidak merusak data, tetapi dapat menyulitkan proses belajar dan pemeliharaan karena developer dapat mengira halaman masih digunakan.

## 5. Kesimpulan

Secara umum, fondasi aplikasi **cukup stabil** berdasarkan 279 test Laravel, 79 test Flutter, analisis statis bersih, dan dua build yang berhasil. Alur kritis booking, lock kursi, pembayaran, webhook, tiket QR, role, pengemudi, laporan, serta notifikasi sudah memiliki perlindungan test yang baik.

Bug tombol manifes, pengiriman jadwal penuh, dan teks mobile yang terlalu teknis sudah diperbaiki. Temuan yang masih memerlukan keputusan atau tindakan terpisah adalah:

1. seeder dan kredensial demo tidak mengikuti aturan akun terbaru;
2. migration snapshot transaksi tunai lama masih Pending;
3. referensi notifikasi masih bergantung pada judul yang dikenal;
4. aturan bentrok jadwal perlu dipastikan: satu perjalanan per hari atau berdasarkan rentang waktu;
5. file upload web masih dapat mengikuti bahasa bawaan browser.

Perubahan seeder dan migration harus dipisahkan dari perbaikan aplikasi karena berhubungan dengan data pengujian dan data lama.
