# Rangkuman Belajar Koding dan Sidang

File ini dibuat sebagai catatan belajar untuk memahami isi project `AplikasiTravel`. Fokusnya bukan menghafal semua kode, tetapi memahami alur, alasan teknis, dan bagian yang sering ditanya saat sidang tugas akhir.

## 1. Gambaran Umum Project

Project ini adalah aplikasi travel untuk Rusly Travel dengan dua bagian utama:

- `frontend`: aplikasi mobile Flutter untuk penumpang dan pengemudi.
- `backend`: aplikasi Laravel untuk API mobile dan web admin.

Backend menjadi pusat aturan bisnis. Flutter hanya menampilkan data, mengirim input user, dan menerima hasil dari API. Aturan penting seperti harga, booking kursi, bagasi, pembayaran, role user, dan validasi tetap dicek di Laravel supaya lebih aman.

## 2. Folder dan File yang Paling Penting

### Frontend Flutter

- `frontend/lib/main.dart`: titik awal aplikasi Flutter.
- `frontend/lib/app.dart`: pengaturan utama aplikasi.
- `frontend/lib/core/services/travs_api_service.dart`: tempat Flutter memanggil API Laravel.
- `frontend/lib/core/services/travs_auth_service.dart`: mengatur login, token, dan sesi user.
- `frontend/lib/core/services/travs_location_service.dart`: mengambil lokasi user.
- `frontend/lib/features/auth`: tampilan login dan register.
- `frontend/lib/features/home`: halaman utama sesuai role.
- `frontend/lib/features/schedule`: detail jadwal dan form pemesanan.
- `frontend/lib/features/booking`: detail pemesanan.
- `frontend/lib/features/payment`: tampilan pembayaran.
- `frontend/lib/features/ticket`: detail tiket dan QR.
- `frontend/lib/features/pickup`: pilih titik jemput di peta.

### Backend Laravel

- `backend/routes/api.php`: daftar endpoint API untuk mobile.
- `backend/app/Http/Controllers/Api`: controller API.
- `backend/app/Http/Middleware/EnsureUserHasRole.php`: pembatas akses berdasarkan role.
- `backend/app/Providers/AppServiceProvider.php`: konfigurasi global, termasuk rate limit.
- `backend/app/Services/BookingService.php`: proses utama booking.
- `backend/app/Services/BookingCalculator.php`: hitung biaya kursi dan bagasi.
- `backend/app/Services/PakasirService.php`: integrasi pembayaran Pakasir.
- `backend/app/Models`: model tabel database.
- `backend/database/migrations`: struktur tabel database.
- `backend/database/seeders`: data awal/demo.
- `backend/tests/Feature`: pengujian fitur utama.

Catatan tambahan:

- `travs_location_service.dart` mengubah koordinat pickup menjadi alamat. Urutannya sederhana: coba geocoding bawaan perangkat dulu, jika gagal coba OpenStreetMap, jika tetap gagal baru tampilkan koordinat.
- `travs_schedule_detail_page.dart` menampilkan denah kursi mobil top-view. Nomor kursi diambil dari backend, sedangkan kursi pengemudi hanya penanda visual dengan label `P`.
- Foto kendaraan tersimpan di data armada. Admin dapat mengunggah foto mobil, lalu foto ini dipakai ulang di jadwal mobile, detail jadwal, ringkasan pilih kursi, dan dashboard pengemudi.
- `PakasirService.php` mengurus komunikasi ke Pakasir dan menyiapkan `checkout_url` agar Flutter bisa membuka halaman pembayaran.
- `routes/api.php` menyediakan endpoint versi `/api/v1`. Endpoint lama `/api` masih dipertahankan supaya aplikasi lama tidak langsung rusak.
- Token login mobile punya waktu kedaluwarsa. Jika token sudah expired, aplikasi menghapus session lokal dan user harus login ulang.
- Forgot password membuat token yang disimpan dalam bentuk hash dan mengirim email reset jika mailer sudah dikonfigurasi.
- Driver manifest punya dua aksi lapangan: pickup penumpang dan penurunan penumpang. Scan QR tetap menjadi bukti boarding/hadir.

