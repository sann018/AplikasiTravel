# Narasi Alur Sistem Aplikasi RuslyTravel

**Pembaruan dokumen:** 21 Juni 2026
**Cakupan:** Web Admin dan aplikasi mobile untuk Tamu, Penumpang, serta Pengemudi

## 1. Gambaran Umum Sistem

RuslyTravel adalah sistem pemesanan kursi travel lintas kota. Sistem dibagi menjadi dua aplikasi yang memakai database dan backend Laravel yang sama:

1. **Web Admin** dipakai untuk mengatur operasional travel, seperti armada, pengemudi, rute, jadwal, pemesanan, pembayaran, biaya, laporan, dan pengaturan sistem.
2. **Aplikasi Mobile Flutter** dipakai oleh tamu, penumpang, dan pengemudi sesuai hak akses masing-masing.

Alur utamanya dimulai saat admin menyiapkan armada, pengemudi, rute, dan jadwal. Penumpang kemudian memilih jadwal dan kursi, mengisi data perjalanan, lalu menyelesaikan pembayaran. Setelah pembayaran valid, tiket QR diterbitkan. Pada hari keberangkatan, pengemudi memindai tiket, memantau manifest, dan mengonfirmasi penumpang turun di tujuan.

## 2. Pembagian Hak Akses

| Pengguna | Aplikasi | Hak akses utama |
|---|---|---|
| Admin | Web Admin | Mengelola seluruh data operasional dan laporan |
| Tamu | Mobile | Melihat rute, jadwal, harga, kursi, dan informasi umum |
| Penumpang | Mobile | Membuat pemesanan, membayar, menerima tiket, dan melihat riwayat |
| Pengemudi | Mobile | Melihat tugas, manifest, pembayaran tunai, scan tiket, dan status perjalanan |

Hak akses diperiksa kembali di backend. Jadi, menyembunyikan tombol di tampilan saja tidak dianggap cukup. Contohnya, akun penumpang tetap akan ditolak jika mencoba memanggil endpoint khusus pengemudi.

## 3. Alur Autentikasi dan Akun

### 3.1 Login Mobile

Pengguna memasukkan email atau nomor HP dan kata sandi. Flutter mengirim data tersebut ke API login. Backend memeriksa akun, status aktif, kata sandi yang sudah di-hash, dan role pengguna. Jika benar, backend menerbitkan token Laravel Sanctum. Token disimpan pada perangkat dan dipakai pada request berikutnya.

Setelah aplikasi dibuka kembali, sesi lokal dimuat lalu data akun diperbarui melalui endpoint profil. Karena itu, perubahan nama, foto, nomor HP, atau Nomor SIM pada akun lama akan terlihat setelah refresh, membuka ulang halaman, atau login kembali.

### 3.2 Daftar Akun Penumpang

Pendaftaran mandiri hanya tersedia untuk penumpang. Pengguna mengisi nama, email, nomor HP, kata sandi, dan konfirmasi kata sandi. Backend memvalidasi format serta memastikan email dan nomor HP belum dipakai akun lain.

Akun pengemudi tidak didaftarkan secara mandiri. Admin membuat akun pengemudi agar identitas, NIK untuk Nomor SIM, status verifikasi, dan data operasionalnya dapat diperiksa lebih dahulu.

### 3.3 Lupa Password

Sistem saat ini menggunakan email dan token reset. Token asli tidak disimpan langsung di database, melainkan disimpan dalam bentuk hash. Token berlaku 60 menit, hanya dapat dipakai sekali, dan semua sesi lama dicabut setelah password berhasil direset.

Pada mode demo lokal, token dikembalikan melalui respons API agar fitur dapat diuji tanpa layanan email atau SMS berbayar. Pada production, token seharusnya dikirim melalui email atau SMS dan tidak boleh ditampilkan di respons API.

### 3.4 Edit Profil

