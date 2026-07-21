# 07 — Materi Siap Pakai untuk BAB IV & BAB V Laporan

> Bab ini berisi **draft tulisan** yang bisa langsung kamu salin-sesuaikan ke laporan Tugas Akhir.
> Angka, nama file, dan fakta di sini diambil dari kondisi kode project yang sebenarnya. Ganti bagian
> `[...]` sesuai punyamu, dan tambahkan screenshot di tempat yang ditandai.

---

# BAB IV — HASIL DAN PEMBAHASAN

## 4.1 Gambaran Umum Sistem

Aplikasi RuslyTravel merupakan aplikasi pemesanan jasa transportasi umum travel lintas kota berbasis
mobile yang dilengkapi panel administrasi berbasis web. Sistem ini dirancang untuk mempermudah
proses pemesanan tiket travel yang sebelumnya dilakukan secara manual, mulai dari melihat jadwal
keberangkatan, memilih kursi, melakukan pembayaran, hingga penerbitan tiket elektronik.

Sistem terdiri atas dua bagian utama yang saling terhubung. Bagian pertama adalah **backend** yang
dibangun menggunakan framework Laravel dan berfungsi sebagai pusat pengolahan data serta penyedia
layanan API. Bagian kedua adalah **aplikasi mobile** yang dibangun menggunakan framework Flutter dan
digunakan oleh penumpang serta pengemudi. Selain itu, backend juga menyediakan **panel web admin**
yang digunakan oleh pengelola untuk mengatur seluruh data operasional.

Sistem melayani empat peran pengguna, yaitu:
1. **Tamu**, yaitu pengunjung yang belum masuk dan hanya dapat melihat informasi rute serta jadwal.
2. **Penumpang**, yaitu pengguna terdaftar yang dapat melakukan pemesanan tiket dan pembayaran.
3. **Pengemudi**, yaitu pengemudi yang bertugas melihat daftar penumpang (manifest) dan memvalidasi
   tiket melalui pemindaian QR code.
4. **Admin**, yaitu pengelola yang mengatur data rute, armada, pengemudi, jadwal, pemesanan,
   pembayaran, laporan, dan pengaturan sistem melalui panel web.

### 4.1.1 Analisis Kebutuhan Sistem

Analisis kebutuhan dibagi menjadi kebutuhan perangkat keras dan perangkat lunak yang diperlukan untuk
membangun dan menjalankan sistem.

#### a. Kebutuhan Perangkat Keras

Perangkat keras yang digunakan dalam pengembangan sistem ini adalah sebagai berikut:

| No | Perangkat Keras | Spesifikasi Minimal |
|----|-----------------|---------------------|
| 1 | Laptop/Komputer | Processor Intel Core i3 atau setara, RAM 8 GB, penyimpanan 256 GB |
| 2 | Smartphone Android | Android 8.0 (Oreo) ke atas, RAM 3 GB (untuk pengujian aplikasi mobile) |
| 3 | Koneksi Internet | Diperlukan untuk komunikasi antara aplikasi dan server |

> *Catatan: sesuaikan spesifikasi di atas dengan perangkat yang sebenarnya kamu pakai. Untuk fitur
> peta dan pemindaian QR, smartphone perlu memiliki GPS dan kamera.*

#### b. Kebutuhan Perangkat Lunak

Perangkat lunak yang digunakan dalam pengembangan sistem ini adalah:

| No | Perangkat Lunak | Fungsi |
|----|-----------------|--------|
| 1 | Sistem Operasi Windows | Sistem operasi pengembangan |
| 2 | PHP 8.3 | Bahasa pemrograman backend |
| 3 | Laravel 13 | Framework backend |
| 4 | Composer | Pengelola paket/library PHP |
| 5 | Laravel Sanctum | Autentikasi token untuk API mobile |
| 6 | MySQL | Sistem manajemen basis data (database `aplikasi_travel`) |
| 7 | Flutter (Dart) | Framework aplikasi mobile |
| 8 | Node.js & Vite | Membangun aset CSS/JavaScript panel admin |
| 9 | Visual Studio Code | Editor kode |
| 10 | Pakasir (sandbox) | Payment gateway untuk pembayaran QRIS/transfer |
| 11 | Web browser (Chrome) | Mengakses panel web admin |