## 3. Alur Utama Pemesanan

1. Penumpang membuka jadwal.
2. Penumpang memilih jadwal, kursi, titik jemput, dan bagasi.
3. Flutter mengirim data ke API Laravel.
4. Laravel memvalidasi data.
5. Laravel menghitung biaya melalui `BookingCalculator`.
6. Laravel menyimpan pemesanan melalui `BookingService`.
7. Laravel membuat data pembayaran dan tiket.
8. Flutter menampilkan detail pemesanan, pembayaran, dan tiket.

Hal penting: perhitungan harga tidak boleh hanya di Flutter, karena data dari frontend bisa dimanipulasi. Karena itu backend tetap menghitung ulang.

## 3.1 Alur Titik Jemput

1. Penumpang menekan `Gunakan GPS` atau `Pilih di Peta`.
2. Flutter mendapat latitude dan longitude.
3. Flutter meminta alamat dari `TravSLocationService`.
4. Sistem mencoba mengubah koordinat menjadi alamat lengkap seperti jalan, desa/kelurahan, kecamatan, kabupaten/kota, dan provinsi.
5. Alamat disimpan bersama pemesanan agar admin dan pengemudi tidak hanya melihat angka koordinat.

Bagian yang berpotensi ditanya saat sidang: mengapa tetap menyimpan koordinat walaupun alamat sudah ada. Jawabannya, alamat dipakai agar mudah dibaca manusia, sedangkan koordinat dipakai untuk membuka lokasi tepat di Google Maps.

## 3.2 Alur Pembayaran Pakasir

1. Penumpang memilih metode QRIS.
2. Backend membuat draft pemesanan dan pembayaran.
3. Backend membuat transaksi ke Pakasir melalui `PakasirService`.
4. Backend mengirim `checkout_url` ke Flutter.
5. Flutter membuka halaman pembayaran Pakasir.
6. Setelah pembayaran berhasil, webhook/refresh status mengubah pembayaran menjadi berhasil dan tiket diterbitkan.

Bagian penting untuk sidang: API key Pakasir tidak disimpan di Flutter. API key hanya ada di Laravel supaya lebih aman dan tidak mudah dibaca dari aplikasi mobile.

Untuk demo tugas akhir, mode yang dipakai adalah **simulasi Pakasir lokal**. Artinya tidak ada transaksi uang nyata dan tidak ada callback publik dari Pakasir. Simulasi dipilih supaya demo tetap stabil di laptop, tetapi alur konsepnya tetap sama: backend memvalidasi pembayaran, status berubah, lalu tiket QR diterbitkan.

Jika ditanya kenapa tidak pakai transaksi nyata, jawab:

> Fokus tugas akhir ini adalah membuktikan konsep sistem pemesanan sampai tiket digital. Transaksi nyata butuh server publik, HTTPS, webhook URL tetap, dan konfigurasi production. Untuk demo lokal, simulasi lebih aman dan tidak mengganggu validitas alur sistem.

Jika ditanya webhook signature verification, jawab:

> Signature verification belum diperlukan pada mode simulasi lokal karena aplikasi tidak menerima callback publik dari luar. Fitur itu baru relevan saat sistem benar-benar dipasang ke server production dan menerima webhook asli dari payment gateway.

Pembayaran tunai berbeda dari QRIS/transfer. Pada metode tunai, pemesanan tetap masuk ke admin dan manifest pengemudi sebagai belum bayar. Setelah uang diterima, admin atau pengemudi yang sesuai jadwal dapat mengonfirmasi pembayaran tunai, lalu sistem menerbitkan tiket digital.

## 3.3 Alur Pengemudi

1. Pengemudi login memakai akun yang dibuat admin.
2. Pengemudi membuka manifest perjalanan.
3. Pengemudi membuka alamat jemput di Google Maps jika perlu.
4. Pengemudi menandai penumpang sudah dijemput.
5. Pengemudi scan QR tiket saat boarding.
6. Setelah penumpang sampai tujuan, pengemudi menandai penumpang sudah turun.
7. Status perjalanan dapat diubah menjadi berangkat dan selesai sesuai urutan.