Penumpang dan pengemudi dapat mengubah nama, email, nomor HP, password, dan foto profil. Foto dikirim sebagai multipart, divalidasi tipenya, dikompres dari sisi aplikasi, lalu disimpan pada storage backend.

Untuk pengemudi, Edit Profil juga menyediakan **Nomor SIM (NIK KTP)** yang wajib berisi tepat 16 digit dan harus unik. Setelah penyimpanan berhasil, aplikasi mengambil ulang data profil dan memperbarui sesi lokal supaya data baru langsung tampil.

## 4. Alur Aplikasi Mobile Sebagai Tamu

Mode tamu dipakai untuk melihat informasi sebelum pengguna membuat akun atau login. Tamu tidak dapat mengunci kursi atau membuat pemesanan.

### 4.1 Menu Beranda

Beranda menampilkan pengenalan layanan, jumlah jadwal yang dapat dilihat, rute atau jadwal populer, serta tombol masuk. Tamu dapat membuka detail jadwal untuk melihat rute, harga, armada, foto kendaraan, waktu keberangkatan, dan kursi yang tersedia.

### 4.2 Menu Rute

Tamu melihat daftar rute dalam bentuk bookmark. Setelah satu rute dipilih, aplikasi menampilkan jadwal untuk rute tersebut. Jadwal dapat disaring berdasarkan semua tanggal, besok, atau tanggal tertentu.

Jika tamu mencoba melanjutkan ke pemesanan, aplikasi mengarahkannya ke halaman login. Hal ini mencegah tamu mengunci kursi tanpa identitas akun.

### 4.3 Menu Info

Menu Info menjelaskan gambaran alur pemesanan, titik jemput, pembayaran, tiket, dan operasional pengemudi. Menu ini tidak membuat atau mengubah data transaksi.

### 4.4 Menu Akun

Menu Akun menampilkan status bahwa pengguna belum login. Tersedia tombol untuk masuk atau mendaftar sebagai penumpang. Pengemudi memakai akun yang telah dibuat oleh admin.

## 5. Alur Aplikasi Mobile Sebagai Penumpang

Navbar penumpang terdiri dari **Beranda, Rute, Pesanan, Tiket, dan Profil**.

### 5.1 Menu Beranda

Beranda menampilkan sapaan pengguna, informasi perjalanan terdekat, pembayaran yang masih menunggu, dan rute populer. Informasi perjalanan terdekat hanya mengambil pemesanan yang valid dan telah dibayar, bukan pemesanan yang dibatalkan.

Data beranda diambil ulang dari backend saat halaman direfresh. Artinya, akun lama tetap dapat menerima jadwal, tiket, atau perubahan status baru selama data tersebut memang berelasi dengan akun tersebut.

### 5.2 Menu Rute dan Jadwal

Penumpang memilih rute, kemudian memilih jadwal berdasarkan tanggal. Backend hanya mengirim jadwal yang masih dibuka. Ketika jadwal memasuki hari keberangkatan dan tersisa satu jam atau kurang, jadwal otomatis berubah menjadi **ditutup**. Jadwal tersebut tidak lagi tersedia untuk pemesanan penumpang, tetapi tetap tersimpan di admin sebagai arsip operasional.

Detail jadwal berisi:

- kota asal dan tujuan;
- tanggal serta jam keberangkatan;
- estimasi tiba;
- harga per kursi;
- armada, nomor polisi, foto kendaraan, dan kapasitas;
- pengemudi yang ditugaskan;
- denah serta status kursi.

### 5.3 Pemilihan Kursi

Denah kursi menyesuaikan kapasitas dan layout kendaraan. Kursi pengemudi diberi label **P** dan tidak dapat dipilih. Kursi penumpang mempunyai status tersedia, dipilih, terkunci, atau telah dipesan.

Saat penumpang mulai mengisi form, backend mengunci kursi selama 30 menit. Penguncian dilakukan di database agar dua pengguna tidak dapat memesan kursi yang sama. Jika waktu habis tanpa pemesanan, kursi dilepas kembali.

