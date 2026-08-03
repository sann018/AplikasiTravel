# Rekap Database Terbaru RuslyTravel

## 1. Identitas Rekap

Dokumen ini merupakan laporan struktur database yang sedang aktif pada proyek
RuslyTravel. Seluruh nama tabel, nama kolom, tipe data, nilai bawaan, serta
constraint penting diperiksa langsung dari schema MySQL aktif.

| Informasi | Nilai |
|---|---|
| Nama aplikasi | RuslyTravel |
| Nama database aktif | `aplikasi_travel` |
| DBMS | MySQL |
| Tanggal verifikasi | 28 Juli 2026 |
| Total tabel | 25 tabel |
| Tabel bisnis utama | 15 tabel |
| Tabel internal Laravel | 9 tabel |
| Tabel backup legacy | 1 tabel |

Database lain bernama `travs_db` yang berada pada server lokal tidak termasuk
dalam laporan ini karena koneksi proyek saat ini menggunakan
`aplikasi_travel`.

Migration `2026_07_25_000100_backfill_cash_payment_snapshots.php` masih
berstatus pending ketika verifikasi dilakukan. Migration tersebut hanya
melakukan backfill data pembayaran tunai lama dan tidak mengubah struktur tabel.

## 2. Daftar Seluruh Tabel

| No | Nama Tabel | Kategori | Fungsi |
|---:|---|---|---|
| 1 | `pengguna` | Bisnis utama | Menyimpan akun penumpang, pengemudi, dan admin |
| 2 | `pengemudi` | Bisnis utama | Menyimpan profil dan verifikasi pengemudi |
| 3 | `rute` | Bisnis utama | Menyimpan master rute dan tarif perjalanan |
| 4 | `kendaraan` | Bisnis utama | Menyimpan data armada |
| 5 | `jadwal` | Bisnis utama | Menyimpan jadwal perjalanan |
| 6 | `kursi_jadwal` | Bisnis utama | Menyimpan status kursi pada setiap jadwal |
| 7 | `pemesanan` | Bisnis utama | Menyimpan transaksi pemesanan |
| 8 | `detail_pemesanan` | Bisnis utama | Menyimpan penumpang dan kursi yang dipesan |
| 9 | `item_bagasi` | Bisnis utama | Menyimpan barang bawaan pemesanan |
| 10 | `pembayaran` | Bisnis utama | Menyimpan transaksi pembayaran |
| 11 | `tiket_elektronik` | Bisnis utama | Menyimpan tiket dan token QR penumpang |
| 12 | `notifikasi` | Bisnis utama | Menyimpan pemberitahuan pengguna dan admin |
| 13 | `biaya_operasional_perjalanan` | Bisnis utama | Menyimpan biaya operasional perjalanan |
| 14 | `banner_beranda` | Bisnis utama | Menyimpan banner beranda aplikasi |
| 15 | `pengaturan_sistem` | Bisnis utama | Menyimpan konfigurasi aplikasi aktif |
| 16 | `cache` | Internal Laravel | Menyimpan cache aplikasi |
| 17 | `cache_locks` | Internal Laravel | Menyimpan kunci proses cache |
| 18 | `failed_jobs` | Internal Laravel | Menyimpan antrean yang gagal |
| 19 | `job_batches` | Internal Laravel | Menyimpan kelompok antrean |
| 20 | `jobs` | Internal Laravel | Menyimpan antrean yang akan diproses |
| 21 | `migrations` | Internal Laravel | Mencatat migration yang telah dijalankan |
| 22 | `password_reset_tokens` | Internal Laravel | Menyimpan token pengaturan ulang kata sandi |
| 23 | `personal_access_tokens` | Internal Laravel | Menyimpan token akses Laravel Sanctum |
| 24 | `sessions` | Internal Laravel | Menyimpan session pengguna |
| 25 | `pengaturan_sistem_legacy_backup` | Backup legacy | Menyimpan salinan pengaturan sistem lama |

## 3. Keterangan Umum

- **Primary key** adalah identitas unik setiap baris.
- **Foreign key** menghubungkan suatu kolom dengan tabel lain.
- **Unique** berarti nilai kolom atau gabungan kolom tidak boleh sama.
- **Auto increment** berarti nilainya dibuat otomatis oleh database.
- **Wajib** berarti kolom tidak menerima `NULL`.
- **Boleh kosong** berarti kolom dapat menerima `NULL`.
- Nilai `created_at` dan `updated_at` merupakan atribut waktu standar Laravel.
- Tipe `TINYINT(1)` digunakan MySQL untuk menyimpan nilai boolean.

# 4. Rekap Tabel Bisnis Utama

## 4.1 Tabel `pengguna`

