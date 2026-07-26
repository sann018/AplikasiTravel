# Rangkuman Belajar dan Persiapan Sidang — RuslyTravel

> **Status audit:** diperbarui 25 Juli 2026 berdasarkan source code project `E:\POLNEP FILE\TUGAS AKHIR\AplikasiTravel`.
>
> Dokumen ini menjelaskan implementasi yang ada di source code saat audit, bukan janji fitur. Fokusnya adalah alur yang perlu dipahami saat sidang serta batasan yang perlu disampaikan secara jujur.

## 1. Gambaran sistem

RuslyTravel adalah sistem pemesanan travel lintas kota dengan dua aplikasi utama:

- **Frontend mobile:** Flutter untuk tamu, penumpang, dan pengemudi.
- **Backend:** Laravel 13 sebagai API mobile, web admin, aturan bisnis, dan akses database.

Backend adalah sumber kebenaran. Flutter hanya mengirim input dan menampilkan hasil API. Harga, kursi, status pemesanan, pembayaran, tiket, dan hak akses tetap divalidasi kembali oleh backend.

Kalimat singkat untuk sidang:

> RuslyTravel menggunakan Flutter sebagai aplikasi mobile dan Laravel sebagai backend API serta web admin. Pemisahan ini membuat aturan bisnis seperti pemesanan kursi, pembayaran, dan hak akses diproses terpusat di server.

## 2. Struktur project yang penting

### Frontend Flutter

- `frontend/lib/main.dart` dan `frontend/lib/app.dart`: titik awal serta konfigurasi aplikasi.
- `frontend/lib/core/services/travs_api_service.dart`: komunikasi HTTP ke API Laravel.
- `frontend/lib/core/services/travs_auth_service.dart`: penyimpanan sesi dan token login pada perangkat.
- `frontend/lib/core/models/travs_booking_contract.dart`: kontrak data booking/pembayaran dari API.
- `frontend/lib/features/auth`: masuk, daftar penumpang, verifikasi email, dan reset kata sandi.
- `frontend/lib/features/schedule`: daftar/detail jadwal dan pemilihan kursi.
- `frontend/lib/features/payment`: konfirmasi pemesanan dan tampilan pembayaran.
- `frontend/lib/features/booking`: daftar serta detail pemesanan.
- `frontend/lib/features/ticket`: tiket elektronik dan QR.
- `frontend/lib/features/home`: beranda sesuai peran, profil, notifikasi, dan operasional pengemudi.
- `frontend/lib/features/pickup`: pemilihan lokasi jemput pada peta.

### Backend Laravel

- `backend/routes/api.php`: endpoint yang dipakai aplikasi mobile.
- `backend/routes/web.php`: halaman dan aksi web admin.
- `backend/app/Http/Controllers/Api`: controller API mobile.
- `backend/app/Http/Controllers/Web/Admin`: controller web admin.
- `backend/app/Services/BookingService.php`: pembuatan booking, lock kursi, pembayaran, tiket, dan pembatalan otomatis.
- `backend/app/Services/BookingCalculator.php`: menghitung subtotal tiket.
- `backend/app/Services/PakasirService.php`: integrasi metode pembayaran digital Pakasir.
- `backend/app/Services/DepartureReminderService.php`: pengingat H-1 jam keberangkatan.
- `backend/app/Services/AdminOperationalNotificationService.php`: notifikasi operasional admin.
- `backend/app/Services/ScheduleLifecycleService.php`: menutup jadwal yang sudah melewati batas pemesanan.
- `backend/app/Models`: representasi tabel dan relasi database.
- `backend/database/migrations`: struktur database yang dapat dilacak dengan migration.
- `backend/tests/Feature` dan `frontend/test`: automated test backend dan Flutter.

## 3. Peran pengguna dan hak akses