**Library/paket pendukung utama:**
- Backend: `laravel/framework`, `laravel/sanctum`, `laravel/tinker`.
- Mobile: `http`, `shared_preferences`, `qr_flutter`, `mobile_scanner`, `flutter_map`, `latlong2`,
  `geolocator`, `geocoding`, `image_picker`, `google_fonts`, `url_launcher`, `permission_handler`,
  `intl`.

---

## 4.2 Implementasi Sistem

Bagian ini menampilkan hasil implementasi antarmuka sistem beserta penjelasan fungsi tiap halaman.
Untuk setiap halaman, sertakan tangkapan layar (screenshot), lalu tuliskan penjelasannya.

> **Cara memakai bagian ini:** ambil screenshot tiap halaman dari aplikasi/panel yang berjalan, lalu
> tempel di atas penjelasan masing-masing. Penjelasan fungsi sudah disiapkan di bawah, tinggal
> disesuaikan.

### 4.2.1 Implementasi Antarmuka Panel Admin (Web)

**Gambar 4.x — Halaman Login Admin**
*[Sisipkan screenshot halaman login admin]*
Halaman login admin merupakan pintu masuk pengelola ke panel administrasi. Admin memasukkan email dan
kata sandi. Jika benar, admin diarahkan ke halaman dasbor. Halaman ini memastikan hanya pengguna
berperan admin yang dapat mengakses panel.

**Gambar 4.x — Halaman Dasbor Admin**
*[Sisipkan screenshot dasbor]*
Halaman dasbor menampilkan ringkasan operasional seperti jumlah pemesanan, ringkasan keuangan, dan
jadwal terdekat. Halaman ini berfungsi sebagai pusat pemantauan cepat bagi admin.

**Gambar 4.x — Halaman Kelola Rute**
*[Sisipkan screenshot]*
Halaman ini digunakan admin untuk menambah, mengubah, dan menghapus data rute perjalanan lintas kota
beserta harga tiketnya. Yang perlu dilakukan admin: mengisi kota asal, kota tujuan, estimasi durasi,
dan harga tiket.

**Gambar 4.x — Halaman Kelola Armada Kendaraan**
*[Sisipkan screenshot]*
Halaman ini digunakan untuk mengelola data kendaraan/armada, meliputi nomor plat, merek, kapasitas
kursi, dan foto. Data kapasitas kursi digunakan untuk membentuk denah kursi saat pembuatan jadwal.

**Gambar 4.x — Halaman Kelola Pengemudi**
*[Sisipkan screenshot]*
Halaman ini digunakan admin untuk membuat dan mengelola akun pengemudi beserta data nomor SIM. Akun
pengemudi dibuat oleh admin, bukan mendaftar sendiri.

**Gambar 4.x — Halaman Kelola Jadwal Perjalanan**
*[Sisipkan screenshot]*
Halaman ini menggabungkan rute, kendaraan, pengemudi, dan waktu keberangkatan menjadi satu jadwal.
Saat jadwal dibuat, sistem otomatis menghasilkan kursi sesuai kapasitas kendaraan dan menghitung
estimasi waktu tiba berdasarkan durasi rute.

**Gambar 4.x — Halaman Kelola Pemesanan**
*[Sisipkan screenshot]*
Halaman ini menampilkan daftar seluruh pemesanan yang masuk beserta statusnya. Admin dapat melihat
detail tiap pemesanan, termasuk data penumpang dan status pembayaran.

**Gambar 4.x — Halaman Pembayaran**
*[Sisipkan screenshot]*
Halaman ini menampilkan data transaksi pembayaran. Admin dapat memantau status pembayaran dan
mengonfirmasi pembayaran tunai bila diperlukan.

**Gambar 4.x — Halaman Biaya Operasional**
*[Sisipkan screenshot]*
Halaman ini mencatat pengeluaran operasional tiap perjalanan (misalnya bahan bakar dan tol) beserta
bukti. Berfungsi untuk pelaporan keuangan operasional.