**Fungsi:** menyimpan identitas, kredensial, dan jenis akun seluruh pengguna.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas unik pengguna. |
| 2 | `nama` | VARCHAR(255) | Wajib; nama lengkap pengguna. |
| 3 | `email` | VARCHAR(255) | Wajib dan unique; alamat email akun. |
| 4 | `no_hp` | VARCHAR(20) | Wajib dan unique; nomor telepon pengguna. |
| 5 | `foto_profil` | VARCHAR(255) | Boleh kosong; path atau lokasi foto profil. |
| 6 | `peran` | ENUM('penumpang','pengemudi','admin') | Wajib, default `penumpang`; menentukan hak akses pengguna. |
| 7 | `token_push` | VARCHAR(255) | Boleh kosong; token perangkat untuk notifikasi push. |
| 8 | `status_aktif` | TINYINT(1) | Wajib, default `1`; menandai akun aktif atau nonaktif. |
| 9 | `email_verified_at` | TIMESTAMP | Boleh kosong; waktu email berhasil diverifikasi. |
| 10 | `password` | VARCHAR(255) | Wajib; menyimpan hash kata sandi, bukan kata sandi asli. |
| 11 | `remember_token` | VARCHAR(100) | Boleh kosong; token fitur ingat sesi masuk. |
| 12 | `otp_code` | VARCHAR(255) | Boleh kosong; menyimpan hash kode OTP. |
| 13 | `otp_expires_at` | TIMESTAMP | Boleh kosong; batas waktu berlakunya OTP. |
| 14 | `created_at` | TIMESTAMP | Boleh kosong; waktu akun dibuat. |
| 15 | `updated_at` | TIMESTAMP | Boleh kosong; waktu akun terakhir diperbarui. |

## 4.2 Tabel `pengemudi`

**Fungsi:** menyimpan profil operasional dan hasil verifikasi pengemudi.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas profil pengemudi. |
| 2 | `pengguna_id` | BIGINT UNSIGNED | Wajib, foreign key ke `pengguna.id`, dan unique; satu pengguna maksimal memiliki satu profil pengemudi. |
| 3 | `nomor_sim` | VARCHAR(50) | Wajib dan unique; nomor SIM atau identitas pengemudi. |
| 4 | `foto_sim` | VARCHAR(255) | Boleh kosong; path foto SIM. |
| 5 | `status` | ENUM('aktif','nonaktif') | Wajib, default `aktif`; status operasional pengemudi. |
| 6 | `status_verifikasi` | ENUM('menunggu','terverifikasi','ditolak') | Wajib, default `menunggu`; status pemeriksaan pengemudi. |
| 7 | `catatan_verifikasi` | TEXT | Boleh kosong; catatan hasil pemeriksaan. |
| 8 | `verified_at` | TIMESTAMP | Boleh kosong; waktu proses verifikasi diselesaikan. |
| 9 | `verified_by` | BIGINT UNSIGNED | Boleh kosong; foreign key ke `pengguna.id` untuk admin yang memverifikasi. |
| 10 | `created_at` | TIMESTAMP | Boleh kosong; waktu profil dibuat. |
| 11 | `updated_at` | TIMESTAMP | Boleh kosong; waktu profil terakhir diperbarui. |

## 4.3 Tabel `rute`

**Fungsi:** menyimpan kota asal, kota tujuan, jarak, durasi, dan tarif rute.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas rute. |
| 2 | `kota_asal` | VARCHAR(255) | Wajib; kota awal perjalanan. |
| 3 | `kota_tujuan` | VARCHAR(255) | Wajib; kota akhir perjalanan. |
| 4 | `deskripsi_jalur` | VARCHAR(255) | Boleh kosong; informasi jalur yang dilalui. |
| 5 | `jarak_km` | DECIMAL(8,2) | Boleh kosong; jarak rute dalam kilometer. |
| 6 | `estimasi_durasi_menit` | INT UNSIGNED | Boleh kosong; perkiraan durasi perjalanan dalam menit. |
| 7 | `harga_tiket` | DECIMAL(12,2) | Wajib; harga tiket rute saat ini. |
| 8 | `status_aktif` | TINYINT(1) | Wajib, default `1`; menandai rute dapat digunakan atau tidak. |
| 9 | `created_at` | TIMESTAMP | Boleh kosong; waktu rute dibuat. |
| 10 | `updated_at` | TIMESTAMP | Boleh kosong; waktu rute terakhir diperbarui. |

## 4.4 Tabel `kendaraan`

