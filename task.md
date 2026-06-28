# Task Breakdown, Gap Analysis, dan Roadmap Implementasi

Status: Implementasi inti selesai, hardening dan backlog lanjutan aktif

Pembaruan terakhir: 22 Juni 2026

Verifikasi terbaru: backend 131 test/648 assertion lulus, Flutter 15 test lulus, dan `flutter analyze` tanpa issue.

Ringkasan perubahan terbaru:

- [x] Admin fee Rusly Travel dihapus; biaya channel mengikuti Pakasir.
- [x] Upload logo menghasilkan WEBP maksimal 512x512 px dan dipakai web admin serta mobile.
- [x] Dashboard admin minimalis dan card statistik menuju halaman terkait.
- [x] Filter admin memakai tombol `Terapkan Filter` untuk membatasi request otomatis.
- [x] Notifikasi Admin, Pengemudi, dan Penumpang otomatis terbaca saat daftar dibuka.
- [x] Badge unread mobile mengikuti backend dan polling tidak menghabiskan status unread.
- [x] Riwayat notifikasi terbaca dapat dihapus untuk periode 7/30/90 hari atau semua terbaca.
- [x] Hak akses dan keamanan penghapusan notifikasi diuji.
- [x] Notifikasi operasional dibuat wajib; tab pengaturan template/switch dihapus.
- [x] Notifikasi admin untuk booking baru, tunai menunggu konfirmasi, pembayaran berhasil/gagal/kedaluwarsa, keberangkatan dekat, serta kapasitas hampir penuh.
- [ ] Rekap Biaya menjadi laporan dengan export Excel/CSV.
- [ ] Menu Audit Sistem.
- [ ] Hapus data lama Pemesanan, Pembayaran, dan Biaya Operasional.
Tanggal pembaruan: 16 Juni 2026
Terakhir diverifikasi frontend: `flutter analyze` (no issues) dan `flutter test` (11 test) lulus pada 16 Juni 2026.
Verifikasi backend terakhir: `php artisan test --filter=PaymentGatewayTest` lulus pada 8 Juni 2026.

Statistik project saat ini:

- 30+ endpoint API
- 68 backend test
- 11 Flutter test
- 14 tabel utama

Catatan penting:

- scope tambahan untuk titipan dan carter dihapus dari proyek
- fokus akhir hanya pada layanan travel penumpang lintas kota
- layout mobile dan web admin tetap mengacu ke wireframe Figma yang dibagikan user
- visual utama tetap memakai navy, putih, dan aksen oranye

## 0. Dashboard Checklist Progres

Checklist ini dipakai untuk memantau pekerjaan harian. Item yang sudah benar-benar tersambung, buildable, dan sudah diuji dasar diberi tanda `[x]`. Item yang masih UI preview, scaffold, atau belum teruji end-to-end produksi tetap `[ ]`.

### Sudah dikerjakan / sudah tersambung

