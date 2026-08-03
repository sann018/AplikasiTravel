# Penjelasan Use Case Diagram RuslyTravel

## 1. Hasil Pemeriksaan Diagram

Use case diagram RuslyTravel telah menggambarkan fungsi sistem pada tingkat umum dan sudah sesuai
untuk digunakan dalam laporan Tugas Akhir. Diagram tersebut melibatkan empat aktor, yaitu
Penumpang, Tamu, Pengemudi, dan Admin. Setiap aktor terhubung dengan fungsi yang sesuai dengan hak
akses dan tanggung jawabnya.

Fungsi utama yang telah tercakup meliputi pencarian jadwal, pemesanan, penguncian kursi,
pengisian data penumpang dan barang bawaan, penentuan lokasi, pembayaran, tiket elektronik,
manifes penumpang, operasional perjalanan, pengelolaan data, dan laporan.

Use case pada diagram sengaja disajikan secara umum agar diagram tetap mudah dibaca. Proses yang
lebih terperinci, seperti penguncian kursi dua tahap, batas waktu pemesanan, pembayaran melalui
Pakasir, validasi tiket QR, dan perubahan status perjalanan dijelaskan melalui activity diagram,
flowchart, sequence diagram, atau uraian proses bisnis.

### Catatan Judul Diagram

Judul pada gambar masih menggunakan frasa **Berbasis Mobile**, sedangkan Admin menggunakan web
admin Laravel. Sebelum gambar dimasukkan sebagai versi final laporan, judul yang lebih sesuai
adalah:

> **Sistem Aplikasi Pemesanan Jasa Transportasi Umum Lintas Kota Berbasis Mobile dan Web**

Perubahan tersebut hanya memperjelas ruang lingkup platform dan tidak mengubah isi maupun aktor
pada diagram.

## 2. Narasi Use Case Diagram

Use case diagram RuslyTravel menggambarkan hubungan antara pengguna dan fungsi yang disediakan
oleh sistem. Sistem ini melibatkan empat aktor, yaitu Penumpang, Tamu, Pengemudi, dan Admin.
Masing-masing aktor mempunyai hak akses dan tanggung jawab yang berbeda sesuai dengan proses
bisnis perjalanan.

Penumpang menggunakan aplikasi mobile untuk mencari jadwal, memilih dan mengunci kursi,
melengkapi data pemesanan, menentukan titik jemput dan titik turun, memilih metode pembayaran,
serta memperoleh tiket elektronik. Tamu hanya dapat melihat jadwal dan informasi yang bersifat
publik tanpa melakukan login. Pengemudi menjalankan kegiatan operasional berdasarkan jadwal yang
ditugaskan, sedangkan Admin mengelola data utama, pemesanan, transaksi, biaya operasional, dan
laporan melalui web admin.

## 3. Deskripsi Aktor

**Tabel 3.x Deskripsi Aktor**

| No. | Aktor | Deskripsi |
|---:|---|---|
| 1 | Penumpang | Pengguna yang telah mempunyai akun dan menggunakan aplikasi mobile untuk mencari jadwal, membuat pemesanan, memilih kursi, menentukan lokasi perjalanan, melakukan pembayaran, melihat tiket elektronik, menerima informasi pemesanan, dan mengelola profil. |
| 2 | Tamu | Pengguna yang belum melakukan autentikasi. Tamu hanya dapat melihat jadwal perjalanan dan informasi lain yang bersifat publik. Untuk membuat pemesanan, Tamu harus mendaftar atau melakukan login sebagai Penumpang. |
| 3 | Pengemudi | Pengguna yang menjalankan perjalanan sesuai jadwal tugas yang diberikan. Pengemudi dapat melihat jadwal dan riwayat perjalanan, membuka manifes penumpang, memindai tiket QR, serta menjalankan proses operasional perjalanan. |
| 4 | Admin | Pengguna yang mengelola sistem melalui web admin. Admin dapat mengelola rute, armada, akun pengemudi, jadwal perjalanan, data pemesanan dan transaksi, biaya operasional, notifikasi, serta laporan operasional. |

## 4. Deskripsi Use Case Penumpang

Penumpang merupakan aktor utama dalam proses pencarian jadwal dan pemesanan perjalanan. Sebelum
mengakses fitur yang memerlukan data pribadi, Penumpang harus melakukan login menggunakan akun
yang telah terdaftar.

**Tabel 3.x Deskripsi Use Case Penumpang**