**Fungsi:** menyimpan identitas, kapasitas, susunan kursi, dan informasi
perawatan kendaraan.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas kendaraan. |
| 2 | `nomor_plat` | VARCHAR(20) | Wajib dan unique; nomor polisi kendaraan. |
| 3 | `merk_kendaraan` | VARCHAR(255) | Wajib; merek kendaraan. |
| 4 | `warna` | VARCHAR(30) | Wajib; warna kendaraan. |
| 5 | `tahun_kendaraan` | INT UNSIGNED | Boleh kosong; tahun produksi kendaraan. |
| 6 | `tipe_kendaraan` | VARCHAR(255) | Boleh kosong; tipe atau model kendaraan. |
| 7 | `kapasitas_kursi` | INT UNSIGNED | Wajib; jumlah kursi penumpang. |
| 8 | `seat_layout` | JSON | Boleh kosong; konfigurasi susunan kursi kendaraan. |
| 9 | `fasilitas` | TEXT | Boleh kosong; daftar fasilitas kendaraan. |
| 10 | `total_kilometer` | INT UNSIGNED | Wajib, default `0`; total kilometer yang telah ditempuh. |
| 11 | `km_servis_berikutnya` | INT UNSIGNED | Boleh kosong; target kilometer untuk servis berikutnya. |
| 12 | `tgl_terakhir_servis` | DATE | Boleh kosong; tanggal servis terakhir. |
| 13 | `tgl_pajak_tahunan` | DATE | Boleh kosong; tanggal batas pajak tahunan. |
| 14 | `status_kendaraan` | ENUM('aktif','perawatan','nonaktif') | Wajib, default `aktif`; status operasional kendaraan. |
| 15 | `foto_kendaraan` | VARCHAR(255) | Boleh kosong; path foto kendaraan. |
| 16 | `created_at` | TIMESTAMP | Boleh kosong; waktu data kendaraan dibuat. |
| 17 | `updated_at` | TIMESTAMP | Boleh kosong; waktu data kendaraan diperbarui. |

## 4.5 Tabel `jadwal`

**Fungsi:** menghubungkan kendaraan, rute, pengemudi, dan waktu perjalanan.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas jadwal. |
| 2 | `kendaraan_id` | BIGINT UNSIGNED | Wajib; foreign key ke `kendaraan.id`. |
| 3 | `rute_id` | BIGINT UNSIGNED | Wajib; foreign key ke `rute.id`. |
| 4 | `pengemudi_id` | BIGINT UNSIGNED | Wajib; foreign key ke `pengemudi.id`. |
| 5 | `waktu_berangkat` | DATETIME | Wajib; tanggal dan waktu keberangkatan. |
| 6 | `estimasi_tiba` | DATETIME | Boleh kosong; perkiraan tanggal dan waktu tiba. |
| 7 | `status` | ENUM('draft','dibuka','ditutup','berangkat','selesai','dibatalkan') | Wajib, default `draft`; tahap operasional jadwal. |
| 8 | `created_at` | TIMESTAMP | Boleh kosong; waktu jadwal dibuat. |
| 9 | `updated_at` | TIMESTAMP | Boleh kosong; waktu jadwal terakhir diperbarui. |

## 4.6 Tabel `kursi_jadwal`

**Fungsi:** menyimpan nomor dan status setiap kursi pada suatu jadwal.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas kursi jadwal. |
| 2 | `jadwal_id` | BIGINT UNSIGNED | Wajib; foreign key ke `jadwal.id`. |
| 3 | `nomor_kursi` | INT UNSIGNED | Wajib; nomor kursi dan unique jika digabungkan dengan `jadwal_id`. |
| 4 | `status_kursi` | ENUM('tersedia','terkunci','dipesan','nonaktif') | Boleh kosong, default `tersedia`; kondisi kursi. |
| 5 | `dikunci_oleh` | BIGINT UNSIGNED | Boleh kosong; foreign key ke `pengguna.id` untuk pemilik kunci kursi. |
| 6 | `locked_at` | DATETIME | Boleh kosong; waktu kursi mulai dikunci. |
| 7 | `lock_expires_at` | DATETIME | Boleh kosong; batas akhir kunci kursi. |
| 8 | `updated_at` | TIMESTAMP | Boleh kosong; waktu status kursi diperbarui. |

Tabel ini tidak mempunyai kolom `created_at`.

## 4.7 Tabel `pemesanan`

**Fungsi:** menyimpan transaksi pemesanan, titik jemput dan turun, snapshot
tarif, serta batas waktu pembayaran.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas pemesanan. |
| 2 | `pengguna_id` | BIGINT UNSIGNED | Wajib; foreign key ke `pengguna.id` untuk pemesan. |
| 3 | `jadwal_id` | BIGINT UNSIGNED | Wajib; foreign key ke `jadwal.id`. |
| 4 | `kode_booking` | VARCHAR(255) | Wajib dan unique; kode pemesanan. |
| 5 | `jumlah_kursi` | INT UNSIGNED | Wajib; jumlah kursi yang dipesan. |
| 6 | `batas_kunci_kursi` | DATETIME | Boleh kosong; batas penguncian kursi saat pengisian formulir. |
| 7 | `batas_pembayaran` | DATETIME | Boleh kosong; batas penyelesaian pembayaran. |
| 8 | `lintang_jemput` | DECIMAL(10,7) | Boleh kosong; koordinat latitude titik jemput. |
| 9 | `bujur_jemput` | DECIMAL(10,7) | Boleh kosong; koordinat longitude titik jemput. |
| 10 | `alamat_jemput` | TEXT | Boleh kosong; alamat titik jemput. |
| 11 | `catatan_jemput` | VARCHAR(500) | Boleh kosong; patokan atau catatan penjemputan. |
| 12 | `lintang_turun` | DECIMAL(10,7) | Boleh kosong; koordinat latitude titik turun. |
| 13 | `bujur_turun` | DECIMAL(10,7) | Boleh kosong; koordinat longitude titik turun. |
| 14 | `alamat_turun` | TEXT | Boleh kosong; alamat titik turun. |
| 15 | `catatan_turun` | VARCHAR(500) | Boleh kosong; patokan atau catatan titik turun. |
| 16 | `subtotal_tiket` | DECIMAL(12,2) | Wajib, default `0.00`; snapshot subtotal tiket ketika transaksi dibuat. |
| 17 | `total_harga` | DECIMAL(12,2) | Wajib, default `0.00`; total nilai transaksi RuslyTravel. |
| 18 | `status_pemesanan` | ENUM('draft','menunggu_pembayaran','terbayar','dibatalkan','selesai') | Wajib, default `draft`; tahap pemesanan. |
| 19 | `created_at` | TIMESTAMP | Boleh kosong; waktu pemesanan dibuat. |
| 20 | `updated_at` | TIMESTAMP | Boleh kosong; waktu pemesanan terakhir diperbarui. |