- [x] Shell mobile final untuk guest, penumpang, dan pengemudi.
- [x] Shell mobile role-based diselaraskan ke wireframe Figma penumpang dan pengemudi.
- [x] Auth mobile login, register, guest mode, restore session, dan logout.
- [x] Route guard role dasar untuk penumpang, pengemudi, dan admin.
- [x] Guest schedule dan detail jadwal bisa dibaca dari API.
- [x] Seat lock dua tahap dasar untuk checkout penumpang.
- [x] Cleanup expired form lock dan expired pending booking via scheduler.
- [x] Checkout penumpang live dari mobile ke backend.
- [x] Pemilihan kursi live dengan guard race condition dasar.
- [x] Mode tamu/non-login dikunci dari pilihan kursi dan input pemesanan agar tidak memicu seat lock.
- [x] Form pickup dengan GPS, map picker, dan reverse geocoding.
- [x] Koordinat dan alamat pickup tersimpan di backend.
- [x] Detail pemesanan bisa membuka pickup di Google Maps.
- [x] Riwayat pemesanan penumpang live.
- [x] Detail pemesanan penumpang live dan punya tombol langsung ke halaman tiket.
- [x] Tiket QR penumpang live per kursi setelah pembayaran terverifikasi.
- [x] Daftar tiket, notifikasi UI, riwayat pengemudi UI, detail perjalanan pengemudi UI, dan preview scan ticket sudah punya tampilan mobile baru.
- [x] Payment preview pending untuk QRIS, transfer, dan tunai sudah dirapikan mengikuti pola gateway eksternal / Pakasir.
- [x] Backend membangun URL bayar Pakasir resmi `https://app.pakasir.com/pay/{slug}/{amount}?order_id=...` untuk redirect pembayaran.
- [x] Setelah booking QRIS atau transfer berhasil dibuat, aplikasi otomatis membuka halaman bayar Pakasir sandbox/production sesuai mode project.
- [x] Estimasi checkout tidak lagi menampilkan biaya admin statis; biaya channel pembayaran mengikuti hasil gateway saat user memilih QRIS/transfer.
- [x] Transfer bank diarahkan ke halaman bayar Pakasir tanpa mode QRIS-only, sehingga pilihan bank/virtual account dilakukan di Pakasir.
- [x] Redirect "kembali ke merchant" Pakasir bisa diatur lewat `PAKASIR_RETURN_URL`; untuk demo lokal sekarang dinonaktifkan dengan `PAKASIR_RETURN_URL=disabled`.
- [x] Denah kursi mobile kembali memakai gambar mobil top-view dengan tombol kursi fleksibel mengikuti `seat_layout`; kursi pengemudi ditandai `P` warna abu-abu.
- [x] Asset mobil `frontend/assets/images/seat_taksi_top.png` sudah didaftarkan di `pubspec.yaml`.
- [x] Foto kendaraan dari backend tampil pada daftar jadwal penumpang/tamu, detail jadwal, dan ringkasan pilih kursi.
- [x] Dashboard pengemudi menampilkan foto kendaraan yang ditugaskan melalui data `kendaraan_foto_url`.
- [x] Copywriting frontend dibersihkan dari istilah teknis yang tampil ke user seperti backend, wireframe, reverse geocoding, URL dev, dan teks verifikasi khusus development.
- [x] Brand tampilan utama mobile diselaraskan menjadi RuslyTravel tanpa refactor class internal.
- [x] Login mobile dipisahkan jelas antara tombol masuk sebagai penumpang dan masuk sebagai pengemudi, dengan akses tamu tetap tersedia.
- [x] Profil mobile penumpang dan pengemudi memiliki pengaturan notifikasi perangkat dan pilihan bahasa Indonesia/English.
- [x] Beranda penumpang disesuaikan ke desain terbaru: hero armada, info perjalanan/pembayaran terbaru, dan rute populer tanpa menu akses tercepat lama.
- [x] Daftar pemesanan memakai ikon notifikasi dengan badge jumlah, dan kartu ringkasan menunggu/terbayar/total dibuat responsif sama lebar.
- [x] Edit profil penumpang tersambung ke backend untuk ubah nama, email, nomor HP, password, dan foto profil dari kamera/galeri dengan kompresi gambar.
- [x] Foto profil mobile disinkronkan ulang dari backend setelah disimpan dan avatar punya fallback agar tidak tampil kosong jika gambar gagal dimuat.
- [x] Endpoint auth profil sekarang konsisten mengirim `foto_profil` dan `foto_profil_url` pada register/login/me/update/upload sehingga foto tetap muncul setelah buka ulang aplikasi dan login ulang.
- [x] Admin dapat mengunggah foto profil pengemudi dari form tambah/edit, lalu foto tampil di daftar pengemudi.
- [x] Header admin memakai foto profil admin jika tersedia dan fallback inisial jika belum ada foto.
- [x] Admin pemesanan menampilkan foto profil akun penumpang pemesan pada daftar dan detail pemesanan.
- [x] API pemesanan dan manifest pengemudi membawa data akun pemesan termasuk `foto_url`, agar frontend tidak menebak sendiri data foto penumpang.
- [x] Foto barang bawaan dikompresi saat dipilih dari kamera/galeri dan ditampilkan kembali pada detail pemesanan.
- [x] Filter daftar jadwal memakai pemilih tanggal fleksibel, menggantikan filter Hari/Jam.
- [x] Halaman pemesanan/pembayaran menampilkan konfirmasi saat user menekan back sebelum proses selesai.
- [x] Format alamat pickup dari koordinat diperjelas dengan komponen alamat yang lebih lengkap.
- [x] Konversi alamat pickup diperkuat dengan fallback OpenStreetMap agar output alamat tidak lagi menampilkan kode koordinat sebagai teks utama.
- [x] Resolver URL media diperbaiki supaya foto profil dan foto barang bawaan yang tersimpan sebagai path, `/storage`, atau URL lokal tetap tampil di emulator/mobile.
- [x] Simulasi pembayaran lokal untuk mempercepat validasi flow tiket.
- [x] Bug reset status kursi saat admin edit jadwal sudah diperbaiki.
- [x] Admin web dasar: dashboard, CRUD kendaraan, CRUD pengemudi, CRUD rute, CRUD jadwal, pemesanan, biaya operasional, laporan, notifikasi, dan pengaturan.
- [x] Admin armada kendaraan menampilkan thumbnail foto mobil, preview foto 500x500, upload foto mobil, dan dropdown tipe kendaraan.
- [x] Admin jadwal menolak pengemudi yang sudah punya jadwal pada tanggal dan jam yang sama.
- [x] Admin dan pengemudi dapat mengonfirmasi pembayaran tunai sesuai hak akses, lalu tiket digital diterbitkan.
- [x] Tampilan tiket penumpang menyembunyikan token mentah dan menampilkan informasi pendukung seperti jadwal, pengemudi, dan kendaraan.
- [x] Audit dokumentasi fitur lintas perubahan sudah dibuat di `AUDIT_DOKUMENTASI_FITUR.md`.
- [x] Test dasar backend dan Flutter berjalan hijau.

### Belum selesai / prioritas berikutnya

- [x] Integrasi backend Pakasir untuk konsep create transaksi, refresh status, dan reconcile.
- [x] Mode simulasi Pakasir lokal ditetapkan sebagai target demo TA yang aman.
- [x] Transaksi nyata Pakasir, callback publik production, dan webhook signature verification tidak menjadi target saat ini.
- [ ] Forgot password end-to-end.
- [x] Scan QR sekali pakai di sisi pengemudi dengan kamera dan endpoint validasi.
- [x] Manifest pengemudi live dari backend, bukan data sample/preview.
- [ ] Action operasional pengemudi lengkap: pickup, hadir/boarding, berangkat, selesai.
- [ ] Dashboard pembayaran admin yang fokus untuk monitoring payment exception.
- [x] Guard lifecycle admin agar status booking/payment tidak loncat sembarangan.
- [x] Rate limit API untuk membatasi spam klik/query pada auth, booking, upload, payment, dan driver action.
- [ ] Notifikasi FCM berbasis event.
- [ ] Laporan final dan hardening QA penuh.

## 1. Ringkasan Audit yang Mempengaruhi Prioritas

Fakta teknis utama:

- [x] Backend dan frontend masih buildable.
- [x] Auth penumpang sudah ada.
- [x] Guest schedule sudah ada.
- [x] Seat lock backend inti dan cleanup expired lock sudah distabilkan untuk flow draft booking.
- [x] Dashboard admin sudah cukup matang sebagai basis kerja.
- [x] Mobile sudah punya shell baru, checkout live, history booking, dan detail tiket QR.
- [x] Scaffold Pakasir QRIS sudah ada di backend.
- [x] Pengemudi sudah punya manifest live dan scan live.
- [x] Payment gateway Pakasir sudah punya konsep create QRIS, refresh status, dan simulasi lokal untuk demo.
- [x] Payment gateway nyata dengan callback publik tidak menjadi target demo TA saat ini.

