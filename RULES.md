# RULES CODING — APLIKASI TRAVEL LINTAS KOTA
# (Mobile App + Web Admin)
# Dibuat untuk: Keamanan, Kualitas, dan Kemudahan Pemahaman

---

## 🔒 KEAMANAN (SECURITY) — WAJIB

1. Gunakan Prepared Statement / Parameterized Query di semua query database — jangan pernah menggabungkan input user langsung ke dalam string SQL untuk menghindari SQL Injection.

2. Terapkan Rate Limiting di semua endpoint API — batasi jumlah request per user/IP dalam rentang waktu tertentu (contoh: maks 10 request/menit) untuk mencegah spam dan serangan brute force.

3. Selalu validasi dan sanitasi input dari user di sisi server (backend) — jangan hanya mengandalkan validasi di frontend/aplikasi mobile karena bisa dilewati.

4. Gunakan HTTPS untuk semua komunikasi antara aplikasi mobile, web admin, dan server — jangan pernah mengirim data sensitif lewat HTTP biasa.

5. Simpan password dengan metode hashing + salt menggunakan algoritma aman seperti bcrypt atau argon2 — jangan pernah simpan password dalam bentuk plain text.

6. Gunakan JWT (JSON Web Token) untuk autentikasi, dan pastikan token memiliki waktu kedaluwarsa (expiry time) serta mekanisme refresh token yang aman.

7. Sembunyikan semua konfigurasi sensitif (API key, password database, secret key) di file .env — jangan pernah di-push ke Git/GitHub. Tambahkan .env ke .gitignore.

---

## 🧪 TESTING — TDD (Test Driven Development) — WAJIB

8. Tulis unit test terlebih dahulu sebelum membuat fungsi — pastikan setiap fungsi penting (hitung harga, buat pesanan, login) punya test-nya masing-masing.

9. Buat integration test untuk alur utama aplikasi seperti: user melakukan pemesanan dari awal sampai pembayaran berhasil — pastikan semua komponen bekerja bersama dengan benar.

10. Jalankan semua test otomatis sebelum deploy ke server — jika ada test yang gagal, jangan lanjutkan proses deploy sampai bug-nya diperbaiki.

11. Beri nama test yang jelas dan deskriptif — contoh: harusGagalJikaSeaTSudahTerisi() sehingga langsung tahu apa yang diuji tanpa perlu baca kodenya dulu.

---

## 📝 KETERBACAAN & KUALITAS KODE — WAJIB

12. Tambahkan komentar dalam Bahasa Indonesia yang sederhana dan santai di setiap fungsi atau blok kode penting — fokus pada "kenapa" dan "untuk apa", bukan "apa" (itu sudah jelas dari kodenya).

13. Gunakan nama variabel dan fungsi yang deskriptif — hindari nama seperti a, x, tmp. Gunakan nama yang langsung menggambarkan isinya, contoh: hargaTiket, hitungTotalPembayaran().

14. Satu fungsi hanya punya satu tanggung jawab (Single Responsibility) — jika fungsi terlalu panjang dan mengerjakan banyak hal, pecah jadi beberapa fungsi kecil yang lebih mudah dipahami.

15. Hindari duplikasi kode (DRY — Don't Repeat Yourself) — jika ada logika yang dipakai di banyak tempat, buat jadi fungsi terpisah yang bisa dipanggil ulang.

16. Gunakan format kode yang konsisten di seluruh project — pakai tools seperti Prettier (JavaScript) atau dart format (Flutter) agar semua kode terlihat seragam dan rapi.

---

## 🏗️ ARSITEKTUR & STRUKTUR PROJECT — PRO TIP

17. Pisahkan kode berdasarkan lapisan (layer) — minimal pisahkan antara: routes (jalan masuk request), controllers (logika utama), services (proses bisnis), dan models (data/database). Jangan campur semuanya di satu file.

18. Buat folder yang terstruktur dan konsisten — kelompokkan file berdasarkan fitur atau fungsinya. Contoh: folder /auth, /pemesanan, /pembayaran, /user.

19. Gunakan versioning pada API — buat endpoint dimulai dengan /api/v1/... sehingga jika nanti ada perubahan besar, bisa buat /api/v2/ tanpa merusak yang sudah ada.

20. Pisahkan konfigurasi environment antara development (lokal), staging (uji coba), dan production (live) — jangan pakai database production untuk testing.

---

## 🗄️ DATABASE — PRO TIP

21. Selalu gunakan database migration untuk setiap perubahan struktur tabel — jangan ubah tabel database secara manual. Ini memastikan semua anggota tim dan server punya struktur database yang sama.

22. Tambahkan indexing pada kolom yang sering dipakai untuk pencarian atau filtering (contoh: kolom kota_asal, tanggal_berangkat) — ini mempercepat query secara signifikan.

23. Gunakan transaksi database untuk operasi yang melibatkan banyak tabel sekaligus — contoh: saat membuat pesanan, kurangi seat, dan catat pembayaran harus berhasil semua atau gagal semua (tidak boleh setengah-setengah).

24. Buat backup database secara rutin dan otomatis — minimal sekali sehari — dan simpan di lokasi terpisah dari server utama.

---

## 📊 LOGGING & ERROR HANDLING — PRO TIP

25. Catat semua error dan aktivitas penting ke dalam log (file atau layanan logging) — jangan hanya tampilkan error ke user tanpa menyimpannya. Log sangat membantu saat debugging di production.

26. Jangan tampilkan pesan error teknis (stack trace, nama tabel, dsb.) langsung ke user — tampilkan pesan yang ramah dan mudah dimengerti, sementara detail error dicatat di log server.

27. Gunakan kode status HTTP yang tepat di response API — 200 (berhasil), 400 (input salah), 401 (belum login), 403 (tidak punya akses), 404 (data tidak ada), 500 (error server).

28. Buat format response API yang konsisten di semua endpoint — selalu sertakan field status, message, dan data sehingga tim frontend tahu apa yang harus dibaca.

---

## 🔁 VERSION CONTROL & WORKFLOW — PRO TIP

29. Gunakan Git sejak awal project dan commit secara rutin — beri pesan commit yang jelas, contoh: "Tambah fitur pilih kursi" bukan cuma "update" atau "fix".

30. Gunakan branch terpisah untuk setiap fitur atau perbaikan — jangan langsung coding di branch main/master. Contoh: buat branch fitur/pemesanan-tiket, setelah selesai baru digabung (merge).

31. Jangan pernah push file sensitif ke repository — seperti file .env, credential, atau file konfigurasi yang berisi password. Selalu cek .gitignore sebelum commit.