## 4.8 Tabel `detail_pemesanan`

**Fungsi:** menyimpan satu penumpang dan satu kursi di dalam pemesanan.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas detail pemesanan. |
| 2 | `pemesanan_id` | BIGINT UNSIGNED | Wajib; foreign key ke `pemesanan.id`. |
| 3 | `kursi_jadwal_id` | BIGINT UNSIGNED | Wajib, foreign key ke `kursi_jadwal.id`, dan unique; satu kursi hanya dapat dipakai satu detail aktif. |
| 4 | `nomor_kursi` | INT UNSIGNED | Wajib; snapshot nomor kursi. |
| 5 | `nama_penumpang` | VARCHAR(255) | Wajib; nama penumpang yang menempati kursi. |
| 6 | `no_hp_penumpang` | VARCHAR(20) | Wajib; nomor telepon penumpang. |
| 7 | `status_kehadiran` | ENUM('menunggu','hadir','tidak_hadir') | Wajib, default `menunggu`; status kehadiran penumpang. |
| 8 | `dijemput_pada` | DATETIME | Boleh kosong; waktu penumpang dijemput. |
| 9 | `diturunkan_pada` | DATETIME | Boleh kosong; waktu penumpang diturunkan. |
| 10 | `created_at` | TIMESTAMP | Boleh kosong; waktu detail dibuat. |
| 11 | `updated_at` | TIMESTAMP | Boleh kosong; waktu detail terakhir diperbarui. |

## 4.9 Tabel `item_bagasi`

**Fungsi:** menyimpan rincian barang bawaan yang terkait dengan pemesanan.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas item bagasi. |
| 2 | `pemesanan_id` | BIGINT UNSIGNED | Wajib; foreign key ke `pemesanan.id`. |
| 3 | `deskripsi_item` | VARCHAR(255) | Wajib; nama atau deskripsi barang. |
| 4 | `metode_hitung` | VARCHAR(20) | Boleh kosong; metode pencatatan ukuran atau jumlah barang. |
| 5 | `posisi_barang` | VARCHAR(30) | Wajib, default `masuk_bagasi`; posisi penempatan barang. |
| 6 | `kategori_ukuran` | VARCHAR(30) | Boleh kosong; kategori ukuran barang. |
| 7 | `jenis_barang_besar` | VARCHAR(50) | Boleh kosong; klasifikasi barang berukuran besar. |
| 8 | `berat_kg` | DECIMAL(5,2) | Boleh kosong; berat barang dalam kilogram. |
| 9 | `ukuran_cm` | INT UNSIGNED | Boleh kosong; ukuran gabungan barang dalam sentimeter. |
| 10 | `panjang_cm` | INT UNSIGNED | Boleh kosong; panjang barang dalam sentimeter. |
| 11 | `lebar_cm` | INT UNSIGNED | Boleh kosong; lebar barang dalam sentimeter. |
| 12 | `jumlah_item` | INT UNSIGNED | Wajib, default `1`; jumlah barang. |
| 13 | `foto_url` | VARCHAR(255) | Boleh kosong; lokasi foto utama barang. |
| 14 | `foto_urls` | JSON | Boleh kosong; daftar lokasi beberapa foto barang. |
| 15 | `biaya_item` | DECIMAL(12,2) | Wajib, default `0.00`; nilai kompatibilitas biaya item. |
| 16 | `created_at` | TIMESTAMP | Boleh kosong; waktu item dibuat. |
| 17 | `updated_at` | TIMESTAMP | Boleh kosong; waktu item terakhir diperbarui. |

## 4.10 Tabel `pembayaran`