| No. | Use Case | Deskripsi |
|---:|---|---|
| 1 | Login | Memungkinkan Penumpang masuk ke aplikasi menggunakan akun yang telah terdaftar agar dapat menggunakan fungsi pemesanan dan mengakses data pribadi. |
| 2 | Pencarian dan Pilih Jadwal | Memungkinkan Penumpang melihat dan memilih jadwal perjalanan berdasarkan rute, tanggal, waktu keberangkatan, kendaraan, harga, dan ketersediaan kursi. |
| 3 | Pilih Kursi (Seat Lock) | Memungkinkan Penumpang memilih kursi yang tersedia. Kursi yang dipilih dikunci sementara agar tidak digunakan pengguna lain pada waktu yang sama. |
| 4 | Buat Pemesanan | Membentuk pemesanan berdasarkan jadwal, kursi, data penumpang, lokasi, dan informasi perjalanan yang telah dilengkapi. |
| 5 | Isi Biodata Penumpang dan Barang Bawaan | Mencatat nama serta nomor HP penumpang sesuai kursi yang dipilih dan mencatat informasi barang bawaan beserta foto apabila diperlukan. |
| 6 | Tentukan Titik Jemput dan Titik Turun Berbasis Lokasi | Mencatat lokasi penjemputan dan penurunan menggunakan alamat, peta digital, dan koordinat lokasi agar dapat digunakan dalam proses operasional perjalanan. |
| 7 | Pilih dan Lakukan Pembayaran | Memungkinkan Penumpang memilih pembayaran digital melalui Pakasir atau pembayaran tunai. Pembayaran digital dapat menggunakan QRIS atau Virtual Account, sedangkan pembayaran tunai dikonfirmasi pada proses operasional. |
| 8 | Lihat Tiket Elektronik dan QR Code | Menampilkan tiket elektronik setiap penumpang beserta QR unik yang digunakan untuk validasi tiket saat proses keberangkatan. |
| 9 | Lihat Riwayat Pemesanan | Menampilkan daftar pemesanan yang pernah dibuat beserta status pemesanan dan pembayarannya. |
| 10 | Lihat Notifikasi | Menampilkan pemberitahuan mengenai pemesanan, pembayaran, batas waktu, keberangkatan, dan perubahan status perjalanan. |
| 11 | Kelola Profil Akun | Memungkinkan Penumpang melihat dan memperbarui informasi profil serta mengganti kata sandi akun. |

## 5. Deskripsi Use Case Tamu

Tamu merupakan pengguna yang belum melakukan login. Hak akses Tamu dibatasi pada informasi yang
bersifat umum dan tidak mencakup pemesanan maupun penguncian kursi.

**Tabel 3.x Deskripsi Use Case Tamu**

| No. | Use Case | Deskripsi |
|---:|---|---|
| 1 | Lihat Jadwal dan Informasi Lain yang Bersifat Publik | Menampilkan jadwal perjalanan, detail rute, waktu keberangkatan, harga tiket, ketersediaan kursi, banner, serta informasi umum lain tanpa mewajibkan pengguna melakukan login. |

Apabila Tamu ingin memilih kursi atau membuat pemesanan, pengguna diarahkan untuk melakukan
pendaftaran atau login sebagai Penumpang.

## 6. Deskripsi Use Case Pengemudi

Pengemudi merupakan aktor yang menjalankan kegiatan operasional pada jadwal perjalanan yang
telah ditentukan oleh Admin.

**Tabel 3.x Deskripsi Use Case Pengemudi**

| No. | Use Case | Deskripsi |
|---:|---|---|
| 1 | Login | Memungkinkan Pengemudi masuk menggunakan akun aktif yang telah dibuat oleh Admin. |
| 2 | Lihat Jadwal Tugas | Menampilkan jadwal perjalanan yang menggunakan Pengemudi tersebut sebagai petugas perjalanan. |
| 3 | Lihat Manifes Penumpang | Menampilkan daftar penumpang dalam suatu jadwal, meliputi identitas penumpang, nomor kursi, status pembayaran, titik jemput dan turun, serta informasi barang bawaan. |
| 4 | Pindai dan Verifikasi Tiket QR | Memindai QR unik pada tiket elektronik untuk memeriksa validitas tiket dan membantu pencatatan kehadiran penumpang. |
| 5 | Kelola Operasional Perjalanan | Menjalankan kegiatan operasional yang meliputi proses penjemputan, konfirmasi pembayaran tunai, pencatatan kehadiran, memulai perjalanan, mencatat penumpang turun, dan menyelesaikan perjalanan. |
| 6 | Lihat Riwayat Perjalanan | Menampilkan perjalanan terdahulu yang pernah ditugaskan dan dijalankan oleh Pengemudi. |

## 7. Deskripsi Use Case Admin

Admin merupakan aktor yang mengelola data dan memonitor operasional RuslyTravel melalui
antarmuka web admin.

**Tabel 3.x Deskripsi Use Case Admin**

