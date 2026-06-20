# Fitur yang Perlu Dipertimbangkan

## Pengiriman Token Reset Password

### Kondisi Sistem Saat Ini

Fitur lupa password saat ini masih menggunakan mode demo lokal:

1. Pengguna memasukkan email yang terdaftar.
2. Backend membuat token reset acak.
3. Token dalam bentuk hash disimpan di database.
4. Token asli dikembalikan melalui respons API.
5. Aplikasi Flutter mengisi token tersebut secara otomatis ke form reset password.
6. Token hanya dapat digunakan satu kali dan memiliki batas waktu.

Cara ini cocok untuk pengujian dan demonstrasi tugas akhir, tetapi tidak cocok
untuk penggunaan produksi karena token reset dapat terlihat pada respons API.

### SMTP Gmail

SMTP Gmail dapat dipertimbangkan untuk mengirim token reset ke email pengguna.
Akun Gmail pribadi umumnya dapat digunakan tanpa biaya untuk skala kecil, tetapi
Google dapat mengubah kebijakan dan batas pengiriman sewaktu-waktu.

Batasan yang perlu diperhatikan:

- Jumlah email yang dapat dikirim setiap hari dibatasi.
- Akun dapat dibatasi sementara jika aktivitas dianggap sebagai spam.
- SMTP Gmail kurang cocok untuk pengiriman email dalam jumlah besar.
- Akun harus mengaktifkan verifikasi dua langkah.
- Aplikasi harus menggunakan App Password, bukan password utama Gmail.
- App Password dan data akun email wajib disimpan di `.env` dan tidak boleh
  dimasukkan ke GitHub.
- Google Workspace untuk email dengan domain perusahaan biasanya berbayar.

### Rekomendasi

Untuk demo tugas akhir, SMTP Gmail pribadi sudah cukup karena jumlah pengiriman
email masih sedikit. Token tidak lagi perlu ditampilkan pada respons API setelah
pengiriman email berhasil diterapkan.

Untuk penggunaan produksi, pertimbangkan layanan email transaksi seperti:

- Brevo
- Mailgun
- SendGrid
- Amazon SES

Layanan tersebut lebih cocok untuk pengiriman email aplikasi karena memiliki
pencatatan status pengiriman, batas penggunaan yang lebih jelas, dan pengelolaan
email gagal yang lebih baik.

## Login dengan Google

Login dengan Google belum diterapkan pada project saat ini. Sistem autentikasi
yang aktif menggunakan email atau nomor HP dan password.

Fitur Login dengan Google dapat dipertimbangkan nanti jika ingin mempercepat
proses pendaftaran. Namun, penerapannya membutuhkan konfigurasi OAuth, pengelolaan
akun yang login melalui Google, dan penyesuaian keamanan pada frontend serta
backend.

## SMS OTP

SMS OTP juga belum diterapkan. Layanan SMS biasanya berbayar untuk setiap pesan
yang dikirim dan membutuhkan penyedia SMS resmi.

Jika fitur ini diterapkan, sistem perlu memiliki:

- OTP acak yang disimpan dalam bentuk hash.
- Masa berlaku OTP yang singkat.
- Batas permintaan OTP untuk mencegah spam.
- Batas percobaan memasukkan OTP.
- OTP sekali pakai yang langsung dihapus setelah berhasil.
- Pesan API yang tidak membocorkan apakah nomor HP terdaftar.

Untuk kebutuhan tugas akhir, pengiriman token melalui email lebih hemat dan lebih
mudah diterapkan dibandingkan SMS OTP.