**Fungsi:** menyimpan satu transaksi pembayaran untuk satu pemesanan, termasuk
snapshot nilai dari penyedia pembayaran.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas pembayaran. |
| 2 | `pemesanan_id` | BIGINT UNSIGNED | Wajib, foreign key ke `pemesanan.id`, dan unique; satu pemesanan maksimal memiliki satu pembayaran. |
| 3 | `order_id_gateway` | VARCHAR(255) | Boleh kosong dan unique; ID transaksi pada payment gateway. |
| 4 | `gateway_reference` | VARCHAR(255) | Boleh kosong; referensi transaksi gateway atau lokal. |
| 5 | `metode_bayar` | VARCHAR(50) | Wajib; metode pembayaran seperti tunai, QRIS, atau virtual account. |
| 6 | `paid_channel` | VARCHAR(255) | Boleh kosong; kanal pembayaran yang digunakan. |
| 7 | `nominal_transaksi` | BIGINT UNSIGNED | Boleh kosong; snapshot nilai transaksi RuslyTravel. |
| 8 | `biaya_admin_provider` | BIGINT UNSIGNED | Boleh kosong; snapshot biaya administrasi penyedia pembayaran. |
| 9 | `total_dibayar_penumpang` | BIGINT UNSIGNED | Boleh kosong; snapshot total yang dibayar penumpang. |
| 10 | `payment_number` | TEXT | Boleh kosong; nomor pembayaran, kode virtual account, atau data QR. |
| 11 | `jumlah_bayar` | DECIMAL(12,2) | Wajib, default `0.00`; nilai pembayaran kompatibilitas transaksi lama. |
| 12 | `status_bayar` | ENUM('menunggu','berhasil','gagal','kedaluwarsa') | Wajib, default `menunggu`; status pembayaran. |
| 13 | `gateway_response` | TEXT | Boleh kosong; salinan respons payment gateway. |
| 14 | `raw_webhook_payload` | JSON | Boleh kosong; payload asli yang diterima melalui webhook. |
| 15 | `expired_at` | DATETIME | Boleh kosong; batas waktu pembayaran dari provider. |
| 16 | `webhook_verified_at` | DATETIME | Boleh kosong; waktu webhook berhasil diverifikasi. |
| 17 | `dibayar_pada` | DATETIME | Boleh kosong; waktu pembayaran berhasil. |
| 18 | `created_at` | TIMESTAMP | Boleh kosong; waktu pembayaran dibuat. |
| 19 | `updated_at` | TIMESTAMP | Boleh kosong; waktu pembayaran terakhir diperbarui. |

## 4.11 Tabel `tiket_elektronik`

**Fungsi:** menyimpan satu tiket elektronik dan token QR untuk satu penumpang.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas tiket elektronik. |
| 2 | `detail_pemesanan_id` | BIGINT UNSIGNED | Wajib, foreign key ke `detail_pemesanan.id`, dan unique; satu detail penumpang maksimal memiliki satu tiket. |
| 3 | `kode_qr` | VARCHAR(255) | Wajib; kode tiket yang ditampilkan sebagai QR. |
| 4 | `token_unik_qr` | VARCHAR(100) | Wajib dan unique; token untuk memverifikasi tiket. |
| 5 | `sudah_dipindai` | TINYINT(1) | Wajib, default `0`; menandai tiket sudah digunakan atau belum. |
| 6 | `dipindai_pada` | DATETIME | Boleh kosong; waktu tiket dipindai. |
| 7 | `diterbitkan_pada` | DATETIME | Boleh kosong; waktu tiket diterbitkan. |
| 8 | `created_at` | TIMESTAMP | Boleh kosong; waktu data tiket dibuat. |
| 9 | `updated_at` | TIMESTAMP | Boleh kosong; waktu data tiket terakhir diperbarui. |

## 4.12 Tabel `notifikasi`

**Fungsi:** menyimpan pemberitahuan untuk pengguna maupun admin.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas notifikasi. |
| 2 | `pengguna_id` | BIGINT UNSIGNED | Wajib; foreign key ke `pengguna.id` sebagai penerima. |
| 3 | `judul` | VARCHAR(255) | Wajib; judul notifikasi. |
| 4 | `pesan` | TEXT | Wajib; isi notifikasi. |
| 5 | `jenis` | ENUM('pembayaran','perjalanan','sistem') | Wajib, default `sistem`; kategori notifikasi. |
| 6 | `referensi_id` | BIGINT UNSIGNED | Boleh kosong; referensi logis ke data terkait dan bukan foreign key fisik. |
| 7 | `sudah_dibaca` | TINYINT(1) | Wajib, default `0`; menandai notifikasi sudah dibaca atau belum. |
| 8 | `created_at` | TIMESTAMP | Boleh kosong; waktu notifikasi dibuat. |
| 9 | `updated_at` | TIMESTAMP | Boleh kosong; waktu notifikasi terakhir diperbarui. |

## 4.13 Tabel `biaya_operasional_perjalanan`

