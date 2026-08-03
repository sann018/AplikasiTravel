# Batasan Masalah dan Saran Pengembangan RuslyTravel

Dokumen ini merangkum kondisi aktual sistem RuslyTravel berdasarkan konfigurasi dan kode program
yang digunakan pada saat audit. Penjelasan di dalamnya dapat digunakan sebagai bahan penyusunan
batasan masalah pada BAB I dan saran pengembangan pada BAB V Laporan Tugas Akhir.

## 1. Basis Data yang Digunakan

RuslyTravel saat ini menggunakan:

- DBMS: **MySQL**
- host: `127.0.0.1`
- port: `3306`
- nama basis data: `aplikasi_travel`
- lingkungan penggunaan: lokal

File `database.sqlite` masih tersedia di dalam repository dan `.env.example` masih menggunakan
SQLite sebagai konfigurasi contoh. Akan tetapi, database runtime Laravel mengikuti konfigurasi
`backend/.env`, yaitu MySQL.

### Narasi batasan masalah

> Basis data sistem menggunakan MySQL yang dijalankan pada lingkungan lokal. Penelitian belum
> mencakup penempatan basis data pada server produksi, replikasi data, pencadangan otomatis,
> pemantauan performa, dan pengujian beban dengan jumlah pengguna yang besar.

### Narasi saran pengembangan

> Basis data RuslyTravel dapat ditempatkan pada server produksi atau layanan basis data terkelola
> dengan dukungan pencadangan otomatis, pemantauan performa, pengendalian akses, dan mekanisme
> pemulihan data.

## 2. Firebase Cloud Messaging

### Kondisi saat ini

Push notification melalui Firebase Cloud Messaging atau FCM belum diterapkan pada runtime
RuslyTravel. Project Flutter belum memasang `firebase_core` dan `firebase_messaging`. Backend juga
belum memiliki layanan pengiriman pesan ke FCM.

Notifikasi yang berjalan saat ini merupakan notifikasi internal. Data notifikasi disimpan dalam
database dan dimuat oleh aplikasi melalui API. Kolom `token_push` sudah tersedia pada tabel
pengguna sebagai persiapan, tetapi belum digunakan sebagai alur utama pengiriman notifikasi.

### Tahapan penerapan FCM

Penerapan FCM membutuhkan beberapa pekerjaan berikut:

1. Membuat project pada Firebase Console.
2. Mendaftarkan aplikasi Android.
3. Menambahkan berkas konfigurasi `google-services.json`.
4. Memasang package `firebase_core` dan `firebase_messaging`.
5. Meminta izin notifikasi pada perangkat.
6. Mengambil token FCM untuk setiap instalasi aplikasi.
7. Mengirim dan menyimpan token tersebut pada backend.
8. Memperbarui token ketika token perangkat berubah.
9. Menambahkan layanan pengiriman pesan dari Laravel ke FCM.
10. Menghubungkan pengiriman pesan dengan peristiwa pembayaran, keberangkatan, dan perubahan
    jadwal.
11. Menangani notifikasi ketika aplikasi berada dalam kondisi foreground, background, dan
    terminated.
12. Mengarahkan pengguna ke halaman yang sesuai ketika notifikasi ditekan.

Untuk Android, penerapan dasar FCM memiliki tingkat kesulitan sedang. Penerapan dasar diperkirakan
membutuhkan sekitar satu sampai dua hari kerja. Implementasi yang lebih lengkap, termasuk deep
link, pembaruan token, scheduler, penanganan kegagalan, dan pengujian background, dapat membutuhkan
sekitar tiga sampai tujuh hari kerja. Perkiraan tersebut dapat berubah bergantung pada konfigurasi
perangkat dan Firebase.

### Biaya FCM

Firebase Cloud Messaging termasuk layanan tanpa biaya. Biaya yang mungkin muncul bukan berasal
dari pengiriman FCM, melainkan dari layanan pendukung seperti hosting backend, basis data cloud,
penyimpanan cloud, domain, atau layanan Google Cloud lain yang digunakan.

FCM tidak wajib menggunakan hosting untuk tahap pengembangan. Backend lokal dapat mengirim pesan
ke server FCM selama komputer terhubung ke internet. Akan tetapi, penggunaan operasional yang
selalu aktif membutuhkan backend, scheduler, dan queue worker yang terus berjalan serta dapat
diakses secara stabil.

### Narasi batasan masalah