**Gambar 4.x — Halaman Laporan (Pemesanan & Transaksi)**
*[Sisipkan screenshot]*
Halaman laporan menampilkan rekap pemesanan dan transaksi yang dapat difilter berdasarkan tanggal dan
status, serta dapat diekspor ke format CSV.

**Gambar 4.x — Halaman Pengaturan Sistem**
*[Sisipkan screenshot]*
Halaman ini digunakan admin untuk mengubah pengaturan seperti nama aplikasi, logo, aturan pemesanan
(batas kursi, durasi kunci kursi), metode pembayaran yang aktif, serta banner beranda.

### 4.2.2 Implementasi Antarmuka Aplikasi Mobile

**Gambar 4.x — Halaman Splash**
*[Sisipkan screenshot]*
Halaman pembuka yang tampil saat aplikasi dijalankan sambil memuat sesi pengguna.

**Gambar 4.x — Halaman Login & Registrasi**
*[Sisipkan screenshot]*
Halaman ini digunakan untuk masuk atau mendaftar akun. Registrasi memerlukan verifikasi email melalui
kode OTP. Akun yang dibuat melalui aplikasi mobile selalu berperan sebagai penumpang.

**Gambar 4.x — Halaman Beranda**
*[Sisipkan screenshot]*
Halaman utama setelah masuk. Tampilan beranda menyesuaikan peran pengguna (penumpang atau pengemudi).
Terdapat navigasi bawah untuk berpindah antarmenu.

**Gambar 4.x — Halaman Daftar & Detail Jadwal**
*[Sisipkan screenshot]*
Menampilkan daftar jadwal keberangkatan beserta detailnya, termasuk denah kursi yang dapat dipilih
penumpang. Ketika penumpang memilih kursi, kursi dikunci sementara agar tidak dipilih pengguna lain.

**Gambar 4.x — Halaman Form Pemesanan & Pembayaran**
*[Sisipkan screenshot]*
Halaman ini digunakan penumpang untuk mengisi data penumpang tiap kursi, menentukan titik jemput dan
titik turun melalui peta, mencatat barang bawaan, memilih metode pembayaran, dan menyelesaikan
pemesanan.

**Gambar 4.x — Halaman Detail Pemesanan**
*[Sisipkan screenshot]*
Menampilkan ringkasan pemesanan, status pembayaran, dan tombol untuk membuka tiket.

**Gambar 4.x — Halaman Tiket QR**
*[Sisipkan screenshot]*
Menampilkan tiket elektronik ber-QR code untuk setiap kursi. QR hanya aktif setelah pembayaran
berhasil, dan digunakan pengemudi untuk memvalidasi penumpang.

**Gambar 4.x — Halaman Beranda & Manifest Pengemudi**
*[Sisipkan screenshot]*
Pengemudi dapat melihat perjalanan yang ditugaskan dan daftar penumpang (manifest). Pengemudi
memindai QR tiket untuk validasi, menandai penumpang dijemput/turun, dan mengonfirmasi pembayaran
tunai.

---

## 4.3 Implementasi Database

Basis data sistem diimplementasikan menggunakan MySQL dengan nama database `aplikasi_travel`.
Struktur tabel dibuat menggunakan fitur *migration* pada Laravel, sehingga struktur tabel
terdefinisi dalam kode dan dapat dibentuk secara otomatis melalui perintah `php artisan migrate`.
Akses data dilakukan menggunakan Eloquent ORM, yaitu setiap tabel diwakili oleh sebuah kelas Model.

Basis data terdiri atas beberapa kelompok tabel, yaitu tabel akun pengguna, tabel master operasional
(rute, kendaraan, pengemudi, jadwal, kursi), tabel transaksi (pemesanan, detail pemesanan, bagasi,
pembayaran, tiket elektronik), serta tabel pendukung (notifikasi, biaya operasional, pengaturan
sistem, banner).

> **Sisipkan di sini:** gambar **ERD (Entity Relationship Diagram)**. Kamu bisa menggambarnya ulang
> dari diagram relasi pada modul `03_DATABASE_DAN_MODEL.md` bagian 3.4 menggunakan draw.io atau
> dbdiagram.io.