### 5.4 Form Pemesanan dan Pencegahan Keluar Tidak Sengaja

Penumpang mengisi biodata per kursi, metode pembayaran, titik jemput, titik turun, dan barang bawaan. Jika pengguna menekan tombol kembali atau berpindah halaman sebelum pemesanan selesai, aplikasi menampilkan konfirmasi:

- **Ya, batalkan pemesanan** untuk menghentikan sesi dan melepas data sementara;
- **Lanjutkan pemesanan** untuk tetap berada pada proses yang sedang berjalan.

Jika aplikasi masuk latar belakang karena tombol Home, sesi form tetap mengikuti batas waktu backend. Penguncian bukan hanya bergantung pada tampilan Flutter.

### 5.5 Titik Jemput dan Titik Turun

Lokasi dapat diambil dari GPS atau dipilih melalui peta. Latitude dan longitude dikirim ke layanan reverse geocoding untuk mendapatkan alamat dalam bentuk teks. Koordinat dan alamat disimpan bersama supaya admin dan pengemudi dapat melihat lokasi dengan jelas.

Di detail manifest pengemudi, tombol peta dapat membuka lokasi tersebut di Google Maps. Titik jemput dan titik turun disimpan terpisah karena keduanya memiliki tujuan operasional yang berbeda.

### 5.6 Barang Bawaan

Penumpang memilih salah satu metode perhitungan:

1. **Berat + jumlah item**; atau
2. **Ukuran + jumlah item**.

Aturan bawaan sistem saat ini:

- berat gratis sampai 10 kg per penumpang;
- berat maksimal 20 kg per penumpang;
- tambahan berat dikenakan Rp3.000 per kg;
- total panjang dan lebar maksimal 160 cm;
- dua item pertama gratis;
- tambahan item dikenakan Rp2.500 per item;
- maksimal 10 item per penumpang;
- total berat bagasi satu kendaraan maksimal 50 kg;
- foto barang bawaan wajib jika pengaturan tersebut aktif.

Frontend hanya menampilkan estimasi. Backend menghitung ulang seluruh biaya dan batas bagasi agar nominal tidak dapat dimanipulasi dari aplikasi.

### 5.7 Ringkasan dan Pembuatan Pemesanan

Sebelum data disimpan, penumpang melihat harga tiket, biaya bagasi, biaya channel pembayaran jika tersedia, diskon, dan total tagihan. Saat tombol pemesanan ditekan, backend menjalankan transaksi database untuk:

- memastikan jadwal masih dapat dipesan;
- memeriksa kursi masih dikunci oleh pengguna yang benar;
- menghitung ulang total;
- membuat pemesanan dan detail penumpang;
- menyimpan data bagasi;
- membuat catatan pembayaran;
- memperpanjang penguncian kursi ke batas pembayaran.

Jika salah satu proses gagal, transaksi dibatalkan seluruhnya agar tidak terjadi data setengah tersimpan.

### 5.8 Menu dan Halaman Pembayaran

Setelah pemesanan dibuat, pengguna mempunyai waktu pembayaran sekitar 5 menit sesuai pengaturan sistem atau waktu kedaluwarsa dari gateway. Menutup halaman pembayaran tidak langsung membatalkan pemesanan. Status tetap tersimpan di backend sampai pembayaran berhasil atau batas waktu benar-benar habis.

Metode pembayaran:

- **QRIS Pakasir**: pengguna membuka halaman pembayaran atau memindai QR yang tersedia.
- **Transfer bank/Pakasir**: pengguna mengikuti nomor atau channel pembayaran yang diberikan gateway.
- **Tunai**: pemesanan masuk ke manifest dengan status belum lunas dan dikonfirmasi oleh pengemudi atau admin setelah uang diterima.

Dalam mode simulasi lokal, tombol simulasi Pakasir digunakan untuk menirukan pembayaran berhasil. Backend kemudian menyinkronkan status pembayaran. Pada integrasi publik, webhook Pakasir atau refresh status melakukan rekonsiliasi yang sama.

