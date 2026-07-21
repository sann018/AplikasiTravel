# 03 — Database & Model (Bahan BAB 4.3)

> Tujuan bab ini: kamu paham **semua tabel**, **isi kolomnya**, **hubungan antar tabel**, dan
> **bagaimana Model mewakili tabel**. Bab ini juga jadi bahan langsung untuk **BAB 4.3 Implementasi
> Database** di laporanmu.

---

## 3.1 Konsep Dasar: Migration, Tabel, dan Model

Ada 3 istilah yang harus dibedakan:

- **Migration** (`database/migrations/`) = "cetak biru" atau blueprint. File PHP yang menuliskan
  perintah "buat tabel X dengan kolom A, B, C". Saat `php artisan migrate` dijalankan, migration
  ini diterjemahkan jadi tabel nyata di MySQL.
- **Tabel** = tempat data sungguhan tersimpan di MySQL.
- **Model** (`app/Models/`) = kelas PHP yang mewakili satu tabel, sehingga kita bisa mengakses data
  pakai kode (bukan tulis SQL manual). Contoh: `Pemesanan::find(1)` mengambil baris pemesanan id 1.

> **Poin sidang:** kalau ditanya "bagaimana kamu membuat tabel?", jawab: "Lewat migration Laravel.
> Migration adalah file yang mendefinisikan struktur tabel, lalu dijalankan dengan perintah
> `php artisan migrate` sehingga tabelnya otomatis terbentuk di database MySQL."

---

## 3.2 Daftar Semua Tabel

Sistem ini punya tabel-tabel berikut (dikelompokkan berdasarkan fungsinya):

### Kelompok Akun
| Tabel | Fungsi |
|-------|--------|
| `pengguna` | Semua akun (penumpang, pengemudi, admin) |
| `personal_access_tokens` | Token login mobile (Sanctum) |
| `password_reset_tokens` | Token reset kata sandi |

### Kelompok Master Operasional
| Tabel | Fungsi |
|-------|--------|
| `rute` | Trayek antar kota (asal → tujuan, harga) |
| `kendaraan` | Data armada/mobil |
| `pengemudi` | Data sopir (terhubung ke `pengguna`) |
| `jadwal` | Jadwal keberangkatan |
| `kursi_jadwal` | Kursi untuk tiap jadwal |

### Kelompok Transaksi
| Tabel | Fungsi |
|-------|--------|
| `pemesanan` | Booking utama |
| `detail_pemesanan` | Data tiap penumpang dalam 1 booking |
| `item_bagasi` | Barang bawaan |
| `pembayaran` | Data pembayaran |
| `tiket_elektronik` | Tiket ber-QR |

### Kelompok Pendukung
| Tabel | Fungsi |
|-------|--------|
| `notifikasi` | Pemberitahuan ke pengguna |
| `biaya_operasional_perjalanan` | Pengeluaran perjalanan (bensin, tol) |
| `system_settings` | Pengaturan sistem (nama app, aturan booking, dll) |
| `home_banners` | Banner promosi di beranda mobile |

### Kelompok Bawaan Laravel (jarang disentuh)
`cache`, `jobs`, `sessions` — tabel teknis Laravel. Pada project ini tidak dipakai runtime karena
setting-nya memakai file/sync, bukan database (bisa diabaikan di laporan atau disebut sebagai tabel
sistem bawaan framework).

---

## 3.3 Struktur Kolom Tiap Tabel (Detail)

Di bawah ini struktur final tiap tabel. Untuk laporan BAB 4.3, kamu bisa ubah jadi tabel berformat
"Nama Kolom | Tipe Data | Keterangan".

### Tabel `pengguna`
File migration: `0001_01_01_000000_create_users_table.php` + `2026_05_30_000100_add_otp_columns...`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik akun |
| nama | string | Nama lengkap |
| email | string, unik | Email login |
| no_hp | string(20), unik | Nomor HP |
| foto_profil | string, nullable | Path foto profil |
| peran | enum(penumpang, pengemudi, admin) | Peran pengguna |
| token_push | string, nullable | (untuk notifikasi push, belum dipakai) |
| status_aktif | boolean | Akun aktif/tidak |
| email_verified_at | timestamp, nullable | Waktu verifikasi email |
| password | string | Kata sandi (ter-hash) |
| otp_code | string, nullable | Kode OTP verifikasi/registrasi |
| otp_expires_at | timestamp, nullable | Kadaluarsa OTP |
| created_at, updated_at | timestamp | Waktu dibuat/diubah |

