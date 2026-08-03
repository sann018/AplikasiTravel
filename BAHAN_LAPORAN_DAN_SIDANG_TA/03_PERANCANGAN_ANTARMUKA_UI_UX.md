# Perancangan Antarmuka (UI/UX) RuslyTravel

## 1. Pendekatan Perancangan

Antarmuka RuslyTravel dibedakan berdasarkan konteks penggunaan. Aplikasi mobile mengutamakan
interaksi singkat dengan satu kolom, card, tombol aksi yang lebar, dan navigasi bawah. Web admin
mengutamakan pemindaian data melalui sidebar, filter, tabel responsif, status, serta halaman
detail. Kedua antarmuka memakai identitas visual yang sama, yaitu navy sebagai warna utama,
oranye sebagai aksen, putih sebagai permukaan, hijau untuk kondisi berhasil, serta merah untuk
kondisi gagal atau dibatalkan.

Perancangan tidak hanya mempertimbangkan tampilan, tetapi juga status proses. Tombol dan informasi
yang ditampilkan berubah sesuai kondisi pemesanan, pembayaran, perjalanan, dan hak akses.
Contohnya, QR tiket tidak tersedia sebelum pembayaran sah, countdown tunai tidak ditampilkan, dan
tombol pemindaian ditolak ketika perjalanan telah selesai.

## 2. Antarmuka Tamu

Tamu dapat membuka aplikasi tanpa autentikasi untuk melihat:

1. splash dan identitas aplikasi;
2. banner serta informasi penting;
3. rute dan jadwal yang sedang dibuka;
4. jumlah kursi yang tersedia;
5. detail jadwal, kendaraan, pengemudi, harga, dan denah kursi.

Ketika tamu memilih tindakan pemesanan, aplikasi mengarahkan ke autentikasi. Pola ini memisahkan
informasi publik dan transaksi yang membutuhkan identitas. Card jadwal yang tidak memiliki kursi
tersedia disaring pada sisi tampilan mobile sehingga tidak muncul di beranda.

Sumber:

- [`frontend/lib/features/home/bagian_beranda/halaman_informasi.dart`](../frontend/lib/features/home/bagian_beranda/halaman_informasi.dart#L423)
- [`frontend/lib/features/home/bagian_beranda/kartu_beranda.dart`](../frontend/lib/features/home/bagian_beranda/kartu_beranda.dart)

## 3. Antarmuka Penumpang

### 3.1 Autentikasi dan Profil

Form registrasi meminta nama, email Gmail, nomor HP, dan kata sandi. Pesan kesalahan ditempatkan
dekat field agar pengguna mengetahui input yang harus diperbaiki. Verifikasi email, lupa kata
sandi, ganti kata sandi, foto profil, dan edit profil berada dalam alur akun yang sama.

### 3.2 Daftar dan Detail Jadwal

Daftar jadwal menyajikan rute, tanggal, jam, kendaraan, sisa kursi, harga, dan aksi pemesanan.
Detail jadwal memuat ringkasan perjalanan, informasi kendaraan/pengemudi, denah kursi, legenda
warna, serta ringkasan harga. Status visual kursi adalah:

| Status | Warna | Makna |
|---|---|---|
| tersedia | hijau | dapat dipilih |
| pilihan pengguna | kuning/oranye | sedang dipilih pada perangkat |
| terkunci | abu-abu | sedang diproses pengguna lain atau sesi aktif |
| dipesan | merah | telah menjadi tiket |
| nonaktif | abu-abu muda | tidak digunakan pada jadwal |

Warna bukan satu-satunya petunjuk karena tersedia legenda teks dan status.

### 3.3 Konfirmasi Pemesanan

Halaman konfirmasi memuat:

- ringkasan rute dan kursi yang dikunci;
- sisa waktu pengisian form;
- data pemesan dan setiap penumpang;
- titik jemput dan titik turun;
- alamat serta catatan/patokan;
- barang bawaan dan foto;
- subtotal tiket;
- tombol membuat pemesanan.

Metode pembayaran tidak dipilih pada tahap ini. Setelah form berhasil dikirim, backend membuat
pemesanan berstatus `menunggu_pembayaran`, kemudian aplikasi membuka halaman pembayaran.

### 3.4 Pemilihan Lokasi

Pengguna dapat memakai GPS perangkat, mengetuk/geser peta, atau memakai hasil geocoding nama
tujuan. Alamat diisi melalui reverse geocoding, tetapi tetap tersedia fallback teks manual ketika
alamat tidak ditemukan. Titik turun awal diarahkan dari kota tujuan rute, bukan dari posisi GPS
pengguna.

### 3.5 Pembayaran

Susunan halaman pembayaran terdiri atas:

1. banner status;
2. detail perjalanan;
3. rincian pembayaran;
4. tombol aksi utama.

Nominal, fee, total, metode, batas waktu, serta waktu pembayaran hanya ditampilkan pada satu card
rincian pembayaran untuk menghindari pengulangan. QRIS/VA menampilkan tombol melanjutkan
pembayaran. Metode tunai menampilkan instruksi pembayaran kepada pengemudi dan tidak mempunyai
countdown provider.

### 3.6 Detail Pemesanan dan Tiket

Detail pemesanan menyajikan informasi pemesan, perjalanan, pembayaran, penumpang/kursi, titik
jemput/turun, dan barang bawaan. Nilai pembayaran diratakan agar mudah dipindai.

Setiap penumpang mempunyai tiket sendiri. Jika satu pemesanan berisi beberapa penumpang, pilihan
nama pemilik tiket berada di atas card sehingga tiket tidak ditumpuk panjang ke bawah. Card tiket
memuat rute, waktu, kursi, QR, pengemudi, kendaraan, dan status pemindaian. Fitur unduh merender
card yang sama menjadi PNG supaya hasil di galeri konsisten dengan tampilan.

## 4. Antarmuka Pengemudi

Menu utama pengemudi memakai istilah **Perjalanan**, karena halaman tersebut berisi daftar tugas
jadwal. Istilah **Manifes Penumpang** digunakan di dalam detail perjalanan untuk menyebut daftar
penumpang, kursi, kontak, lokasi, barang, pembayaran, serta status operasional.

Fungsi utama antarmuka pengemudi:

- melihat perjalanan hari ini dan riwayat;
- membuka detail rute, armada, waktu, dan jumlah penumpang;
- membuka alamat pada aplikasi peta;
- menghubungi penumpang melalui WhatsApp;
- menandai dijemput dan diturunkan;
- mengonfirmasi pembayaran tunai;
- memindai QR tiket;
- memulai dan menyelesaikan perjalanan;
- mencatat biaya operasional beserta bukti.

Tombol aksi dibuat sesuai urutan status agar pengemudi tidak dapat melompati proses. Backend tetap
memvalidasi urutan tersebut walaupun request dikirim di luar Flutter.

## 5. Antarmuka Web Admin

### 5.1 Kerangka

Web admin memakai sidebar, topbar, judul halaman, deskripsi, area konten, notifikasi, dan profil
admin. Halaman detail/form mempunyai tautan kembali dengan tujuan jelas. Pada layar kecil,
sidebar dapat dibuka sebagai panel dan tabel dapat digeser horizontal.

### 5.2 Dasbor

Dasbor memuat card statistik armada, pengemudi, rute, jadwal, booking menunggu, pendapatan, biaya,
dan rute yang paling banyak diselesaikan penumpang. Daftar jadwal hari ini memberi akses cepat ke
detail operasional.

### 5.3 Master dan Operasional

Halaman armada, pengemudi, rute, dan jadwal memakai pola:

```text
Judul + deskripsi                         Tombol tambah
Filter
Ringkasan (jika relevan)
Tabel responsif + status + aksi
Pagination / empty state
```

Form jadwal menampilkan armada, rute, pengemudi, waktu, status, dan kursi yang dinonaktifkan.
Pilihan pengemudi dapat diberi tanda tidak tersedia berdasarkan tanggal yang dipilih.

### 5.4 Monitoring

Halaman pemesanan dan pembayaran menampilkan data operasional untuk ditindaklanjuti. Detail
pemesanan memisahkan perjalanan, pemesan, pembayaran, penumpang, dan barang bawaan. Detail jadwal
hanya menghitung pemesanan dengan pembayaran berhasil agar ringkasan pendapatan serta penumpang
tidak tercampur booking batal.

### 5.5 Laporan

Laporan pemesanan dan transaksi mempunyai filter status, metode, tanggal mulai, dan tanggal
selesai. Card ringkasan menampilkan jumlah serta nilai agregat. Nilai pendapatan RuslyTravel,
biaya admin Pakasir, dan total yang dibayar penumpang dipisahkan.

### 5.6 Pengaturan dan Notifikasi

Pengaturan mengelompokkan identitas aplikasi, pemesanan, pembayaran, notifikasi, akun admin,
informasi penting, dan banner beranda. Daftar notifikasi dikelompokkan berdasarkan tanggal dan
membedakan status baca.

Monitor Tiket disediakan untuk mencari kode tiket serta memeriksa status pemindaian. Laporan
okupansi tidak ditampilkan karena tidak termasuk ruang lingkup proposal.

## 6. Prinsip UX yang Diterapkan

1. **Visibility of system status**: status, countdown, loading, dan hasil aksi selalu ditampilkan.
2. **Error prevention**: tombol dinonaktifkan atau ditolak ketika status tidak memenuhi syarat.
3. **Consistency**: nama status, warna, card, tombol, dan format tanggal konsisten.
4. **Recognition over recall**: pengguna memilih kursi dari peta dan memilih metode dari daftar,
   bukan mengingat kode.
5. **Progressive disclosure**: daftar hanya memuat ringkasan; informasi panjang berada di detail.
6. **Responsive design**: grid berubah menjadi satu kolom dan tabel dapat scroll.
7. **Accessible actions**: tombol ikon mempunyai label/tooltip pada web, fokus keyboard terlihat,
   dan status tidak hanya bergantung pada warna.
8. **Backend-driven state**: UI tidak mengarang status, fee, atau deadline.

## 7. Narasi Akademik Siap Pakai

> Perancangan antarmuka RuslyTravel menggunakan pendekatan berbasis peran dan status proses.
> Aplikasi mobile dirancang dengan pola satu kolom agar sesuai dengan penggunaan saat bepergian,
> sedangkan web admin memanfaatkan tabel, filter, dan ringkasan untuk mendukung pemantauan data.
> Informasi yang sama tidak ditampilkan berulang pada beberapa card. Aksi utama berubah mengikuti
> keadaan transaksi, misalnya countdown hanya muncul pada pembayaran digital yang masih aktif,
> sedangkan tiket hanya dapat dibuka setelah pembayaran terverifikasi. Konsistensi visual dijaga
> melalui warna navy dan oranye serta komponen card, status, tombol, dan pesan kesalahan yang
> seragam.

## 8. Pemetaan Gambar yang Disarankan dalam Laporan

| Gambar | Penjelasan yang Menyertai |
|---|---|
| Beranda tamu | informasi publik, jadwal tersedia, dan pembatasan akses pemesanan |
| Detail jadwal/pilih kursi | denah, legenda status, ringkasan, dan harga |
| Konfirmasi pemesanan | data penumpang, lokasi, barang, serta batas pengisian |
| Pembayaran | banner status, detail perjalanan, rincian pembayaran, aksi |
| Detail pemesanan | hubungan pemesan, penumpang, status, lokasi, dan pembayaran |
| Tiket & QR | tiket per penumpang dan token pemindaian |
| Perjalanan pengemudi | daftar tugas dan status perjalanan |
| Manifes Penumpang | tindakan jemput, bayar tunai, scan, dan turun |
| Dasbor admin | KPI dan ringkasan operasional |
| Detail jadwal admin | penumpang berhasil, pendapatan, biaya, surplus/defisit |
| Laporan transaksi | pemisahan nominal, fee, dan total penumpang |

## Pembaruan Card Statistik — 27 Juli 2026

Card statistik menggunakan pola visual yang sama, tetapi maknanya dibedakan melalui judul, nilai
utama, dan keterangan singkat. Perhitungan dilakukan di controller; Blade hanya menampilkan:

- dasbor: pendapatan minggu ini serta rute paling banyak dikunjungi;
- biaya operasional: total biaya periode, jumlah transaksi, dan rute dengan biaya tertinggi;
- laporan pemesanan: total, terbayar, dibatalkan, selesai, dan total nilai;
- laporan transaksi: pendapatan tahun, pendapatan bulan, transaksi berhasil, dan pendapatan
  filter aktif.

Card rute terpopuler kini menampilkan `jumlah pemesanan selesai · jumlah penumpang`. Dua ukuran
tersebut mencegah pengguna mengira satu booking selalu sama dengan satu penumpang.

Rincian query dan potongan tampilan tersedia pada
[`10_STATISTIK_DASBOR_DAN_LAPORAN.md`](10_STATISTIK_DASBOR_DAN_LAPORAN.md).
