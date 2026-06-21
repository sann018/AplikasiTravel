# Panduan Demo Lokal Tanpa Hosting

Status: Final untuk demo sidang
Tanggal pembaruan: 16 Juni 2026

Dokumen ini berisi panduan menjalankan seluruh fitur AplikasiTravel di laptop lokal saat demo sidang, tanpa hosting publik. Cocok untuk skenario:

- Backend Laravel jalan di `localhost:8000`
- HP Android terhubung Wi-Fi yang sama dengan laptop
- Pembayaran memakai mode simulasi Pakasir lokal yang aman untuk demo
- Tidak butuh akun Firebase, tidak butuh domain publik, tidak butuh server VPS

## 1. Persiapan Awal

### 1.1 Menjalankan backend Laravel

```powershell
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

`--host=0.0.0.0` penting agar HP fisik di Wi-Fi yang sama bisa mengakses backend laptop.

### 1.2 Menjalankan Flutter ke HP fisik

Cari IP laptop di Wi-Fi:

```powershell
ipconfig
# Cari "IPv4 Address" pada adapter Wi-Fi, contoh: 192.168.1.5
```

Jalankan Flutter dengan `--dart-define`:

```powershell
cd frontend
flutter run --dart-define=API_BASE_URL=http://192.168.1.5:8000
```

HP akan ngomong ke backend laptop selama berada di Wi-Fi yang sama.

### 1.3 Menjalankan Flutter ke Android Emulator

Tidak butuh `--dart-define` karena alamat `10.0.2.2` sudah di-handle otomatis di `travs_api_service.dart:34`:

```powershell
cd frontend
flutter run
```

## 2. Mode Simulasi Pakasir

### 2.1 Mode Simulasi Lokal (utama untuk demo)

Saat sidang, mode ini **menjadi mode utama**. Ini bukan transaksi nyata dengan callback publik. Mode ini sengaja dipakai agar demo stabil, aman, dan tidak bergantung pada domain, tunnel, internet, atau dashboard payment gateway.

`backend/.env`:
```env
PAKASIR_ENABLED=false
```

Saat user klik bayar, backend akan menerbitkan tiket lewat endpoint `simulate-payment` (lihat `BookingController::simulatePayment`). Tombol simulasi sudah tampil di Flutter saat config tidak aktif.

Yang dibuktikan oleh mode simulasi:

- booking berhasil dibuat
- data pembayaran masuk ke backend
- status pembayaran bisa berubah menjadi berhasil
- tiket QR digital terbit setelah pembayaran valid
- pengemudi tetap bisa scan QR tiket

**Cara demo**:
1. Penumpang pilih kursi dan checkout
2. Halaman pembayaran muncul dengan tombol "Simulasi Bayar"
3. Klik tombol → tiket QR terbit otomatis

Penjelasan ke penguji:

> "Mode simulasi ini dipakai untuk demo sidang karena transaksi nyata membutuhkan callback URL publik. Backend tetap menjalankan konsep lifecycle pembayaran: pemesanan dibuat, pembayaran divalidasi, status berubah, lalu tiket QR diterbitkan."

### 2.2 Batasan Mode Simulasi

Mode simulasi tidak melakukan:

- transaksi uang nyata
- callback publik dari Pakasir
- setup production webhook
- webhook signature verification

Hal tersebut belum diperlukan untuk demo lokal. Signature verification baru relevan jika server sudah menerima webhook production dari payment gateway secara publik.

### 2.3 Catatan Jika Nanti Masuk Produksi

Untuk production, baru diperlukan server publik, HTTPS, webhook URL tetap, dan validasi callback sesuai dokumentasi payment gateway. Tahap ini tidak menjadi target demo lokal.

## 3. Push Notification Tanpa Firebase

Sistem ini menggunakan **polling + flutter_local_notifications**. Mobile app menanyakan notifikasi terbaru ke backend tiap 30 detik, lalu menampilkan notifikasi sistem Android jika ada yang baru.

### 3.1 Cara Demo

1. Buka mobile app di HP, login sebagai penumpang
2. Di laptop, buka admin web → ubah status pemesanan ke "terbayar" atau verifikasi cash
3. Backend trigger event → catat ke tabel `notifikasi`
4. Dalam 30 detik, mobile akan polling endpoint `/api/v1/notifications`
5. Notifikasi muncul di system tray HP (bunyi + getar)

### 3.2 Penjelasan ke Penguji

Pendekatan polling dipilih karena:

- Tidak membutuhkan layanan eksternal Google Firebase (gratis namun butuh credential)
- Tidak membutuhkan queue worker produksi
- Tabel `notifikasi` sudah menjadi sumber tunggal untuk web admin dan mobile

Batasan jujur:

- Notifikasi hanya muncul saat aplikasi sedang aktif (foreground / background hidup)
- Saat aplikasi ditutup penuh, notifikasi tertahan sampai user membuka kembali (catch-up dialog)

Untuk produksi, integrasi Firebase Cloud Messaging tinggal menambah service di backend. Endpoint `/api/v1/auth/push-token` sudah disiapkan di sisi mobile sehingga migrasi tidak butuh perubahan struktur.

## 4. Backup Database Harian

Backup memakai custom artisan command yang dijalankan via Laravel Scheduler.

### 4.1 Cara kerja

- File SQLite di-copy ke `storage/app/backups/` dengan timestamp
- Backup retention: 7 file terbaru disimpan, sisanya dihapus otomatis
- Scheduler harian via `routes/console.php`

### 4.2 Cara demo

```powershell
cd backend
php artisan travs:backup-database
```

Output:
```
Backup berhasil: storage/app/backups/database-2026-05-15-080000.sqlite
Backup lama dihapus: 2 file.
```

### 4.3 Cara aktifkan scheduler harian

Pada server produksi, daftarkan cron job:
```cron
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Untuk demo lokal, tinggal jelaskan: "Scheduler sudah disiapkan via Laravel Schedule, tinggal pasang cron job di server saat deploy."

## 5. HTTPS Production

Tidak diimplementasi pada penelitian ini karena membutuhkan domain dan sertifikat SSL berbayar/Let's Encrypt yang tidak praktis di environment lokal. Saat deployment, HTTPS dapat diaktifkan via:

- Reverse proxy Nginx + Let's Encrypt
- Cloudflare proxy gratis
- Hosting yang sudah include SSL otomatis

Backend Laravel tidak perlu perubahan kode karena mengikuti `APP_URL` di `.env`.

## 6. Environment dev/staging/production

Struktur sudah disiapkan via `.env.example`. Saat deployment:

- `.env` di server staging berisi credential staging
- `.env` di server production berisi credential produksi
- Tidak ada perubahan kode antar environment

## 7. Checklist Sebelum Sidang

- [ ] Backend jalan di laptop, bisa diakses dari HP via Wi-Fi yang sama
- [ ] Flutter di HP bisa login, pilih jadwal, checkout, dan terima tiket QR
- [ ] Pakasir mode simulasi lokal bisa menerbitkan tiket end-to-end
- [ ] Notifikasi muncul di HP saat admin verifikasi cash atau payment success
- [ ] Backup database bisa dijalankan via `php artisan travs:backup-database`
- [ ] `php artisan test` lulus
- [ ] `flutter analyze` lulus
- [ ] `flutter test` lulus
