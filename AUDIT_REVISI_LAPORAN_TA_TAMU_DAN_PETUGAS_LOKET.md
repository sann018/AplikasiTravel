# Audit Revisi Laporan TA: Alur Tamu dan Pemesanan oleh Petugas Loket

## 1. Tujuan dan Ruang Lingkup Audit

Dokumen ini merupakan hasil pencocokan antara:

1. laporan Tugas Akhir `TA_Ihsan Al Husna_Revisi - Copy.docx`;
2. implementasi backend Laravel dan aplikasi Flutter RuslyTravel per 11 Agustus 2026;
3. migration yang menambahkan dukungan pemesanan loket;
4. pengujian otomatis khusus Petugas Loket; dan
5. kaidah umum penyusunan *use case diagram*.

File DOCX asli tidak diubah. Seluruh saran, teks pengganti, serta koreksi diagram disusun dalam file pendamping ini agar dapat diperiksa terlebih dahulu sebelum dipindahkan ke laporan.

## 2. Kesimpulan Utama

| Bagian yang Diperiksa | Status | Kesimpulan |
|---|---|---|
| Alur Tamu pada aplikasi | Sudah diperbaiki | Tamu dapat melihat informasi publik tanpa autentikasi. Ketika hendak memesan, pengguna diarahkan untuk mendaftar atau masuk sebagai Penumpang. Tidak ada akun dengan peran Tamu. |
| Istilah pada antarmuka Tamu | Sudah diperbaiki | Antarmuka menggunakan keterangan seperti `Mode Tamu`, `Daftar atau Masuk untuk Memesan`, dan `Masuk untuk Memesan`. |
| Narasi Tamu pada laporan | Belum sepenuhnya diperbaiki | Narasi Use Case Umum dan Use Case Tamu masih menyebut “Tamu ... masuk ke dalam sistem”. Kalimat ini masih dapat menimbulkan pertanyaan “Tamu masuk untuk apa?”. |
| Solusi pemesanan langsung, telepon, dan kondisi mendesak | Sudah diimplementasikan | Solusinya adalah akun **Petugas Loket** pada aplikasi mobile, bukan memakai akun Admin Web dan bukan mewajibkan calon Penumpang membuat akun. |
| Pengelolaan akun Petugas Loket | Sudah diimplementasikan | Admin membuat, mengaktifkan, menonaktifkan, mengubah, dan memantau akun Petugas Loket melalui web admin. |
| Pemesanan loket tanpa akun Penumpang | Sudah diimplementasikan | Kolom `pengguna_id` pada pemesanan boleh kosong. Identitas kontak pemesan disimpan sebagai snapshot dan petugas pencatat disimpan pada `dibuat_oleh`. |
| Use case pada laporan | Belum sesuai implementasi | Diagram masih memiliki empat aktor dan belum menampilkan Petugas Loket serta fungsi Admin untuk mengelola akun Petugas Loket. |
| Proses bisnis pada laporan | Belum sesuai implementasi | Diagram pemesanan belum memiliki cabang pemesanan mandiri dan pemesanan berbantuan Petugas Loket. Diagram pembayaran belum mencantumkan konfirmasi tunai oleh Petugas Loket. |
| ERD dan rancangan tabel | Belum sesuai implementasi | ERD belum memuat relasi pencatat pemesanan, kepemilikan akun Penumpang yang bersifat opsional, atribut sumber pemesanan, dan peran `petugas_loket`. |
| Gambar implementasi basis data | Harus diganti | Gambar relasi basis data pada laporan masih memperlihatkan struktur lama sebelum penambahan kolom pemesanan loket. |
| Pengujian Black Box pada laporan | Harus ditambah | Angka 32 skenario belum mencakup modul Petugas Loket. Jumlah hasil akhir tidak boleh diubah sebelum skenario tambahan benar-benar diuji dan dicatat. |

Kesimpulannya, revisi pada aplikasi sudah tersedia dan pengujian otomatis terfokus berhasil. Namun, laporan belum sinkron dengan kondisi tersebut. Oleh karena itu, laporan belum aman dinyatakan selesai sebelum seluruh bagian pada daftar revisi di dokumen ini diperbarui.

## 3. Bukti Implementasi pada Project

### 3.1 Pemisahan Tamu dan Pengguna Terautentikasi

Pada implementasi saat ini, Tamu bukan jenis akun. Tamu adalah kondisi ketika aplikasi digunakan tanpa sesi autentikasi. Tamu memperoleh akses baca terhadap beranda, rute, jadwal, dan detail jadwal. Tindakan pemesanan mengarahkan Tamu ke halaman autentikasi.

Bukti utama:

- `frontend/lib/features/home/bagian_beranda/profil.dart` menampilkan `Mode Tamu` dan satu aksi `Daftar atau Masuk untuk Memesan`;
- `frontend/lib/features/schedule/bagian_detail_jadwal/peta_kursi.dart` menggunakan label `Masuk untuk Memesan`;
- pengujian Flutter memastikan profil Tamu hanya mempunyai satu aksi autentikasi dan tidak menampilkan tombol keluar yang berulang.

Makna untuk laporan:

> Tamu tidak melakukan proses masuk sebagai Tamu. Tamu hanya mengakses informasi publik. Proses masuk digunakan oleh pemilik akun terdaftar untuk memperoleh hak akses sebagai Penumpang, Pengemudi, atau Petugas Loket.

### 3.2 Peran Petugas Loket

Peran pengguna pada sistem menjadi:

```text
penumpang
pengemudi
petugas_loket
admin
```

Admin tetap menggunakan web admin. Login mobile hanya menerima Penumpang, Pengemudi, dan Petugas Loket. Token Petugas Loket dibatasi dengan kemampuan `mobile-counter`, sehingga akun tersebut tidak memperoleh hak akses Admin Web.

Bukti utama:

- `backend/app/Http/Controllers/Api/AuthController.php` memisahkan kemampuan token berdasarkan peran;
- `backend/routes/api.php` melindungi prefix `/api/petugas-loket` dengan autentikasi, peran, dan kemampuan token;
- `frontend/lib/core/models/travs_user_session.dart` memetakan `petugas_loket` menjadi mode Petugas Loket;
- `frontend/lib/features/home/travs_home_page.dart` menggunakan komponen Penumpang yang sama dengan konteks dan data Petugas Loket.

### 3.3 Pemesanan Langsung, Telepon, dan Kondisi Mendesak

Alur yang telah diimplementasikan adalah:

```text
Calon Penumpang datang ke loket atau memesan melalui telepon
                            ↓
Petugas Loket masuk memakai akun internal pada perangkat Rusly Travel
                            ↓
Petugas mencari akun Penumpang jika calon Penumpang sudah memiliki akun
                            ↓
Akun ditemukan? ── Ya ──> pemesanan dapat dihubungkan ke akun tersebut
        │
        Tidak
        ↓
Pemesanan dilanjutkan tanpa akun Penumpang
                            ↓
Petugas memilih jadwal dan mengunci kursi
                            ↓
Petugas mengisi kontak pemesan, data Penumpang, lokasi, dan barang bawaan
                            ↓
Petugas memilih pembayaran tunai atau digital
                            ↓
Pembayaran berhasil dikonfirmasi
                            ↓
Sistem menerbitkan satu tiket QR untuk setiap Penumpang
```

