# Pengujian, Temuan Audit, dan Latihan Sidang

## 1. Hasil Pengujian Aktual

Pengujian dijalankan pada 26 Juli 2026 dari workspace aktif.

### Backend

```text
Perintah   : php artisan test --no-ansi
Hasil      : 271 test lulus
Assertion  : 1.715
Durasi     : 42,48 detik
Status     : PASS
```

### Flutter

```text
Perintah   : flutter test
Hasil      : 72 test lulus
Status     : PASS
```

Seluruh pengujian otomatis lulus. Hasil tersebut membuktikan fungsi yang tercakup test, tetapi
tidak menggantikan pengujian perangkat, browser, scheduler deployment, dan integrasi Pakasir
secara live.

## 2. Matriks Pengujian yang Sudah Tersedia

| Area | Bukti Test |
|---|---|
| Validasi akun | Gmail, HP 11–12 digit, password minimal 6 karakter dengan huruf dan angka/simbol |
| Autentikasi | verifikasi email, login, lupa/reset password, role |
| Penguncian kursi | lock, release, dua pengguna, sesi form habis |
| Pemesanan | lokasi wajib, barang bawaan, snapshot total, double submit |
| Pembayaran | QRIS, VA, cash, timeout, recovery, snapshot fee/total |
| Webhook | validasi, mismatch, idempotensi, status gagal/kedaluwarsa |
| Tiket | terbit setelah bayar, scan sekali, jadwal/pengemudi yang benar |
| Pengemudi | manifes, pickup, dropoff, cash, perjalanan, biaya |
| Jadwal | konflik driver/armada, penutupan satu jam, status selesai |
| Admin | UI detail, filter, laporan, export, notifikasi, pengaturan |
| Pendapatan | fee terpisah, booking batal tidak masuk, bulan/tahun |
| Flutter | render pembayaran, peta, ticket download, izin galeri, history |

Daftar test dapat dilihat pada:

- [`backend/tests/`](../backend/tests/)
- [`frontend/test/`](../frontend/test/)

## 3. Rencana Pengujian Manual untuk BAB IV

### 3.1 Black-box Penumpang

| Kasus | Langkah | Hasil yang Diharapkan |
|---|---|---|
| Registrasi valid | isi Gmail, HP 11–12 digit, password valid | akun dibuat dan meminta verifikasi |
| Kursi bersamaan | dua akun memilih kursi yang sama | akun kedua ditolak/abu-abu |
| Form kedaluwarsa | tunggu sampai deadline | submit ditolak dan kursi dilepas |
| Booking valid | isi penumpang dan lokasi sebelum deadline | status menunggu pembayaran |
| QRIS/VA | pilih metode digital | snapshot nominal, fee, total, number, expiry muncul |
| Tunai | pilih cash | tidak ada countdown, status tetap menunggu |
| Pembayaran selesai | selesaikan/simulasikan provider | booking terbayar dan tiket terbit |
| Pembayaran habis | lewatkan deadline | pembayaran kedaluwarsa, booking batal, kursi tersedia |
| Multi tiket | booking 2–3 kursi | pilihan nama dan tiket per penumpang |
| Unduh tiket | tekan unduh | minta izin jika perlu dan simpan PNG |

### 3.2 Black-box Pengemudi

| Kasus | Hasil yang Diharapkan |
|---|---|
| membuka jadwal orang lain | ditolak 403 |
| scan token palsu | tiket tidak ditemukan |
| scan dua kali | scan kedua ditolak 409 |
| scan setelah jadwal selesai | ditolak |
| pickup penumpang valid | `dijemput_pada` terisi |
| dropoff sebelum hadir | ditolak |
| konfirmasi tunai | pembayaran berhasil dan tiket terbit |
| mulai perjalanan | status berangkat dan admin menerima notifikasi |
| selesaikan perjalanan | booking terbayar menjadi selesai |

### 3.3 Black-box Admin

| Kasus | Hasil yang Diharapkan |
|---|---|
| driver sama pada tanggal sama | jadwal baru ditolak |
| kendaraan sama pada tanggal sama | jadwal baru ditolak |
| rute tanpa durasi | jadwal ditolak |
| detail jadwal | hanya booking pembayaran berhasil |
| pendapatan | memakai nominal, fee tidak masuk |
| filter terbalik | tanggal selesai sebelum mulai ditolak |
| export XLS | periode/filter sama dan AutoFilter tersedia |
| konfirmasi tunai digital | tombol tidak ada/request ditolak |
| hapus data berelasi | ditolak dan histori tidak rusak |

## 4. Temuan Audit yang Perlu Dicatat

### 4.1 Migration Backfill Tunai Lama Masih Pending

`2026_07_25_000100_backfill_cash_payment_snapshots.php` belum dijalankan. Transaksi baru sudah
menyimpan snapshot dengan benar. Sebelum deploy ke database lama:

1. buat backup;
2. periksa jumlah transaksi tunai historis;
3. jalankan migration pada staging;
4. cocokkan nominal sebelum/after;
5. baru jalankan pada database target.

### 4.2 Fee Pakasir adalah Ketergantungan Eksternal