| Peran | Fungsi utama |
|---|---|
| Tamu | Melihat banner, rute, jadwal, dan detail jadwal tanpa membuat pemesanan. |
| Penumpang | Memilih kursi, mengisi data perjalanan, membayar, melihat tiket, dan mengelola profil. |
| Pengemudi | Melihat Manifes, melakukan pickup, scan QR tiket, menandai turun, mengubah status perjalanan, serta mencatat biaya operasional. |
| Admin | Mengelola master data, jadwal, pemesanan, pembayaran, biaya operasional, laporan, notifikasi, banner, dan pengaturan. |

API yang membutuhkan login memakai middleware `auth:sanctum`. Endpoint penumpang dan pengemudi juga dibatasi middleware `role:penumpang` atau `role:pengemudi`; web admin memakai `auth` dan `role:admin`.

Jawaban sidang:

> Role-based access control membatasi endpoint dan halaman berdasarkan peran. Jadi penumpang tidak dapat memanggil operasi pengemudi, dan pengemudi tidak dapat mengakses web admin.

## 4. Validasi akun dan keamanan dasar

Validasi utama diterapkan di Flutter **dan** Laravel. Validasi server tetap wajib karena validasi aplikasi mobile dapat dilewati.

| Data | Aturan yang berlaku |
|---|---|
| Email | Harus format email valid dan berakhiran `@gmail.com`. |
| Nomor HP | Hanya angka, tepat 11–12 digit, dan unik. |
| Kata sandi | Panjang 6–8 karakter, wajib memiliki huruf dan minimal satu angka atau simbol. |
| Foto profil | File gambar tervalidasi sebelum disimpan. |

Pendaftaran mandiri hanya membuat akun **penumpang**. Akun pengemudi dibuat melalui admin; akun tersebut dicatat sebagai telah diverifikasi oleh admin. Penumpang baru harus menyelesaikan verifikasi kode email sebelum token login diterbitkan. Kode verifikasi disimpan dalam bentuk hash dan berlaku 15 menit.

Password di model `User` memakai cast `hashed`, sehingga yang disimpan database adalah hash, bukan kata sandi asli.

**Catatan jujur:** token Sanctum dihapus saat logout dan seluruh token dihapus setelah reset password. Namun, dari source yang diaudit belum tampak konfigurasi masa kedaluwarsa token Sanctum secara eksplisit. Jangan menyebut token otomatis expired bila fitur itu belum ditambahkan/dikonfigurasi.

## 5. Alur jadwal dan kursi

1. Admin membuat rute, kendaraan, pengemudi, lalu jadwal.
2. Saat jadwal dibuat, sistem menyiapkan kursi per jadwal pada tabel `kursi_jadwal`.
3. Admin dapat menonaktifkan kursi tertentu untuk **jadwal itu saja**. Konfigurasi kursi tidak lagi diletakkan pada form kendaraan.
4. Penumpang melihat detail jadwal dan kursi yang tersedia.
5. Penumpang memilih kursi; backend mengunci kursi sementara selama form pemesanan diisi.

Satu kendaraan atau pengemudi tidak boleh dipakai pada jadwal aktif lain di tanggal yang sama. Validasi ini mencegah bentrok penugasan operasional.

Jadwal dengan status `dibuka` otomatis menjadi `ditutup` ketika waktu keberangkatan sudah masuk H-1 jam. Penutupan dilakukan saat proses terkait berjalan dan melalui scheduler setiap menit.

Status jadwal yang dipakai: `draft`, `dibuka`, `ditutup`, `berangkat`, dan `selesai`.

## 6. Alur pemesanan dan dua batas waktu

Fitur ini memiliki **dua tahap waktu** yang berbeda. Ini adalah bagian penting untuk dijelaskan saat sidang.

```text
Pilih kursi
  -> lock form (seat_lock_form_minutes, default 30 menit)
  -> isi data penumpang, titik jemput/turun, barang, metode pembayaran
  -> booking draft dibuat
  -> lock pembayaran (seat_lock_payment_minutes, default 5 menit)
  -> pembayaran berhasil
  -> tiket elektronik diterbitkan
```