Solusi ini tepat untuk kasus mendesak karena calon Penumpang tidak diwajibkan memasang aplikasi, membuat akun, atau memahami proses aplikasi terlebih dahulu. Walaupun dibantu Petugas Loket, transaksi tetap memakai `BookingService`, validasi jadwal, penguncian kursi, perhitungan harga, pembayaran, dan penerbitan tiket yang sama dengan pemesanan mandiri.

### 3.4 Pemisahan Pemilik dan Pencatat Pemesanan

Pemesanan mandiri:

```text
pengguna_id       = ID akun Penumpang
dibuat_oleh       = null
sumber_pemesanan  = aplikasi_penumpang
```

Pemesanan loket yang dihubungkan ke akun Penumpang:

```text
pengguna_id       = ID akun Penumpang
dibuat_oleh       = ID Petugas Loket
sumber_pemesanan  = loket
```

Pemesanan loket tanpa akun Penumpang:

```text
pengguna_id       = null
dibuat_oleh       = ID Petugas Loket
sumber_pemesanan  = loket
```

Pemisahan tersebut mencegah ID Petugas Loket disimpan sebagai pemilik perjalanan. `pengguna_id` menunjukkan akun Penumpang yang terkait, sedangkan `dibuat_oleh` menunjukkan siapa yang mencatat transaksi.

### 3.5 Hasil Pengujian Otomatis Terfokus

Pengujian yang dijalankan pada audit ini menghasilkan:

| Lingkungan | Hasil |
|---|---|
| Laravel `PetugasLoketBookingTest` dan `PetugasLoketManagementTest` | 10 test lulus, 38 assertion |
| Flutter `travs_counter_booking_source_test.dart` dan `travs_home_ui_simplification_test.dart` | 18 test lulus |

Pengujian tersebut membuktikan antara lain:

- Petugas dapat membuat pemesanan tanpa akun Penumpang;
- pemesanan dapat dihubungkan ke akun Penumpang yang sudah ada;
- Petugas tidak dapat membuka pemesanan milik Petugas lain;
- token Petugas tidak dapat menggunakan endpoint Penumpang;
- pencarian akun Penumpang harus menggunakan email atau nomor HP lengkap;
- konfirmasi pembayaran tunai bersifat idempoten;
- Admin dapat membuat akun Petugas dan mengirim tautan aktivasi;
- Petugas tidak dapat membuka halaman pengelolaan web;
- penonaktifan akun mencabut token mobile; dan
- Flutter menampilkan konteks Petugas Loket dengan komponen Penumpang yang sama.

Hasil tersebut merupakan pengujian otomatis kode, bukan pengganti pengujian Black Box manual yang dicantumkan pada laporan.

## 4. Audit Use Case

### 4.1 Masalah pada Diagram dan Narasi Sekarang

Diagram umum pada laporan sudah tidak menghubungkan aktor Tamu langsung dengan use case `Masuk`. Hal tersebut merupakan perbaikan yang benar. Namun, masih terdapat dua narasi yang bertentangan dengan gambar:

1. narasi Use Case Umum menyatakan bahwa Tamu dapat melakukan pendaftaran **atau masuk ke dalam sistem**;
2. narasi Use Case Tamu dan Penumpang menyatakan bahwa Tamu dapat melihat informasi publik, mencari jadwal, melakukan pendaftaran, **serta masuk ke dalam sistem**.

Kedua kalimat tersebut perlu diganti. Pertanyaan dosen muncul bukan hanya dari garis pada diagram, tetapi juga dari penggunaan kata `Masuk` yang tidak membedakan kondisi Tamu dengan autentikasi akun terdaftar.

Selain itu, diagram saat ini belum menampilkan aktor Petugas Loket. Akibatnya, solusi untuk pemesanan langsung dan telepon tidak memiliki representasi pada rancangan kebutuhan, walaupun fiturnya sudah ada pada aplikasi.