### Tabel `rute`
File: `2026_04_22_000100_create_master_operasional_tables.php`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik rute |
| kota_asal | string | Kota keberangkatan |
| kota_tujuan | string | Kota tujuan |
| deskripsi_jalur | string, nullable | Keterangan jalur |
| jarak_km | decimal, nullable | Jarak tempuh (km) |
| estimasi_durasi_menit | integer, nullable | Perkiraan lama perjalanan |
| harga_tiket | decimal | Harga tiket per kursi |
| status_aktif | boolean | Rute aktif/tidak |

### Tabel `kendaraan`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik |
| nomor_plat | string(20), unik | Plat nomor |
| merk_kendaraan | string | Merek mobil |
| warna | string(30) | Warna |
| tahun_kendaraan | integer, nullable | Tahun |
| tipe_kendaraan | string, nullable | Tipe |
| kapasitas_kursi | integer | Jumlah kursi |
| seat_layout | json, nullable | Denah kursi |
| fasilitas | text, nullable | Fasilitas (AC, dll) |
| total_kilometer, km_servis_berikutnya, tgl_terakhir_servis, tgl_pajak_tahunan | - | Data perawatan |
| status_kendaraan | enum(aktif, perawatan, nonaktif) | Status |
| foto_kendaraan | string, nullable | Foto |

### Tabel `pengemudi`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik |
| pengguna_id | FK/UQ → pengguna | Akun sopir; satu akun maksimal satu profil pengemudi |
| nomor_sim | string(50), unik | Nomor SIM/NIK |
| foto_sim | string, nullable | Foto SIM |
| status | enum(aktif, nonaktif) | Status kerja |
| status_verifikasi | enum(menunggu, terverifikasi, ditolak) | Status verifikasi |
| catatan_verifikasi, verified_at, verified_by | - | Data verifikasi |

### Tabel `jadwal`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik |
| kendaraan_id | FK → kendaraan | Armada yang dipakai |
| rute_id | FK → rute | Rute yang dilayani |
| pengemudi_id | FK → pengemudi | Sopir yang bertugas |
| waktu_berangkat | datetime | Jam keberangkatan |
| estimasi_tiba | datetime, nullable | Perkiraan tiba (dihitung otomatis) |
| status | enum(draft, dibuka, ditutup, berangkat, selesai, dibatalkan) | Status jadwal |

### Tabel `kursi_jadwal`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik |
| jadwal_id | FK → jadwal | Milik jadwal mana |
| nomor_kursi | integer | Nomor kursi |
| status_kursi | enum(tersedia, terkunci, dipesan, nonaktif) | Status kursi |
| dikunci_oleh | FK → pengguna, nullable | Siapa yang mengunci |
| locked_at, lock_expires_at | datetime, nullable | Waktu kunci & kadaluarsa kunci |

Constraint gabungan memastikan nomor kursi tidak berulang dalam satu jadwal:
`UNIQUE (jadwal_id, nomor_kursi)`.

### Tabel `pemesanan` (setelah pembersihan bug)

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik |
| pengguna_id | FK → pengguna | Pemesan |
| jadwal_id | FK → jadwal | Jadwal yang dipesan |
| kode_booking | string, unik | Kode booking |
| jumlah_kursi | integer | Banyak kursi |
| batas_kunci_kursi | datetime, nullable | Batas waktu kunci form |
| batas_pembayaran | datetime, nullable | Batas waktu bayar |
| lintang_jemput, bujur_jemput, alamat_jemput, catatan_jemput | - | Titik jemput |
| lintang_turun, bujur_turun, alamat_turun, catatan_turun | - | Titik turun |
| subtotal_tiket | decimal | Subtotal harga tiket |
| total_harga | decimal | Total bayar (= subtotal) |
| status_pemesanan | enum(draft, menunggu_pembayaran, terbayar, dibatalkan, selesai) | Status |

> **Catatan penting untuk sidang:** kolom `biaya_bagasi` dan `diskon` **sudah dihapus** karena fitur
> tersebut tidak digunakan. Total pemesanan sekarang selalu sama dengan subtotal tiket. Ini contoh
> "perbedaan rancangan awal dan implementasi akhir" yang bisa kamu jelaskan di laporan.