Implikasi prioritas:

- fase awal tetap fokus ke stabilisasi core booking penumpang sampai payment gateway dan QR scan lapangan benar-benar final
- penambahan fitur baru di luar alur penumpang harus dihentikan
- seat engine, payment, QR, dan driver ops adalah jalur kritis

## 2. Gap Analysis dan Rencana Refactor

| Status | Modul / file / flow existing | Kondisi saat ini | Masalah tersisa | Aksi | Alasan |
|---|---|---|---|---|---|
| [x] | `backend/app/Services/BookingService.php` | draft booking, form lock, payment lock, row lock, cleanup expired, simulasi payment, dan issue ticket sudah ada | perlu test concurrency lebih berat | `refactor lanjut` | mencegah double booking dan menjaga lifecycle payment |
| [x] | `backend/app/Http/Controllers/Web/Admin/JadwalController.php` | CRUD jadwal berjalan dan bug reset kursi existing sudah ditangani | warning operasional masih bisa diperkuat | `refactor lanjut` | menjaga integritas data |
| [x] | `backend/app/Http/Controllers/Web/Admin/JadwalController.php` | validasi bentrok jadwal pengemudi pada tanggal dan jam yang sama sudah ada | perlu uji operasional multi-jadwal lebih luas | `verify` | mencegah satu pengemudi mendapat dua tugas bersamaan |
| [x] | `backend/app/Http/Controllers/Web/Admin/KendaraanController.php` | CRUD armada, upload foto kendaraan, thumbnail tabel, preview foto, dan dropdown tipe kendaraan sudah ada | perlu uji visual langsung di browser admin setelah asset cache dibersihkan | `verify` | data armada lebih rapi dan foto mobil tersambung ke mobile |
| [x] | `backend/routes/console.php` | command `travs:release-expired-bookings` dan scheduler setiap menit sudah ada | perlu validasi di deployment server | `verify` | memenuhi aturan seat lock |
| [x] | `backend/app/Http/Controllers/Api/BookingController.php` | schedule detail, estimate, lock form, store booking, history, detail, simulasi payment, dan refresh payment status sudah ada | mode produksi payment belum menjadi target demo | `extend` | demo lokal tetap stabil memakai simulasi |
| [x] | `backend/app/Http/Controllers/Api/AuthController.php` | register/login/logout/me/forgot/reset password sudah ada | auth inti lengkap | `done` | auth inti sudah lengkap |
| [x] | `backend/app/Http/Controllers/Api/DriverController.php` | dashboard, manifest live, scan QR satu kali, action pickup, dan update status trip dasar sudah ada | monitoring admin trip belum final | `extend` | pengemudi sudah mulai operasional, masih perlu polish flow lapangan |
| [x] | `backend/app/Http/Controllers/Web/Admin/PemesananController.php` | admin bisa lihat/ubah status booking dengan lifecycle guard | dashboard payment exception belum dipisah | `refactor lanjut` | mencegah status loncat |
| [x] | `backend/app/Services/BookingCalculator.php` | rule bagasi, upload foto, biaya tambahan, dan kapasitas berat lintas booking sudah dihitung | perlu hardening retensi file upload | `refactor lanjut` | validasi operasional harus benar |
| [x] | `backend/config/services.php` | config Pakasir sudah ada | config maps/FCM belum final | `extend` | integrasi eksternal perlu env jelas |
| [x] | `backend/.env.example` | env Pakasir sudah disiapkan | env maps/FCM/deployment belum final | `extend` | deployment target butuh env jelas |
| [x] | `frontend/lib/core/services/travs_api_service.dart` | bisa baca bootstrap, jadwal, booking, dan dashboard | fallback mock dihapus, error dilempar ke UI | `done` | app sekarang jujur terhadap error |
| [x] | `frontend/lib/features/home/travs_home_page.dart` | shell role-based, notifikasi UI, daftar tiket, riwayat pengemudi UI, detail perjalanan UI, scan preview, dan sinkronisasi tampilan tamu/pengemudi sudah dirapikan | mock fallback dihapus, error/loading/empty state ditampilkan jujur | `done` | app menampilkan error state yang benar saat API gagal |
| [x] | `frontend/lib/features/schedule/travs_schedule_detail_page.dart` | detail jadwal dan submit ke payment/create booking sudah tersambung | perlu hardening loading/empty/error | `verify` | flow penumpang sudah hidup |
| [x] | `frontend/lib/features/payment/travs_payment_preview_page.dart` | create booking live, tampilan payment, dan simulasi Pakasir lokal sudah siap untuk demo | payment gateway nyata belum menjadi target demo | `verify` | flow pembayaran dapat dijelaskan tanpa transaksi uang nyata |
| [x] | `frontend/android/app/src/main/AndroidManifest.xml` | permission internet dan location sudah ada | permission camera untuk scan QR belum final | `extend` | perlu untuk QR dan pickup point |
| [ ] | admin web Blade | dashboard dan CRUD usable | belum ada dashboard pembayaran fokus dan warning operasional cukup kuat | `extend` | kebutuhan admin final |
| [ ] | notifikasi database | tabel dan halaman ada | belum ada trigger event, push delivery, unread flow | `extend` | fitur belum hidup |
| [x] | tiket elektronik | generate token, tampilan QR penumpang, dan scan endpoint one-time validation sudah ada | monitoring admin scan belum final | `extend` | tiket digital sudah operasional untuk driver scan |
| [x] | test suite | backend test mencakup payment webhook, QR scan, driver manifest, dan admin lifecycle | coverage frontend scanner masih manual/analyze | `extend` | QA coverage inti sudah naik |

Keputusan umum:

- `keep`
  model inti penumpang, admin CRUD, pengaturan sistem, struktur jadwal, pembayaran, dan tiket yang sudah ada.
- `refactor`
  seat engine, lifecycle booking, mobile service layer, dan warning operasional admin.
