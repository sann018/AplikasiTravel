# 08 — Latihan & Simulasi Sidang

> Bab ini berisi **latihan praktik** dan **simulasi tanya-jawab** agar kamu siap menghadapi sesi
> buka kode saat sidang. Kerjakan latihannya langsung di VS Code, jangan hanya dibaca.

---

## Bagian 1 — Latihan Praktik Ubah Tampilan (WAJIB dicoba)

Kerjakan satu per satu. Setiap latihan sudah lengkap: file, langkah, dan cara mengembalikan.

### Latihan 1 — Ganti warna tombol utama admin (mudah)
- **File:** `backend/resources/css/app.css`
- **Langkah:** cari `--travs-accent: #f58a2a;`, ganti jadi warna lain (misal `#2563eb` biru).
- **Uji:** `cd backend` lalu `npm run build`, refresh browser, lihat tombol berubah.
- **Kembalikan:** ganti lagi ke `#f58a2a`.

### Latihan 2 — Ganti warna aplikasi mobile (mudah)
- **File:** `frontend/lib/core/theme/travs_theme.dart`
- **Langkah:** cari `static const Color orange = Color(0xFFF58A2A);`, ganti jadi `Color(0xFF2563EB);`.
- **Uji:** saat `flutter run` berjalan, tekan `R` (hot restart).
- **Kembalikan:** ganti balik ke `0xFFF58A2A`.

### Latihan 3 — Ubah teks judul halaman admin (mudah)
- **File:** `backend/resources/views/admin/jadwal/index.blade.php`
- **Langkah:** cari `@section('page-title', 'Jadwal Perjalanan')`, ganti teksnya jadi
  `'Daftar Jadwal Keberangkatan'`.
- **Uji:** refresh browser (tidak perlu build).

### Latihan 4 — Perbesar sudut membulat kartu admin (sedang)
- **File:** `backend/resources/css/app.css`
- **Langkah:** cari `.travs-card { ... border-radius: 22px; }`, ganti jadi `border-radius: 8px;`
  (lebih tajam) atau `40px` (lebih bulat).
- **Uji:** `npm run build`, refresh.

### Latihan 5 — Ubah ukuran font judul mobile (sedang)
- **File:** `frontend/lib/core/theme/travs_theme.dart`
- **Langkah:** cari `titleLarge:` lalu `fontSize: 20,`, ubah jadi `fontSize: 26,`.
- **Uji:** hot restart (`R`).

### Latihan 6 — Perlebar sidebar admin (sedang)
- **File:** `backend/resources/css/app.css`
- **Langkah:** cari `.travs-admin-shell { ... grid-template-columns: 250px minmax(0, 1fr); }`,
  ubah `250px` jadi `300px`.
- **Uji:** `npm run build`, refresh.

### Latihan 7 — Ubah bentuk tombol mobile jadi kapsul (sedang)
- **File:** `frontend/lib/core/theme/travs_theme.dart`
- **Langkah:** di `elevatedButtonTheme`, cari `BorderRadius.circular(18)`, ubah jadi `circular(30)`.
- **Uji:** hot restart.

### Latihan 8 — Ganti ikon menu admin (sedang)
- **File:** `backend/resources/views/admin/layouts/app.blade.php`
- **Langkah:** cari `'label' => 'Jadwal Perjalanan', ... 'icon' => 'calendar'`, ganti `'calendar'`
  jadi `'clock'`.
- **Uji:** refresh browser.

> **Target:** kamu harus bisa melakukan Latihan 1-8 tanpa melihat modul lagi. Ulangi sampai lancar.

---

## Bagian 2 — Simulasi Tanya-Jawab Sidang

Latih menjawab pertanyaan berikut dengan bahasamu sendiri. Jawaban di bawah adalah panduan, bukan
hafalan mati.

### Pertanyaan Konsep

**Q: Aplikasi ini tentang apa?**
> A: Aplikasi pemesanan tiket travel lintas kota. Penumpang bisa lihat jadwal, pilih kursi, bayar,
> dan dapat tiket QR lewat HP. Pengemudi memvalidasi tiket dengan scan QR. Admin mengelola semua data
> lewat panel web.

**Q: Teknologi apa yang dipakai?**
> A: Backend pakai Laravel (PHP) dengan database MySQL. Aplikasi mobile pakai Flutter. Autentikasi
> API pakai Laravel Sanctum. Pembayaran pakai gateway Pakasir.