### Tabel `detail_pemesanan`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik |
| pemesanan_id | FK → pemesanan | Milik booking mana |
| kursi_jadwal_id | FK → kursi_jadwal, unik | Kursi yang diambil |
| nomor_kursi | integer | Nomor kursi |
| nama_penumpang | string | Nama penumpang |
| no_hp_penumpang | string(20) | HP penumpang |
| status_kehadiran | enum(menunggu, hadir, tidak_hadir) | Status check-in |
| dijemput_pada, diturunkan_pada | datetime, nullable | Waktu jemput & turun |

### Tabel `item_bagasi`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik |
| pemesanan_id | FK → pemesanan | Milik booking mana |
| deskripsi_item | string | Nama barang |
| metode_hitung | string, nullable | Cara ukur (berat/ukuran) |
| posisi_barang | string | dipegang_sendiri / masuk_bagasi |
| kategori_ukuran | string, nullable | kecil / besar |
| jenis_barang_besar | string, nullable | sepeda/motor/dll |
| berat_kg, ukuran_cm, panjang_cm, lebar_cm | - | Dimensi |
| jumlah_item | integer | Jumlah |
| foto_url, foto_urls | - | Foto barang |
| biaya_item | decimal | Biaya (default 0) |

### Tabel `pembayaran`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik |
| pemesanan_id | FK/UQ → pemesanan | Untuk booking mana; satu booking maksimal satu pembayaran |
| order_id_gateway, gateway_reference | string, nullable | ID dari Pakasir |
| metode_bayar | string(50) | qris / transfer / cash |
| paid_channel | string, nullable | Kanal bayar sebenarnya |
| jumlah_bayar | decimal | Nominal |
| status_bayar | enum(menunggu, berhasil, gagal, kedaluwarsa) | Status |
| gateway_response, raw_webhook_payload | - | Rekaman balasan Pakasir (audit) |
| expired_at, webhook_verified_at, dibayar_pada | datetime, nullable | Waktu-waktu penting |

### Tabel `tiket_elektronik`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint (PK) | Nomor unik |
| detail_pemesanan_id | FK → detail_pemesanan, unik | Untuk penumpang mana |
| kode_qr | string | Isi teks QR |
| token_unik_qr | string(100), unik | Token unik untuk validasi |
| sudah_dipindai | boolean | Sudah discan sopir? |
| dipindai_pada, diterbitkan_pada | datetime, nullable | Waktu scan & terbit |

### Tabel `notifikasi`, `biaya_operasional_perjalanan`, `system_settings`, `home_banners`
Struktur ringkas:
- `notifikasi`: `pengguna_id`, `judul`, `pesan`, `jenis` (pembayaran/perjalanan/sistem), `sudah_dibaca`.
- `biaya_operasional_perjalanan`: `jadwal_id`, `jenis_biaya`, `nominal`, `keterangan`, `bukti_url`,
  `tanggal_biaya`, `created_by`. Kolom `created_by` boleh kosong dan memakai `ON DELETE SET NULL`
  agar histori biaya tetap tersimpan bila akun pencatat dihapus.
- `system_settings`: `group`, `key`, `value`, `type` — menyimpan konfigurasi seperti nama aplikasi,
  batas kursi per booking, durasi kunci kursi, metode bayar aktif, dll.
- `home_banners`: `judul`, `subjudul`, `gambar_path`, `aktif`, `urutan`.

---

## 3.4 Relasi Antar Tabel (ERD Sederhana)

```
pengguna ──1:N──> pemesanan ──1:N──> detail_pemesanan ──1:1──> tiket_elektronik
   │                  │                     │
   │ 1:1              │ 1:1                 └── kursi_jadwal (1:1)
   ▼                  ▼
pengemudi         pembayaran
   │
   │ (bertugas di)     pemesanan ──1:N──> item_bagasi
   ▼
 jadwal <── rute (N:1)
   │   <── kendaraan (N:1)
   │
   ├──1:N──> kursi_jadwal
   └──1:N──> biaya_operasional_perjalanan

pengguna (0:N) ── mencatat ──> biaya_operasional_perjalanan (0:1 pencatat)
```

Penjelasan relasi kunci:
- Satu **pengguna** bisa punya banyak **pemesanan** (1:N).
- Satu **pengguna** dapat mempunyai nol atau satu **profil pengemudi**. Constraint
  `UNIQUE (pengemudi.pengguna_id)` menegakkan batas maksimal satu profil.