- `extend`
  auth, payment, QR, driver operations, GPS, notifications, reports, dan QA.
- `remove`
  semua referensi UI dan backlog untuk titipan dan carter.

## 3. Task Breakdown Implementasi

### Epic 1 - Audit & Stabilization

- Status: sebagian besar selesai
- Priority: P0
- Dependency: tidak ada
- Feature: hardening fondasi repository sebelum implementasi lanjut.
- User Story: sebagai tim pengembang, kami ingin fondasi existing stabil supaya fitur inti tidak dibangun di atas bug kritis.
- Checklist:
- [x] Audit struktur backend Laravel dan frontend Flutter.
- [x] Hapus scope tambahan titipan dan carter dari backlog.
- [x] Perbaiki bug reset kursi saat admin edit jadwal.
- [x] Tambahkan cleanup expired form lock dan expired pending booking.
- [x] Tambahkan scheduler `travs:release-expired-bookings`.
- [x] Tambahkan regression test dasar booking existing, admin dashboard, export laporan, dan expired lock.
- [x] Tambahkan rate limit route API penting agar spam klik tidak mengeksekusi query berlebihan.
- [x] Standarkan response error semua API penting.
- [x] Hilangkan fallback mock diam-diam pada jalur kritis mobile.
- Definition of Done: bug seat reset hilang, expired lock bisa dibersihkan, build tetap hijau.

### Epic 2 - Core Auth & Roles

- Status: sebagian selesai
- Priority: P0
- Dependency: Epic 1
- Feature: autentikasi dan otorisasi per role.
- User Story: sebagai penumpang atau pengemudi, saya ingin login sesuai peran agar hanya fitur yang relevan yang muncul.
- Checklist:
- [x] Login mobile.
- [x] Register mobile.
- [x] Guest mode.
- [x] Tampilan auth disinkronkan untuk penumpang, pengemudi, dan tamu.
- [x] Restore session.
- [x] Logout.
- [x] Endpoint `/api/auth/me`.
- [x] Middleware role dasar untuk penumpang, pengemudi, dan admin.
- [x] Admin login web.
- [x] Forgot password end-to-end.
- [x] Reset password end-to-end.
- [ ] Test forbidden access per role.
- Definition of Done: role flow jelas dan akses endpoint terjaga.

### Epic 3 - Guest & Passenger Home

- Status: sebagian besar selesai
- Priority: P1
- Dependency: Epic 2
- Feature: beranda mobile final sesuai wireframe dan scope akhir.
- User Story: sebagai guest atau penumpang, saya ingin landing flow yang jelas agar cepat menuju jadwal, pemesanan, dan tiket.
- Checklist:
- [x] Home shell guest.
- [x] Home shell penumpang.
- [x] Home shell pengemudi.
- [x] Beranda tamu dan pengemudi memakai pola hero, akses cepat, dan istilah yang sinkron dengan penumpang.
- [x] Navigasi penumpang `Beranda / Rute / Pesanan / Tiket / Profil`.
- [x] Navigasi pengemudi `Beranda / Manifest / Riwayat / Profil`.
- [x] Bootstrap mobile dari backend.
- [x] Daftar jadwal dari API.
- [x] Layout daftar rute dan daftar jadwal dibuat seperti bookmark card dari data jadwal yang sudah dimuat.
- [x] Thumbnail rute memakai gambar daerah tujuan dan thumbnail jadwal memakai gambar kendaraan.
- [x] Thumbnail jadwal memakai foto kendaraan dari backend jika armada memiliki foto.
- [x] Detail jadwal dan ringkasan pilih kursi menampilkan foto kendaraan sebagai identitas unit mobil.
- [x] Bottom navbar dibuat custom dengan indikator animasi geser.
- [x] Tombol back tersedia di tab non-beranda dan halaman detail tetap memakai AppBar back.
- [x] Riwayat booking penumpang dari API.
- [x] Daftar tiket aktif dari booking penumpang.
- [x] State loading-empty-error dibuat konsisten di semua halaman.
- [ ] Black box test khusus guest mode, passenger mode, dan refresh state.
- Definition of Done: home mobile stabil, tidak ada referensi fitur di luar scope.

### Epic 4 - Passenger Booking

- Status: selesai untuk flow draft booking live
- Priority: P0
- Dependency: Epic 1, Epic 2, Epic 3
- Feature: flow booking penumpang dari mobile ke backend.
- User Story: sebagai penumpang, saya ingin memilih jadwal, kursi, pickup point, biodata, dan bagasi sampai booking terbentuk.
- Checklist:
- [x] Detail jadwal live dari backend.
- [x] Estimasi biaya booking.
- [x] Pilih kursi.
- [x] Tampilan pilih kursi dibuat seperti denah mobil dan fleksibel mengikuti jumlah kursi pada `seat_map`.
- [x] Mode tamu/non-login hanya bisa melihat status kursi, tidak bisa memilih kursi atau mengisi data pemesanan.
- [x] Isi biodata penumpang per kursi.
- [x] Isi data bagasi dasar.
- [x] Pilihan hitung bagasi berat + item atau ukuran + item dengan input angka.
- [x] Upload foto barang bawaan dari kamera/galeri sebelum submit booking.
- [x] Upload foto barang bawaan mendukung maksimal 3 foto dan tetap kompatibel dengan data foto lama.
- [x] Rincian tambahan biaya bagasi tampil pada ringkasan checkout.
- [x] Isi pickup point.
- [x] Submit booking live dari payment screen.
- [x] Booking muncul di backend dan admin.
- [x] Detail booking menampilkan penumpang, kursi, pickup, bagasi, dan payment.
- [x] Test backend create booking multi-seat dan lock selected seats.
- [x] Test upload foto bagasi dan biaya tambahan item di atas batas gratis.
- [ ] Test validation error lengkap untuk payload booking.
- [x] Test batas bagasi lintas booking.
- Definition of Done: booking terbentuk dari mobile dan muncul di admin.

### Epic 5 - Seat Lock Engine