Sistem juga menolak jadwal yang membuat satu pengemudi mendapat dua perjalanan pada tanggal dan jam yang sama. Validasi ini penting supaya tugas pengemudi tidak bentrok secara operasional.

## 4. Alur Halaman Rute dan Jadwal

Alur halaman rute dan jadwal dibuat bertahap:

1. User membuka tab `Rute`.
2. Flutter menampilkan daftar rute dalam bentuk bookmark card.
3. User memilih salah satu rute.
4. Flutter memfilter jadwal dari data yang sudah dimuat.
5. User memilih jadwal dengan tombol `Detail` atau `Pesan`.
6. Flutter membuka detail jadwal.
7. Detail jadwal mengambil data lebih lengkap dari backend.

Bagian penting untuk sidang:

- Klik rute tidak langsung mengeksekusi query baru.
- Jadwal difilter secara lokal dari data jadwal yang sudah diterima.
- Chip `Semua`, `Besok`, dan `Hari/Jam` juga bekerja di data lokal, bukan query baru.
- Card rute menampilkan gambar daerah tujuan.
- Card jadwal menampilkan gambar mobil/kendaraan yang digunakan.
- Query detail baru dilakukan saat user benar-benar membuka salah satu jadwal.
- Backend tetap punya rate limit untuk melindungi endpoint jika user klik berulang.

Cara menjelaskan saat sidang:

> Halaman rute memakai data jadwal yang sudah dimuat dari backend. Saat user menekan rute, aplikasi hanya memfilter data lokal, sehingga lebih ringan dan tidak membuat query berulang.

## 5. Alur Navbar dan Tombol Back

Navbar bawah penumpang berisi `Beranda`, `Rute`, `Pesanan`, `Tiket`, dan `Profil`. Menu aktif diberi indikator bulat yang bergeser agar user tahu sedang berada di halaman mana.

Aturan tombol back:

- Beranda tidak memakai tombol back.
- Tab selain beranda punya tombol back untuk kembali ke beranda.
- Halaman detail tetap memakai `AppBar` dengan tombol back bawaan.
- Halaman detail pemesanan menyediakan tombol untuk membuka halaman tiket.

## 6. Konsep Rate Limit untuk Batasi Spam Klik

Rate limit adalah pembatas jumlah request dalam waktu tertentu. Tujuannya agar user yang klik tombol berkali-kali tidak membuat backend menjalankan query terlalu banyak.

Di project ini rate limit dibuat di:

- `backend/app/Providers/AppServiceProvider.php`
- `backend/routes/api.php`

Jenis rate limit yang dipakai:

- `travs-read`: untuk mengambil data, misalnya jadwal dan dashboard.
- `travs-auth`: untuk login dan register.
- `travs-action`: untuk aksi penting, misalnya booking, pembayaran, pickup, scan tiket.
- `travs-upload`: untuk upload foto bagasi.

Cara menjelaskan saat sidang:

> Sistem memakai throttle Laravel untuk membatasi request berdasarkan user yang login atau IP address. Jika request terlalu sering, backend mengembalikan status 429, sehingga query tidak terus dijalankan.

## 7. Kenapa Ada Service Class

Tidak semua logic ditulis langsung di controller. Controller cukup menerima request, memanggil service, lalu mengembalikan response.

Service dipakai agar kode lebih rapi:

- `BookingService`: mengurus proses booking.
- `BookingCalculator`: mengurus hitungan biaya.
- `PakasirService`: mengurus komunikasi dengan payment gateway.

Cara menjelaskan saat sidang:

> Controller bertugas sebagai pintu masuk request. Service berisi aturan bisnis agar kode tidak menumpuk di controller dan lebih mudah diuji.

## 8. Bagasi

Fitur bagasi mendukung dua cara hitung:

- berat + jumlah item
- ukuran + jumlah item

Aturan penting:

- maksimal gratis 2 item
- item di atas 2 dikenakan biaya tambahan Rp 3000 per item
- berat gratis sampai 10 kg
- ukuran dihitung dari panjang x lebar dengan batas gratis 160 cm
- foto bagasi dapat diupload

