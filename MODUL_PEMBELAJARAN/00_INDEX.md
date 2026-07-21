# Modul Pembelajaran Aplikasi RuslyTravel (TravS)

> Modul ini dibuat khusus untuk membantu kamu **memahami seluruh kode program** aplikasi
> "Rancang Bangun Aplikasi Pemesanan Jasa Transportasi Umum Lintas Kota Pada Rusly Travel
> Berbasis Mobile", sekaligus **persiapan sidang** di mana kamu akan diminta membuka kode,
> mengubah tampilan (warna, ukuran, bentuk, posisi), atau memperbaiki bug.

---

## Cara Memakai Modul Ini

Baca berurutan dari atas ke bawah. Tiap file punya fokus berbeda:

| File | Isi | Untuk Apa |
|------|-----|-----------|
| `00_INDEX.md` | Daftar isi + peta belajar (file ini) | Titik awal |
| `01_GAMBARAN_UMUM_DAN_ARSITEKTUR.md` | Apa itu sistem ini, siapa penggunanya, teknologi, arsitektur besar | Paham konsep dulu |
| `02_STRUKTUR_DAN_SETUP.md` | Struktur folder, cara menjalankan project dari nol | Tahu di mana file berada |
| `03_DATABASE_DAN_MODEL.md` | Semua tabel, relasi, dan Model Eloquent | Bahan **BAB 4.3 Implementasi Database** |
| `04_FITUR_BACKEND.md` | Fitur backend per modul + potongan kode | Paham logika bisnis |
| `05_FITUR_FLUTTER.md` | Halaman aplikasi mobile per fitur + potongan kode | Paham sisi pengguna |
| `06_PANDUAN_UBAH_TAMPILAN.md` | **Cara ganti warna, ukuran, posisi, dan perbaiki bug** | **PALING PENTING untuk sidang** |
| `07_MATERI_BAB4_BAB5.md` | Draft siap pakai untuk BAB IV & BAB V laporan | Menyusun laporan |
| `08_LATIHAN_SIDANG.md` | Simulasi tanya-jawab + latihan tugas ubah UI | Latihan praktik |

---

## Peta Belajar yang Disarankan

**Tahap 1 — Paham konsep (1-2 jam)**
1. Baca `01_GAMBARAN_UMUM_DAN_ARSITEKTUR.md` sampai paham 3 hal:
   - Ada berapa jenis pengguna dan apa bedanya
   - Backend (Laravel) dan Frontend (Flutter) itu dua hal terpisah yang saling bicara lewat API
   - Admin pakai web, penumpang & pengemudi pakai aplikasi mobile

**Tahap 2 — Kenali struktur (1 jam)**
2. Baca `02_STRUKTUR_DAN_SETUP.md`, buka VS Code, cocokkan tiap folder yang disebut dengan yang ada di komputermu.

**Tahap 3 — Dalami isi (3-4 jam)**
3. Baca `03`, `04`, `05` sambil membuka file kode yang disebut. Jangan hanya baca modul — buka file aslinya biar terbiasa.

**Tahap 4 — Latihan praktik (WAJIB, 2-3 jam)**
4. Baca `06_PANDUAN_UBAH_TAMPILAN.md` lalu **praktikkan langsung**: ganti satu warna, ubah satu ukuran font, geser satu tombol. Ini yang paling sering diminta saat sidang.
5. Kerjakan `08_LATIHAN_SIDANG.md`.

**Tahap 5 — Menulis laporan**
6. Pakai `07_MATERI_BAB4_BAB5.md` sebagai kerangka BAB IV & V.

---

## Ringkasan Super Singkat (baca ini kalau buru-buru)

- **Nama sistem:** RuslyTravel, di dalam kode diberi merek "TravS".
- **Jenis:** Aplikasi pemesanan tiket travel (angkutan umum) lintas kota, berbasis mobile + panel admin web.
- **Backend:** Laravel 13 (PHP 8.3) + Laravel Sanctum (untuk login API). Database MySQL.
- **Frontend mobile:** Flutter (bahasa Dart). Dipakai penumpang dan pengemudi.
- **Web admin:** Halaman web bawaan Laravel (Blade) dengan CSS custom. Dipakai admin.
- **4 peran pengguna:** Tamu, Penumpang, Pengemudi, Admin.
- **Alur inti:** Tamu lihat jadwal → daftar/login → pilih kursi → isi data & titik jemput/turun → bayar (QRIS/Transfer/Tunai) → tiket QR terbit → pengemudi scan QR saat perjalanan.

---

## Catatan Penting Sebelum Sidang

1. **Jangan menghafal, tapi pahami alur.** Penguji lebih suka kamu bisa menelusuri "kalau saya klik tombol ini, kode mana yang jalan".
2. **Kuasai `06_PANDUAN_UBAH_TAMPILAN.md`.** Di situ ada latihan konkret ubah warna/ukuran/posisi.
3. **Tahu beda dua dunia:** kalau diminta ubah tampilan **admin** → edit file `.blade.php` + `app.css`. Kalau diminta ubah tampilan **mobile** → edit file `.dart`.
4. Semua istilah kode di project ini pakai **bahasa Indonesia** (contoh: `pemesanan`, `pengemudi`, `jadwal`), jadi lebih mudah dibaca.