### Tahap 1 — waktu pengisian form

- Kursi yang dipilih menjadi `terkunci` oleh user yang memilihnya.
- Masa lock diambil dari pengaturan `seat_lock_form_minutes`; nilai awalnya 30 menit.
- Jika form tidak selesai hingga waktunya habis, lock form dilepas dan kursi kembali `tersedia`.
- Pada tahap ini belum ada data pemesanan draft yang disimpan.

### Tahap 2 — waktu pembayaran

- Setelah validasi form sukses, backend membuat `pemesanan` berstatus `menunggu_pembayaran`, detail penumpang, data bagasi, dan satu data pembayaran.
- Batas pembayaran disimpan di `pemesanan.batas_pembayaran` dan `pembayaran.expired_at`.
- Kursi tetap terkunci sampai batas pembayaran.
- Jika pembayaran berhasil sebelum deadline, status pemesanan menjadi `terbayar`, pembayaran menjadi `berhasil`, kursi menjadi `dipesan`, dan tiket QR diterbitkan.
- Jika deadline terlewati serta `auto_cancel_booking` aktif, pemesanan menjadi `dibatalkan`, pembayaran menjadi `kedaluwarsa`, dan kursi dilepas.

Proses pembatalan dipanggil dari alur booking dan scheduler `booking:auto-cancel-expired` setiap menit. Dengan demikian, data yang sudah melewati batas waktu tidak semestinya tetap menggantung sebagai `menunggu_pembayaran`.

Jika penumpang keluar dari aplikasi setelah draft tercipta, pemesanan masih dapat dibuka dan dibayar selama `batas_pembayaran` belum lewat. Setelah lewat, backend menolak pembayaran dan menyatakan sesi telah berakhir.

Jawaban sidang:

> Lock kursi dilakukan di server dan diperiksa ulang dalam transaksi database. Jadi dua pengguna yang memilih kursi sama tidak bisa sama-sama menyimpan pemesanan.

## 7. Perhitungan harga, bagasi, dan nilai historis

Saat ini `BookingCalculator` menghitung:

```text
subtotal_tiket = harga_tiket rute pada saat booking × jumlah kursi
total_harga    = subtotal_tiket
```

Barang bawaan tetap dicatat sebagai data operasional untuk Manifes, tetapi **tidak menambah biaya otomatis** pada versi kode saat ini. Hal ini penting agar penjelasan sidang sesuai implementasi, bukan sekadar pengaturan legacy.

Data pemesanan menyimpan `subtotal_tiket` dan `total_harga`. Karena nominal total disimpan di pemesanan ketika booking dibuat, perubahan harga rute setelahnya tidak mengubah total booking lama.

Untuk tampilan tarif per penumpang pada detail jadwal, nilai historis dihitung dari:

```text
subtotal_tiket / jumlah_kursi
```

Bukan dari harga rute saat ini. Dengan cara ini, laporan histori tetap benar jika harga rute berubah.

Pengaturan bagasi masih tersedia, misalnya batas berat gratis 10 kg, batas ukuran 160 cm, batas 2 item gratis, dan batas berat kendaraan 50 kg. Namun tarif bagasi otomatis tidak menjadi bagian dari `total_harga` pada implementasi saat audit.

## 8. Pembayaran: digital, tunai, dan snapshot nominal

Metode yang dikenali:

- QRIS;
- virtual account beberapa bank melalui Pakasir;
- pembayaran tunai;
- `transfer` lama hanya dipertahankan sebagai kompatibilitas data lama.

Pembayaran digital dibuat melalui backend menggunakan `PakasirService`; credential provider tidak dikirim atau disimpan di Flutter. Backend memvalidasi metode, membuat transaksi provider, memverifikasi nominal integer Rupiah, lalu memperbarui status berdasarkan respons gateway/webhook atau refresh status.

Setiap pembayaran menyimpan snapshot berikut:

| Kolom pembayaran | Makna |
|---|---|
| `order_id_gateway` | ID transaksi yang unik untuk gateway; berbeda dari kode booking. |
| `nominal_transaksi` | Pendapatan tiket RuslyTravel pada transaksi tersebut. |
| `biaya_admin_provider` | Biaya provider/Pakasir, jika diberikan provider. |
| `total_dibayar_penumpang` | Total final yang dibayar penumpang menurut provider. |
| `payment_number` | Nomor pembayaran/virtual account jika tersedia. |

`order_id_gateway` memiliki unique constraint. Snapshot nominal melindungi laporan histori: biaya provider atau total pada provider tidak dihitung ulang memakai pengaturan yang mungkin berubah di masa depan.

Pembayaran tunai tidak langsung menerbitkan tiket. Admin atau pengemudi yang memang ditugaskan pada jadwal dapat mengonfirmasi uang tunai; baru setelah itu pembayaran menjadi berhasil dan tiket diterbitkan. Jika jadwal diselesaikan sementara pembayaran tunai tetap belum terkonfirmasi, booking dianggap no-show, dibatalkan, dan kursinya dilepas.

Untuk demo lokal tersedia simulasi pembayaran; kode membatasi simulasi hanya pada environment `local` atau `testing`.

## 9. Tiket elektronik dan Manifes

Setelah pembayaran valid, sistem membuat tiket elektronik untuk setiap detail penumpang/kursi. QR memuat identitas tiket dan diverifikasi server saat dipindai oleh pengemudi.

Alur pengemudi:

1. Membuka jadwal tugas dan Manifes.
2. Melihat penumpang yang berhak tampil: booking terbayar/selesai, serta booking tunai yang masih menunggu konfirmasi.
3. Menandai penumpang dijemput.
4. Scan QR untuk mencatat kehadiran/boarding.
5. Setelah status jadwal `berangkat`, pengemudi dapat menandai penumpang turun.
6. Mengubah jadwal secara berurutan hingga `selesai`.

Menu utama pengemudi menggunakan istilah **Perjalanan** agar mudah dipahami. Istilah **Manifes** tetap digunakan secara khusus pada detail perjalanan untuk menyebut daftar penumpang, kursi, titik jemput/turun, dan barang bawaan.

Pada web admin, halaman **Monitor Tiket** diaktifkan untuk memantau tiket QR yang sudah atau belum dipindai. Halaman **Laporan Okupansi** tidak diaktifkan karena tidak termasuk kebutuhan proposal Tugas Akhir.

## 10. Detail jadwal admin

Halaman **Detail Jadwal** bersifat read-only sebagai ringkasan operasional, agar tidak menggandakan form detail booking atau form biaya.

Informasi yang tersedia antara lain:

- rute, jadwal berangkat, estimasi tiba, armada, pengemudi, dan status jadwal;
- total booking: menunggu pembayaran, terbayar, selesai, dan dibatalkan;
- status penumpang: menunggu, hadir, tidak hadir, dan turun;
- kondisi kursi: tersedia, dipesan, nonaktif;
- pendapatan diterima, nilai yang masih menunggu pembayaran, biaya operasional, dan selisih tercatat;
- daftar penumpang dengan tautan menuju detail pemesanan;
- biaya operasional yang telah menjadi data final.

Pendapatan diterima hanya menjumlahkan booking dengan status `terbayar`/`selesai`, pembayaran `berhasil`, dan memakai `nominal_transaksi` snapshot. Biaya provider tidak dihitung sebagai pendapatan RuslyTravel.

## 11. Notifikasi operasional

Notifikasi dibedakan antara inbox admin dan riwayat notifikasi user. Halaman admin juga memiliki filter serta pengelompokan berdasarkan tanggal.

Event penting yang dicatat untuk admin:

- pemesanan baru;
- pembayaran tunai yang perlu konfirmasi;
- pembayaran berhasil, gagal, atau kedaluwarsa;
- pembayaran masuk setelah booking sudah dibatalkan, sehingga perlu pengecekan/refund manual;
- kapasitas kursi atau berat bagasi hampir penuh;
- jadwal mendekati keberangkatan;
- armada benar-benar telah berangkat.

Scheduler mengecek jadwal yang berangkat dalam rentang satu jam, setiap 15 menit. Notifikasi admin untuk jadwal mendekati keberangkatan tetap dibuat walaupun pengingat penumpang dimatikan pada pengaturan.

`referensi_id` dapat menunjuk booking **atau** jadwal. Untuk menghindari salah tujuan bila ID keduanya sama, kode membedakan referensi jadwal menggunakan daftar judul event jadwal (`Jadwal mendekati keberangkatan`, `Armada telah berangkat`, dan sebagainya). UI kemudian hanya menampilkan tombol **Detail Jadwal** untuk referensi jadwal; notifikasi booking memakai detail pemesanan.

**Catatan yang perlu diketahui:** `DepartureReminderService` masih memakai `updateOrCreate(..., ['sudah_dibaca' => false])` untuk pengingat penumpang. Bila scheduler berjalan lagi pada pengingat yang sama, notifikasi yang sebelumnya dibaca dapat kembali menjadi belum dibaca. Ini adalah risiko yang masih perlu diperbaiki sebelum mengklaim mekanisme read-state sepenuhnya idempoten.

## 12. Web admin

Web admin mengelola:

- dashboard;
- banner beranda;
- armada kendaraan;
- pengemudi;
- rute;
- jadwal dan kursi per jadwal;
- pemesanan dan detailnya;
- pembayaran serta konfirmasi tunai;
- biaya operasional dan rekap biaya;
- laporan pemesanan/transaksi, termasuk ekspor CSV/XLS;
- notifikasi admin serta riwayat user;
- pengaturan umum, booking, pembayaran, bagasi, dan notifikasi.

Laporan transaksi membedakan tiga nilai: pendapatan RuslyTravel, biaya admin Pakasir, dan total yang dibayar penumpang. Ini menghindari salah interpretasi bahwa biaya provider adalah pendapatan travel.

## 13. Rate limit dan respons API

Laravel membatasi request berdasarkan user login atau IP:

| Limiter | Batas |
|---|---:|
| `travs-read` | 90 request/menit |
| `travs-auth` | 8 request/menit |
| `travs-verification-code` | 6 request/menit per email + IP |
| `travs-verification-resend` | 3 request/menit per email + IP |
| `travs-action` | 20 request/menit |
| `travs-upload` | 6 request/menit |
| `travs-admin-action` | 12 request/menit |
| `travs-webhook` | 60 request/menit per IP |

Jika batas terlampaui, Laravel memberi HTTP `429 Too Many Requests`. Tujuannya mencegah spam klik dan mengurangi beban query pada operasi penting seperti login, booking, pembayaran, dan upload.

## 14. Database inti

Entitas inti sistem adalah:

```text
pengguna -> pengemudi
pengguna -> pemesanan -> detail_pemesanan -> tiket_elektronik
rute + kendaraan + pengemudi -> jadwal -> kursi_jadwal
jadwal -> pemesanan
pemesanan -> pembayaran
pemesanan -> item_bagasi
jadwal -> biaya_operasional_perjalanan
pengguna -> notifikasi
```

Dokumen relasi, kolom, kardinalitas, dan audit ERD lengkap tersedia di [REKAP_DATABASE_DAN_AUDIT_ERD_RUSLYTRAVEL.md](REKAP_DATABASE_DAN_AUDIT_ERD_RUSLYTRAVEL.md).

## 15. Pengujian yang tersedia

Backend memiliki Feature Test untuk area penting, antara lain:

- validasi akun, email verification, reset password, serta foto profil;
- booking flow, payment gateway, dan laporan pendapatan;
- operasi admin, detail jadwal, notifikasi, biaya operasional, dan rate limit;
- role/keamanan serta constraint database.