| No. | Use Case | Deskripsi |
|---:|---|---|
| 1 | Login | Memungkinkan Admin masuk ke web admin menggunakan akun yang mempunyai hak akses pengelolaan sistem. |
| 2 | Kelola Jadwal Perjalanan | Memungkinkan Admin menambah, melihat, mengubah, dan mengatur jadwal perjalanan berdasarkan rute, armada, pengemudi, waktu keberangkatan, dan susunan kursi jadwal. |
| 3 | Kelola Data Rute | Memungkinkan Admin mengelola kota asal, kota tujuan, jalur perjalanan, jarak, estimasi durasi, harga tiket, dan status rute. |
| 4 | Kelola Akun Pengemudi | Memungkinkan Admin membuat, melihat, mengubah, mengaktifkan, dan menonaktifkan akun Pengemudi. |
| 5 | Kelola Armada | Memungkinkan Admin mengelola identitas kendaraan, kapasitas kursi, nomor plat, kondisi, dan status penggunaan armada. |
| 6 | Lihat Data Pemesanan dan Transaksi | Menampilkan data pemesanan dan pembayaran untuk membantu Admin memonitor status pemesanan, metode pembayaran, nominal transaksi, tiket, dan proses pembayaran tunai maupun digital. |
| 7 | Lihat Laporan Operasional | Menampilkan ringkasan dan laporan pemesanan, transaksi, pendapatan, serta data operasional berdasarkan status dan periode yang dipilih. Data laporan dapat diekspor sesuai filter yang diterapkan. |
| 8 | Kelola Biaya Operasional | Memungkinkan Admin melihat, menambah, mengubah, dan memeriksa biaya yang berhubungan dengan suatu perjalanan beserta bukti pengeluarannya. |
| 9 | Lihat Notifikasi | Menampilkan pemberitahuan mengenai pemesanan, pembayaran, jadwal keberangkatan, perjalanan, dan kegiatan operasional lain yang perlu diketahui Admin. |

## 8. Hubungan Antara Use Case

Hubungan `<<include>>` digunakan apabila suatu fungsi selalu menjadi bagian dari proses utama.
Pada diagram RuslyTravel, use case **Buat Pemesanan** mempunyai hubungan `<<include>>` dengan
beberapa proses berikut.

| No. | Use Case Utama | Relasi | Use Case yang Disertakan | Penjelasan |
|---:|---|---|---|---|
| 1 | Buat Pemesanan | `<<include>>` | Pencarian dan Pilih Jadwal | Jadwal perjalanan harus ditentukan sebelum data pemesanan dapat dibentuk. |
| 2 | Buat Pemesanan | `<<include>>` | Pilih Kursi (Seat Lock) | Pemesanan harus mempunyai kursi yang masih tersedia dan berhasil dikunci. |
| 3 | Buat Pemesanan | `<<include>>` | Isi Biodata Penumpang dan Barang Bawaan | Data penumpang diperlukan sesuai dengan jumlah kursi yang dipilih, sedangkan informasi barang bawaan dicatat apabila tersedia. |
| 4 | Buat Pemesanan | `<<include>>` | Tentukan Titik Jemput dan Titik Turun Berbasis Lokasi | Informasi lokasi diperlukan sebagai acuan penjemputan dan penurunan penumpang. |

Use case **Pilih dan Lakukan Pembayaran** tidak dijadikan `<<include>>` wajib dari Buat Pemesanan.
Sistem terlebih dahulu membentuk data pemesanan dengan status menunggu pembayaran. Setelah itu,
Penumpang memilih metode pembayaran. Pemisahan tersebut juga memungkinkan penggunaan pembayaran
digital maupun tunai.

Login diperlakukan sebagai prasyarat bagi Penumpang, Pengemudi, dan Admin untuk mengakses fungsi
yang memerlukan autentikasi. Oleh sebab itu, Login tidak harus dihubungkan menggunakan
`<<include>>` ke seluruh fungsi pada diagram.

Use case pengelolaan rute, armada, akun pengemudi, dan jadwal perjalanan merupakan fungsi mandiri.
Data rute, armada, dan pengemudi digunakan saat penyusunan jadwal, tetapi ketiganya tidak menjadi
`<<include>>` dari Kelola Jadwal Perjalanan.

## 9. Kesimpulan

Use case diagram telah mewakili ruang lingkup utama sistem pada tingkat umum dan dapat digunakan
dalam laporan Tugas Akhir setelah judul platform disesuaikan menjadi berbasis Mobile dan Web.
Pembagian empat aktor telah sesuai dengan hak akses sistem. Fungsi yang lebih teknis tidak perlu
ditambahkan sebagai oval baru agar diagram tetap ringkas dan mudah dipahami.

Proses terperinci seperti penguncian kursi dua tahap, batas waktu konfirmasi dan pembayaran,
integrasi Pakasir, validasi QR, konfirmasi pembayaran tunai, serta transisi status perjalanan
dijelaskan pada diagram perilaku dan uraian proses bisnis lainnya.