Secara metodologis, use case digunakan untuk menunjukkan fungsi utama yang disediakan sistem dan interaksi antara fungsi tersebut dengan pengguna di luar sistem. Oleh karena itu, aktor harus menggambarkan peran yang benar-benar berinteraksi dengan fungsi, bukan sekadar label halaman. Prinsip ini sejalan dengan pembahasan Aquino, de Saqui-Sannes, dan Vingerhoeds mengenai kebutuhan abstraksi dan pengorganisasian aktor serta fungsi pada use case diagram ([Consensus, 2021](https://consensus.app/papers/a-methodological-assistant-for-uml-and-sysml-use-case-aquino-saqui-sannes/a7d1b527c5615de181b28bb0d275c0f8/?utm_source=chatgpt)).

### 4.2 Aktor Final yang Disarankan

| Aktor | Status Autentikasi | Platform | Tanggung Jawab Utama |
|---|---|---|---|
| Tamu | Tidak terautentikasi | Mobile | Melihat informasi publik dan memulai pendaftaran akun Penumpang. |
| Penumpang | Terautentikasi | Mobile | Membuat dan mengelola pemesanan mandiri miliknya. |
| Petugas Loket | Terautentikasi | Mobile | Membantu membuat pemesanan bagi calon Penumpang yang datang langsung atau memesan melalui telepon. |
| Pengemudi | Terautentikasi | Mobile | Menjalankan operasional perjalanan dan memverifikasi Penumpang. |
| Admin | Terautentikasi | Web | Mengelola data, akun internal, transaksi, pengaturan, dan laporan. |

### 4.3 Perubahan Use Case Diagram Umum

Diagram Umum harus memiliki lima aktor. Tambahkan aktor **Petugas Loket** dengan hubungan ke fungsi ringkas berikut:

- Masuk ke Akun Petugas Loket;
- Pemesanan Berbantuan Petugas dan Penguncian Kursi;
- Pemilihan dan Pemeriksaan Pembayaran;
- Konfirmasi Pembayaran Tunai;
- Tiket dan Riwayat Pemesanan Loket.

Pada aktor Admin tambahkan:

- Kelola Akun Petugas Loket.

Perbaikan istilah:

- ganti use case umum `Masuk` menjadi `Masuk ke Akun Terdaftar` apabila satu oval tetap digunakan untuk seluruh peran;
- jangan hubungkan Tamu dengan `Masuk ke Akun Terdaftar`;
- hubungkan Tamu hanya dengan `Akses Informasi Publik` dan `Daftar Akun Penumpang`;
- `Daftar Akun Penumpang` tetap `include` terhadap `Verifikasi Email dengan OTP` pada diagram terperinci.

### 4.4 Diagram Tambahan yang Disarankan

Tambahkan satu diagram baru bernama **Use Case Petugas Loket**, sehingga penyajian menjadi:

1. Use Case Diagram Umum;
2. Use Case Diagram Tamu dan Penumpang;
3. Use Case Diagram Petugas Loket;
4. Use Case Diagram Pengemudi;
5. Use Case Diagram Admin.

Use Case Petugas Loket memuat:

- Masuk;
- Lihat Jadwal;
- Cari Akun Penumpang;
- Buat Pemesanan Loket;
- Pilih dan Kunci Kursi;
- Isi Data Pemesan dan Penumpang;
- Tentukan Titik Jemput dan Titik Turun;
- Catat Barang Bawaan;
- Pilih Metode Pembayaran;
- Cek Status Pembayaran Digital;
- Konfirmasi Pembayaran Tunai;
- Lihat Detail Pemesanan;
- Lihat dan Unduh Tiket;
- Lihat Riwayat Pemesanan Loket; dan
- Kelola Profil dan Kata Sandi.

Hubungan yang disarankan:

```text
Buat Pemesanan Loket
  <<include>> Cari dan Pilih Jadwal
  <<include>> Pilih dan Kunci Kursi
  <<include>> Isi Data Pemesan dan Penumpang
  <<include>> Tentukan Titik Jemput dan Titik Turun

Catat Barang Bawaan
  <<extend>> Buat Pemesanan Loket

Pilih Metode Pembayaran
  diperluas oleh Pembayaran Digital atau Pembayaran Tunai
```

### 4.5 Narasi Pengganti Use Case Umum

> Use Case Diagram Umum menggambarkan hubungan antara lima aktor, yaitu Tamu, Penumpang, Petugas Loket, Pengemudi, dan Admin, dengan fungsi utama pada sistem. Tamu merupakan pengguna yang belum terautentikasi dan hanya dapat melihat informasi publik serta melakukan pendaftaran akun Penumpang. Setelah melakukan autentikasi, Penumpang dapat mencari jadwal, memilih dan mengunci kursi, membuat pemesanan, memilih metode pembayaran, memperoleh tiket elektronik, serta melihat riwayat pemesanan dan notifikasi. Petugas Loket menggunakan aplikasi mobile untuk membuat pemesanan berbantuan bagi calon Penumpang yang datang langsung atau memesan melalui telepon, termasuk memilih kursi, mengisi data, memproses pembayaran, dan memperoleh tiket. Pengemudi mengakses jadwal tugas, manifes Penumpang, verifikasi tiket, pembayaran tunai, dan operasional perjalanan. Admin menggunakan aplikasi web untuk mengelola data utama, jadwal, akun Pengemudi dan Petugas Loket, pemesanan, pembayaran, biaya operasional, notifikasi, pengaturan sistem, dan laporan. Pembagian tersebut menunjukkan bahwa setiap aktor memperoleh fungsi sesuai peran dan kewenangannya.

### 4.6 Narasi Pengganti Use Case Tamu dan Penumpang

> Use Case Diagram Tamu dan Penumpang menggambarkan perbedaan fungsi antara pengguna yang belum terautentikasi dan pemilik akun Penumpang. Tamu dapat melihat informasi publik dan melakukan pendaftaran akun Penumpang. Pendaftaran dilanjutkan dengan verifikasi alamat email menggunakan kode OTP. Tamu tidak melakukan proses masuk sebagai Tamu. Setelah pemilik akun terverifikasi melakukan autentikasi, sistem memberikan hak akses Penumpang. Penumpang dapat mencari dan memilih jadwal, mengunci kursi sementara, mengisi data Penumpang, menentukan titik jemput dan titik turun, serta mencatat barang bawaan apabila diperlukan. Setelah pemesanan terbentuk, Penumpang dapat memilih pembayaran melalui QRIS, Virtual Account, atau tunai, memeriksa status pembayaran digital, melihat riwayat dan detail pemesanan, menerima notifikasi, serta melihat dan mengunduh tiket elektronik berbasis QR Code.

### 4.7 Narasi Baru Use Case Petugas Loket

> Use Case Diagram Petugas Loket menggambarkan fungsi pelayanan pemesanan berbantuan melalui aplikasi mobile. Petugas Loket masuk menggunakan akun internal yang dibuat dan dikelola oleh Admin. Petugas dapat mencari akun Penumpang berdasarkan email atau nomor HP. Apabila akun ditemukan, pemesanan dapat dihubungkan dengan akun tersebut; apabila tidak ditemukan, pemesanan tetap dapat dilanjutkan tanpa membuat akun Penumpang. Petugas kemudian mencari jadwal, memilih dan mengunci kursi, mengisi identitas pemesan serta Penumpang, menentukan titik jemput dan titik turun, mencatat barang bawaan, dan memilih metode pembayaran. Untuk pembayaran digital, Petugas dapat memeriksa status transaksi, sedangkan pembayaran tunai dapat dikonfirmasi setelah uang diterima. Setelah pembayaran berhasil, Petugas dapat membuka dan mengunduh tiket yang diterbitkan sistem. Akses Petugas dibatasi hanya pada pemesanan loket yang dibuat oleh akun tersebut.

### 4.8 Tambahan Narasi Use Case Admin

Tambahkan kalimat berikut pada uraian Admin:

> Admin juga mengelola akun Petugas Loket melalui fungsi tambah, ubah, aktivasi, penonaktifan, pengiriman ulang tautan aktivasi, serta pemantauan jumlah pemesanan yang dibuat oleh setiap Petugas. Admin tidak menggunakan akun yang sama pada aplikasi mobile, sehingga hak akses pengelolaan web tetap terpisah dari pelayanan pemesanan loket.

## 5. Audit Gambar Proses Bisnis

### 5.1 Gambar Proses Bisnis Pemesanan

Status gambar sekarang: **alur Penumpang mandiri sudah benar, tetapi belum memuat pemesanan berbantuan Petugas Loket**.

Perbaikan yang diperlukan:

1. tambahkan keputusan awal `Sumber pemesanan?`;
2. cabang `Aplikasi Penumpang` menggunakan akun Penumpang;
3. cabang `Loket/Telepon` dilakukan Petugas Loket;
4. pada cabang loket, tambahkan pencarian akun Penumpang yang bersifat opsional;
5. gabungkan kedua cabang sebelum proses memilih jadwal dan mengunci kursi;
6. setelah pemesanan disimpan, sistem mencatat sumber pemesanan dan identitas Petugas pencatat.

Alur gambar pengganti:

```text
Mulai
  ↓
Sumber pemesanan?
  ├─ Aplikasi Penumpang → autentikasi akun Penumpang
  └─ Loket/Telepon → autentikasi Petugas Loket
                         ↓
                   Cari akun Penumpang
                         ↓
               Ditemukan? ─ Ya → hubungkan akun
                         └ Tidak → lanjut tanpa akun
  ↓
Pilih rute, jadwal, dan kursi
  ↓
Sistem mengunci kursi sementara
  ↓
Isi data pemesan, data Penumpang, lokasi, dan barang bawaan jika ada
  ↓
Data valid dan waktu masih tersedia?
  ├─ Tidak → perbaiki data atau sesi berakhir
  └─ Ya
       ↓
Sistem menghitung harga dan membuat pemesanan
  ↓
Sistem menyimpan sumber pemesanan dan Petugas pencatat jika berasal dari loket
  ↓
Status pemesanan menunggu pembayaran
  ↓
Lanjut ke proses pembayaran
```

Narasi siap pakai:

> Gambar proses bisnis pemesanan menunjukkan dua sumber transaksi yang dilayani oleh sistem. Pemesanan mandiri dilakukan Penumpang melalui akun miliknya, sedangkan pemesanan langsung atau melalui telepon dibantu oleh Petugas Loket menggunakan akun internal pada perangkat Rusly Travel. Pada pemesanan loket, Petugas dapat mencari akun Penumpang yang sudah tersedia atau melanjutkan transaksi tanpa akun. Setelah perbedaan sumber transaksi ditentukan, kedua jalur menggunakan proses yang sama, yaitu memilih jadwal, mengunci kursi sementara, mengisi data pemesan dan Penumpang, menentukan titik jemput dan titik turun, serta mencatat barang bawaan apabila ada. Backend memvalidasi kepemilikan kunci kursi, batas waktu, dan kelengkapan data sebelum menghitung harga dan menyimpan pemesanan. Sistem juga menyimpan sumber transaksi dan identitas Petugas pencatat agar pemesanan loket dapat ditelusuri tanpa mengubah logika utama pemesanan.

### 5.2 Gambar Proses Bisnis Pembayaran

Status gambar sekarang: **alur digital dan tunai sudah tergambar, tetapi pelaksana konfirmasi tunai belum lengkap**.

Perbaikan yang diperlukan:

- ubah kotak `Pembayaran tunai kepada Pengemudi` menjadi `Pembayaran tunai kepada Petugas Loket atau Pengemudi`;
- ubah kotak `Pengemudi atau Admin mengonfirmasi pembayaran` menjadi `Petugas Loket, Pengemudi, atau Admin mengonfirmasi sesuai kewenangan`;
- pada cabang digital, tambahkan pemeriksaan melalui webhook dan/atau tombol `Cek Status Pembayaran`;
- tegaskan bahwa kegagalan atau kedaluwarsa digital melepaskan kursi, sedangkan tunai tidak memakai batas waktu provider setelah metode dipilih.

Narasi siap pakai:

> Gambar proses bisnis pembayaran dimulai ketika pemesanan berstatus menunggu pembayaran. Pengguna memilih metode digital berupa QRIS atau Virtual Account, atau pembayaran tunai. Pada pembayaran digital, backend membuat transaksi melalui Pakasir, menyimpan nominal transaksi, biaya admin provider, total pembayaran, nomor pembayaran, dan waktu kedaluwarsa sebagai snapshot. Status pembayaran diperbarui melalui webhook terverifikasi atau pemeriksaan manual menggunakan tombol Cek Status Pembayaran. Apabila pembayaran digital belum berhasil sampai batas efektif berakhir, pemesanan dibatalkan dan kursi dilepaskan. Pada metode tunai, sistem tidak memanggil Pakasir dan tidak menggunakan hitung mundur provider. Uang dapat diterima Petugas Loket ketika melayani transaksi atau Pengemudi saat penjemputan, kemudian pembayaran dikonfirmasi oleh Petugas Loket, Pengemudi yang ditugaskan, atau Admin sesuai kewenangan. Pembayaran yang berhasil mengubah status pemesanan menjadi terbayar dan memicu penerbitan tiket elektronik.

### 5.3 Gambar Proses Bisnis Penerbitan dan Verifikasi E-Ticket

Status gambar sekarang: **logika inti benar**, tetapi jalur penerimaan tiket loket belum disebutkan.

Perbaikan kecil yang diperlukan:

- ganti `Penumpang menunjukkan QR Code kepada Pengemudi` menjadi `Pemegang tiket menunjukkan QR Code kepada Pengemudi`;
- tambahkan catatan bahwa tiket pemesanan loket dapat dibuka atau diunduh oleh Petugas untuk diberikan kepada calon Penumpang;
- tetap pertahankan satu token QR unik untuk setiap `detail_pemesanan`.

Narasi siap pakai:

> Gambar proses bisnis penerbitan dan verifikasi tiket elektronik dimulai setelah pembayaran dinyatakan berhasil. Backend membuat satu data tiket untuk setiap detail Penumpang dan menghasilkan token QR unik. Pada pemesanan mandiri, tiket tersedia pada akun Penumpang. Pada pemesanan loket, tiket tersedia pada riwayat Petugas Loket sehingga dapat diunduh dan diberikan kepada calon Penumpang. Saat keberangkatan, Pengemudi memindai QR Code dan backend memeriksa keberadaan token, kesesuaian jadwal, penugasan Pengemudi, status pembayaran, serta riwayat pemindaian. Tiket yang valid ditandai sudah dipindai dan status kehadiran Penumpang diperbarui, sedangkan tiket yang tidak valid atau telah digunakan ditolak.

### 5.4 Gambar Proses Bisnis Operasional Perjalanan Pengemudi

Status gambar sekarang: **masih sesuai dan tidak memerlukan perubahan struktur besar**.

Penyesuaian narasi:

> Gambar proses bisnis operasional perjalanan menunjukkan urutan kegiatan Pengemudi pada jadwal yang ditugaskan. Pengemudi membuka detail perjalanan dan manifes, menuju titik penjemputan, serta memeriksa metode pembayaran setiap Penumpang. Untuk pemesanan tunai yang belum dibayar, Pengemudi dapat menerima dan mengonfirmasi pembayaran sebelum verifikasi kehadiran. Pengemudi kemudian memindai QR Code atau mencatat kehadiran sesuai aturan sistem, memulai perjalanan, menandai Penumpang yang telah turun, mencatat biaya operasional apabila ada, dan menyelesaikan perjalanan. Pemesanan mandiri dan pemesanan loket masuk ke manifes yang sama karena keduanya menggunakan jadwal, detail Penumpang, kursi, pembayaran, dan tiket yang sama.

## 6. Audit ERD dan Perancangan Basis Data

### 6.1 Apakah Ada Tabel Baru?

Tidak ada tabel bisnis baru. Fitur Petugas Loket menggunakan tabel `pengguna` dan `pemesanan` yang sudah tersedia.

Perubahan dilakukan pada dua tabel:

1. `pengguna`: pilihan nilai pada atribut `peran` ditambah `petugas_loket`;
2. `pemesanan`: ditambah lima kolom dan `pengguna_id` diubah menjadi nullable.

### 6.2 Perubahan Relasi dan Kardinalitas

ERD saat ini harus diperbaiki pada hubungan `pengguna` dengan `pemesanan`.

Relasi kepemilikan akun Penumpang:

```text
pengguna (0,N) ── memiliki pemesanan ── pemesanan (0,1)
FK: pemesanan.pengguna_id → pengguna.id
```

Maknanya, satu akun Penumpang dapat memiliki nol sampai banyak pemesanan. Satu pemesanan dapat terhubung ke nol atau satu akun Penumpang karena pemesanan loket dapat dibuat untuk orang yang belum memiliki akun.

Relasi pencatatan oleh Petugas Loket:

```text
pengguna (0,N) ── mencatat pemesanan ── pemesanan (0,1)
FK: pemesanan.dibuat_oleh → pengguna.id
```

Maknanya, satu Petugas Loket dapat mencatat nol sampai banyak pemesanan. Satu pemesanan dicatat oleh nol atau satu Petugas; nilai tersebut kosong untuk pemesanan mandiri.

Relasi `pengguna` dengan `kursi_jadwal` melalui `dikunci_oleh` tetap berlaku. Akun yang mengunci kursi dapat berupa Penumpang atau Petugas Loket, sedangkan otorisasi peran dikendalikan aplikasi.

### 6.3 Narasi Pengganti ERD

> Entity Relationship Diagram menggambarkan struktur entitas, atribut, hubungan, dan kardinalitas yang digunakan pada sistem RuslyTravel. Entitas pengguna menyimpan akun Penumpang, Pengemudi, Petugas Loket, dan Admin melalui atribut peran. Profil operasional Pengemudi dipisahkan pada entitas pengemudi, sedangkan Petugas Loket tidak memerlukan tabel profil tersendiri karena data identitas dan status akunnya telah diwakili oleh tabel pengguna. Entitas pemesanan terhubung secara opsional dengan akun Penumpang melalui pengguna_id dan terhubung secara opsional dengan Petugas pencatat melalui dibuat_oleh. Pemisahan tersebut memungkinkan pemesanan mandiri dan pemesanan loket menggunakan transaksi yang sama tanpa menjadikan Petugas sebagai pemilik perjalanan. Atribut sumber_pemesanan membedakan transaksi dari aplikasi Penumpang dan loket, sedangkan nama_pemesan, no_hp_pemesan, dan email_pemesan menyimpan snapshot kontak pada saat transaksi dibuat. Hubungan lain, yaitu jadwal, kursi_jadwal, detail_pemesanan, pembayaran, item_bagasi, tiket_elektronik, dan biaya_operasional_perjalanan tetap digunakan bersama oleh seluruh sumber pemesanan.

### 6.4 Rancangan Terbaru Tabel `pengguna`

| No | Nama Kolom | Tipe Data | Penjelasan |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment. |
| 2 | `nama` | VARCHAR(255) | Nama pengguna. Validasi antarmuka dapat memiliki batas yang lebih kecil daripada kapasitas kolom. |
| 3 | `email` | VARCHAR(255) | Email unik untuk autentikasi dan komunikasi akun. |
| 4 | `no_hp` | VARCHAR(20) | Nomor HP unik pengguna. |
| 5 | `foto_profil` | VARCHAR(255), nullable | Path foto profil. |
| 6 | `peran` | ENUM('penumpang','pengemudi','petugas_loket','admin') | Menentukan jenis akun dan hak akses. |
| 7 | `token_push` | VARCHAR(255), nullable | Kolom persiapan token perangkat; push notification belum menjadi implementasi produksi. |
| 8 | `status_aktif` | TINYINT(1) | Menentukan apakah akun dapat digunakan. |
| 9 | `email_verified_at` | TIMESTAMP, nullable | Waktu email dinyatakan terverifikasi. |
| 10 | `password` | VARCHAR(255) | Hash kata sandi. |
| 11 | `remember_token` | VARCHAR(100), nullable | Token pengingat sesi web. |
| 12 | `otp_code` | VARCHAR(255), nullable | Hash kode OTP verifikasi email. |
| 13 | `otp_expires_at` | TIMESTAMP, nullable | Batas berlaku OTP. |
| 14 | `created_at` | TIMESTAMP, nullable | Waktu data dibuat. |
| 15 | `updated_at` | TIMESTAMP, nullable | Waktu data diperbarui. |

Perubahan terhadap tabel pada laporan sekarang hanya berada pada baris `peran`, yaitu penambahan `petugas_loket`, dan pada penjelasan fungsi tabel agar menyebut empat jenis akun terautentikasi.

### 6.5 Rancangan Terbaru Tabel `pemesanan`

| No | Nama Kolom | Tipe Data | Penjelasan |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment. |
| 2 | `pengguna_id` | BIGINT UNSIGNED, nullable | FK opsional ke `pengguna.id` sebagai akun Penumpang terkait. Nullable untuk pemesanan loket tanpa akun. |
| 3 | `dibuat_oleh` | BIGINT UNSIGNED, nullable | FK opsional ke `pengguna.id` sebagai Petugas Loket yang mencatat transaksi. |
| 4 | `sumber_pemesanan` | VARCHAR(30) | Sumber transaksi, yaitu `aplikasi_penumpang` atau `loket`; memiliki indeks. |
| 5 | `nama_pemesan` | VARCHAR(100), nullable | Snapshot nama kontak pemesan ketika transaksi dibuat. |
| 6 | `no_hp_pemesan` | VARCHAR(20), nullable | Snapshot nomor HP kontak pemesan. |
| 7 | `email_pemesan` | VARCHAR(100), nullable | Snapshot email kontak apabila tersedia. |
| 8 | `jadwal_id` | BIGINT UNSIGNED | FK ke `jadwal.id`. |
| 9 | `kode_booking` | VARCHAR(255) | Kode booking unik. |
| 10 | `jumlah_kursi` | INT UNSIGNED | Jumlah kursi pada pemesanan. |
| 11 | `batas_kunci_kursi` | DATETIME, nullable | Batas penguncian kursi selama pengisian form. |
| 12 | `batas_pembayaran` | DATETIME, nullable | Batas pembayaran efektif jika berlaku. |
| 13 | `lintang_jemput` | DECIMAL(10,7), nullable | Koordinat lintang titik jemput. |
| 14 | `bujur_jemput` | DECIMAL(10,7), nullable | Koordinat bujur titik jemput. |
| 15 | `alamat_jemput` | TEXT, nullable | Alamat titik jemput. |
| 16 | `catatan_jemput` | VARCHAR(500), nullable | Patokan atau catatan penjemputan. |
| 17 | `lintang_turun` | DECIMAL(10,7), nullable | Koordinat lintang titik turun. |
| 18 | `bujur_turun` | DECIMAL(10,7), nullable | Koordinat bujur titik turun. |
| 19 | `alamat_turun` | TEXT, nullable | Alamat titik turun. |
| 20 | `catatan_turun` | VARCHAR(500), nullable | Patokan atau catatan titik turun. |
| 21 | `subtotal_tiket` | DECIMAL(12,2) | Snapshot subtotal tiket. |
| 22 | `total_harga` | DECIMAL(12,2) | Snapshot total komponen pemesanan RuslyTravel. |
| 23 | `status_pemesanan` | ENUM('draft','menunggu_pembayaran','terbayar','dibatalkan','selesai') | Status pemesanan. |
| 24 | `created_at` | TIMESTAMP, nullable | Waktu data dibuat. |
| 25 | `updated_at` | TIMESTAMP, nullable | Waktu data diperbarui. |

### 6.6 Gambar Implementasi Basis Data

Gambar `Database Relasi Tabel` pada laporan wajib diambil ulang setelah MySQL aktif dan migration terbaru diterapkan. Gambar sekarang masih menunjukkan:

- `pengguna.peran` tanpa `petugas_loket`;
- `pemesanan.pengguna_id` sebagai relasi lama;
- tidak adanya `dibuat_oleh`, `sumber_pemesanan`, `nama_pemesan`, `no_hp_pemesan`, dan `email_pemesan`.

Pada audit ini koneksi MySQL lokal di `127.0.0.1:3306` sedang tidak aktif, sehingga status fisik database tidak dapat diperiksa ulang secara langsung. Migration, backup sebelum perubahan role, model, controller, dan pengujian otomatis menunjukkan struktur yang dimaksud, tetapi screenshot final tetap harus dibuat dari database yang benar-benar sedang digunakan setelah MySQL dijalankan.

### 6.7 Catatan Kecil pada Komentar Migration

Komentar pada migration pemesanan menyebut `admin pencatat`, sedangkan fungsi sebenarnya menyimpan **Petugas Loket pencatat**. Ini tidak mengubah perilaku kode, tetapi sebaiknya dikoreksi pada sesi kode terpisah agar tidak membingungkan saat pembacaan source code.

## 7. Bagian Laporan yang Harus Disesuaikan

### 7.1 Abstrak dan Abstract

Abstrak sekarang belum menyebut Petugas Loket dan masih menyatakan 32 skenario. Gunakan naskah berikut setelah jumlah Black Box Testing final telah diperbarui.

#### Naskah Abstrak yang Disarankan

> Rusly Travel merupakan penyedia jasa transportasi umum lintas kota di Kalimantan Barat yang melayani rute Pontianak–Sanggau–Sintang–Suhaid. Pemesanan dan ketersediaan kursi sebelumnya dicatat menggunakan buku booking, sedangkan informasi jadwal disampaikan melalui WhatsApp dan Facebook. Proses tersebut berisiko menimbulkan kesalahan pencatatan, pemesanan kursi ganda, keterlambatan informasi, dan kesulitan melayani calon penumpang yang datang langsung atau memesan melalui telepon. Penelitian ini menghasilkan aplikasi pemesanan jasa transportasi umum lintas kota berbasis mobile yang terintegrasi dengan backend dan dasbor administrasi berbasis web. Sistem dikembangkan menggunakan metode System Development Life Cycle model Waterfall, Flutter untuk aplikasi mobile, Laravel untuk backend dan dasbor Admin, serta MySQL untuk basis data. Aplikasi menyediakan akses informasi publik bagi Tamu, pemesanan mandiri bagi Penumpang, pemesanan berbantuan bagi Petugas Loket, dan fungsi operasional bagi Pengemudi. Fitur utama meliputi autentikasi dan verifikasi email, pencarian jadwal, penguncian kursi secara real-time, titik jemput dan turun, pencatatan barang bawaan, pembayaran digital melalui Pakasir dan pembayaran tunai, tiket elektronik berbasis QR Code, manifes Penumpang, serta laporan operasional. Pemesanan loket dapat dibuat tanpa akun Penumpang dan tetap mencatat sumber transaksi serta Petugas pembuat agar data dapat ditelusuri. Pengujian fungsional dilakukan menggunakan Black Box Testing pada lingkungan pengembangan Android. Seluruh skenario yang telah dijalankan menghasilkan keluaran sesuai hasil yang diharapkan. Pengujian belum mencakup penggunaan operasional langsung, audit keamanan, pengukuran performa, dan penyelesaian dana pada mode produksi.

> **Kata Kunci:** pemesanan transportasi, Flutter, Laravel, penguncian kursi, Petugas Loket, Payment Gateway, QR Code

Jangan menulis jumlah skenario baru sebelum tabel pengujian Petugas Loket selesai dilaksanakan. Setelah diuji, kalimat hasil dapat diubah menjadi `Sebanyak X dari X skenario ...`.

#### Suggested Abstract

> Rusly Travel is an intercity public transportation provider in West Kalimantan serving the Pontianak–Sanggau–Sintang–Suhaid routes. Reservations and seat availability were previously recorded in booking books, while schedule information was distributed through WhatsApp and Facebook. This process created risks of recording errors, duplicate seat reservations, delayed information, and difficulties in serving passengers who booked directly at the counter or by telephone. This study produced a mobile-based intercity transportation reservation application integrated with a Laravel backend and a web-based administration dashboard. The system was developed using the Waterfall System Development Life Cycle model, Flutter for the mobile application, Laravel for the backend and admin dashboard, and MySQL for data storage. The application provides public information access for guests, self-service reservations for passengers, assisted reservations for counter staff, and trip operations for drivers. Its main features include authentication and email verification, schedule search, real-time seat locking, pickup and drop-off points, baggage records, digital payments through Pakasir and cash payments, QR-based electronic tickets, passenger manifests, and operational reports. Counter reservations may be created without a passenger account while preserving the reservation source and responsible staff member for traceability. Functional testing was conducted in an Android development environment using Black Box Testing. All executed scenarios produced the expected outputs. The evaluation did not include direct operational deployment, security auditing, performance testing, or production payment settlement.

> **Keywords:** transportation reservation, Flutter, Laravel, seat locking, counter staff, Payment Gateway, QR Code

### 7.2 BAB I

#### Tambahan pada Latar Belakang

Tambahkan setelah uraian mengenai proses pemesanan melalui WhatsApp dan pencatatan buku:

> Selain melayani pemesanan melalui komunikasi digital, Rusly Travel juga perlu menangani calon Penumpang yang datang langsung atau menghubungi petugas dalam waktu dekat dengan jadwal keberangkatan. Sebagian calon Penumpang belum memiliki aplikasi, belum mempunyai akun, atau membutuhkan bantuan dalam mengisi data. Apabila seluruh transaksi diwajibkan dilakukan sendiri oleh Penumpang, kondisi tersebut dapat memperlambat pelayanan dan menyebabkan pemesanan tetap dicatat di luar sistem. Oleh karena itu, sistem menyediakan pemesanan berbantuan melalui akun Petugas Loket pada aplikasi mobile. Petugas menggunakan alur jadwal, penguncian kursi, pembayaran, dan penerbitan tiket yang sama dengan Penumpang, sedangkan sistem mencatat sumber transaksi dan identitas Petugas pembuat untuk menjaga keterlacakan data.

#### Batasan Masalah yang Perlu Diganti

Ganti batasan aktor menjadi:

> Sistem terdiri atas satu aplikasi mobile dengan akses Tamu tanpa autentikasi serta hak akses terautentikasi bagi Penumpang, Pengemudi, dan Petugas Loket. Admin mengelola sistem melalui dasbor berbasis web.

Tambahkan:

> Akun Petugas Loket dibuat dan dikelola oleh Admin. Petugas dapat membuat pemesanan berbantuan untuk calon Penumpang yang datang langsung atau memesan melalui telepon. Pemesanan tersebut dapat dihubungkan dengan akun Penumpang yang telah tersedia atau disimpan tanpa akun Penumpang.

Ganti batasan pembayaran tunai menjadi:

> Pembayaran tunai dicatat dalam sistem dan dapat dikonfirmasi oleh Petugas Loket, Pengemudi yang ditugaskan, atau Admin sesuai dengan sumber transaksi dan kewenangannya.

#### Tujuan

Tambahkan frasa berikut pada tujuan:

> ... serta menyediakan pemesanan berbantuan melalui Petugas Loket agar transaksi langsung dan telepon tetap tercatat pada alur pemesanan yang sama.

#### Manfaat

Tambahkan manfaat bagi Petugas Loket:

> Sistem membantu Petugas Loket melayani calon Penumpang yang datang langsung atau memesan melalui telepon tanpa melakukan pencatatan di luar sistem. Petugas dapat memilih jadwal dan kursi, mencatat identitas serta lokasi Penumpang, memproses pembayaran, dan memperoleh tiket dari satu alur yang sama dengan pemesanan mandiri.

### 7.3 BAB II

Bagian dasar teori dan keterkaitan teori masih menyebut empat pengguna. Ubah menjadi lima aktor pada tingkat use case, yaitu Tamu, Penumpang, Petugas Loket, Pengemudi, dan Admin.

Tambahkan penjelasan singkat mengenai kontrol akses berbasis peran:

> Kontrol akses berbasis peran digunakan untuk membatasi fungsi sistem berdasarkan tanggung jawab pengguna. Pada sistem RuslyTravel, Penumpang, Pengemudi, Petugas Loket, dan Admin menggunakan akun pada tabel pengguna, tetapi memperoleh endpoint dan kemampuan yang berbeda. Tamu tidak mempunyai akun dan hanya memperoleh akses informasi publik. Pemisahan tersebut mencegah Petugas Loket memperoleh fungsi Admin Web sekaligus menjaga agar pemesanan yang dibuatnya dapat ditelusuri.

### 7.4 BAB III

Perubahan wajib:

1. ubah pernyataan empat modul menjadi lima kelompok akses;
2. tambahkan kebutuhan fungsional Petugas Loket;
3. perbarui Use Case Umum dan tambahkan Use Case Petugas Loket;
4. perbarui arsitektur agar mobile menyebut Tamu, Penumpang, Petugas Loket, dan Pengemudi;
5. perbarui ERD;
6. perbarui tabel `pengguna` dan `pemesanan`;
7. tambahkan rancangan antarmuka Petugas Loket dan pengelolaan akun Petugas pada web;
8. tambahkan skenario pengujian Petugas Loket.

#### Kebutuhan Fungsional Petugas Loket

> Sistem harus memfasilitasi Petugas Loket untuk masuk menggunakan akun internal yang dibuat Admin, melihat jadwal, memilih dan mengunci kursi, mencari akun Penumpang secara opsional, membuat pemesanan tanpa akun Penumpang, mengisi data pemesan dan Penumpang, memilih metode pembayaran, memeriksa status pembayaran digital, mengonfirmasi pembayaran tunai, melihat riwayat pemesanan yang dibuat oleh akunnya, serta melihat dan mengunduh tiket. Sistem harus membatasi Petugas agar hanya dapat mengakses pemesanan loket yang dibuat oleh akun tersebut.

#### Tambahan Kebutuhan Fungsional Admin

> Sistem harus memungkinkan Admin membuat, mengubah, mengaktifkan, menonaktifkan, mengirim ulang tautan aktivasi, serta melihat statistik pemesanan dari akun Petugas Loket.

#### Rancangan Antarmuka yang Perlu Ditambah

- halaman mobile Beranda Petugas Loket;
- halaman daftar jadwal/Pemesanan Loket;
- form kontak pemesan dan opsi menghubungkan akun Penumpang;
- detail pemesanan yang menampilkan `Dicatat oleh Petugas Loket`;
- daftar tiket loket;
- halaman web daftar, tambah, edit, dan detail Petugas Loket.

### 7.5 BAB IV

Tambahkan subbagian **Implementasi Modul Petugas Loket** setelah modul Penumpang atau sebelum modul Pengemudi.

Naskah siap pakai:

> Modul Petugas Loket menggunakan aplikasi mobile dan komponen antarmuka yang sama dengan modul Penumpang, tetapi ditampilkan dengan konteks Mode Petugas Loket. Akun Petugas dibuat dan dikendalikan oleh Admin melalui web, sedangkan registrasi publik tetap hanya menghasilkan akun Penumpang. Setelah masuk, Petugas dapat melihat ringkasan jumlah pemesanan loket, jadwal yang tersedia, riwayat transaksi yang dibuat oleh akunnya, dan tiket yang telah diterbitkan. Pada proses pemesanan, Petugas dapat mencari akun Penumpang menggunakan email atau nomor HP lengkap. Apabila akun ditemukan, pemesanan dapat dihubungkan dengan akun tersebut; apabila tidak ditemukan, Petugas tetap dapat mencatat data pemesan dan melanjutkan transaksi tanpa akun Penumpang. Backend menyimpan nilai `sumber_pemesanan` sebagai `loket` dan `dibuat_oleh` sebagai ID Petugas agar asal transaksi dapat ditelusuri. Seluruh validasi jadwal, penguncian kursi, harga, pembayaran, dan penerbitan tiket menggunakan layanan yang sama dengan pemesanan Penumpang sehingga tidak terdapat logika transaksi kedua.

Tambahkan pada implementasi Admin:

> Pada menu Petugas Loket, Admin dapat menambah akun, mengirim tautan aktivasi, memperbarui data, mengubah status, serta melihat jumlah pemesanan yang dibuat oleh setiap Petugas. Sistem menggunakan kata sandi acak pada saat akun dibuat dan meminta Petugas menentukan kata sandi melalui tautan aktivasi. Ketika akun dinonaktifkan, token mobile dicabut agar sesi yang masih tersimpan tidak dapat digunakan kembali.

Perbarui paragraf registrasi Penumpang karena isi laporan sekarang sudah tidak sesuai kode. Kondisi saat ini adalah:

- nama lengkap maksimal 50 karakter dan hanya menerima huruf serta spasi;
- email tidak dibatasi pada Gmail dan menerima domain valid seperti Yahoo, Outlook, Proton, atau email institusi;
- nomor HP terdiri atas 11–12 digit angka;
- kata sandi 6–20 karakter dan mengandung huruf serta sedikitnya angka atau simbol;
- OTP enam digit berlaku 15 menit.

### 7.6 Pengujian Black Box

Tambahkan kelompok **Modul Petugas Loket**. Skenario minimum yang disarankan:

| No | Fitur yang Diuji | Tujuan Pengujian | Langkah Pengujian | Output yang Diharapkan |
|---:|---|---|---|---|
| 1 | Masuk sebagai Petugas Loket | Memastikan akun internal mendapat antarmuka dan akses yang sesuai | Masukkan kredensial Petugas aktif | Sistem menampilkan Mode Petugas Loket dan tidak memberikan akses Admin Web. |
| 2 | Mencari akun Penumpang | Memastikan pencarian exact tidak menampilkan akun yang salah | Masukkan email atau nomor HP lengkap | Sistem menampilkan satu akun yang cocok atau informasi bahwa pemesanan dapat dilanjutkan tanpa akun. |
| 3 | Membuat pemesanan tanpa akun | Memastikan calon Penumpang tanpa aplikasi tetap dapat dilayani | Pilih jadwal, kursi, isi data, dan simpan tanpa menghubungkan akun | Sistem membuat pemesanan dengan `pengguna_id` kosong, sumber `loket`, dan Petugas pencatat. |
| 4 | Membuat pemesanan dengan akun Penumpang | Memastikan transaksi loket dapat terhubung ke akun yang tersedia | Cari akun, pilih akun, lalu selesaikan pemesanan | Sistem menghubungkan pemesanan ke akun Penumpang dan tetap mencatat Petugas pembuat. |
| 5 | Konflik penguncian kursi | Memastikan Petugas dan Penumpang tidak memperoleh kursi yang sama | Kunci kursi dari satu perangkat, lalu pilih kursi yang sama dari perangkat lain | Sistem menolak pengguna kedua dan menampilkan kursi tidak tersedia. |
| 6 | Konfirmasi pembayaran tunai | Memastikan tiket terbit satu kali | Pilih tunai dan konfirmasi pembayaran, kemudian ulangi permintaan | Sistem menandai pembayaran berhasil dan tidak membuat tiket duplikat. |
| 7 | Pembatasan pemesanan antarpetugas | Memastikan Petugas hanya dapat melihat transaksi miliknya | Akses ID pemesanan yang dibuat Petugas lain | Sistem menolak akses tanpa membocorkan data pemesanan. |
| 8 | Penonaktifan akun | Memastikan akun yang dinonaktifkan tidak dapat digunakan | Admin menonaktifkan akun saat token mobile masih tersimpan | Sistem mencabut token dan menolak permintaan berikutnya. |

Jumlah 32 skenario pada abstrak, BAB IV, evaluasi, dan kesimpulan harus dihitung ulang setelah tabel tambahan diuji. Jangan menganggap test Laravel/Flutter sebagai hasil Black Box manual.

### 7.7 BAB V

Tambahkan pada kesimpulan:

> Sistem mendukung pemesanan mandiri oleh Penumpang dan pemesanan berbantuan oleh Petugas Loket. Pemesanan loket dapat dibuat tanpa akun Penumpang untuk melayani transaksi langsung atau melalui telepon, sedangkan sumber transaksi, kontak pemesan, dan identitas Petugas pencatat disimpan agar riwayat dapat ditelusuri. Kedua sumber pemesanan menggunakan mekanisme jadwal, penguncian kursi, pembayaran, tiket, dan manifes yang sama.

Perbarui kesimpulan pembatasan akses:

> Pembatasan akses diterapkan terhadap Penumpang, Petugas Loket, Pengemudi, dan Admin. Tamu memperoleh akses publik tanpa autentikasi. Token Petugas Loket dibatasi pada endpoint pelayanan loket, sedangkan pengelolaan data tetap menjadi kewenangan Admin melalui web.

Pada saran push notification, tambahkan Petugas Loket sebagai penerima yang relevan apabila fitur tersebut dikembangkan.

## 8. Urutan Revisi yang Disarankan

1. Perbaiki Use Case Diagram Umum dan narasinya agar Tamu tidak diasosiasikan dengan masuk sebagai Tamu.
2. Tambahkan aktor dan Use Case Diagram Petugas Loket.
3. Perbaiki Use Case Admin dengan fungsi Kelola Akun Petugas Loket.
4. Perbaiki diagram Proses Bisnis Pemesanan dan Pembayaran.
5. Perbarui ERD serta tabel rancangan `pengguna` dan `pemesanan`.
6. Aktifkan MySQL, pastikan migration terbaru berstatus `Ran`, lalu ambil ulang screenshot relasi database.
7. Tambahkan wireframe dan implementasi Modul Petugas Loket.
8. Tambahkan dan jalankan Black Box Testing Petugas Loket.
9. Hitung ulang jumlah skenario, lalu perbarui abstrak, hasil pengujian, evaluasi, dan kesimpulan.
10. Perbarui Abstract bahasa Inggris setelah Abstrak bahasa Indonesia final.
11. Perbarui daftar isi, daftar tabel, daftar gambar, nomor gambar, dan referensi silang secara otomatis di Microsoft Word.

## 9. Temuan Tambahan pada DOCX

Selain perubahan Petugas Loket, ditemukan beberapa isi lama yang perlu diselaraskan:

- registrasi masih ditulis khusus Gmail, padahal backend menerima format email RFC dari berbagai domain;
- kata sandi masih ditulis 6–8 karakter, padahal aturan terbaru 6–20 karakter;
- beberapa bagian menyebut aplikasi mobile hanya digunakan Tamu, Penumpang, dan Pengemudi;
- beberapa bagian menyebut autentikasi hanya berdasarkan Penumpang, Pengemudi, dan Admin;
- pembayaran tunai hanya disebut dapat dikonfirmasi Admin atau Pengemudi;
- istilah `Masuk Sebagai Tamu` masih muncul pada uraian rancangan halaman, padahal antarmuka terbaru menggunakan tombol `Tamu` untuk akses tanpa autentikasi;
- judul/caption Gambar 4 pada daftar gambar tidak lagi sama dengan nomor gambar aktual setelah penambahan gambar proses bisnis;
- narasi beberapa gambar masih merujuk nomor lama, misalnya uraian setelah Gambar 4.5 menyebut Gambar 4.1;
- gambar relasi database dan ERD masih memakai skema lama;
- angka hasil pengujian masih 32 dan belum mencakup Petugas Loket.

## 10. Hal yang Belum Dapat Dipastikan pada Audit Ini

1. MySQL lokal sedang tidak berjalan, sehingga status migration dan struktur database fisik tidak dapat diverifikasi langsung pada 11 Agustus 2026.
2. Pengiriman email aktivasi Petugas melalui SMTP nyata belum diuji pada audit ini.
3. Pembayaran Pakasir sandbox dan penggunaan dua perangkat untuk konflik kursi tidak diuji secara langsung pada audit ini.
4. Delapan skenario Black Box Petugas Loket di atas masih merupakan rencana pengujian dan tidak boleh diberi status `Sesuai` sebelum dilaksanakan.
5. Penomoran halaman, gambar, tabel, dan daftar isi final harus diperiksa kembali setelah revisi dimasukkan ke DOCX.

## 11. Referensi Kode Utama

- `backend/database/migrations/2026_08_09_000100_add_counter_booking_fields_to_pemesanan_table.php`
- `backend/database/migrations/2026_08_10_000200_add_counter_staff_role_to_pengguna_table.php`
- `backend/app/Services/BookingService.php`
- `backend/app/Http/Controllers/Api/PetugasLoketController.php`
- `backend/app/Http/Controllers/Web/Admin/PetugasLoketController.php`
- `backend/app/Http/Controllers/Api/AuthController.php`
- `backend/routes/api.php`
- `backend/routes/web.php`
- `frontend/lib/core/models/travs_user_session.dart`
- `frontend/lib/features/home/travs_home_page.dart`
- `frontend/lib/features/schedule/travs_schedule_detail_page.dart`
- `backend/tests/Feature/PetugasLoketBookingTest.php`
- `backend/tests/Feature/PetugasLoketManagementTest.php`
- `frontend/test/travs_counter_booking_source_test.dart`
- `frontend/test/travs_home_ui_simplification_test.dart`