Flutter memiliki test untuk kontrak pembayaran/booking, guard UI, data pengemudi/penumpang, notifikasi, pengaturan, validasi, serta pemilih lokasi peta.

Perintah verifikasi yang relevan:

```powershell
cd backend
php artisan test

cd ..\frontend
flutter test
flutter analyze
```

Jalankan scheduler pada environment deploy agar pembatalan otomatis, penutupan jadwal, dan pengingat keberangkatan benar-benar bekerja:

```powershell
cd backend
php artisan schedule:work
```

## 16. Pertanyaan sidang yang sering muncul

### Mengapa harga dihitung di backend?

Karena data dari aplikasi dapat dimanipulasi. Backend menghitung subtotal dari harga rute dan jumlah kursi, lalu menyimpan hasilnya sebagai histori transaksi.

### Mengapa perlu lock kursi dan transaksi database?

Lock kursi memberi waktu user mengisi form. Saat booking disimpan, backend kembali mengunci data dalam transaksi database agar dua user tidak berhasil mengambil kursi yang sama.

### Mengapa ada dua batas waktu?

Batas form mencegah kursi tertahan oleh user yang belum tentu jadi memesan. Batas pembayaran mencegah draft booking tertahan setelah form sudah selesai tetapi uang belum dibayar.

### Mengapa detail jadwal tidak memuat semua detail penumpang?

Detail jadwal adalah ringkasan operasional. Detail personal, pembayaran, titik lokasi, dan tiket tetap dibuka melalui halaman detail pemesanan agar informasi tidak berulang dan halaman tetap mudah dibaca.

### Mengapa nominal transaksi, biaya provider, dan total bayar dipisahkan?

Karena pendapatan travel berbeda dari biaya payment gateway dan berbeda pula dari total yang dibayar penumpang. Pemisahan snapshot menjaga laporan histori tetap akurat.

### Mengapa payment gateway diakses dari backend?

API key provider lebih aman disimpan di server. Backend juga dapat memvalidasi nominal, mencocokkan order ID, dan mengubah status booking secara konsisten.

### Mengapa perlu Manifes?

Manifes membantu pengemudi melihat penumpang per jadwal, kursi, status pembayaran yang relevan, titik jemput/turun, barang bawaan, dan status kehadiran saat operasi lapangan.

### Apa fungsi migration?

Migration adalah riwayat struktur database yang dapat dijalankan ulang secara terkontrol. Ini lebih aman daripada mengubah tabel manual.

## 17. Ringkasan presentasi 30 detik

> RuslyTravel adalah sistem pemesanan travel lintas kota berbasis Flutter dan Laravel. Penumpang memilih jadwal serta kursi, lalu backend mengunci kursi sementara, memvalidasi data, membuat booking dengan batas pembayaran, dan menerbitkan tiket QR setelah pembayaran sukses. Admin mengelola armada, rute, jadwal, pembayaran, biaya, dan laporan; pengemudi menggunakan Manifes untuk menjalankan perjalanan. Seluruh aturan penting dipusatkan di backend agar konsistensi data kursi, harga, status pembayaran, dan hak akses tetap terjaga.

## 18. Checklist sebelum sidang/demo

- Pastikan migration sudah diterapkan pada database demo.
- Pastikan scheduler berjalan bila ingin mendemokan expiry booking atau reminder.
- Pastikan konfigurasi mail tersedia bila ingin mendemokan verifikasi email/reset password.
- Pastikan konfigurasi Pakasir atau mode simulasi lokal dipilih sesuai skenario demo.
- Siapkan akun admin, pengemudi, dan penumpang beserta jadwal yang masih aktif.
- Jangan mengklaim fitur token expiry eksplisit sebelum konfigurasi tersebut benar-benar ditambahkan.
- Sampaikan risiko read-state notifikasi pengingat berulang secara jujur apabila penguji menanyakan idempotensi notifikasi.