**Q: Kenapa backend dan frontend dipisah?**
> A: Supaya satu backend bisa melayani banyak jenis klien. Aplikasi mobile dan panel web admin
> sama-sama mengambil data dari backend yang sama lewat API. Ini membuat sistem lebih terorganisir.

**Q: Ada berapa peran pengguna? Apa bedanya?**
> A: Empat: tamu (hanya lihat jadwal), penumpang (memesan tiket), pengemudi (validasi tiket & lihat
> manifest), admin (kelola semua data lewat web).

**Q: Apa itu API?**
> A: API adalah jembatan komunikasi antara aplikasi mobile dan backend. Aplikasi mengirim permintaan
> (misalnya "ambil daftar jadwal"), backend membalas dengan data dalam format JSON.

### Pertanyaan Alur

**Q: Coba jelaskan alur pemesanan dari awal sampai dapat tiket.**
> A: Penumpang lihat jadwal → pilih kursi (kursi dikunci sementara agar tidak direbut) → isi data
> penumpang dan titik jemput/turun → pilih metode bayar → bayar lewat Pakasir atau tunai → setelah
> pembayaran berhasil, sistem menerbitkan tiket QR untuk tiap kursi.

**Q: Apa itu seat lock / penguncian kursi? Kenapa perlu?**
> A: Saat penumpang memilih kursi dan mengisi form, kursi itu dikunci sementara supaya tidak dipilih
> orang lain di waktu yang sama. Kalau dalam batas waktu tidak dibayar, kunci dilepas otomatis dan
> kursi bisa dipesan orang lain lagi.

**Q: Bagaimana sistem mencegah dua orang memesan kursi yang sama?**
> A: Ada dua pengaman. Pertama, saat mengunci kursi, sistem memakai transaksi database dengan
> penguncian baris (`lockForUpdate`) agar tidak ada dua proses bersamaan. Kedua, kolom kursi di tabel
> detail pemesanan dibuat unik, jadi mustahil satu kursi masuk dua pemesanan.

**Q: Bagaimana tiket dibuat?**
> A: Begitu pembayaran berstatus berhasil, sistem menjalankan fungsi penerbitan tiket. Tiap kursi
> mendapat satu tiket dengan token QR unik. Token itu yang di-scan pengemudi.

**Q: Bagaimana keamanan pembayaran dijaga?**
> A: Backend tidak langsung percaya notifikasi dari Pakasir. Ia menanyakan ulang status transaksi
> yang sebenarnya ke Pakasir dan mencocokkan nominalnya. Jadi status pembayaran tidak bisa dipalsukan.

### Pertanyaan Kode/Teknis

**Q: Kalau saya klik tombol 'Tambah Jadwal', kode mana yang jalan?**
> A: Tombol itu mengarah ke route `admin.jadwal.create` yang memanggil method `create` di
> `JadwalController`. Method itu menampilkan form. Saat form dikirim, jalan method `store` yang
> memvalidasi dan menyimpan data ke tabel `jadwal`, sekaligus membuat kursi otomatis.

**Q: Di mana warna aplikasi diatur?**
> A: Untuk web admin di file `resources/css/app.css` bagian `:root`. Untuk aplikasi mobile di file
> `lib/core/theme/travs_theme.dart`.

**Q: Bagaimana data dari database sampai ke layar aplikasi?**
> A: Aplikasi memanggil API lewat file `travs_api_service.dart`. Backend menerima di route,
> mengambil data lewat Model dari database, membungkus jadi JSON, lalu mengirim balik. Aplikasi
> mengubah JSON jadi objek Dart (lewat `fromJson`) dan menampilkannya.

**Q: Apa itu Model di Laravel?**
> A: Model adalah kelas yang mewakili satu tabel database. Lewat Model kita bisa mengakses dan
> memanipulasi data tanpa menulis SQL manual. Contoh: `Pemesanan` mewakili tabel `pemesanan`.

**Q: Apa beda StatelessWidget dan StatefulWidget di Flutter?**
> A: StatelessWidget tampilannya tetap. StatefulWidget bisa berubah tampilannya, misalnya setelah
> data selesai dimuat, dengan memanggil `setState()`.

### Pertanyaan Jebakan / Kritis