Test memakai mock/fake response. Untuk menyatakan integrasi fee selesai, perlu raw response
Transaction Create dari proyek Pakasir yang benar. Jika `fee = 0`, backend harus menyimpan nol dan
konfigurasi penanggung fee pada provider perlu diperiksa.

### 4.3 Direct API dan Hosted URL

Backend membuat transaksi melalui Direct API, lalu menyimpan hosted `checkout_url` untuk order
yang sama. Tidak ada pemanggilan Transaction Create kedua, tetapi integrasi belum murni
menampilkan QR/VA seluruhnya di Flutter. Jelaskan pola ini jika ditanya penguji.

### 4.4 Notifikasi Pengingat Penumpang

`DepartureReminderService` memakai:

```php
Notifikasi::query()->updateOrCreate(
    [...],
    ['pesan' => $message, 'sudah_dibaca' => false],
);
```

Karena scheduler berjalan berkala, notifikasi pengingat penumpang yang sudah dibaca berpotensi
ditandai belum dibaca lagi. Test admin memastikan notifikasi admin tidak ganda, tetapi kondisi baca
pengingat penumpang ini perlu test/fix terpisah sebelum disebut sempurna.

Sumber:
[`backend/app/Services/DepartureReminderService.php`](../backend/app/Services/DepartureReminderService.php#L51)

### 4.5 Referensi Notifikasi Belum Bertipe

Tabel notifikasi hanya mempunyai `referensi_id`. Tidak ada `referensi_tipe` dalam migration.
Model/admin menggunakan konteks judul untuk membedakan ID jadwal dan pemesanan. Test mengamankan
kasus ID yang kebetulan sama pada notifikasi admin, tetapi desain database masih dapat
ditingkatkan.

### 4.6 Detail Penumpang Booking Kedaluwarsa Dihapus

Saat pembayaran kedaluwarsa, `detail_pemesanan` dihapus, sedangkan induk pemesanan dibatalkan.
Artinya histori pembatalan tidak menyimpan nama/kursi sedetail transaksi berhasil. Ini bukan error
test saat ini, tetapi keputusan data yang perlu diterangkan atau ditinjau apabila laporan
membutuhkan audit lengkap.

### 4.7 Penyaringan Jadwal Penuh Berada di Flutter

API bootstrap masih dapat mengirim jadwal `sisa_kursi = 0`; Flutter menyaringnya sebelum card
beranda dibentuk. Perilaku UI sudah sesuai, tetapi API belum meniadakan data tersebut.

### 4.8 Laporan Okupansi adalah Kode Yatim

Method controller dan view okupansi masih tersedia, tetapi tidak mempunyai route/menu aktif.
Jangan masukkan sebagai fitur hasil implementasi. Aman dipertahankan sebagai kode arsip, namun
dapat dihapus pada cleanup terpisah setelah memastikan tidak ada kebutuhan proposal.

### 4.9 Tile OpenStreetMap Publik

`flutter test` menampilkan peringatan bahwa public tile server OpenStreetMap bukan layanan bebas
untuk trafik produksi tanpa memperhatikan kebijakan pemakaian. Untuk penelitian/lokal masih dapat
dipakai, tetapi deployment komersial perlu provider tile yang sesuai atau self-host.

### 4.10 Database Produksi Belum Dibuktikan dari Repository

`.env.example` memakai SQLite. Karena `.env` nyata tidak boleh dibuka atau dimasukkan laporan,
jenis database deployment perlu dikonfirmasi dari lingkungan server sebelum laporan menyatakan
MySQL.

### 4.11 Push Notification Belum Aktif

Kolom `token_push` ada, tetapi alur utama notifikasi menggunakan tabel dan polling/refresh aplikasi.
Jangan mengklaim Firebase Cloud Messaging jika belum diimplementasikan.

## 5. Batas Klaim Pengujian

Kalimat yang aman:

> Pengujian otomatis pada workspace menghasilkan 271 test backend dan 72 test Flutter yang
> seluruhnya lulus. Pengujian tersebut mencakup validasi, role, penguncian kursi, pembayaran,
> webhook, tiket, operasional pengemudi, laporan, dan render komponen utama. Pengujian provider
> Pakasir secara live, kebijakan tile map produksi, scheduler pada server, dan performa beban
> bersamaan tetap memerlukan verifikasi pada lingkungan deployment.

Jangan menulis:

- “Sistem bebas bug.”
- “Fee Pakasir pasti sesuai produksi.”
- “Semua platform Flutter sudah diuji.”
- “Notifikasi real-time push sudah tersedia.”
- “Database pasti MySQL” tanpa bukti deployment.

## 6. Pertanyaan dan Jawaban Sidang

### Mengapa backend menjadi source of truth?

Karena payload Flutter dapat dimodifikasi. Backend harus mengambil harga dari database, memeriksa
status kursi, menghitung total, dan menentukan status sebelum menyimpan transaksi.

### Bagaimana mencegah dua penumpang memilih kursi yang sama?

Sistem menggunakan status `terkunci`, identitas pengunci, expiry, transaksi database, dan
`lockForUpdate()`. Constraint unik pada detail kursi menjadi pengaman tambahan.

### Apa beda batas form dan batas pembayaran?

Batas form berlaku sejak kursi dikunci sampai data konfirmasi dikirim. Setelah booking dibuat,
kunci dipindahkan ke batas pembayaran digital. Tunai tidak mempunyai deadline provider setelah
metode dipilih.

### Mengapa booking dibuat sebelum transaksi Pakasir?

Agar transaksi provider hanya dibuat setelah pengguna memilih metode. Booking menjadi identitas
lokal dan menyimpan harga historis, sedangkan provider hanya menangani upaya pembayaran.

### Bagaimana mencegah webhook palsu?

Backend memvalidasi project, order ID, nominal, metode, serta status, lalu meminta Transaction
Detail ke Pakasir. Tiket diterbitkan hanya jika webhook dan detail sama-sama completed.

### Mengapa fee tidak masuk pendapatan?

Fee merupakan biaya provider yang dibayar penumpang. Pendapatan jasa RuslyTravel adalah
`nominal_transaksi`; `total_dibayar_penumpang` hanya menjelaskan beban akhir pelanggan.

### Mengapa tiket dibuat per penumpang?

Satu booking dapat mempunyai beberapa kursi dan nama. Relasi tiket ke `detail_pemesanan`
memungkinkan QR, kursi, dan status scan berbeda untuk setiap orang.

### Mengapa lokasi memakai latitude dan longitude?

Koordinat lebih presisi daripada teks alamat. Reverse geocoding membantu pengguna membaca alamat,
sedangkan pengemudi tetap dapat membuka titik yang sama di aplikasi peta.

### Mengapa konflik jadwal diperiksa per tanggal, bukan jam?

Implementasi menerapkan aturan konservatif satu tugas aktif per pengemudi/armada per hari untuk
mencegah overlap perjalanan antarkota. Konsekuensinya, jadwal berbeda jam pada hari yang sama
tetap ditolak.

### Apa fungsi snapshot?

Snapshot menyimpan nilai ketika transaksi terjadi. Perubahan harga rute atau fee di masa depan
tidak mengubah histori transaksi lama.

### Apa kegunaan scheduler?

Scheduler melepaskan kunci kedaluwarsa, membatalkan booking pending, menutup jadwal satu jam
sebelum berangkat, serta mengirim pengingat. Ia mengurangi ketergantungan pada pengguna yang
harus membuka halaman.

### Apa perbedaan kode booking dan order ID gateway?

Kode booking mengidentifikasi pemesanan RuslyTravel. Order ID gateway mengidentifikasi transaksi
Pakasir. Pemisahan menghindari kebingungan ketika satu sistem perlu menyimpan identitas bisnis dan
identitas provider.

### Mengapa total memakai integer?

Rupiah tidak memerlukan pecahan pada transaksi ini. Integer menghindari kesalahan floating point
dan mempermudah perbandingan nominal webhook.

### Apakah barang bawaan dihitung biaya?

Tidak pada implementasi aktif. Barang, dimensi, posisi, dan foto dicatat untuk operasional, tetapi
`biaya_item` transaksi baru nol dan total booking hanya subtotal tiket.

### Mengapa laporan okupansi tidak ditampilkan?

Fitur tersebut tidak termasuk ruang lingkup proposal akhir. Kode lama masih ada sebagai arsip,
tetapi tidak mempunyai route/menu aktif sehingga tidak diklaim sebagai fitur.

## 7. Demonstrasi Sidang yang Disarankan

Urutan demo aman:

1. admin membuat jadwal dan tunjukkan validasi konflik;
2. penumpang A mengunci kursi;
3. penumpang B melihat kursi abu-abu/ditolak;
4. penumpang A menyelesaikan form;
5. pilih QRIS/VA dan tunjukkan pemisahan nominal-fee-total;
6. simulasikan pembayaran sandbox;
7. tunjukkan tiket per penumpang;
8. pengemudi scan QR dan coba scan kedua;
9. tandai pickup/dropoff lalu selesaikan perjalanan;
10. admin membuka detail jadwal dan export laporan.

Siapkan skenario cadangan tunai apabila jaringan Pakasir tidak tersedia.

## Hasil Uji Real-Time — 27 Juli 2026

| Pengujian | Hasil |
|---|---|
| Koneksi WebSocket ke Reverb port 6001 | Berhasil |
| Subscribe channel publik `jadwal.{id}` | Berhasil |
| Event backend diterima client | Berhasil |
| Backend keseluruhan | 275 test, 1.743 assertion lulus |
| Statistik pendapatan dan rute | 5 test, 33 assertion lulus |
| Booking flow | 25 test, 148 assertion lulus |
| Flutter | 78 test lulus |
| `flutter analyze` | Tidak ada masalah |
| Build APK debug | Berhasil |
| Build Vite admin | Berhasil |
| Composer validation | Berhasil |

Uji tambahan untuk card rute memastikan controller mengirim jumlah pemesanan selesai dan jumlah
penumpang sebagai dua nilai berbeda. Karena nilai dihitung melalui agregasi, tidak ada migration
baru yang diperlukan.