Jika pembayaran QRIS atau transfer berhasil:

1. pembayaran menjadi **berhasil**;
2. pemesanan menjadi **terbayar**;
3. kursi menjadi **dipesan**;
4. tiket elektronik diterbitkan;
5. notifikasi pembayaran dibuat.

Jika waktu pembayaran habis dan gateway belum menyatakan berhasil, pemesanan dibatalkan dan kursi dilepas. Sebelum pembatalan, backend mencoba menyinkronkan status terakhir agar pembayaran yang sebenarnya berhasil tidak salah dianggap kedaluwarsa.

### 5.9 Menu Pesanan

Menu Pesanan menampilkan ringkasan jumlah menunggu, terbayar, dan total. Setiap kartu dapat dibuka untuk melihat:

- kode pemesanan dan status;
- jadwal serta rute;
- identitas penumpang per kursi;
- pengemudi dan armada;
- titik jemput dan titik turun;
- barang bawaan serta foto;
- rincian pembayaran;
- status hadir dan turun;
- tombol bayar jika masih menunggu;
- tombol menuju tiket jika pembayaran berhasil.

Pemesanan yang selesai tetap disimpan sebagai riwayat. Pemesanan yang kedaluwarsa atau dibatalkan tidak dianggap sebagai perjalanan aktif.

### 5.10 Menu Tiket

Tiket hanya muncul setelah pemesanan berstatus terbayar dan QR diterbitkan. Tiket terbaru dipisahkan dari tiket yang telah dipakai agar mudah ditemukan.

Halaman detail tiket menampilkan QR, nama penumpang, kursi, rute, jadwal, pengemudi, nomor HP pengemudi, foto pengemudi, armada, nomor polisi, dan foto kendaraan. Token asli tidak ditampilkan sebagai teks untuk mengurangi risiko penyalahgunaan.

QR hanya diberikan kepada pengemudi saat keberangkatan. Setelah dipindai, tiket tidak dapat dipakai untuk kedua kalinya.

### 5.11 Menu Profil

Profil menampilkan foto, nama, email, dan role. Menu di dalamnya meliputi Edit Profil, pengaturan notifikasi, pilihan bahasa, Tentang Aplikasi, serta Keluar.

Pengaturan notifikasi meminta izin perangkat dan dapat dihidupkan atau dimatikan. Pilihan bahasa disimpan pada perangkat. Keluar akan menghapus token lokal dan mencoba mencabut token pada backend.

## 6. Alur Aplikasi Mobile Sebagai Pengemudi

Navbar pengemudi terdiri dari **Beranda, Manifest, Riwayat, dan Profil**. Tombol scan QR tersedia pada bagian yang membutuhkan validasi tiket.

### 6.1 Menu Beranda

Beranda menampilkan nama pengemudi, Nomor HP, Nomor SIM berbasis NIK 16 digit, foto kendaraan, armada yang ditugaskan, jumlah jadwal aktif, jumlah penumpang, jumlah manifest, dan tugas terdekat.

Pengemudi hanya melihat jadwal yang memang ditugaskan kepadanya. Jadwal baru tidak otomatis muncul di seluruh akun pengemudi.

### 6.2 Menu Manifest

Manifest menampilkan perjalanan yang ditugaskan dan daftar penumpangnya. Pemesanan yang tampil adalah:

- pemesanan terbayar;
- pemesanan selesai; atau
- pemesanan tunai yang masih menunggu konfirmasi.

Setiap penumpang menampilkan nama, nomor kursi, nomor HP, status pembayaran, status kehadiran, titik jemput, titik turun, ringkasan bagasi, dan harga. Detail penumpang juga dapat menampilkan foto profil dan foto barang bawaan.

### 6.3 Konfirmasi Pembayaran Tunai

Penumpang tunai tetap masuk ke manifest dengan label belum lunas. Setelah uang diterima, pengemudi menekan tombol konfirmasi pembayaran. Backend mengunci data transaksi, memastikan metode benar-benar tunai dan belum pernah dikonfirmasi, lalu mengubah pembayaran menjadi berhasil dan menerbitkan tiket.