**Fungsi:** menyimpan biaya operasional yang dikeluarkan untuk suatu jadwal
perjalanan.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas biaya operasional. |
| 2 | `jadwal_id` | BIGINT UNSIGNED | Wajib; foreign key ke `jadwal.id`. |
| 3 | `jenis_biaya` | VARCHAR(255) | Wajib; kategori pengeluaran. |
| 4 | `nominal` | DECIMAL(12,2) | Wajib; jumlah biaya. |
| 5 | `keterangan` | TEXT | Boleh kosong; penjelasan biaya. |
| 6 | `bukti_url` | VARCHAR(255) | Boleh kosong; lokasi foto atau dokumen bukti. |
| 7 | `status_verifikasi` | VARCHAR(30) | Wajib, default `disetujui`; status pemeriksaan biaya. |
| 8 | `diverifikasi_oleh` | BIGINT UNSIGNED | Boleh kosong; foreign key ke `pengguna.id` untuk verifikator. |
| 9 | `diverifikasi_pada` | TIMESTAMP | Boleh kosong; waktu biaya diverifikasi. |
| 10 | `catatan_verifikasi` | TEXT | Boleh kosong; catatan hasil verifikasi. |
| 11 | `tanggal_biaya` | DATETIME | Wajib; tanggal dan waktu pengeluaran. |
| 12 | `created_by` | BIGINT UNSIGNED | Boleh kosong; foreign key ke `pengguna.id` untuk pencatat biaya. |
| 13 | `created_at` | TIMESTAMP | Boleh kosong; waktu data biaya dibuat. |
| 14 | `updated_at` | TIMESTAMP | Boleh kosong; waktu data biaya terakhir diperbarui. |

## 4.14 Tabel `banner_beranda`

**Fungsi:** menyimpan banner yang ditampilkan pada halaman beranda aplikasi.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas banner. |
| 2 | `judul` | VARCHAR(120) | Wajib; judul utama banner. |
| 3 | `subjudul` | VARCHAR(255) | Boleh kosong; teks tambahan banner. |
| 4 | `path_gambar` | VARCHAR(255) | Wajib; lokasi file gambar banner. |
| 5 | `aktif` | TINYINT(1) | Wajib, default `1`, dan memiliki index; menandai banner ditampilkan atau tidak. |
| 6 | `urutan` | INT UNSIGNED | Wajib, default `0`; menentukan urutan penampilan banner. |
| 7 | `dibuat_oleh` | BIGINT UNSIGNED | Boleh kosong; foreign key ke `pengguna.id` untuk admin pembuat banner. |
| 8 | `created_at` | TIMESTAMP | Boleh kosong; waktu banner dibuat. |
| 9 | `updated_at` | TIMESTAMP | Boleh kosong; waktu banner terakhir diperbarui. |

## 4.15 Tabel `pengaturan_sistem`

**Fungsi:** menyimpan konfigurasi aplikasi aktif dengan pola key-value.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas pengaturan. |
| 2 | `kelompok` | VARCHAR(50) | Wajib dan memiliki index; kelompok konfigurasi. |
| 3 | `kunci` | VARCHAR(100) | Wajib dan unique; nama unik konfigurasi. |
| 4 | `nilai` | TEXT | Boleh kosong; nilai konfigurasi yang disimpan. |
| 5 | `tipe` | ENUM('string','integer','boolean','float','text') | Wajib, default `string`; menentukan cara nilai dikonversi aplikasi. |
| 6 | `deskripsi` | VARCHAR(255) | Boleh kosong; penjelasan fungsi konfigurasi. |
| 7 | `diperbarui_oleh` | BIGINT UNSIGNED | Boleh kosong; foreign key ke `pengguna.id` untuk admin terakhir yang mengubah pengaturan. |
| 8 | `created_at` | TIMESTAMP | Boleh kosong; waktu pengaturan dibuat. |
| 9 | `updated_at` | TIMESTAMP | Boleh kosong; waktu pengaturan terakhir diperbarui. |

# 5. Rekap Tabel Internal Laravel

Tabel pada bagian ini dibentuk dan digunakan oleh Laravel. Nama tabel dan
kolomnya mengikuti kontrak framework sehingga tidak disarankan diganti tanpa
menyesuaikan konfigurasi Laravel.

## 5.1 Tabel `cache`

**Fungsi:** menyimpan cache aplikasi ketika driver cache menggunakan database.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `key` | VARCHAR(255) | Primary key; nama unik cache. |
| 2 | `value` | MEDIUMTEXT | Wajib; data cache yang disimpan. |
| 3 | `expiration` | BIGINT | Wajib dan memiliki index; waktu kedaluwarsa cache dalam Unix timestamp. |

## 5.2 Tabel `cache_locks`

**Fungsi:** menyimpan kunci atomik agar proses yang sama tidak dijalankan
bersamaan.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `key` | VARCHAR(255) | Primary key; nama unik kunci cache. |
| 2 | `owner` | VARCHAR(255) | Wajib; identitas proses pemilik kunci. |
| 3 | `expiration` | BIGINT | Wajib dan memiliki index; waktu kedaluwarsa kunci dalam Unix timestamp. |