Berikut penjelasan struktur tabel utama (struktur lengkap tiap tabel tersedia dan dapat ditampilkan
dalam bentuk tabel "Nama Field | Tipe Data | Keterangan"; salin dari modul `03` bagian 3.3):

**Tabel `pengguna`** — menyimpan seluruh akun pengguna (penumpang, pengemudi, admin) beserta perannya.

**Tabel `rute`** — menyimpan data trayek antar kota beserta harga tiket.

**Tabel `kendaraan`** — menyimpan data armada beserta kapasitas kursi.

**Tabel `pengemudi`** — menyimpan data pengemudi, terhubung ke tabel `pengguna`.

**Tabel `jadwal`** — menyimpan jadwal keberangkatan yang merupakan gabungan rute, kendaraan, dan
pengemudi pada waktu tertentu.

**Tabel `kursi_jadwal`** — menyimpan data kursi untuk tiap jadwal beserta status (tersedia, terkunci,
dipesan).

**Tabel `pemesanan`** — menyimpan data pemesanan/booking beserta titik jemput, titik turun, dan total
harga.

**Tabel `detail_pemesanan`** — menyimpan data tiap penumpang dalam satu pemesanan.

**Tabel `pembayaran`** — menyimpan data transaksi pembayaran beserta status dan metodenya.

**Tabel `tiket_elektronik`** — menyimpan tiket ber-QR untuk tiap penumpang.

Relasi antar tabel diimplementasikan menggunakan *foreign key*. Sebagai contoh, satu `pengguna`
dapat memiliki banyak `pemesanan`, satu `jadwal` memiliki banyak `kursi_jadwal` dan `pemesanan`,
serta satu `pemesanan` memiliki banyak `detail_pemesanan` dan satu `pembayaran`.

Hubungan satu banding satu opsional diperkuat dengan unique constraint pada
`pengemudi.pengguna_id`, `pembayaran.pemesanan_id`, `detail_pemesanan.kursi_jadwal_id`, dan
`tiket_elektronik.detail_pemesanan_id`. Tabel `kursi_jadwal` juga memiliki unique constraint
gabungan pada `(jadwal_id, nomor_kursi)`, sehingga nomor kursi tidak dapat berulang dalam jadwal
yang sama tetapi dapat digunakan pada jadwal berbeda. Relasi pencatat biaya menggunakan
`created_by` yang nullable dengan `ON DELETE SET NULL`, sehingga satu biaya memiliki nol atau satu
pencatat dan histori biaya tetap tersimpan bila akun pencatat dihapus.

> **Catatan implementasi (dapat ditulis sebagai perbedaan rancangan dan implementasi):** pada tahap
> pengembangan, terdapat penyederhanaan sistem di mana kolom biaya bagasi dan diskon pada tabel
> pemesanan dihapus karena fitur tersebut tidak digunakan, sehingga total harga pemesanan sama dengan
> subtotal harga tiket.

---

## 4.4 Pengujian Sistem

Pengujian sistem dilakukan untuk memastikan seluruh fungsi berjalan sesuai yang diharapkan. Metode
pengujian yang digunakan adalah **Black Box Testing**, yaitu pengujian yang berfokus pada
fungsionalitas sistem tanpa melihat struktur kode internal. Pengujian dilakukan dengan memberikan
masukan tertentu lalu mengamati apakah keluaran sesuai dengan yang diharapkan.

> **Tambahan pendukung (opsional untuk laporan):** selain pengujian black box manual, sistem juga
> dilengkapi pengujian otomatis (automated test) pada sisi backend sebanyak **194 pengujian** yang
> seluruhnya berhasil (lulus). Ini dapat disebutkan sebagai bukti kestabilan sistem.

### 4.4.1 Black Box Testing

Berikut hasil pengujian black box terhadap fungsi-fungsi utama sistem.

**Tabel 4.x — Pengujian Fitur Autentikasi**