Konfirmasi ini hanya tersedia untuk pembayaran tunai. QRIS dan transfer tidak boleh dikonfirmasi manual oleh pengemudi karena statusnya berasal dari gateway.

### 6.4 Scan Tiket dan Kehadiran

Pengemudi memindai QR penumpang. Backend memeriksa:

- token tiket ditemukan;
- tiket berasal dari perjalanan milik pengemudi tersebut;
- pemesanan telah terbayar;
- tiket belum pernah dipindai.

Jika valid, tiket ditandai sudah dipindai dan status penumpang berubah dari **Belum Hadir** menjadi **Hadir**. Tampilan manifest langsung diperbarui menggunakan data respons terbaru.

### 6.5 Status Pickup, Turun, dan Barang Aman

Pengemudi dapat membuka detail lokasi jemput dan tujuan melalui peta. Setelah penumpang benar-benar turun, pengemudi menekan tombol **Turun**. Aplikasi menampilkan konfirmasi untuk memastikan penumpang telah sampai dan seluruh barang bawaan aman serta tidak tertinggal.

Penumpang hanya dapat ditandai turun setelah statusnya hadir. Setelah semua penumpang dalam satu pemesanan turun, pemesanan berubah menjadi **selesai**. Jika tidak ada pemesanan aktif tersisa pada jadwal, perjalanan juga dapat menjadi selesai.

### 6.6 Perubahan Status Perjalanan

Status perjalanan tidak boleh diloncati. Urutannya adalah:

`dibuka/ditutup -> berangkat -> selesai`

Aturan ini mencegah perjalanan langsung ditandai selesai sebelum proses keberangkatan dijalankan.

### 6.7 Menu Riwayat

Riwayat menampilkan perjalanan berjalan atau selesai sesuai filter. Informasi mencakup rute, jadwal, jumlah penumpang, pendapatan perjalanan, status, dan detail manifest. Data ini berasal dari jadwal yang pernah ditugaskan kepada akun pengemudi tersebut.

### 6.8 Menu Profil Pengemudi

Profil pengemudi mengikuti pola profil penumpang, tetapi informasinya disesuaikan dengan pekerjaan pengemudi. Pengemudi dapat mengubah foto, nama, email, Nomor HP, password, dan Nomor SIM/NIK 16 digit.

Data armada dan jadwal tidak dapat diganti sendiri oleh pengemudi karena penugasannya merupakan kewenangan admin.

## 7. Alur Web Admin

Admin login melalui halaman web menggunakan akun ber-role admin. Session web berbeda dari token aplikasi mobile. Setelah login, middleware memastikan halaman admin tidak dapat dibuka oleh penumpang atau pengemudi.

### 7.1 Menu Dasbor

Dasbor memberikan ringkasan operasional: total armada, rute aktif, jadwal hari ini, pemesanan hari ini, pemesanan menunggu, pengemudi terverifikasi, biaya hari ini, pendapatan tujuh hari, pemesanan terbaru, dan biaya terbaru.

Angka pada dasbor berasal dari database sehingga berubah saat transaksi, jadwal, pembayaran, atau biaya operasional diperbarui.

### 7.2 Menu Armada Kendaraan

Admin dapat mencari, menambah, mengedit, menonaktifkan, dan menghapus armada yang aman untuk dihapus. Data armada mencakup nomor polisi, merek, tipe kendaraan, warna, tahun, kapasitas, layout kursi, fasilitas, status, data servis, pajak, kilometer, dan foto.

Foto ditampilkan sebagai thumbnail dan dapat dibuka melalui preview. Layout kursi menjadi dasar denah kursi dinamis pada aplikasi penumpang.

### 7.3 Menu Pengemudi

