# Task Breakdown, Gap Analysis, dan Roadmap Implementasi

Status: Backlog implementasi aktif dengan checklist progres per fitur
Tanggal pembaruan: 1 Mei 2026
Terakhir diverifikasi: `php artisan test`, `flutter analyze`, dan `flutter test` lulus pada pengecekan lokal terakhir.

Catatan penting:

- scope tambahan untuk titipan dan carter dihapus dari proyek
- fokus akhir hanya pada layanan travel penumpang lintas kota
- layout mobile dan web admin tetap mengacu ke wireframe Figma yang dibagikan user
- visual utama tetap memakai navy, putih, dan aksen oranye

## 0. Dashboard Checklist Progres

Checklist ini dipakai untuk memantau pekerjaan harian. Item yang sudah benar-benar tersambung, buildable, dan sudah diuji dasar diberi tanda `[x]`. Item yang masih UI preview, scaffold, atau belum teruji end-to-end produksi tetap `[ ]`.

### Sudah dikerjakan / sudah tersambung

- [x] Shell mobile final untuk guest, penumpang, dan pengemudi.
- [x] Shell mobile role-based diselaraskan ke wireframe Figma penumpang dan sopir.
- [x] Auth mobile login, register, guest mode, restore session, dan logout.
- [x] Route guard role dasar untuk penumpang, pengemudi, dan admin.
- [x] Guest schedule dan detail jadwal bisa dibaca dari API.
- [x] Seat lock dua tahap dasar untuk checkout penumpang.
- [x] Cleanup expired form lock dan expired pending booking via scheduler.
- [x] Checkout penumpang live dari mobile ke backend.
- [x] Pemilihan kursi live dengan guard race condition dasar.
- [x] Form pickup dengan GPS, map picker, dan reverse geocoding.
- [x] Koordinat dan alamat pickup tersimpan di backend.
- [x] Detail booking bisa membuka pickup di Google Maps.
- [x] Riwayat booking penumpang live.
- [x] Detail booking penumpang live.
- [x] Tiket QR penumpang live per kursi setelah pembayaran terverifikasi.
- [x] Daftar tiket, notifikasi UI, riwayat sopir UI, detail perjalanan sopir UI, dan preview scan ticket sudah punya tampilan mobile baru.
- [x] Payment preview pending untuk QRIS, transfer, dan tunai sudah dirapikan mengikuti pola gateway eksternal / Pakasir.
- [x] Simulasi pembayaran lokal untuk mempercepat validasi flow tiket.
- [x] Bug reset status kursi saat admin edit jadwal sudah diperbaiki.
- [x] Admin web dasar: dashboard, CRUD kendaraan, CRUD pengemudi, CRUD rute, CRUD jadwal, pemesanan, biaya operasional, laporan, notifikasi, dan pengaturan.
- [x] Test dasar backend dan Flutter berjalan hijau.

### Belum selesai / prioritas berikutnya

- [x] Integrasi backend Pakasir real API untuk create QRIS, refresh status, dan webhook.
- [x] Webhook Pakasir final dengan skenario success, failed, duplicate callback, dan expired.
- [ ] Uji transaksi nyata dari akun Pakasir sampai callback publik masuk ke server.
- [ ] Forgot password end-to-end.
- [x] Scan QR sekali pakai di sisi pengemudi dengan kamera dan endpoint validasi.
- [x] Manifest pengemudi live dari backend, bukan data sample/preview.
- [ ] Action operasional pengemudi lengkap: pickup, hadir/boarding, berangkat, selesai.
- [ ] Dashboard pembayaran admin yang fokus untuk monitoring payment exception.
- [x] Guard lifecycle admin agar status booking/payment tidak loncat sembarangan.
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
- [x] Payment gateway Pakasir sudah punya create QRIS, refresh status, dan webhook final secara backend.
- [ ] Payment gateway masih perlu uji transaksi nyata dari akun Pakasir dan callback publik.

Implikasi prioritas:

- fase awal tetap fokus ke stabilisasi core booking penumpang sampai payment gateway dan QR scan lapangan benar-benar final
- penambahan fitur baru di luar alur penumpang harus dihentikan
- seat engine, payment, QR, dan driver ops adalah jalur kritis

## 2. Gap Analysis dan Rencana Refactor