> Notifikasi pada sistem saat ini disimpan dalam basis data dan ditampilkan ketika aplikasi
> mengambil data melalui API. Push notification melalui Firebase Cloud Messaging belum diterapkan
> karena penelitian difokuskan pada penyelesaian alur utama pemesanan dan operasional.

### Narasi saran pengembangan

> Pengembangan selanjutnya dapat menerapkan Firebase Cloud Messaging agar pengingat pembayaran,
> keberangkatan, perubahan jadwal, dan status perjalanan tetap diterima ketika aplikasi berada di
> latar belakang atau tidak sedang dibuka.

## 3. Layanan Lokasi yang Digunakan

Fitur lokasi RuslyTravel menggunakan beberapa komponen yang saling melengkapi.

| Teknologi | Penggunaan |
|---|---|
| `geolocator` | Meminta izin lokasi dan mengambil koordinat GPS perangkat |
| `geocoding` | Mengubah alamat menjadi koordinat dan koordinat menjadi alamat |
| `flutter_map` | Menampilkan peta interaktif pada aplikasi Flutter |
| `latlong2` | Merepresentasikan latitude dan longitude di dalam aplikasi |
| OpenStreetMap | Menampilkan peta jalan |
| Nominatim | Mencari alamat dan melakukan reverse geocoding sebagai fallback |
| Esri World Imagery | Menampilkan pilihan peta citra satelit |
| Google Maps URL | Membuka koordinat titik jemput atau turun di Google Maps |
| `url_launcher` | Membuka tautan Google Maps dari aplikasi |

Google Maps pada implementasi ini digunakan melalui Maps URL berdasarkan koordinat, bukan melalui
Google Maps Platform API berbayar. Oleh karena itu, fitur membuka koordinat di Google Maps tidak
membutuhkan API key Google Maps.

Nama fitur yang sesuai untuk laporan:

> **Fitur Penentuan Titik Jemput dan Titik Turun Berbasis Geolokasi dan Peta Digital**

### Narasi penjelasan fitur

> Fitur penentuan titik jemput dan titik turun memanfaatkan GPS perangkat melalui Geolocator,
> tampilan peta melalui Flutter Map, serta data peta OpenStreetMap dan Esri World Imagery. Proses
> geocoding dan reverse geocoding digunakan untuk mengubah alamat menjadi koordinat maupun
> koordinat menjadi alamat. Koordinat yang tersimpan dapat dibuka kembali menggunakan Google Maps
> URL sehingga penumpang dan pengemudi dapat melihat titik lokasi yang sama.

Istilah singkat yang dapat digunakan pada tabel fitur:

- geolokasi perangkat;
- pemilihan titik melalui peta;
- pencarian alamat;
- geocoding dan reverse geocoding;
- input tautan Google Maps;
- navigasi eksternal melalui Google Maps;
- peta jalan dan citra satelit.

### Narasi batasan masalah

> Ketepatan titik lokasi bergantung pada sensor GPS, izin lokasi, koneksi internet, kualitas data
> penyedia peta, dan ketelitian pengguna ketika memilih titik. Sistem belum menghitung rute jalan,
> jarak tempuh, tarif berdasarkan jarak, atau posisi kendaraan secara langsung.

### Narasi saran pengembangan

> Layanan lokasi dapat dikembangkan dengan menambahkan perhitungan rute, estimasi jarak dan waktu
> tempuh, navigasi, serta pelacakan posisi kendaraan secara langsung. Untuk penggunaan berskala
> besar, sistem dapat menggunakan penyedia geocoding dan peta yang memiliki jaminan layanan
> produksi.

## 4. WebSocket dan Pembaruan Real-Time

WebSocket merupakan koneksi dua arah yang tetap terbuka antara aplikasi dan server. Pada API
biasa, aplikasi meminta data, server memberikan respons, kemudian koneksi selesai. Pada WebSocket,
server dapat langsung mengirim perubahan kepada aplikasi tanpa menunggu pengguna melakukan refresh.

Contoh penggunaannya pada RuslyTravel:

1. Penumpang pertama memilih kursi A1.
2. Backend mengunci kursi A1.
3. Server mengirim event perubahan kursi.
4. Kursi A1 pada perangkat penumpang lain langsung berubah menjadi tidak tersedia.

WebSocket tidak wajib untuk menjaga keamanan pemesanan. Backend RuslyTravel saat ini telah
melakukan validasi ketersediaan, penguncian data, pembatasan waktu penguncian, dan penolakan ketika
kursi telah digunakan pengguna lain. WebSocket hanya membuat perubahan tersebut lebih cepat
terlihat pada perangkat lain.