Admin membuat akun pengemudi sekaligus data pengemudinya. Data yang diperiksa meliputi nama, email, Nomor HP, Nomor SIM yang memakai NIK KTP 16 digit, foto SIM, foto profil, status akun, dan status verifikasi.

Pengemudi yang belum terverifikasi tidak dapat diperlakukan sebagai pengemudi aktif. Email, Nomor HP, dan NIK harus unik.

### 7.4 Menu Rute Perjalanan

Admin mengatur kota asal, kota tujuan, jalur, jarak, estimasi durasi, harga tiket, dan status aktif. Rute aktif baru terlihat pada aplikasi setelah mempunyai jadwal yang dibuka.

Rute menjadi acuan harga dasar tiket. Total akhir tetap dihitung ulang ketika pemesanan dibuat.

### 7.5 Menu Jadwal Perjalanan

Admin menghubungkan rute, armada, pengemudi, waktu berangkat, estimasi tiba, dan status jadwal. Saat jadwal dibuat, sistem menyiapkan kursi berdasarkan kapasitas kendaraan.

Satu pengemudi tidak dapat menerima dua jadwal aktif pada tanggal yang sama. Pengemudi akan diberi label terkunci jika masih memiliki jadwal berstatus draft, dibuka, ditutup, atau berangkat. Pengemudi tersedia kembali setelah tugas sebelumnya selesai atau dibatalkan.

Satu jam sebelum keberangkatan pada hari H, jadwal yang masih dibuka otomatis menjadi ditutup. Penumpang tidak melihatnya sebagai jadwal yang dapat dipesan, sedangkan admin tetap melihat arsipnya.

### 7.6 Menu Pemesanan

Admin memantau seluruh pemesanan dan dapat memfilter berdasarkan kode, penumpang, rute, status, status pembayaran, dan tanggal. Detail pemesanan memuat akun pemesan, manifest per kursi, armada, pengemudi, lokasi, bagasi, foto, pembayaran, kehadiran, dan status turun.

Perubahan status dibatasi agar transaksi yang dibatalkan tidak dapat sembarang dikembalikan menjadi terbayar. Pembayaran gateway seharusnya mengikuti hasil sinkronisasi gateway, sedangkan pengecualian tunai dapat dikonfirmasi oleh admin.

### 7.7 Menu Pembayaran

Menu Pembayaran memisahkan pembayaran menunggu, berhasil, gagal, kedaluwarsa, serta pembayaran tunai yang membutuhkan tindakan. Admin dapat mengonfirmasi pembayaran tunai setelah uang diterima.

Konfirmasi tunai akan memperbarui pembayaran, pemesanan, kursi, tiket, dan notifikasi dalam satu alur yang konsisten. QRIS dan transfer diproses melalui Pakasir atau sinkronisasi status, bukan tombol konfirmasi manual admin biasa.

### 7.8 Menu Biaya Operasional

Admin mencatat biaya yang berhubungan dengan perjalanan, misalnya BBM, tol, parkir, servis, atau konsumsi pengemudi. Data mencakup jadwal, jenis biaya, nominal, tanggal, keterangan, pembuat data, dan bukti jika tersedia.

Data dapat ditambah, diperbarui, atau dihapus sesuai kebutuhan laporan.

### 7.9 Menu Rekap Biaya

Rekap Biaya mengelompokkan biaya operasional agar admin dapat melihat total pengeluaran pada periode tertentu. Menu ini membantu membandingkan pendapatan perjalanan dengan pengeluaran operasional.

### 7.10 Menu Laporan Pemesanan

Laporan Pemesanan menampilkan transaksi berdasarkan rentang tanggal dan status pemesanan. Admin dapat mengekspor hasilnya ke CSV untuk kebutuhan arsip atau pengolahan lanjutan.

### 7.11 Menu Laporan Transaksi

Laporan Transaksi berfokus pada pembayaran, metode pembayaran, status, nominal, dan periode transaksi. Hasil filter juga dapat diekspor ke CSV.

### 7.12 Menu Pengaturan