## 5.3 Tabel `failed_jobs`

**Fungsi:** menyimpan pekerjaan antrean yang gagal diproses.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas kegagalan antrean. |
| 2 | `uuid` | VARCHAR(255) | Wajib dan unique; identitas universal antrean. |
| 3 | `connection` | TEXT | Wajib; nama koneksi antrean. |
| 4 | `queue` | TEXT | Wajib; nama jalur antrean. |
| 5 | `payload` | LONGTEXT | Wajib; data pekerjaan yang gagal. |
| 6 | `exception` | LONGTEXT | Wajib; detail exception atau kesalahan. |
| 7 | `failed_at` | TIMESTAMP | Wajib, default waktu saat ini; waktu pekerjaan dinyatakan gagal. |

## 5.4 Tabel `job_batches`

**Fungsi:** menyimpan status sekumpulan pekerjaan antrean yang dijalankan
sebagai satu batch.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | VARCHAR(255) | Primary key; identitas batch. |
| 2 | `name` | VARCHAR(255) | Wajib; nama batch. |
| 3 | `total_jobs` | INT | Wajib; jumlah seluruh pekerjaan dalam batch. |
| 4 | `pending_jobs` | INT | Wajib; jumlah pekerjaan yang belum selesai. |
| 5 | `failed_jobs` | INT | Wajib; jumlah pekerjaan yang gagal. |
| 6 | `failed_job_ids` | LONGTEXT | Wajib; daftar ID pekerjaan yang gagal. |
| 7 | `options` | MEDIUMTEXT | Boleh kosong; opsi tambahan batch. |
| 8 | `cancelled_at` | INT | Boleh kosong; waktu pembatalan dalam Unix timestamp. |
| 9 | `created_at` | INT | Wajib; waktu pembuatan dalam Unix timestamp. |
| 10 | `finished_at` | INT | Boleh kosong; waktu selesai dalam Unix timestamp. |

## 5.5 Tabel `jobs`

**Fungsi:** menyimpan pekerjaan antrean yang menunggu atau sedang diproses.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas pekerjaan. |
| 2 | `queue` | VARCHAR(255) | Wajib dan memiliki index; nama jalur antrean. |
| 3 | `payload` | LONGTEXT | Wajib; data pekerjaan yang akan dijalankan. |
| 4 | `attempts` | TINYINT UNSIGNED | Wajib; jumlah percobaan pemrosesan. |
| 5 | `reserved_at` | INT UNSIGNED | Boleh kosong; waktu pekerjaan mulai dicadangkan dalam Unix timestamp. |
| 6 | `available_at` | INT UNSIGNED | Wajib; waktu pekerjaan boleh diproses dalam Unix timestamp. |
| 7 | `created_at` | INT UNSIGNED | Wajib; waktu pekerjaan dibuat dalam Unix timestamp. |

## 5.6 Tabel `migrations`

**Fungsi:** mencatat migration Laravel yang telah dijalankan pada database.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | INT UNSIGNED | Primary key dan auto increment; identitas catatan migration. |
| 2 | `migration` | VARCHAR(255) | Wajib; nama file migration. |
| 3 | `batch` | INT | Wajib; nomor kelompok eksekusi migration. |

## 5.7 Tabel `password_reset_tokens`

**Fungsi:** menyimpan token sementara untuk proses pengaturan ulang kata sandi.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `email` | VARCHAR(255) | Primary key; email pemilik permintaan reset. |
| 2 | `token` | VARCHAR(255) | Wajib; token reset kata sandi. |
| 3 | `created_at` | TIMESTAMP | Boleh kosong; waktu token dibuat. |

## 5.8 Tabel `personal_access_tokens`

**Fungsi:** menyimpan token autentikasi API yang dikelola Laravel Sanctum.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | BIGINT UNSIGNED | Primary key dan auto increment; identitas token. |
| 2 | `tokenable_type` | VARCHAR(255) | Wajib dan memiliki index gabungan; nama model pemilik token. |
| 3 | `tokenable_id` | BIGINT UNSIGNED | Wajib dan memiliki index gabungan; ID model pemilik token. |
| 4 | `name` | TEXT | Wajib; nama token atau perangkat. |
| 5 | `token` | VARCHAR(64) | Wajib dan unique; hash token akses. |
| 6 | `abilities` | TEXT | Boleh kosong; daftar kemampuan atau izin token. |
| 7 | `last_used_at` | TIMESTAMP | Boleh kosong; waktu terakhir token digunakan. |
| 8 | `expires_at` | TIMESTAMP | Boleh kosong dan memiliki index; batas waktu berlaku token. |
| 9 | `created_at` | TIMESTAMP | Boleh kosong; waktu token dibuat. |
| 10 | `updated_at` | TIMESTAMP | Boleh kosong; waktu token terakhir diperbarui. |