- Status: selesai untuk form lock, payment lock dasar, dan cleanup backend
- Priority: P0
- Dependency: Epic 4
- Feature: seat lock dua tahap yang aman.
- User Story: sebagai penumpang, saya ingin kursi yang saya pilih tidak diambil orang lain selama periode yang sah.
- Checklist:
- [x] Form lock sebelum checkout.
- [x] Payment lock setelah booking dibuat.
- [x] Row lock pada operasi kursi kritis.
- [x] Cleanup expired form lock.
- [x] Auto cancel expired pending booking.
- [x] Scheduler cleanup setiap menit.
- [x] Countdown 30 menit pengisian form dan 5 menit pembayaran tampil di mobile.
- [x] Test lock seats untuk checkout form.
- [x] Test expired pending booking dilepas sebelum booking baru dibuat.
- [x] Test cleanup command release expired form lock.
- [ ] Test double submit.
- [ ] Test concurrency realistis dengan request paralel.
- Definition of Done: tidak ada double booking pada skenario request bersamaan yang realistis.

### Epic 6 - Payment Integration

- Status: selesai untuk target demo simulasi lokal
- Priority: P0
- Dependency: Epic 4, Epic 5
- Feature: payment digital dengan simulasi lokal.
- User Story: sebagai penumpang, saya ingin proses pembayaran dapat divalidasi saat demo lokal dan tiket terbit otomatis setelah pembayaran dianggap berhasil.
- Checklist:
- [x] `PakasirService` untuk direct backend integration.
- [x] Config `services.pakasir`.
- [x] Env baseline `PAKASIR_ENABLED`, `PAKASIR_BASE_URL`, `PAKASIR_PROJECT`, `PAKASIR_API_KEY`, dan `PAKASIR_USE_REMOTE_SIMULATION`.
- [x] Payment draft dibuat saat booking.
- [x] Create transaction QRIS Pakasir dipanggil dari backend saat config aktif.
- [x] Gateway metadata dikirim ke Flutter.
- [x] Endpoint webhook Pakasir.
- [x] Raw webhook payload disimpan pada pembayaran.
- [x] Refresh payment status via `transactiondetail`.
- [x] Payment preview QRIS, transfer, dan cash di Flutter.
- [x] Simulasi pembayaran lokal untuk testing flow tiket.
- [x] Mode simulasi lokal dipakai sebagai target demo yang aman, tanpa transaksi uang nyata.
- [x] Transaksi Pakasir nyata dan callback publik tidak menjadi target tugas akhir saat ini.
- [x] Webhook success teruji otomatis melalui reconcile `transactiondetail`.
- [x] Webhook failed/expired teruji otomatis.
- [x] Duplicate webhook aman dan idempotent.
- [x] Dashboard admin khusus payment exception.
- [x] Test callback success, failed, duplicate callback, dan expired payment.
- Definition of Done: mode simulasi lokal dapat mengubah booking menjadi terbayar secara aman dan menerbitkan tiket QR untuk kebutuhan demo.

### Epic 7 - Cash Payment

- Status: selesai untuk flow verifikasi cash admin dasar
- Priority: P1
- Dependency: Epic 4
- Feature: alur pembayaran tunai yang jelas.
- User Story: sebagai admin, saya ingin memverifikasi pembayaran tunai tanpa merusak integritas booking.
- Checklist:
- [x] Metode `cash` tersedia sebagai pilihan payment jika setting aktif.
- [x] Mobile menampilkan instruksi/status cash pending.
- [x] Endpoint khusus confirm cash.
- [x] Audit trail confirm cash.
- [x] Tombol verifikasi cash dengan guard double confirm.
- [x] Test cash confirm.
- [x] Test duplicate cash confirm.
- [x] Test cash cancel.
- Definition of Done: cash payment punya alur valid dan tidak konflik dengan payment digital.

### Epic 8 - E-ticket & QR Verification

- Status: sebagian selesai
- Priority: P0
- Dependency: Epic 6 atau Epic 7
- Feature: tiket digital dan scan satu kali pakai.
- User Story: sebagai penumpang dan pengemudi, saya ingin boarding bisa diverifikasi cepat dan aman.
- Checklist:
- [x] Generate token QR unik saat booking menjadi terbayar.
- [x] Halaman tiket aktif di Flutter.
- [x] QR render menggunakan `qr_flutter`.
- [x] Status `sudah_dipindai` tampil di detail tiket.
- [x] Test simulasi payment menerbitkan tiket.
- [x] Endpoint scan QR.
- [x] One-time validation.
- [x] Scan log melalui `sudah_dipindai` dan `dipindai_pada`.
- [x] Feedback scan result live di mobile pengemudi.
- [ ] Monitoring tiket dan status scan di admin.
- [x] Test valid scan, repeat scan, dan wrong trip scan.
- [ ] Test token palsu/tidak dikenal.
- Definition of Done: QR ticket terbit setelah payment valid dan hanya bisa dipakai satu kali.

### Epic 9 - Driver Operations

- Status: UI preview dan dashboard dasar sudah ada, operasional live belum final
- Priority: P1
- Dependency: Epic 2, Epic 8
- Feature: operasional pengemudi mobile.
- User Story: sebagai pengemudi, saya ingin melihat trip, manifest, dan menjalankan boarding dari aplikasi.
- Checklist:
- [x] Role shell pengemudi di mobile.
- [x] Endpoint dashboard pengemudi dasar.
- [x] UI manifest preview.
- [x] UI detail perjalanan pengemudi preview.
- [x] UI scan ticket preview.
- [x] Trip endpoint live.
- [x] Manifest endpoint live.
- [x] Formatter manifest dari booking terbayar.
- [x] Role ownership guard agar pengemudi hanya melihat trip miliknya.
- [x] Scan QR live dari kamera.
- [x] Action pickup.
- [x] Action hadir/boarding melalui scan QR.
- [x] Action berangkat.
- [x] Action selesai.
- [ ] Monitoring status perjalanan di admin.
- [x] Test assigned trip only, manifest akurat, pickup ownership, scan ownership, dan repeat scan aman.
- Definition of Done: pengemudi dapat menjalankan tugas utama lapangan dari mobile.