Pengaturan mengelompokkan konfigurasi umum, pemesanan, pembayaran, bagasi, dan notifikasi. Contohnya adalah durasi penguncian form, maksimal kursi, metode pembayaran aktif, batas bagasi, biaya tambahan, serta template notifikasi.

Admin juga dapat mengganti email dan password akun admin. Password lama wajib benar sebelum perubahan akses disimpan.

### 7.13 Menu Notifikasi

Notifikasi admin menampilkan kejadian sistem dan operasional yang perlu diketahui. Badge jumlah notifikasi belum dibaca tampil pada sidebar. Notifikasi mobile juga memiliki endpoint untuk daftar, jumlah belum dibaca, baca satu, dan baca semua.

## 8. Sinkronisasi Perubahan pada Akun Lama

Perubahan kode aplikasi berlaku untuk akun lama setelah backend dan aplikasi terbaru dijalankan. Namun, perubahan aturan tidak selalu mengubah nilai lama di database secara otomatis.

Contoh:

- Menu atau tampilan baru langsung tersedia setelah aplikasi diperbarui.
- Pemesanan baru yang dikaitkan ke akun lama akan muncul setelah refresh atau login ulang.
- Perubahan jadwal admin akan terlihat saat aplikasi mengambil ulang data.
- Aturan Nomor SIM menjadi NIK 16 digit berlaku saat data disimpan berikutnya.
- Nilai Nomor SIM lama yang belum 16 digit tidak boleh diisi otomatis karena sistem tidak mengetahui NIK asli pemilik akun.

Jadi, **kode dan fitur dapat diperbarui untuk akun lama, tetapi data identitas lama tetap membutuhkan data asli dari pemilik atau admin**.

## 9. Keamanan dan Konsistensi Data

Beberapa perlindungan utama pada sistem:

- query database menggunakan Eloquent atau Query Builder dengan binding;
- validasi dilakukan kembali di backend;
- password disimpan dalam bentuk hash;
- API mobile memakai token Sanctum;
- endpoint diberi rate limiting untuk baca, login, aksi, dan upload;
- role diperiksa melalui middleware;
- file foto dibatasi tipe dan ukurannya;
- operasi penting memakai transaksi dan penguncian baris database;
- tiket QR memiliki token unik dan hanya dapat dipindai sekali;
- pesan error ke pengguna dibuat ramah, sedangkan detail teknis disimpan pada log;
- konfigurasi sensitif disimpan di `.env` dan tidak boleh masuk repository.

## 10. Ringkasan Alur Utama dari Awal Sampai Selesai

1. Admin membuat armada, pengemudi, rute, dan jadwal.
2. Tamu melihat rute dan jadwal, lalu login sebagai penumpang.
3. Penumpang memilih jadwal dan kursi.
4. Backend mengunci kursi selama form diisi.
5. Penumpang mengisi biodata, lokasi, bagasi, dan metode pembayaran.
6. Backend memvalidasi serta membuat pemesanan menunggu pembayaran.
7. Penumpang membayar melalui QRIS, transfer, atau memilih tunai.
8. Pembayaran berhasil membuat pemesanan terbayar dan tiket diterbitkan.
9. Pengemudi melihat penumpang pada manifest perjalanan.
10. Pengemudi mengonfirmasi tunai jika diperlukan dan memindai tiket.
11. Scan tiket mengubah penumpang menjadi hadir.
12. Di tujuan, pengemudi memastikan barang aman lalu menandai penumpang turun.
13. Setelah seluruh proses selesai, pemesanan dan perjalanan masuk riwayat.
14. Admin memantau hasilnya melalui dasbor, laporan, pembayaran, dan rekap biaya.

Dokumen ini menggambarkan perilaku sistem berdasarkan kode dan menu yang tersedia pada tanggal pembaruan. Jika aturan pada menu Pengaturan diubah, nilai operasional seperti batas waktu, biaya bagasi, dan metode pembayaran dapat ikut berubah tanpa mengubah alur dasar aplikasi.