## 5.9 Tabel `sessions`

**Fungsi:** menyimpan session aplikasi ketika driver session menggunakan
database.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `id` | VARCHAR(255) | Primary key; identitas unik session. |
| 2 | `user_id` | BIGINT UNSIGNED | Boleh kosong dan memiliki index; ID pengguna yang memiliki session. |
| 3 | `ip_address` | VARCHAR(45) | Boleh kosong; alamat IP pengguna. |
| 4 | `user_agent` | TEXT | Boleh kosong; informasi browser atau perangkat. |
| 5 | `payload` | LONGTEXT | Wajib; data session yang diserialisasi. |
| 6 | `last_activity` | INT | Wajib dan memiliki index; waktu aktivitas terakhir dalam Unix timestamp. |

# 6. Rekap Tabel Backup Legacy

## 6.1 Tabel `pengaturan_sistem_legacy_backup`

**Fungsi:** menyimpan salinan struktur dan data pengaturan sistem lama sebelum
standardisasi tabel aktif. Tabel ini bukan sumber pengaturan aktif aplikasi.

| **No** | **Nama Kolom** | **Tipe Data** | **Penjelasan** |
|---:|---|---|---|
| 1 | `kunci` | VARCHAR(100) | Primary key; nama unik pengaturan lama. |
| 2 | `nilai` | TEXT | Boleh kosong; nilai pengaturan lama. |
| 3 | `tipe_data` | ENUM('integer','decimal','string','boolean','json') | Wajib, default `string`; tipe data pengaturan lama. |
| 4 | `grup` | ENUM('umum','pemesanan','pembayaran','bagasi','notifikasi') | Wajib dan memiliki index; kelompok pengaturan lama. |
| 5 | `deskripsi` | VARCHAR(255) | Boleh kosong; penjelasan pengaturan lama. |
| 6 | `updated_at` | TIMESTAMP | Boleh kosong; waktu terakhir pengaturan lama diperbarui. |
| 7 | `updated_by` | BIGINT UNSIGNED | Boleh kosong; foreign key ke `pengguna.id` untuk pengguna yang terakhir memperbarui data lama. |

# 7. Ringkasan Relasi Bisnis

| Tabel Anak | Kolom | Mengacu ke |
|---|---|---|
| `pengemudi` | `pengguna_id` | `pengguna.id` |
| `pengemudi` | `verified_by` | `pengguna.id` |
| `jadwal` | `kendaraan_id` | `kendaraan.id` |
| `jadwal` | `rute_id` | `rute.id` |
| `jadwal` | `pengemudi_id` | `pengemudi.id` |
| `kursi_jadwal` | `jadwal_id` | `jadwal.id` |
| `kursi_jadwal` | `dikunci_oleh` | `pengguna.id` |
| `pemesanan` | `pengguna_id` | `pengguna.id` |
| `pemesanan` | `jadwal_id` | `jadwal.id` |
| `detail_pemesanan` | `pemesanan_id` | `pemesanan.id` |
| `detail_pemesanan` | `kursi_jadwal_id` | `kursi_jadwal.id` |
| `item_bagasi` | `pemesanan_id` | `pemesanan.id` |
| `pembayaran` | `pemesanan_id` | `pemesanan.id` |
| `tiket_elektronik` | `detail_pemesanan_id` | `detail_pemesanan.id` |
| `notifikasi` | `pengguna_id` | `pengguna.id` |
| `biaya_operasional_perjalanan` | `jadwal_id` | `jadwal.id` |
| `biaya_operasional_perjalanan` | `diverifikasi_oleh` | `pengguna.id` |
| `biaya_operasional_perjalanan` | `created_by` | `pengguna.id` |
| `banner_beranda` | `dibuat_oleh` | `pengguna.id` |
| `pengaturan_sistem` | `diperbarui_oleh` | `pengguna.id` |

Kolom `notifikasi.referensi_id` merupakan referensi logis dan bukan foreign key
fisik.

# 8. Kesimpulan

Database aktif `aplikasi_travel` memiliki **25 tabel**, yang terdiri dari:

- **15 tabel bisnis utama** untuk proses operasional RuslyTravel;
- **9 tabel internal Laravel** untuk cache, antrean, migration, autentikasi, dan
  session;
- **1 tabel backup legacy** untuk mempertahankan data pengaturan sistem lama.

Nama tabel bisnis aktif telah menggunakan bahasa Indonesia. Beberapa nama kolom
teknis tetap menggunakan bahasa Inggris karena mengikuti standar Laravel,
autentikasi, atau kontrak integrasi payment gateway.

# 9. Sumber Verifikasi

- schema MySQL aktif `aplikasi_travel`;
- `information_schema.TABLES`;
- `information_schema.COLUMNS`;
- `information_schema.STATISTICS`;
- `information_schema.KEY_COLUMN_USAGE`;
- migration Laravel pada `backend/database/migrations`;
- model Eloquent pada `backend/app/Models`.