| Status | Modul / file / flow existing | Kondisi saat ini | Masalah tersisa | Aksi | Alasan |
|---|---|---|---|---|---|
| [x] | `backend/app/Services/BookingService.php` | draft booking, form lock, payment lock, row lock, cleanup expired, simulasi payment, dan issue ticket sudah ada | perlu test concurrency lebih berat dan payment gateway nyata | `refactor lanjut` | mencegah double booking dan menjaga lifecycle payment |
| [x] | `backend/app/Http/Controllers/Web/Admin/JadwalController.php` | CRUD jadwal berjalan dan bug reset kursi existing sudah ditangani | warning operasional masih bisa diperkuat | `refactor lanjut` | menjaga integritas data |
| [x] | `backend/routes/console.php` | command `travs:release-expired-bookings` dan scheduler setiap menit sudah ada | perlu validasi di deployment server | `verify` | memenuhi aturan seat lock |
| [x] | `backend/app/Http/Controllers/Api/BookingController.php` | schedule detail, estimate, lock form, store booking, history, detail, simulasi payment, dan refresh payment status sudah ada | payment refresh masih bergantung konfigurasi gateway | `extend` | menutup gap payment produksi |
| [ ] | `backend/app/Http/Controllers/Api/AuthController.php` | register/login/logout/me dasar sudah ada | belum ada forgot/reset password | `extend` | melengkapi auth inti |
| [x] | `backend/app/Http/Controllers/Api/DriverController.php` | dashboard, manifest live, scan QR satu kali, dan update status trip dasar sudah ada | action pickup belum final | `extend` | pengemudi sudah mulai operasional, masih perlu polish flow lapangan |
| [x] | `backend/app/Http/Controllers/Web/Admin/PemesananController.php` | admin bisa lihat/ubah status booking dengan lifecycle guard | dashboard payment exception belum dipisah | `refactor lanjut` | mencegah status loncat |
| [ ] | `backend/app/Services/BookingCalculator.php` | rule bagasi dasar sudah ada | kapasitas bagasi lintas booking belum dihitung ketat | `refactor` | validasi operasional harus benar |
| [x] | `backend/config/services.php` | config Pakasir sudah ada | config maps/FCM belum final | `extend` | integrasi eksternal perlu env jelas |
| [x] | `backend/.env.example` | env Pakasir sudah disiapkan | env maps/FCM/deployment belum final | `extend` | deployment target butuh env jelas |
| [ ] | `frontend/lib/core/services/travs_api_service.dart` | bisa baca bootstrap, jadwal, booking, dan dashboard | masih fallback ke mock pada beberapa jalur UI | `refactor` | app harus jujur terhadap error |
| [ ] | `frontend/lib/features/home/travs_home_page.dart` | shell role-based, notifikasi UI, daftar tiket, riwayat sopir UI, detail perjalanan UI, dan scan preview sudah dirapikan | data driver detail masih gabungan live dan sample karena endpoint manifest live belum final | `extend` | menjaga layout wireframe tetap hidup sambil menunggu endpoint operasional penuh |
| [x] | `frontend/lib/features/schedule/travs_schedule_detail_page.dart` | detail jadwal dan submit ke payment/create booking sudah tersambung | perlu hardening loading/empty/error | `verify` | flow penumpang sudah hidup |
| [ ] | `frontend/lib/features/payment/travs_payment_preview_page.dart` | create booking live dan tampilan payment sudah dirombak | invoice eksternal, webhook produksi, dan sinkronisasi payment gateway nyata belum final | `extend` | menutup gap payment produksi |
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
- [ ] Standarkan response error semua API penting.
- [ ] Hilangkan fallback mock diam-diam pada jalur kritis mobile.
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
- [x] Restore session.
- [x] Logout.
- [x] Endpoint `/api/auth/me`.
- [x] Middleware role dasar untuk penumpang, pengemudi, dan admin.
- [x] Admin login web.
- [ ] Forgot password end-to-end.
- [ ] Reset password end-to-end.
- [ ] Test forbidden access per role.
- Definition of Done: role flow jelas dan akses endpoint terjaga.

### Epic 3 - Guest & Passenger Home

