# 02 — Struktur Project & Cara Menjalankan

> Tujuan bab ini: kamu tahu **di mana letak setiap file**, dan bisa **menjalankan project dari nol**
> (misalnya di laptop pinjaman saat sidang). Buka VS Code, cocokkan tiap folder yang disebut di sini.

---

## 2.1 Struktur Folder Paling Atas

Di dalam folder `AplikasiTravel/` ada dua folder utama:

```
AplikasiTravel/
├── backend/        ← Backend Laravel (otak sistem + web admin + API)
├── frontend/       ← Aplikasi mobile Flutter (penumpang & pengemudi)
├── MODUL_PEMBELAJARAN/   ← Folder modul belajar ini
└── (beberapa file .md dokumentasi lain)
```

**Ingat aturan sederhana ini:**
- Mau ubah **logika/aturan sistem, database, atau tampilan admin** → masuk `backend/`.
- Mau ubah **tampilan/aplikasi HP** → masuk `frontend/`.

---

## 2.2 Struktur Folder Backend (Laravel)

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/        ← Controller untuk aplikasi mobile (kirim JSON)
│   │   │   └── Web/Admin/  ← Controller untuk halaman web admin
│   │   └── Middleware/     ← Penjaga akses (contoh: cek peran)
│   ├── Models/             ← Wakil tiap tabel database
│   └── Services/           ← Logika bisnis rumit (booking, pembayaran, dll)
├── database/
│   ├── migrations/         ← Blueprint/struktur tabel database
│   └── seeders/            ← Data contoh untuk mengisi database
├── resources/
│   ├── views/admin/        ← Halaman web admin (file .blade.php)
│   ├── css/app.css         ← SEMUA gaya tampilan admin ada di sini
│   └── js/app.js           ← JavaScript admin
├── routes/
│   ├── api.php             ← Daftar alamat API untuk mobile
│   ├── web.php             ← Daftar alamat halaman web admin
│   └── console.php         ← Perintah terjadwal
├── config/                 ← Konfigurasi (database, cache, dll)
├── lang/                   ← Terjemahan pesan (id & en)
├── .env                    ← Setting rahasia (password DB, dll) — JANGAN di-share
└── composer.json           ← Daftar library backend
```

### Folder yang paling sering kamu buka:

| Folder | Isi | Kapan dibuka |
|--------|-----|--------------|
| `app/Http/Controllers/Api/` | Controller mobile | Mengubah respons API mobile |
| `app/Http/Controllers/Web/Admin/` | Controller admin | Mengubah logika halaman admin |
| `app/Models/` | Model | Melihat relasi & kolom tabel |
| `app/Services/` | Logika bisnis | Memahami alur booking/pembayaran |
| `database/migrations/` | Struktur tabel | Bahan BAB 4.3 (database) |
| `resources/views/admin/` | Tampilan admin | **Ubah tampilan admin** |
| `resources/css/app.css` | Gaya/warna admin | **Ubah warna/ukuran admin** |
| `routes/api.php` & `routes/web.php` | Daftar alamat | Melihat endpoint yang tersedia |

---

## 2.3 Struktur Folder Frontend (Flutter)

Kode Flutter yang kamu tulis semuanya ada di `frontend/lib/`:

```
frontend/
├── lib/
│   ├── main.dart           ← Titik awal aplikasi dijalankan
│   ├── app.dart            ← Root aplikasi + logika pindah halaman awal
│   ├── core/
│   │   ├── theme/
│   │   │   └── travs_theme.dart    ← SEMUA warna, font, gaya mobile
│   │   ├── models/         ← Bentuk data (DTO) dari/ke API
│   │   ├── services/       ← Pemanggil API, auth, lokasi, settings
│   │   └── widgets/        ← Komponen UI kecil yang dipakai ulang
│   └── features/           ← Tiap fitur = satu folder = satu halaman
│       ├── launch/         ← Splash screen
│       ├── auth/           ← Login & register
│       ├── home/           ← Beranda (3 peran + navigasi bawah)
│       ├── schedule/       ← Detail jadwal + denah kursi
│       ├── booking/        ← Detail pemesanan
│       ├── payment/        ← Form pembayaran + peta jemput/turun
│       ├── ticket/         ← Tiket QR
│       └── pickup/         ← Pemilih titik jemput di peta
├── assets/                 ← Gambar, ikon, logo
├── android/ ios/ web/ ...  ← Folder tiap platform (jarang disentuh)
└── pubspec.yaml            ← Daftar library Flutter + daftar assets
```

### Folder yang paling sering kamu buka:

| File/Folder | Isi | Kapan dibuka |
|-------------|-----|--------------|
| `lib/core/theme/travs_theme.dart` | Warna & font mobile | **Ubah warna/font mobile** |
| `lib/features/*/` | Halaman-halaman | **Ubah tampilan/tata letak halaman** |
| `lib/core/services/travs_api_service.dart` | Pemanggil API | Melihat cara ambil data dari backend |
| `lib/core/models/` | Bentuk data | Melihat field yang dipakai halaman |
| `pubspec.yaml` | Library & assets | Menambah library/gambar |

> **Catatan:** beberapa file halaman Flutter sangat panjang (contoh `travs_payment_preview_page.dart`
> ~2900 baris). Ini karena satu file berisi halaman + banyak widget kecil di dalamnya. Wajar dan
> tidak masalah; nanti di file `05` dijelaskan cara membacanya.

---

## 2.4 Cara Menjalankan Project dari Nol

Ini penting untuk demo sidang. Ada 3 hal yang harus jalan: **database**, **backend**, **frontend**.

### Langkah 0 — Prasyarat (harus terinstall)
- PHP 8.3+ dan Composer
- MySQL (biasanya lewat XAMPP/Laragon)
- Flutter SDK
- Node.js (untuk membangun CSS/JS admin lewat Vite)

### Langkah 1 — Menyiapkan Backend

```powershell
# masuk ke folder backend
cd backend

# install library PHP
composer install

# install library JS (untuk CSS/JS admin)
npm install

# salin file setting (kalau belum ada .env)
copy .env.example .env

# buat kunci aplikasi
php artisan key:generate
```

Lalu edit `.env`, pastikan setting database benar (nama database `aplikasi_travel`,
user & password sesuai MySQL kamu):
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=aplikasi_travel
DB_USERNAME=root
DB_PASSWORD=
```

### Langkah 2 — Membuat & Mengisi Database

```powershell
# membuat semua tabel sesuai migration
php artisan migrate

# mengisi data contoh (admin, rute, jadwal, dll)
php artisan db:seed
```

> Kalau mau reset total (hapus semua & buat ulang + isi contoh):
> `php artisan migrate:fresh --seed`
> **Hati-hati:** ini menghapus semua data. Jangan dipakai kalau ada data penting.

### Langkah 3 — Menjalankan Backend

```powershell
# membangun CSS & JS admin
npm run build      # atau "npm run dev" untuk mode pengembangan

# menjalankan server backend
php artisan serve
```
Backend jalan di `http://127.0.0.1:8000`. Panel admin ada di alamat itu (langsung diarahkan ke
halaman login admin).

### Langkah 4 — Menjalankan Aplikasi Flutter

```powershell
cd frontend
flutter pub get      # ambil semua library
flutter run          # jalankan di emulator/HP, atau:
flutter run -d chrome  # jalankan di browser
```

> **Poin penting soal alamat API:** aplikasi Flutter otomatis mencoba dua alamat backend:
> `127.0.0.1:8000` (untuk web/HP di jaringan sama) dan `10.0.2.2:8000` (khusus emulator Android).
> Logika ini ada di `travs_api_service.dart`. Jadi kamu tidak perlu ganti alamat manual saat
> pindah antara browser dan emulator.

---

## 2.5 Akun untuk Login (dari Seeder)

Setelah `php artisan db:seed`, biasanya tersedia akun contoh. Cek file
`backend/database/seeders/TravSDemoSeeder.php` untuk melihat email & password persisnya.
Contoh pola yang ada di seeder:
- Admin: `admin@travs.com`
- Penumpang: `penumpang@travs.com`
- Pengemudi: `pengemudi@travs.com`

> Password ada di seeder. Buka file seeder dan cari baris `'password' => ...` untuk memastikan.

---

## 2.6 Alur "Dari Awal Sampai Akhir" Pembuatan Project (untuk cerita di laporan)

Kalau ditanya "bagaimana urutan kamu membangun project ini?", jawaban yang logis dan sesuai struktur:

1. **Rancang database dulu** — menentukan tabel (rute, kendaraan, pengemudi, jadwal, pemesanan, dll)
   lewat file migration di `database/migrations/`.
2. **Buat Model** — tiap tabel dibuatkan Model di `app/Models/` beserta relasinya.
3. **Buat logika bisnis (Service)** — logika rumit seperti booking & pembayaran di `app/Services/`.
4. **Buat Controller + Route** — jembatan antara permintaan dan logika, di `app/Http/Controllers/`
   dan `routes/`.
5. **Buat panel admin (Blade + CSS)** — tampilan web admin di `resources/views/admin/`.
6. **Buat API untuk mobile** — endpoint JSON di `routes/api.php`.
7. **Bangun aplikasi Flutter** — halaman per halaman di `frontend/lib/features/`, memanggil API.
8. **Uji coba** — tulis test otomatis di `backend/tests/` (ada 194 test) dan uji manual.

## 2.7 Pembaruan Setup per 25 Juli 2026

Untuk mendemokan pembatalan otomatis booking, penutupan jadwal, atau pengingat keberangkatan, scheduler Laravel harus dijalankan:

```powershell
cd backend
php artisan schedule:work
```

Pembatalan booking kedaluwarsa dan penutupan jadwal berjalan setiap menit. Pengingat keberangkatan diproses setiap 15 menit untuk jadwal dalam satu jam ke depan. Migration terbaru juga menambah unique constraint `order_id_gateway` serta snapshot nominal pembayaran; jalankan migration hanya pada database environment yang tepat dan backup data penting sebelum perubahan schema.

Lanjut ke `03_DATABASE_DAN_MODEL.md` untuk mendalami struktur database (bahan BAB 4.3).