**Q: Kenapa admin tidak bisa login lewat aplikasi mobile?**
> A: Karena secara desain admin bekerja lewat panel web yang layarnya lebih luas untuk mengelola
> data. Aplikasi mobile sengaja dibatasi untuk penumpang dan pengemudi. Kalau akun admin login di
> mobile, sistem menolaknya.

**Q: Apakah harga bisa dimanipulasi dari aplikasi?**
> A: Tidak. Harga selalu dihitung ulang di backend berdasarkan harga tiket rute dikali jumlah kursi.
> Nilai yang dikirim dari aplikasi tidak dipakai untuk menentukan total bayar.

**Q: Kalau pembayaran berhasil tapi HP mati, apakah tiket hilang?**
> A: Tidak. Tiket diterbitkan di backend saat pembayaran terkonfirmasi, bukan di HP. Penumpang bisa
> membuka lagi tiketnya kapan saja dari menu pemesanan.

**Q: Kamu pernah menemukan dan memperbaiki bug?**
> A: Ya. Contohnya, dulu ada kolom biaya bagasi dan diskon yang sudah tidak dipakai, lalu saya hapus
> dan sederhanakan perhitungan total. Contoh lain, pengemudi dulu tidak bisa menandai penumpang
> dijemput kalau sudah scan tiket duluan; saya perbaiki supaya urutannya fleksibel. (Lihat modul
> `06` bagian C.3 untuk daftar lengkap.)

---

## Bagian 3 — Checklist Kesiapan Sidang

Centang saat sudah bisa:

**Pemahaman konsep:**
- [ ] Bisa menjelaskan aplikasi ini tentang apa dalam 1 menit.
- [ ] Hafal 4 peran pengguna dan bedanya.
- [ ] Bisa menjelaskan teknologi yang dipakai (Laravel, Flutter, MySQL, Sanctum, Pakasir).
- [ ] Bisa menggambar/menjelaskan arsitektur (mobile & admin → API → backend → database).

**Pemahaman alur:**
- [ ] Bisa menceritakan alur pemesanan sampai dapat tiket.
- [ ] Paham konsep seat lock (penguncian kursi).
- [ ] Paham alur pembayaran dan penerbitan tiket.
- [ ] Paham alur scan QR oleh pengemudi.

**Keterampilan praktik (paling penting):**
- [ ] Bisa ubah warna admin (app.css) + build.
- [ ] Bisa ubah warna mobile (travs_theme.dart) + hot restart.
- [ ] Bisa ubah teks di halaman admin (Blade).
- [ ] Bisa ubah teks di halaman mobile (Dart).
- [ ] Bisa ubah ukuran font (admin & mobile).
- [ ] Bisa ubah posisi/jarak elemen (padding, gap, SizedBox).
- [ ] Bisa menjalankan `php artisan serve`, `npm run dev`, `flutter run`.

**Struktur kode:**
- [ ] Tahu bedanya folder `backend/` dan `frontend/`.
- [ ] Tahu ke mana harus buka file kalau diminta ubah admin vs mobile.
- [ ] Bisa menemukan file yang tepat pakai Ctrl+Shift+F (cari di semua file).

**Laporan:**
- [ ] BAB IV & V sudah disusun dari modul `07`.
- [ ] ERD sudah digambar.
- [ ] Screenshot tiap halaman sudah diambil.

---

## Bagian 4 — Tips Saat Sidang

1. **Tenang saat diminta buka kode.** Kalau lupa file mana, buka modul `06` bagian ringkasan
   "Kalau Diminta X, Buka Y".
2. **Pakai Ctrl+Shift+F** (cari di semua file) untuk menemukan teks/label dengan cepat.
3. **Ubah sedikit, uji, lihat.** Jangan ubah banyak sekaligus. Kalau salah, Ctrl+Z.
4. **Jujur kalau tidak tahu**, tapi tunjukkan cara berpikir: "Saya belum hafal persis, tapi ini ada
   di bagian X, biasanya saya cari dengan cara Y."
5. **Siapkan aplikasi sudah berjalan** sebelum sidang (backend `php artisan serve` + `npm run dev`,
   dan `flutter run`) agar perubahan langsung terlihat.
6. **Kalau diminta perbaiki bug**, ikuti langkah di modul `06` bagian C.1: pahami gejala → tentukan
   dunia → cari titik → baca → ubah → uji.

---

Selamat belajar. Kalau kamu sudah menuntaskan seluruh checklist di Bagian 3, kamu sudah sangat siap
untuk sesi buka kode di sidang. Semangat!
