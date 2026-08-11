# Rekomendasi Revisi Aplikasi dan Pemesanan Langsung di Loket

## 1. Tujuan Dokumen

Dokumen ini menjelaskan hasil analisis terhadap revisi aplikasi RuslyTravel. Tiga perbaikan awal dapat diterapkan tanpa mengubah alur utama pemesanan, sedangkan usulan pemesanan langsung oleh petugas perlu dibicarakan terlebih dahulu dengan dosen pembimbing karena berdampak pada ruang lingkup penelitian.

Status pembahasan:

| No. | Revisi | Keputusan Saat Ini |
|---|---|---|
| 1 | Tombol cek status pembayaran | Dapat diterapkan pada aplikasi mobile |
| 2 | Perbaikan konsep tamu dan masuk | Diperbaiki pada penjelasan use case; tamu tidak login sebagai tamu |
| 3 | Informasi validasi di luar placeholder | Dapat diterapkan pada form terkait |
| 4 | Pemesanan langsung oleh petugas/loket | Belum diterapkan dan menunggu arahan dosen pembimbing |

## 2. Tombol Cek Status Pembayaran

### 2.1 Permasalahan

Setelah melakukan pembayaran melalui QRIS atau Virtual Account, pengguna membutuhkan cara untuk meminta sistem memeriksa ulang status transaksi. Tanpa tombol tersebut, pengguna hanya mengandalkan pembaruan otomatis ketika aplikasi dibuka kembali atau ketika halaman dimuat ulang.

### 2.2 Rekomendasi

Tambahkan tombol **Cek Status Pembayaran** pada halaman Pembayaran. Tombol ditempatkan sebagai tombol sekunder di bawah tombol **Lanjutkan Pembayaran**.

Tombol hanya ditampilkan apabila:

1. metode pembayaran digital sudah dipilih;
2. transaksi Pakasir sudah dibuat;
3. status pembayaran masih menunggu;
4. pembayaran belum dibatalkan atau kedaluwarsa.

Tombol tidak ditampilkan pada pembayaran tunai karena pembayaran tunai dikonfirmasi oleh pengemudi atau admin, bukan diperiksa melalui Pakasir.

### 2.3 Alur Pemeriksaan

```text
Penumpang menekan Cek Status Pembayaran
        ↓
Aplikasi mobile mengirim permintaan ke backend RuslyTravel
        ↓
Backend memeriksa transaksi melalui Transaction Detail API Pakasir
        ↓
Backend memperbarui status pembayaran jika ada perubahan
        ↓
Aplikasi menampilkan status terbaru kepada penumpang
```

API key Pakasir tetap berada pada backend dan tidak dikirim ke aplikasi mobile.

## 3. Perbaikan Konsep Tamu pada Use Case

### 3.1 Permasalahan pada Diagram

Pertanyaan “Tamu login untuk apa?” muncul karena aktor **Tamu** dihubungkan dengan use case **Masuk**. Hubungan tersebut dapat menimbulkan kesan bahwa sistem memiliki akun atau sesi khusus bernama tamu.

Pada implementasi RuslyTravel, tamu sebenarnya adalah pengunjung yang belum melakukan autentikasi. Tamu hanya memperoleh akses baca terhadap informasi publik. Setelah berhasil masuk, pengguna tidak lagi bertindak sebagai tamu, tetapi sebagai penumpang, pengemudi, atau admin sesuai akun yang digunakan.

### 3.2 Alur yang Benar

```text
Tamu
  ├─ melihat informasi publik; atau
  └─ mendaftar dan memverifikasi email
                ↓
              Masuk
                ↓
            Penumpang
```

### 3.3 Hubungan Aktor dan Use Case yang Disarankan

#### Aktor Tamu

- Akses Informasi Publik;
- Daftar Akun;
- Verifikasi Email.

#### Aktor Penumpang

- Masuk;
- Cari dan Pilih Jadwal;
- Pemesanan dan Penguncian Kursi;
- Mengisi Data Penumpang;
- Menentukan Titik Jemput dan Titik Turun;
- Memilih Metode dan Melakukan Pembayaran;
- Mengecek Status Pembayaran;
- Melihat Tiket Elektronik;
- Melihat Riwayat Pemesanan;
- Melihat Notifikasi;
- Mengelola Profil.

#### Aktor Pengemudi

- Masuk;
- Melihat Jadwal Tugas;
- Melihat Manifes Penumpang;
- Menjalankan Operasional Perjalanan;
- Memindai dan Memverifikasi Tiket QR;
- Mengonfirmasi Pembayaran Tunai sesuai kewenangan;
- Mencatat Biaya Operasional.

#### Aktor Admin