Bagian yang perlu dipahami:

- Input dari Flutter dikirim sebagai angka, bukan slider.
- Backend tetap memvalidasi dan menghitung biaya tambahan.
- Upload foto divalidasi tipe dan ukuran file.

## 9. Booking, Kursi, dan Lock

Booking kursi perlu hati-hati karena bisa ada dua user memilih kursi yang sama. Karena itu backend memakai proses yang lebih aman pada bagian booking.

Konsep yang perlu dipahami:

- Satu kursi pada satu jadwal tidak boleh dipakai dua penumpang.
- Form pemesanan punya batas waktu 30 menit.
- Pembayaran punya batas waktu 5 menit.
- Operasi penting memakai transaksi database.
- Seat lock mencegah bentrok saat user booking bersamaan.
- Mode tamu atau user yang belum login hanya boleh melihat status kursi, tidak boleh memilih kursi atau mengisi data pemesanan.
- Tampilan kursi di mobile dibuat seperti denah mobil. Posisi kursi disusun fleksibel dari jumlah kursi pada `seat_map`, sehingga bisa menyesuaikan kendaraan kecil atau kendaraan dengan kursi lebih banyak.

Cara menjelaskan saat sidang:

> Seat lock digunakan agar kursi yang sedang dipilih tidak langsung direbut user lain. Backend tetap mengecek ulang saat pemesanan disimpan supaya tidak terjadi double booking.

> User yang belum login tidak diberi akses memilih kursi, karena proses pemilihan kursi berhubungan dengan seat lock. Dengan cara ini, kursi hanya dikunci oleh penumpang yang sudah punya sesi login.

## 10. Pembayaran

Pembayaran dihubungkan melalui backend, bukan langsung dari Flutter ke payment gateway.

Alasannya:

- credential payment gateway lebih aman di backend
- status pembayaran bisa diverifikasi backend
- backend bisa mencocokkan pembayaran dengan booking
- frontend cukup menampilkan instruksi pembayaran dan status terbaru

File penting:

- `backend/app/Services/PakasirService.php`
- `backend/app/Http/Controllers/Api/PaymentGatewayController.php`
- `frontend/lib/features/payment/travs_payment_preview_page.dart`

## 11. Tiket dan QR

Setelah pemesanan valid, sistem membuat tiket elektronik. Tiket memiliki QR untuk dipindai pengemudi.

Hal yang perlu dipahami:

- QR berisi kode tiket.
- Backend memverifikasi kode tiket.
- QR tidak boleh bebas dipakai berkali-kali tanpa aturan.
- Pengemudi memakai endpoint scan tiket.
- Token rahasia QR tidak perlu ditampilkan sebagai teks mentah kepada penumpang. Halaman tiket lebih baik menampilkan informasi yang membantu, seperti jadwal, nama pengemudi, nomor kendaraan, dan foto kendaraan.

## 12. Role User

Project memakai role agar tiap user hanya mengakses fitur yang sesuai.

Role utama:

- `penumpang`: booking, pembayaran, tiket.
- `pengemudi`: melihat trip, manifest, pickup, scan tiket.
- `admin`: mengelola data master, jadwal, pembayaran, laporan, pengaturan.

Tampilan mobile dibuat sesuai role:

- Tamu dapat melihat rute, jadwal, dan info pemesanan, tetapi belum bisa menyimpan pemesanan atau tiket.
- Penumpang mendapat menu beranda, rute, pesanan, tiket, dan profil.
- Pengemudi mendapat menu beranda, manifest, riwayat perjalanan, dan profil.
- Form masuk dipakai bersama oleh penumpang dan pengemudi. Pendaftaran mandiri hanya untuk penumpang, sedangkan akun pengemudi dibuat admin.
- Profil penumpang dan pengemudi memiliki pengaturan notifikasi perangkat dan pilihan bahasa. Pengaturan ini disimpan di sisi aplikasi agar user bisa menyesuaikan pengalaman penggunaan.

File penting:

- `backend/app/Http/Middleware/EnsureUserHasRole.php`
- `backend/routes/api.php`
- `backend/app/Models/User.php`