### Epic 10 - Admin Dashboard Core

- Status: sebagian berjalan dari fondasi existing
- Priority: P1
- Dependency: Epic 1, Epic 4, Epic 6
- Feature: penguatan operasional admin.
- User Story: sebagai admin, saya ingin mengelola armada, jadwal, booking, dan payment dengan guard yang jelas.
- Checklist:
- [x] Admin login.
- [x] Dashboard admin.
- [x] CRUD kendaraan.
- [x] Tabel kendaraan memiliki kolom foto dengan thumbnail dan preview.
- [x] Form tambah/edit kendaraan memakai dropdown tipe kendaraan.
- [x] Foto kendaraan tersimpan dan dikirim ke mobile lewat field `foto_url` / `kendaraan_foto_url`.
- [x] CRUD pengemudi.
- [x] CRUD rute.
- [x] CRUD jadwal.
- [x] Daftar dan detail pemesanan.
- [x] Biaya operasional perjalanan.
- [x] Laporan pemesanan.
- [x] Laporan transaksi.
- [x] Export laporan CSV.
- [x] Pengaturan sistem.
- [x] Test admin dashboard dan export booking report.
- [x] Dashboard pembayaran khusus.
- [x] Filter payment exception.
- [x] Guard transisi status booking/payment.
- [ ] Warning operasional lebih kuat saat jadwal sudah punya booking.
- [ ] Pisahkan logic berat admin ke service.
- [x] Test status jump booking ditolak.
- [ ] Test regression CRUD lengkap dan payment monitoring.
- Definition of Done: admin bisa mengelola operasi utama tanpa status loncat dan tanpa data menipu.

### Epic 11 - GPS / Map / Reverse Geocoding

- Status: sebagian selesai
- Priority: P1
- Dependency: Epic 4
- Feature: pickup point yang benar-benar usable.
- User Story: sebagai penumpang atau pengemudi, saya ingin titik jemput tersimpan jelas dan bisa dibuka di Maps.
- Checklist:
- [x] Dependency `geolocator`.
- [x] Dependency `geocoding`.
- [x] Dependency `flutter_map`.
- [x] Permission Android internet dan location.
- [x] Permission flow GPS.
- [x] Tombol Gunakan GPS.
- [x] Halaman Pilih di Peta.
- [x] Reverse geocoding.
- [x] Contract pickup latitude, longitude, dan address.
- [x] Backend validasi latitude, longitude, dan address.
- [x] Admin detail booking menampilkan alamat jemput.
- [x] Detail booking mobile bisa membuka Google Maps.
- [ ] Test permission denied.
- [ ] Test invalid coordinate.
- [ ] Test map deep link.
- Definition of Done: titik jemput tersimpan sebagai lat, lng, dan alamat dengan UX jelas.

### Epic 12 - Notifications

- Status: selesai untuk inbox database, status baca, badge, dan pembersihan riwayat; FCM masih backlog
- Priority: P2
- Dependency: Epic 6, Epic 8, Epic 9
- Feature: notifikasi event penting.
- User Story: sebagai penumpang atau pengemudi, saya ingin mendapatkan update penting tanpa harus selalu membuka aplikasi.
- Checklist:
- [x] Tabel/model notifikasi dasar.
- [x] Halaman admin notifikasi dasar.
- [x] UI inbox/preview notifikasi di mobile.
- [x] Event trigger booking created.
- [x] Event trigger payment success.
- [x] Event trigger jadwal berubah.
- [x] Event trigger ticket active.
- [ ] Queue job notifikasi.
- [ ] FCM strategy.
- [x] Unread state dan badge tersambung ke backend saat refresh/polling.
- [x] Daftar yang dibuka menandai item sebagai terbaca tanpa mengganggu polling latar.
- [x] Hapus riwayat terbaca berdasarkan 7, 30, 90 hari, atau semua terbaca.
- [x] Validasi pemilik notifikasi untuk API mobile dan middleware admin untuk web.
- [x] Test API notifikasi, hak akses admin, dan keamanan notifikasi belum dibaca.
- [x] Test event operasional admin dan pencegahan duplikasi pengingat jadwal.
- [x] Test event notifikasi utama.
- Definition of Done: event utama menghasilkan notifikasi yang konsisten.

### Epic 13 - Reports, Settings, and QA Hardening

- Status: sebagian berjalan
- Priority: P1
- Dependency: seluruh epic sebelumnya
- Feature: stabilisasi akhir untuk pelaporan dan release.
- User Story: sebagai admin dan pembimbing proyek, saya ingin sistem punya laporan dan kualitas yang layak diuji.
- Checklist:
- [x] Laporan pemesanan.
- [x] Laporan transaksi.
- [x] Export laporan pemesanan CSV.
- [x] Pengaturan sistem grouped.
- [x] Pengajuan biaya operasional dari pengemudi dengan status awal menunggu verifikasi.
- [x] Verifikasi admin untuk menyetujui atau menolak biaya operasional pengemudi.
- [x] Backend feature test dasar.
- [x] Flutter widget test dasar.
- [x] Flutter analyze hijau.
- [ ] Laporan okupansi.
- [x] Laporan biaya operasional final hanya menghitung biaya yang sudah disetujui.
- [ ] Audit trail payment/cash/admin action.
- [ ] Black box checklist final.
- [ ] Regression suite high-risk flow.
- [ ] Bug bash sebelum demo/sidang.
- [ ] UAT checklist.
- Definition of Done: sistem lolos regression utama dan siap untuk demo atau sidang.

## 4. Roadmap / Sprint Plan

### Phase 0 - Audit dan Stabilisasi