- Status: sebagian besar selesai
- Priority: P1
- Dependency: Epic 2
- Feature: beranda mobile final sesuai wireframe dan scope akhir.
- User Story: sebagai guest atau penumpang, saya ingin landing flow yang jelas agar cepat menuju jadwal, booking, dan tiket.
- Checklist:
- [x] Home shell guest.
- [x] Home shell penumpang.
- [x] Home shell pengemudi.
- [x] Navigasi penumpang `Beranda / Rute / Pesanan / Profil`.
- [x] Navigasi pengemudi `Beranda / Manifest / Riwayat / Profil`.
- [x] Bootstrap mobile dari backend.
- [x] Daftar jadwal dari API.
- [x] Riwayat booking penumpang dari API.
- [x] Daftar tiket aktif dari booking penumpang.
- [ ] State loading-empty-error dibuat konsisten di semua halaman.
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
- [x] Isi biodata penumpang per kursi.
- [x] Isi data bagasi dasar.
- [x] Pilihan hitung bagasi berat + item atau ukuran + item dengan input angka.
- [x] Upload foto barang bawaan dari kamera/galeri sebelum submit booking.
- [x] Rincian tambahan biaya bagasi tampil pada ringkasan checkout.
- [x] Isi pickup point.
- [x] Submit booking live dari payment screen.
- [x] Booking muncul di backend dan admin.
- [x] Detail booking menampilkan penumpang, kursi, pickup, bagasi, dan payment.
- [x] Test backend create booking multi-seat dan lock selected seats.
- [x] Test upload foto bagasi dan biaya tambahan item di atas batas gratis.
- [ ] Test validation error lengkap untuk payload booking.
- [ ] Test batas bagasi lintas booking.
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

- Status: sebagian scaffold sudah ada, produksi belum final
- Priority: P0
- Dependency: Epic 4, Epic 5
- Feature: payment digital end-to-end.
- User Story: sebagai penumpang, saya ingin membayar secara digital dan mendapatkan status otomatis.
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
- [ ] Transaksi Pakasir nyata berhasil dibuat dan dicek dari akun Pakasir.
- [ ] Webhook success transaksi nyata dari callback publik.
- [x] Webhook success teruji otomatis melalui reconcile `transactiondetail`.
- [x] Webhook failed/expired teruji otomatis.
- [x] Duplicate webhook aman dan idempotent.
- [x] Dashboard admin khusus payment exception.
- [x] Test callback success, failed, duplicate callback, dan expired payment.
- Definition of Done: payment digital dapat mengubah booking menjadi terbayar secara aman pada transaksi nyata.

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
- [x] UI detail perjalanan sopir preview.
- [x] UI scan ticket preview.
- [x] Trip endpoint live.
- [x] Manifest endpoint live.
- [x] Formatter manifest dari booking terbayar.
- [x] Role ownership guard agar sopir hanya melihat trip miliknya.
- [x] Scan QR live dari kamera.
- [ ] Action pickup.
- [x] Action hadir/boarding melalui scan QR.
- [x] Action berangkat.
- [x] Action selesai.
- [ ] Monitoring status perjalanan di admin.
- [x] Test assigned trip only, manifest akurat, scan ownership, dan repeat scan aman.
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

- Status: belum dimulai penuh
- Priority: P2
- Dependency: Epic 6, Epic 8, Epic 9
- Feature: notifikasi event penting.
- User Story: sebagai penumpang atau pengemudi, saya ingin mendapatkan update penting tanpa harus selalu membuka aplikasi.
- Checklist:
- [x] Tabel/model notifikasi dasar.
- [x] Halaman admin notifikasi dasar.
- [x] UI inbox/preview notifikasi di mobile.
- [ ] Event trigger booking created.
- [x] Event trigger payment success.
- [ ] Event trigger jadwal berubah.
- [x] Event trigger ticket active.
- [ ] Queue job notifikasi.
- [ ] FCM strategy.
- [ ] Unread state live.
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
- [x] Backend feature test dasar.
- [x] Flutter widget test dasar.
- [x] Flutter analyze hijau.
- [ ] Laporan okupansi.
- [ ] Laporan biaya operasional final.
- [ ] Audit trail payment/cash/admin action.
- [ ] Black box checklist final.
- [ ] Regression suite high-risk flow.
- [ ] Bug bash sebelum demo/sidang.
- [ ] UAT checklist.
- Definition of Done: sistem lolos regression utama dan siap untuk demo atau sidang.

## 4. Roadmap / Sprint Plan

### Phase 0 - Audit dan Stabilisasi

- Status: [ ] belum selesai penuh, mayoritas stabilisasi sudah selesai
- Tujuan: memperbaiki bug struktural paling berbahaya.
- Output: seat reset fix, expired lock cleanup, baseline auth flow rapi, scope dokumen final.
- Dependency: tidak ada.
- Risiko: bug inti belum sepenuhnya terlihat sampai concurrency test dibuat.