Cara menjelaskan saat sidang:

> Role dipakai untuk membatasi akses. Misalnya endpoint pengemudi tidak bisa diakses oleh penumpang karena route dilindungi middleware role.

## 13. Upload Foto Bagasi

Upload foto bagasi perlu validasi agar tidak sembarang file masuk ke server.

Yang dicek:

- file harus gambar
- ekstensi dibatasi, misalnya jpg, jpeg, png, webp
- ukuran file dibatasi
- path file disimpan ke database

Cara menjelaskan saat sidang:

> Upload foto tidak langsung dipercaya. Backend mengecek tipe dan ukuran file sebelum menyimpan.

## 14. Bagian yang Agak Rumit tetapi Memang Perlu

Beberapa bagian terlihat lebih rumit karena tujuannya menjaga keamanan dan konsistensi data.

- Transaksi database pada booking.
- Lock kursi agar tidak double booking.
- Integrasi payment gateway.
- Verifikasi QR tiket.
- Rate limit untuk mencegah spam request.

Bagian tersebut sebaiknya tidak disederhanakan berlebihan karena berhubungan dengan keamanan data dan alur transaksi.

## 15. Pertanyaan yang Berpotensi Muncul Saat Sidang

### Apa perbedaan frontend dan backend?

Frontend adalah tampilan yang dipakai user. Backend adalah server yang menyimpan data, mengecek aturan bisnis, dan menyediakan API.

### Kenapa harga dihitung di backend?

Karena input dari frontend bisa dimanipulasi. Backend harus menjadi sumber kebenaran untuk perhitungan biaya.

### Kenapa perlu rate limit?

Untuk membatasi request berulang akibat spam klik. Jika request terlalu banyak, sistem mengembalikan status 429 dan query tidak terus dieksekusi.

### Kenapa perlu role?

Supaya user hanya bisa mengakses fitur sesuai haknya. Penumpang tidak boleh mengakses fitur pengemudi atau admin.

### Kenapa perlu seat lock?

Untuk mencegah dua user mengambil kursi yang sama pada waktu bersamaan.

### Kenapa upload foto harus divalidasi?

Supaya file yang masuk benar-benar gambar dan tidak terlalu besar.

### Kenapa payment gateway lewat backend?

Supaya credential lebih aman dan status pembayaran bisa diverifikasi oleh server.

### Apa arti status 429?

Status 429 berarti terlalu banyak request. Ini muncul saat rate limit terlampaui.

### Apa fungsi migration?

Migration adalah file untuk membuat atau mengubah struktur tabel database.

### Apa fungsi model?

Model adalah penghubung antara kode Laravel dan tabel database.

### Apa fungsi controller?

Controller menerima request dari route, memanggil service atau model, lalu mengembalikan response.

### Apa fungsi service?

Service menyimpan logic bisnis agar controller tidak terlalu panjang dan lebih mudah dipahami.

## 16. Cara Belajar Kode Project Ini

Urutan belajar yang disarankan:

1. Baca `backend/routes/api.php` untuk melihat endpoint.
2. Pilih satu endpoint, lalu buka controllernya.
3. Lihat service yang dipanggil controller.
4. Lihat model dan migration yang berhubungan.
5. Lihat test di `backend/tests/Feature`.
6. Cocokkan dengan tampilan Flutter di folder `frontend/lib/features`.

Contoh belajar fitur booking:

1. Buka route `/penumpang/bookings`.
2. Buka `BookingController`.
3. Buka `BookingService`.
4. Buka `BookingCalculator`.
5. Buka model `Pemesanan`, `DetailPemesanan`, `ItemBagasi`, dan `KursiJadwal`.
6. Buka test `BookingFlowTest`.

## 17. Kalimat Singkat untuk Menjelaskan Project

> Aplikasi ini memakai Flutter sebagai frontend mobile dan Laravel sebagai backend API serta web admin. Backend mengatur validasi, role user, booking kursi, perhitungan biaya, upload bagasi, pembayaran, dan tiket elektronik. Untuk mencegah spam klik, route penting diberi rate limit sehingga request berulang tidak terus mengeksekusi query.