WebSocket dapat dijalankan secara lokal untuk pengembangan. Pada lingkungan produksi, server
WebSocket harus terus berjalan, dapat diakses melalui internet, menggunakan koneksi aman, dan
didukung queue worker.

Project saat ini telah menggunakan Laravel Reverb sebagai server WebSocket dan
`web_socket_channel` sebagai client Flutter. Setiap perubahan status penguncian kursi mengirim
event `kursi.diperbarui` melalui channel jadwal. Event tersebut tidak membawa data pribadi dan
hanya menjadi sinyal agar aplikasi mengambil seat map terbaru dari API. Polling berkala tetap
dipertahankan sebagai fallback ketika koneksi Reverb terputus.

### Narasi implementasi

> Sistem menerapkan komunikasi real-time berbasis WebSocket pada fitur ketersediaan kursi
> menggunakan Laravel Reverb. Ketika status kursi berubah, backend mengirim event pada channel
> jadwal dan aplikasi mengambil data kursi terbaru melalui API. Konsistensi pemesanan tetap dijaga
> oleh validasi, transaksi database, dan penguncian baris pada backend, sedangkan WebSocket
> berfungsi mempercepat pembaruan tampilan pada perangkat lain.

### Narasi batasan masalah

> Implementasi WebSocket pada penelitian ini dibatasi pada pembaruan ketersediaan kursi. Perubahan
> pembayaran, notifikasi, lokasi kendaraan, dan status perjalanan lainnya masih diperoleh melalui
> permintaan API. Server Reverb juga masih dijalankan pada lingkungan lokal sehingga harus
> diaktifkan bersama backend selama pengujian.

### Narasi saran pengembangan

> Penggunaan WebSocket dapat diperluas untuk pembaruan pembayaran, status perjalanan, posisi
> kendaraan, dan informasi operasional lain. Pada lingkungan produksi, Reverb perlu dijalankan
> sebagai proses yang selalu aktif serta dilindungi menggunakan koneksi WebSocket aman.

### Menjalankan real-time pada lingkungan lokal

Jalankan seluruh layanan backend melalui:

```bash
composer run dev
```

Perintah tersebut menjalankan Laravel, queue, Vite, Reverb, dan scheduler. Jika hanya ingin
menjalankan API dan Reverb, gunakan dua terminal:

```bash
php artisan serve
php artisan reverb:start
```

Laravel Pail tidak dijalankan dalam perintah utama tersebut karena membutuhkan ekstensi
`pcntl` yang tidak tersedia pada PHP Windows. Log aplikasi tetap dapat dipantau melalui
PowerShell tanpa memengaruhi API maupun Reverb:

```powershell
Get-Content .\storage\logs\laravel.log -Tail 50 -Wait
```

Pada konfigurasi lokal project ini, Reverb menggunakan port `6001` karena port `8080` telah
digunakan layanan lain pada komputer pengembangan.

## 5. Konfigurasi SMTP

RuslyTravel sudah menerapkan SMTP pada lingkungan runtime dengan kondisi berikut:

- mailer menggunakan SMTP;
- server menggunakan SMTP Gmail;
- port menggunakan `587`;
- koneksi menggunakan TLS;
- username dan password aplikasi telah dikonfigurasi;
- alamat pengirim telah ditentukan.

Kode pengiriman email telah tersedia untuk:

- pengiriman kode verifikasi akun;
- pengiriman ulang kode verifikasi;
- pemulihan atau reset password.

Konfigurasi `MAIL_MAILER=log` pada `.env.example` hanya berfungsi sebagai konfigurasi contoh dan
bukan konfigurasi runtime yang sedang digunakan.

### Narasi implementasi

> Sistem menggunakan SMTP Gmail untuk mengirimkan kode verifikasi akun dan informasi pemulihan
> password. Kredensial SMTP disimpan pada konfigurasi environment backend agar tidak dituliskan
> secara langsung di dalam source code.

### Narasi batasan masalah

> Pengiriman email telah menggunakan SMTP Gmail pada lingkungan pengembangan. Penelitian belum
> mencakup penggunaan domain email resmi perusahaan, pemantauan kegagalan pengiriman, pengelolaan
> reputasi pengirim, retry berbasis queue, serta pengujian pengiriman email dalam jumlah besar.

### Narasi saran pengembangan

> Pengembangan selanjutnya dapat menggunakan layanan email transaksional atau SMTP dengan domain
> resmi perusahaan serta menambahkan queue, retry, dan pencatatan status pengiriman untuk
> meningkatkan keandalan email.

## 6. Ringkasan Kondisi Aktual