- Status: [x] SELESAI
- Tujuan: memperbaiki bug struktural paling berbahaya.
- Output: seat reset fix, expired lock cleanup, baseline auth flow rapi, scope dokumen final.
- Dependency: tidak ada.
- Risiko: bug inti belum sepenuhnya terlihat sampai concurrency test dibuat.

### Phase 1 - Core Flow Penumpang

- Status: [x] SELESAI
- Tujuan: menghidupkan booking penumpang dari mobile ke backend.
- Output: daftar jadwal, detail jadwal, checkout, create booking, riwayat dasar.
- Dependency: Phase 0.
- Risiko: contract API dan UI detail jadwal bisa perlu penyesuaian tambahan.

### Phase 2 - Payment dan Tiket QR

- Status: [x] SELESAI untuk target demo simulasi lokal
- Tujuan: menutup alur transaksi dan boarding.
- Output: payment digital, cash verification, ticket issue, QR verification.
- Dependency: Phase 1.
- Risiko: integrasi payment provider dan aturan expire bisa kompleks.

### Phase 3 - Admin dan Pengemudi

- Status: [x] SELESAI untuk flow inti admin dan pengemudi
- Tujuan: membuat operasional lapangan dan admin benar-benar usable.
- Output: manifest live, scan live, trip status update, dashboard payment admin, warning operasional.
- Dependency: Phase 1 dan Phase 2.
- Risiko: status transisi lintas role harus dijaga sangat ketat.

### Phase 4 - GPS, Notifikasi, Laporan

- Status: [ ] SEBAGIAN SELESAI (notifikasi inbox selesai, laporan lanjutan masih backlog)
- Tujuan: melengkapi kenyamanan dan kontrol operasional.
- Output: pickup point lengkap, deep link Maps, notifikasi penting, laporan utama.
- Dependency: Phase 1 sampai Phase 3.
- Risiko: integrasi eksternal dan permission mobile bisa menambah edge case.

### Phase 5 - Hardening, Testing, Release Prep

- Status: [ ] SEBAGIAN SELESAI
- Tujuan: menutup kualitas akhir sebelum demo atau sidang.
- Output: black box checklist, regression pass, bug fix terakhir, dokumentasi final.
- Dependency: seluruh phase sebelumnya.
- Risiko: discovery bug akhir pada seat engine, payment, atau QR dapat memundurkan timeline.

## 5. Quality Control Summary

Bug risks utama:

- double booking
- expired lock tidak bersih
- QR scan ganda
- payment duplicate callback
- status booking loncat manual

Architecture risks utama:

- controller terlalu gemuk
- service layer belum merata
- Flutter screen masih banyak placeholder
- integrasi eksternal belum punya abstraction final

UX risks utama:

- loading dan error state belum selalu jelas
- guest dan penumpang bisa bingung bila state auth tidak sinkron
- driver butuh UI yang lebih tahan dipakai di lapangan

Data integrity risks utama:

- seat map tidak sinkron dengan detail booking
- payment status tidak sinkron dengan ticket status
- pickup point tidak lengkap

Mitigasi utama:

- transaction untuk operasi kritis
- row lock untuk seat engine
- scheduler cleanup
- webhook verification
- one-time QR validation
- black box testing di flow booking, payment, QR, driver ops, dan admin ops

## 6. Model Testing: Implementasi TDD dan Bug Monitoring

Tujuan bagian ini adalah membuat pengembangan berikutnya mengikuti pola TDD (Test Driven Development): bug atau fitur baru harus punya test yang jelas sebelum dianggap selesai. Dengan begitu, fitur yang ngebug bisa dipantau dari checklist, bukan hanya dari ingatan developer.

### 6.1 Aturan Kerja TDD

- [ ] Tulis test yang gagal dulu untuk bug atau fitur baru.
- [ ] Jalankan test dan pastikan gagal karena fitur memang belum ada atau bug masih terjadi.
- [ ] Implementasi kode paling kecil agar test tersebut lulus.
- [ ] Jalankan test terkait sampai hijau.
- [ ] Jalankan regression test utama sebelum menutup task.
- [ ] Refactor bila perlu tanpa mengubah behavior.
- [ ] Centang task hanya setelah test terkait dan verifikasi manual minimum selesai.

### 6.2 Command Testing Utama

- [x] Backend full test: `php artisan test`
- [x] Backend booking flow: `php artisan test --filter=BookingFlowTest`
- [x] Backend admin operations: `php artisan test --filter=AdminOperationsTest`
- [x] Flutter static analysis: `flutter analyze`
- [x] Flutter widget test: `flutter test`
- [x] Payment gateway focused test: `php artisan test --filter=PaymentGatewayTest`
- [x] QR scan focused test: `php artisan test --filter=DriverOperationsTest`
- [x] Driver operations focused test: `php artisan test --filter=DriverOperationsTest`

### 6.3 Test yang Sudah Ada

- [x] Guest bisa membaca detail jadwal dengan booking rules.
- [x] Penumpang bisa lock kursi untuk checkout form.
- [x] Booking estimate/action dibatasi rate limit dan request spam mendapat status 429.
- [x] Penumpang bisa membuat booking draft multi-seat dan kursi terkunci.
- [x] Expired pending booking dilepas sebelum booking baru dibuat.
- [x] Penumpang bisa simulasi pembayaran dan menerima tiket.
- [x] Cleanup command melepas expired form lock.
- [x] Guest admin diarahkan ke login saat membuka dashboard.
- [x] Admin bisa membuka dashboard.
- [x] Admin bisa export laporan booking sebagai CSV.
- [x] Pengemudi belum terverifikasi tetap nonaktif walau admin memilih aktif.
- [x] Flutter merender auth flow saat tidak ada saved session.
- [x] Pakasir create QRIS menyimpan metadata invoice gateway.
- [x] Pakasir webhook success melakukan reconcile sebelum issue ticket.
- [x] Pakasir webhook failed/expired membatalkan booking tanpa issue ticket.
- [x] Pakasir duplicate webhook idempotent.
- [x] Driver manifest membaca booking terbayar milik pengemudi.
- [x] Driver scan QR valid menandai tiket sudah dipindai dan penumpang hadir.
- [x] Driver tidak bisa scan tiket yang sama dua kali.
- [x] Driver tidak bisa scan tiket dari trip pengemudi lain.
- [x] Driver dapat mengajukan biaya operasional per jadwal dan status awalnya menunggu verifikasi admin.
- [x] Biaya operasional dari pengemudi tidak masuk rekap final sebelum admin menyetujui.
- [x] Admin tidak bisa mengubah booking dibatalkan kembali ke terbayar.
- [x] Admin payment dashboard bisa memfilter exception verifikasi cash.
- [x] Admin confirm cash menerbitkan tiket dan notifikasi pembayaran.
- [x] Admin duplicate confirm cash ditolak tanpa membuat tiket dobel.
- [x] Admin tidak bisa memakai route cash confirm untuk metode non-cash.
- [x] Admin cancel pending cash melepas kursi dan menandai payment kedaluwarsa.