- Satu **jadwal** dibentuk dari satu **rute**, satu **kendaraan**, satu **pengemudi** (masing-masing N:1).
- Satu **jadwal** punya banyak **kursi_jadwal** dan banyak **pemesanan**.
- Satu **pemesanan** punya banyak **detail_pemesanan** (satu per penumpang), satu **pembayaran**,
  dan banyak **item_bagasi**.
- Satu **detail_pemesanan** menghasilkan satu **tiket_elektronik**.
- Satu **pemesanan** dapat belum mempunyai pembayaran, tetapi maksimal memiliki satu record
  pembayaran. Constraint `UNIQUE (pembayaran.pemesanan_id)` menegakkannya.
- Satu pengguna dapat mencatat banyak biaya, sedangkan satu biaya dapat memiliki nol atau satu
  pencatat karena `created_by` nullable dan memakai `ON DELETE SET NULL`.

Catatan constraint untuk ERD Notasi Chen:

```text
pengemudi.pengguna_id [FK/UQ]
pembayaran.pemesanan_id [FK/UQ]
detail_pemesanan.kursi_jadwal_id [FK/UQ]
tiket_elektronik.detail_pemesanan_id [FK/UQ]
UQ gabungan: kursi_jadwal(jadwal_id, nomor_kursi)
```

---

## 3.5 Bagaimana Relasi Ditulis di Model (Contoh Kode)

Relasi di atas ditulis di file Model. Contoh dari `app/Models/Pemesanan.php`:

```php
class Pemesanan extends Model
{
    protected $table = 'pemesanan';

    // Kolom yang boleh diisi massal
    protected $fillable = [
        'pengguna_id', 'jadwal_id', 'kode_booking', 'jumlah_kursi',
        'batas_kunci_kursi', 'batas_pembayaran',
        'lintang_jemput', 'bujur_jemput', 'alamat_jemput', 'catatan_jemput',
        'lintang_turun', 'bujur_turun', 'alamat_turun', 'catatan_turun',
        'subtotal_tiket', 'total_harga', 'status_pemesanan',
    ];

    // Satu pemesanan MILIK satu pengguna
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    // Satu pemesanan MILIK satu jadwal
    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_id');
    }

    // Satu pemesanan PUNYA BANYAK detail penumpang
    public function detailPemesanan(): HasMany
    {
        return $this->hasMany(DetailPemesanan::class, 'pemesanan_id');
    }

    // Satu pemesanan PUNYA SATU pembayaran
    public function pembayaran(): HasOne
    {
        return $this->hasOne(Pembayaran::class, 'pemesanan_id');
    }
}
```

Cara membaca:
- `protected $table` — memberi tahu Model ini mewakili tabel `pemesanan`.
- `protected $fillable` — daftar kolom yang boleh diisi sekaligus (pengaman keamanan).
- `belongsTo` — relasi "milik" (misalnya pemesanan milik satu pengguna).
- `hasMany` — relasi "punya banyak".
- `hasOne` — relasi "punya satu".

Dengan relasi ini, kita bisa menulis kode singkat seperti:
```php
$pemesanan->pengguna->nama;          // nama pemesan
$pemesanan->jadwal->rute->kota_asal; // kota asal jadwal
$pemesanan->detailPemesanan;         // semua penumpang
```

---

## 3.6 Untuk Laporan BAB 4.3 (Implementasi Database)

Yang perlu kamu tampilkan di BAB 4.3:
1. **Gambar ERD** — bisa kamu gambar ulang dari diagram di 3.4 pakai tool seperti draw.io atau
   dbdiagram.io.
2. **Tabel struktur** — untuk tiap tabel penting, buat tabel "Nama Field | Tipe | Keterangan"
   (sudah tersedia lengkap di bagian 3.3, tinggal disalin & dirapikan).
3. **Penjelasan relasi** — jelaskan relasi antar tabel (dari bagian 3.4).
4. **Cara implementasi** — sebutkan bahwa database dibuat lewat migration Laravel dan diakses
   lewat Eloquent Model.

> **Tips laporan:** fokuskan pada tabel inti (pengguna, rute, kendaraan, pengemudi, jadwal,
> kursi_jadwal, pemesanan, detail_pemesanan, pembayaran, tiket_elektronik). Tabel teknis Laravel
> (cache/jobs/sessions) tidak perlu ditampilkan detail.

Lanjut ke `04_FITUR_BACKEND.md` untuk memahami logika bisnis tiap fitur.