| Bagian | Kondisi aktual |
|---|---|
| Basis data | MySQL lokal |
| SMTP | Sudah diterapkan menggunakan Gmail SMTP |
| Firebase Cloud Messaging | Belum diterapkan |
| Notifikasi | Notifikasi internal berbasis database dan API |
| WebSocket | Sudah diterapkan untuk pembaruan ketersediaan kursi |
| Peta utama | OpenStreetMap dan Esri World Imagery |
| Geocoding | Plugin Geocoding dan Nominatim |
| GPS perangkat | Geolocator |
| Google Maps | Maps URL, bukan Google Maps API berbayar |
| Payment gateway | Pakasir dalam mode sandbox |
| Hosting | Backend lokal dengan tunnel ngrok pada konfigurasi saat audit |

## 7. Batasan Masalah Ringkas untuk BAB I

> Sistem RuslyTravel dibatasi pada pengelolaan perjalanan, pemesanan kursi, pembayaran, tiket
> elektronik, operasional pengemudi, dan administrasi melalui aplikasi mobile serta web admin.
> Backend, basis data MySQL, dan penyimpanan berkas masih dijalankan pada lingkungan lokal sehingga
> penelitian belum mencakup deployment produksi, ketersediaan layanan selama 24 jam, pencadangan
> otomatis, dan pengujian beban berskala besar. Pembayaran digital menggunakan Pakasir dalam mode
> sandbox sehingga transaksi belum melibatkan perpindahan dan penyelesaian dana secara nyata.
> Notifikasi yang diterapkan berupa notifikasi internal berbasis database, sedangkan push
> notification melalui Firebase Cloud Messaging belum diterapkan. Pembaruan ketersediaan kursi
> telah menggunakan WebSocket melalui Laravel Reverb dengan polling API sebagai fallback.
> Penentuan titik jemput dan titik turun menggunakan GPS, OpenStreetMap,
> Nominatim, Esri World Imagery, dan Google Maps URL dengan ketepatan yang bergantung pada perangkat,
> koneksi internet, data penyedia peta, dan konfirmasi pengguna. Pengujian aplikasi mobile
> difokuskan pada perangkat Android.

## 8. Saran Ringkas untuk BAB V

> Pengembangan selanjutnya disarankan untuk menempatkan backend dan basis data MySQL pada
> lingkungan produksi yang memiliki HTTPS, pencadangan otomatis, monitoring, scheduler, dan queue
> worker yang selalu aktif. Firebase Cloud Messaging dapat diterapkan agar pengguna menerima
> pengingat pembayaran, keberangkatan, dan perubahan jadwal ketika aplikasi tidak sedang dibuka.
> Penggunaan WebSocket dapat diperluas untuk status pembayaran, perjalanan, dan lokasi kendaraan.
> Integrasi Pakasir dapat dialihkan dari sandbox ke mode produksi setelah konfigurasi merchant,
> webhook, penanggung biaya, dan proses rekonsiliasi diverifikasi. Fitur lokasi juga dapat
> dikembangkan dengan perhitungan rute, jarak, estimasi waktu tempuh, navigasi, dan pelacakan
> kendaraan secara langsung. Pengiriman email dapat ditingkatkan melalui domain resmi, queue,
> retry, dan monitoring status pengiriman.

## 9. Referensi Resmi

- Firebase Cloud Messaging: <https://firebase.google.com/docs/cloud-messaging>
- FCM untuk Flutter: <https://firebase.google.com/docs/cloud-messaging/flutter/get-started>
- Firebase Pricing: <https://firebase.google.com/pricing>
- Laravel Broadcasting dan WebSocket: <https://laravel.com/docs/13.x/broadcasting>
- Google Maps URL: <https://developers.google.com/maps/documentation/urls/get-started>
- Pakasir: <https://pakasir.com/p/docs>

## Catatan Demo Sidang Lokal — 27 Juli 2026

Skenario yang direkomendasikan adalah menjalankan Laravel API, Reverb, scheduler, dan Flutter pada
laptop yang sama. LDPlayer dihubungkan melalui ADB reverse untuk port `8000` dan `6001`, sedangkan
Flutter Web serta web admin memakai `127.0.0.1`.

Ngrok tidak diperlukan untuk skenario tersebut. Ngrok adalah tunnel sementara menuju layanan
lokal, bukan hosting permanen. Jika API port `8000` ditunnel, koneksi Reverb port `6001` tidak
otomatis ikut terbuka dan memerlukan tunnel atau reverse proxy tersendiri.