### 6.4 Test Backlog Berikutnya

- [x] Payment: create transaction Pakasir success.
- [ ] Payment: create transaction Pakasir gagal dan booking tidak rusak.
- [x] Payment: webhook success mengubah payment menjadi berhasil dan menerbitkan tiket.
- [x] Payment: webhook failed/expired tidak menerbitkan tiket.
- [x] Payment: duplicate webhook tidak membuat tiket dobel.
- [x] Payment: refresh status via `transactiondetail`.
- [x] Cash: admin confirm cash.
- [x] Cash: duplicate confirm ditolak.
- [x] Cash: cancel cash melepas kursi sesuai aturan.
- [x] QR: scan valid menandai tiket sudah dipindai.
- [x] QR: scan ulang ditolak.
- [x] QR: scan tiket trip lain ditolak.
- [ ] QR: token palsu/tidak dikenal ditolak.
- [x] Driver: manifest hanya menampilkan trip milik pengemudi.
- [x] Driver: status pickup/boarding/berangkat/selesai mengikuti urutan valid.
- [x] Admin: status booking tidak bisa loncat manual tanpa guard.
- [x] Admin: payment exception muncul di dashboard pembayaran.
- [ ] Notification: booking created membuat notifikasi.
- [x] Notification: payment success membuat notifikasi.
- [ ] Frontend: payment pending/success/failed/expired tampil sesuai response.
- [ ] Frontend: GPS permission denied punya pesan error yang jelas.
- [x] Frontend: API error tidak diam-diam berubah menjadi mock data pada flow penting.

### 6.5 Bug Monitoring Board

| Status | ID | Area | Gejala bug / risiko | Test yang harus dibuat dulu | Catatan |
|---|---|---|---|---|---|
| [x] | PAY-001 | Pakasir webhook | duplicate callback bisa memproses payment lebih dari sekali | `test_duplicate_pakasir_webhook_is_idempotent` | Sudah dibuat idempotent dan teruji |
| [ ] | PAY-002 | Pakasir transaction | gagal create invoice dapat meninggalkan booking menggantung | `test_failed_pakasir_create_marks_payment_failed_safely` | Pastikan kursi/payment state aman |
| [x] | QR-001 | QR scan | tiket bisa dipakai lebih dari sekali | `test_driver_cannot_scan_same_ticket_twice` | Sudah ditolak dengan status 409 |
| [x] | QR-002 | QR scan | pengemudi bisa scan tiket trip lain | `test_driver_cannot_scan_ticket_from_other_trip` | Sudah ditolak dengan status 403 |
| [x] | DRV-001 | Driver manifest | manifest masih campur sample/live | `test_driver_can_load_owned_trip_manifest_from_paid_bookings` | Manifest live sudah dari booking terbayar |
| [x] | ADM-001 | Admin lifecycle | admin bisa loncat status booking/payment manual | `test_admin_cannot_jump_cancelled_booking_back_to_paid` | Guard lifecycle sudah aktif |
| [x] | BAG-001 | Bagasi | kapasitas bagasi lintas booking belum ketat | `test_luggage_capacity_counts_existing_bookings` | Sudah menghitung bagasi aktif dalam jadwal yang sama |
| [x] | SEC-001 | API rate limit | spam klik atau query berulang dapat membebani backend | `test_booking_action_is_rate_limited_to_reduce_spam_clicks` | Route penting sudah memakai throttle Laravel |
| [ ] | FCM-001 | Notifikasi | event penting selain payment success dan FCM belum lengkap | `test_admin_can_confirm_cash_payment_and_issue_ticket_notification` | Payment success database notification sudah teruji; FCM masih backlog |
| [x] | UI-001 | Flutter fallback | API error pada flow penting tidak lagi jatuh ke mock | `test_travs_async_state_error` | Mock fallback dihapus, TravSAsyncState menampilkan error jujur |

### 6.6 Syarat Task Boleh Dicentang

- [ ] Ada implementasi kode atau UI sesuai task.
- [ ] Ada test otomatis untuk logic yang berisiko.
- [ ] `php artisan test` lulus untuk perubahan backend.
- [ ] `flutter analyze` lulus untuk perubahan Flutter.
- [ ] `flutter test` lulus untuk perubahan Flutter yang menyentuh UI/state.
- [ ] Sudah dicek manual untuk flow yang melibatkan perangkat, GPS, kamera, atau payment gateway.
- [ ] `task.md` diperbarui setelah status task berubah.

## 7. Kesimpulan Eksekusi

Dengan scope tambahan yang sudah dihapus, backlog menjadi jauh lebih fokus dan realistis. Jalur implementasi yang benar sekarang adalah:

1. stabilkan engine booking
2. hidupkan flow mobile penumpang
3. tutup payment dan QR
4. kuatkan pengemudi dan admin
5. hardening dan testing

Ini memberi peluang jauh lebih baik untuk menyelesaikan proyek dengan kualitas yang rapi dan bisa dipertanggungjawabkan.