| No | Skenario Pengujian | Masukan | Hasil yang Diharapkan | Hasil | Ket |
|----|--------------------|---------|-----------------------|-------|-----|
| 1 | Registrasi dengan data valid | Nama, email, no HP, password valid | Akun dibuat, kode OTP terkirim | Sesuai | Valid |
| 2 | Registrasi email sudah terdaftar | Email yang sudah ada | Muncul pesan email sudah terdaftar | Sesuai | Valid |
| 3 | Login dengan data benar | Email & password benar | Berhasil masuk, token diberikan | Sesuai | Valid |
| 4 | Login dengan password salah | Password salah | Muncul pesan error | Sesuai | Valid |
| 5 | Login akun admin di mobile | Akun admin | Ditolak dengan pesan admin lewat web | Sesuai | Valid |

**Tabel 4.x — Pengujian Fitur Jadwal & Rute (Admin)**

| No | Skenario Pengujian | Masukan | Hasil yang Diharapkan | Hasil | Ket |
|----|--------------------|---------|-----------------------|-------|-----|
| 1 | Tambah rute baru | Data rute lengkap | Rute tersimpan & tampil di daftar | Sesuai | Valid |
| 2 | Tambah rute data kosong | Form kosong | Muncul pesan validasi wajib diisi | Sesuai | Valid |
| 3 | Buat jadwal baru | Rute, armada, pengemudi, waktu | Jadwal tersimpan, kursi terbentuk otomatis | Sesuai | Valid |
| 4 | Jadwal mendekati keberangkatan | Waktu < 1 jam | Status berubah otomatis jadi ditutup | Sesuai | Valid |

**Tabel 4.x — Pengujian Fitur Pemesanan (Penumpang)**

| No | Skenario Pengujian | Masukan | Hasil yang Diharapkan | Hasil | Ket |
|----|--------------------|---------|-----------------------|-------|-----|
| 1 | Melihat jadwal sebagai tamu | Membuka daftar jadwal | Jadwal tampil tanpa perlu login | Sesuai | Valid |
| 2 | Memilih kursi | Pilih kursi tersedia | Kursi terkunci sementara | Sesuai | Valid |
| 3 | Dua pengguna pilih kursi sama | Kursi yang sama | Hanya satu yang berhasil | Sesuai | Valid |
| 4 | Membuat pemesanan | Data penumpang & titik jemput/turun | Pemesanan dibuat, status menunggu pembayaran | Sesuai | Valid |
| 5 | Memesan tanpa titik jemput/turun | Form tidak lengkap | Muncul pesan validasi | Sesuai | Valid |

**Tabel 4.x — Pengujian Fitur Pembayaran & Tiket**

| No | Skenario Pengujian | Masukan | Hasil yang Diharapkan | Hasil | Ket |
|----|--------------------|---------|-----------------------|-------|-----|
| 1 | Pembayaran berhasil | Pembayaran diselesaikan | Status jadi terbayar, tiket QR terbit | Sesuai | Valid |
| 2 | Pembayaran kedaluwarsa | Melewati batas waktu | Pemesanan dibatalkan, kursi dilepas | Sesuai | Valid |
| 3 | Melihat tiket sebelum bayar | Buka tiket | QR belum aktif, muncul keterangan | Sesuai | Valid |

**Tabel 4.x — Pengujian Fitur Pengemudi**

| No | Skenario Pengujian | Masukan | Hasil yang Diharapkan | Hasil | Ket |
|----|--------------------|---------|-----------------------|-------|-----|
| 1 | Melihat manifest | Buka perjalanan | Daftar penumpang tampil | Sesuai | Valid |
| 2 | Scan QR tiket valid | QR penumpang | Tiket tervalidasi, penumpang hadir | Sesuai | Valid |
| 3 | Scan QR yang sama dua kali | QR sudah dipindai | Ditolak dengan pesan sudah dipindai | Sesuai | Valid |
| 4 | Scan QR trip lain | QR bukan miliknya | Ditolak | Sesuai | Valid |
| 5 | Konfirmasi pembayaran tunai | Penumpang bayar tunai | Pembayaran terkonfirmasi, tiket terbit | Sesuai | Valid |