### Phase 1 - Core Flow Penumpang

- Status: [x] selesai untuk draft booking live
- Tujuan: menghidupkan booking penumpang dari mobile ke backend.
- Output: daftar jadwal, detail jadwal, checkout, create booking, riwayat dasar.
- Dependency: Phase 0.
- Risiko: contract API dan UI detail jadwal bisa perlu penyesuaian tambahan.

### Phase 2 - Payment dan Tiket QR

- Status: [ ] belum selesai penuh
- Tujuan: menutup alur transaksi dan boarding.
- Output: payment digital, cash verification, ticket issue, QR verification.
- Dependency: Phase 1.
- Risiko: integrasi payment provider dan aturan expire bisa kompleks.

### Phase 3 - Admin dan Pengemudi

- Status: [ ] belum selesai penuh
- Tujuan: membuat operasional lapangan dan admin benar-benar usable.
- Output: manifest live, scan live, trip status update, dashboard payment admin, warning operasional.
- Dependency: Phase 1 dan Phase 2.
- Risiko: status transisi lintas role harus dijaga sangat ketat.

### Phase 4 - GPS, Notifikasi, Laporan

- Status: [ ] sebagian GPS selesai, notifikasi/laporan final belum selesai
- Tujuan: melengkapi kenyamanan dan kontrol operasional.
- Output: pickup point lengkap, deep link Maps, notifikasi penting, laporan utama.
- Dependency: Phase 1 sampai Phase 3.
- Risiko: integrasi eksternal dan permission mobile bisa menambah edge case.

### Phase 5 - Hardening, Testing, Release Prep

- Status: [ ] belum selesai penuh
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
- [ ] Driver: status pickup/boarding/berangkat/selesai mengikuti urutan valid.
- [x] Admin: status booking tidak bisa loncat manual tanpa guard.
- [x] Admin: payment exception muncul di dashboard pembayaran.
- [ ] Notification: booking created membuat notifikasi.
- [x] Notification: payment success membuat notifikasi.
- [ ] Frontend: payment pending/success/failed/expired tampil sesuai response.
- [ ] Frontend: GPS permission denied punya pesan error yang jelas.
- [ ] Frontend: API error tidak diam-diam berubah menjadi mock data pada flow penting.

### 6.5 Bug Monitoring Board

| Status | ID | Area | Gejala bug / risiko | Test yang harus dibuat dulu | Catatan |
|---|---|---|---|---|---|
| [x] | PAY-001 | Pakasir webhook | duplicate callback bisa memproses payment lebih dari sekali | `test_duplicate_pakasir_webhook_is_idempotent` | Sudah dibuat idempotent dan teruji |
| [ ] | PAY-002 | Pakasir transaction | gagal create invoice dapat meninggalkan booking menggantung | `test_failed_pakasir_create_marks_payment_failed_safely` | Pastikan kursi/payment state aman |
| [x] | QR-001 | QR scan | tiket bisa dipakai lebih dari sekali | `test_driver_cannot_scan_same_ticket_twice` | Sudah ditolak dengan status 409 |
| [x] | QR-002 | QR scan | sopir bisa scan tiket trip lain | `test_driver_cannot_scan_ticket_from_other_trip` | Sudah ditolak dengan status 403 |
| [x] | DRV-001 | Driver manifest | manifest masih campur sample/live | `test_driver_can_load_owned_trip_manifest_from_paid_bookings` | Manifest live sudah dari booking terbayar |
| [x] | ADM-001 | Admin lifecycle | admin bisa loncat status booking/payment manual | `test_admin_cannot_jump_cancelled_booking_back_to_paid` | Guard lifecycle sudah aktif |
| [ ] | BAG-001 | Bagasi | kapasitas bagasi lintas booking belum ketat | `test_luggage_capacity_counts_existing_bookings` | Terkait operasional |
| [ ] | FCM-001 | Notifikasi | event penting selain payment success dan FCM belum lengkap | `test_admin_can_confirm_cash_payment_and_issue_ticket_notification` | Payment success database notification sudah teruji; FCM masih backlog |
| [ ] | UI-001 | Flutter fallback | API error pada flow penting masih bisa jatuh ke mock | `test_booking_flow_shows_error_when_api_fails` | Penting agar demo jujur terhadap data |

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