- Masuk;
- Mengelola Data Master;
- Mengelola Jadwal;
- Memantau Pemesanan, Pembayaran, dan Tiket;
- Mengelola Biaya Operasional;
- Melihat dan Mengekspor Laporan;
- Mengelola Banner, Notifikasi, dan Pengaturan Sistem.

### 3.4 Istilah Antarmuka

Istilah **Masuk sebagai Tamu** sebaiknya tidak digunakan. Istilah yang lebih tepat adalah:

- **Lanjut sebagai Tamu**; atau
- **Akses Tanpa Masuk**.

Use case menggambarkan hak akses setiap aktor, sedangkan perubahan keadaan dari tamu menjadi penumpang setelah registrasi dan login lebih tepat dijelaskan pada activity diagram atau flowchart autentikasi.

## 4. Aturan Validasi Form

### 4.1 Nama atau Username

Basis data RuslyTravel tidak memiliki kolom khusus bernama `username`. Identitas nama akun disimpan dalam kolom `nama`. Oleh karena itu, istilah yang digunakan pada tampilan adalah **Nama Lengkap**.

Aturan yang disarankan:

- wajib diisi;
- maksimal 100 karakter;
- hanya menerima huruf dan spasi;
- angka tidak diperbolehkan.

Contoh benar:

```text
Ihsan Al Husna
Rusli Driver
```

Contoh salah:

```text
Ihsan123
Driver01
```

Aturan yang sama dapat digunakan untuk nama penumpang pada tiket agar identitas penumpang tidak berisi angka.

### 4.2 Email

Email tidak dibatasi hanya untuk Gmail. Sistem menerima alamat email dengan domain yang valid, misalnya:

```text
pengguna@gmail.com
pengguna@yahoo.com
mahasiswa@polnep.ac.id
```

Email tetap harus mempunyai susunan alamat yang valid, yaitu terdapat nama pengguna, tanda `@`, dan domain.

### 4.3 Penempatan Keterangan Validasi

Placeholder hanya digunakan sebagai contoh singkat. Aturan input ditampilkan sebagai teks bantuan di bawah field agar dapat dibaca penuh.

Contoh:

```text
Nama Lengkap
[Contoh: Ihsan Al Husna]
Hanya gunakan huruf dan spasi; angka tidak diperbolehkan.
```

Jika input salah, pesan kesalahan ditampilkan langsung di bawah field dan dapat membungkus menjadi beberapa baris.

## 5. Kasus Penumpang Datang Langsung Menjelang Keberangkatan

### 5.1 Skenario Masalah

Calon penumpang datang langsung ke tempat Rusly Travel sekitar satu jam sebelum kendaraan berangkat. Penumpang belum memasang aplikasi, belum mempunyai akun, atau belum memahami cara menggunakan aplikasi. Jika penumpang diwajibkan melakukan seluruh proses sendiri, waktu pelayanan menjadi lebih lama dan terdapat risiko kursi tidak sempat dipesan.

Saat ini web admin hanya digunakan untuk pengelolaan dan pemantauan. Admin belum mempunyai fitur untuk membuat pemesanan baru atas nama calon penumpang.

## 6. Alternatif Solusi

### 6.1 Alternatif A — Petugas Membantu Pemesanan pada HP Penumpang

Alur:

```text
Petugas membantu memasang aplikasi
→ penumpang mendaftar
→ verifikasi email
→ memilih jadwal dan kursi
→ mengisi data
→ memilih pembayaran
```

Kelebihan:

- tidak membutuhkan fitur baru yang besar;
- judul berbasis mobile tetap sesuai;
- seluruh pemesanan tetap dimiliki akun penumpang.

Kekurangan:

- kurang cepat untuk kondisi mendesak;
- bergantung pada HP, jaringan internet, dan akses email penumpang;
- tidak menyelesaikan kasus penumpang tanpa smartphone;
- menambah beban pelayanan petugas.

### 6.2 Alternatif B — Pemesanan Berbantuan Petugas melalui Web Admin

Petugas membuat pemesanan dari web admin berdasarkan data calon penumpang. Fitur ini dapat disebut **Pemesanan Loket** atau **Pemesanan Berbantuan Petugas**.

Data yang perlu diisi:

- jadwal perjalanan;
- kursi;
- nama dan nomor HP penumpang;
- titik jemput dan titik turun;
- barang bawaan jika ada;
- metode pembayaran;
- petugas yang membuat pemesanan.

Tiket dapat diberikan dalam bentuk cetak, gambar, atau dikirim melalui WhatsApp setelah pembayaran dikonfirmasi.

Kelebihan:

- paling sesuai dengan proses bisnis travel di lapangan;
- dapat melayani penumpang tanpa aplikasi;
- cepat digunakan untuk pemesanan mendesak;
- kursi, pembayaran, tiket, manifes, dan laporan tetap tercatat dalam satu sistem.

Kekurangan dan dampak teknis:

- membutuhkan form pemesanan baru pada web admin;
- kemungkinan membutuhkan perubahan struktur database;
- harus mencatat sumber pemesanan dan admin pembuat transaksi;
- harus menentukan hubungan pemesanan dengan akun penumpang;
- pembayaran, penerbitan tiket, laporan, dan penguncian kursi harus diuji ulang;
- ruang lingkup penelitian bertambah.

Fitur ini tidak boleh dibuat dengan cara langsung menambahkan nama ke manifes. Prosesnya harus memakai layanan pemesanan dan penguncian kursi yang sama dengan aplikasi mobile agar tidak terjadi kursi ganda.

### 6.3 Alternatif C — Pemesanan Loket melalui Perangkat Mobile Petugas

Rusly Travel menyediakan tablet atau HP khusus yang dipakai petugas untuk membuat pemesanan bagi calon penumpang.

Kelebihan:

- antarmuka utama tetap berbasis mobile;
- sesuai untuk penggunaan pada loket kecil;
- tidak bergantung pada HP calon penumpang.

Kekurangan:

- tetap membutuhkan peran dan menu khusus petugas;
- tetap memerlukan audit pencatatan pembuat pemesanan;
- dampak backend dan database hampir sama dengan pemesanan melalui web admin;
- pengiriman atau pencetakan tiket tetap harus disediakan.

## 7. Rekomendasi Utama

Secara operasional, alternatif terbaik adalah **Pemesanan Berbantuan Petugas melalui Web Admin**. Web admin sudah digunakan sebagai sarana administrasi RuslyTravel, sehingga penambahan ini berfungsi sebagai pelayanan loket dan bukan sebagai website pemesanan publik.

Walaupun demikian, alternatif tersebut belum disarankan untuk langsung dikerjakan sebelum mendapatkan persetujuan dosen pembimbing karena menyentuh:

- ruang lingkup penelitian;
- judul penelitian;
- rancangan use case;
- struktur basis data;
- proses pemesanan;
- pembayaran;
- tiket dan laporan.

## 8. Dampak terhadap Judul Penelitian

Judul saat ini:

> Rancang Bangun Aplikasi Pemesanan Jasa Transportasi Umum Lintas Kota pada Rusly Travel Berbasis Mobile

Jika web hanya berfungsi sebagai dasbor pendukung administrasi, fokus utama penelitian masih dapat dinyatakan berbasis mobile. Namun, apabila admin dapat membuat transaksi pemesanan dari web, dosen dapat menilai bahwa fungsi pemesanan berjalan pada dua platform.

Alternatif judul yang lebih jelas:

> Rancang Bangun Aplikasi Pemesanan Jasa Transportasi Umum Lintas Kota pada Rusly Travel Berbasis Mobile dengan Dasbor Administrasi Web

Frasa **dengan Dasbor Administrasi Web** menjelaskan bahwa layanan utama penumpang tetap berada pada aplikasi mobile, sedangkan web merupakan sarana pendukung bagi pengelola.

## 9. Pertanyaan yang Disarankan untuk Dosen Pembimbing

1. Apakah kasus penumpang yang datang langsung wajib dimasukkan ke ruang lingkup penelitian?
2. Apakah admin diperbolehkan membuat pemesanan berbantuan petugas melalui web admin?
3. Jika fungsi tersebut ditambahkan, apakah judul perlu ditambah frasa “dengan Dasbor Administrasi Web”?
4. Apakah pemesanan loket boleh dibuat tanpa akun penumpang, atau setiap penumpang tetap wajib mempunyai akun?
5. Apakah tiket untuk pemesanan loket cukup dicetak atau dikirim melalui WhatsApp?
6. Apakah pembayaran loket dibatasi pada tunai, atau tetap boleh menggunakan QRIS dan Virtual Account?

## 10. Keputusan Sementara

Sampai terdapat arahan dosen pembimbing, fitur pemesanan langsung melalui web admin **tidak diimplementasikan**. Sistem tetap menggunakan alur pemesanan mobile yang sudah ada. Keputusan ini mencegah perubahan besar pada database dan logika bisnis sebelum ruang lingkup penelitian disepakati.

## 11. Bahan Prototipe untuk Diskusi

Prompt siap salin untuk membuat contoh HTML statis dan desain PNG bagi opsi web admin maupun mobile petugas tersedia pada:

[`PROMPT_PROTOTIPE_PEMESANAN_LOKET_RUSLYTRAVEL.md`](PROMPT_PROTOTIPE_PEMESANAN_LOKET_RUSLYTRAVEL.md)

Prompt tersebut hanya memvisualisasikan usulan tampilan. Isinya tidak dianggap sebagai persetujuan untuk mengubah database, alur pemesanan, pembayaran, tiket, maupun judul penelitian.