> *Sesuaikan isi tabel dengan hasil pengujian yang benar-benar kamu lakukan. Tambah/kurangi skenario
> sesuai kebutuhan. Kolom "Hasil" umumnya diisi "Sesuai" jika keluaran sesuai harapan.*

---

# BAB V — PENUTUP

## 5.1 Kesimpulan

Berdasarkan hasil perancangan, pembangunan, dan pengujian sistem, dapat disimpulkan bahwa:

1. Aplikasi Pemesanan Jasa Transportasi Umum Lintas Kota pada Rusly Travel berbasis mobile telah
   berhasil dibangun menggunakan framework Flutter pada sisi aplikasi mobile dan framework Laravel
   pada sisi backend, dengan basis data MySQL.

2. Sistem berhasil memfasilitasi empat peran pengguna, yaitu tamu, penumpang, pengemudi, dan admin,
   dengan hak akses dan fungsi masing-masing yang telah berjalan sesuai rancangan.

3. Sistem mampu menangani alur pemesanan tiket secara menyeluruh, mulai dari melihat jadwal, memilih
   kursi dengan mekanisme penguncian kursi sementara, mengisi data penumpang dan titik jemput/turun,
   melakukan pembayaran (QRIS, transfer, atau tunai), hingga penerbitan tiket elektronik ber-QR code.

4. Sistem menyediakan fitur bagi pengemudi untuk melihat daftar penumpang (manifest) dan memvalidasi
   tiket melalui pemindaian QR code, sehingga proses verifikasi penumpang menjadi lebih praktis.

5. Panel web admin menyediakan pengelolaan data operasional secara terpusat, meliputi rute, armada,
   pengemudi, jadwal, pemesanan, pembayaran, biaya operasional, laporan, dan pengaturan sistem.

6. Hasil pengujian black box menunjukkan bahwa seluruh fungsi utama sistem berjalan sesuai dengan
   yang diharapkan.

## 5.2 Saran

Untuk pengembangan aplikasi di masa mendatang, disarankan beberapa hal berikut:

1. **Integrasi pembayaran mode produksi.** Saat ini pembayaran menggunakan payment gateway Pakasir
   dalam mode uji coba (sandbox). Untuk penggunaan nyata, perlu diaktifkan mode produksi beserta
   verifikasi keaslian notifikasi pembayaran (signature/tanda tangan digital) agar lebih aman.

2. **Penambahan notifikasi push.** Sistem dapat dikembangkan dengan notifikasi push (misalnya melalui
   Firebase Cloud Messaging) agar pengguna menerima pemberitahuan langsung di perangkat, karena saat
   ini kolom token push telah disiapkan namun fitur pengiriman belum diimplementasikan.

3. **Pelacakan posisi kendaraan secara langsung (real-time tracking).** Penumpang dapat diberi fitur
   memantau posisi kendaraan secara langsung untuk meningkatkan kenyamanan.

4. **Pengembangan versi iOS dan optimasi lintas perangkat.** Karena dibangun dengan Flutter, aplikasi
   berpotensi dikembangkan untuk platform iOS dengan penyesuaian lebih lanjut.

5. **Penambahan fitur ulasan dan penilaian.** Penumpang dapat memberikan ulasan terhadap perjalanan
   dan pengemudi sebagai bahan evaluasi peningkatan layanan.

6. **Peningkatan fitur laporan.** Laporan dapat dikembangkan dengan visualisasi grafik yang lebih
   lengkap serta ekspor ke format lain seperti PDF dan Excel.

7. **Manajemen perawatan armada.** Data perawatan kendaraan (kilometer servis, pajak tahunan) yang
   telah tersedia dapat dikembangkan menjadi fitur pengingat servis dan pajak otomatis.

---

> **Pengingat:** BAB IV dan BAB V di atas adalah draft. Sesuaikan bahasa dengan gaya penulisan
> kampusmu, tambahkan nomor gambar/tabel yang benar, dan sisipkan screenshot serta ERD. Pastikan
> setiap klaim di kesimpulan memang sudah kamu buktikan di BAB IV.
