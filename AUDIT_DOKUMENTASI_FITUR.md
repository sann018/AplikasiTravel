# Audit Dokumentasi Fitur AplikasiTravel

Tanggal audit: 27 Juni 2026

Dokumen ini dibuat untuk menjawab apakah fitur, perbaikan, dan peningkatan dari proses pengerjaan sudah tercatat di dokumentasi project. Sumber audit adalah kode yang ada di repository saat ini, file dokumentasi yang tersedia, dan ringkasan kebutuhan dari riwayat diskusi pengerjaan.

## Kesimpulan Singkat

Belum semua perubahan tercatat merata di semua file dokumentasi sebelum audit ini dilakukan.

Dokumentasi yang paling mendekati kondisi terbaru adalah:

- `STATUS_FITUR_PROJECT.md`
- `task.md`
- `RANGKUMAN_BELAJAR_SIDANG.md`

Dokumen `PRD.md` masih berisi banyak catatan historis dari awal proyek, sehingga tidak boleh dijadikan satu-satunya acuan status terbaru. Setelah audit ini, status terkini sebaiknya dibaca dari:

- `STATUS_FITUR_PROJECT.md` untuk ringkasan fitur.
- `task.md` untuk roadmap/checklist teknis.
- `RANGKUMAN_BELAJAR_SIDANG.md` untuk bahan belajar sidang.
- `AUDIT_DOKUMENTASI_FITUR.md` untuk jejak kecocokan fitur dengan dokumentasi.

## Matriks Perubahan Penting

| Area | Status di Kode | Status Dokumentasi Setelah Audit | Catatan |
|---|---|---|---|
| Branding RuslyTravel | Sudah diterapkan di mobile dan admin | Tercatat | Nama TravS tetap ada pada beberapa class internal agar tidak perlu refactor besar. |
| Role tamu, penumpang, pengemudi | Sudah ada | Tercatat | Tamu hanya boleh melihat data, fitur pemesanan tetap dikunci. |
| Login penumpang dan pengemudi | Sudah dipisahkan secara UI | Tercatat | Register mandiri difokuskan untuk penumpang. |
| Beranda penumpang/tamu/pengemudi | Sudah diselaraskan ke desain mobile | Tercatat | Menu cepat duplikat sudah dikurangi pada role yang relevan. |
| Rute dan jadwal bookmark card | Sudah ada | Tercatat | Jadwal memakai foto kendaraan jika tersedia. |
| Foto kendaraan/armada | Sudah ada di admin dan mobile | Tercatat | Admin bisa upload, tabel menampilkan thumbnail, preview dibatasi sekitar 500x500. |
| Dropdown tipe kendaraan | Sudah ada di form armada | Tercatat | Mengurangi input bebas yang tidak konsisten. |
| Denah kursi top-view | Sudah ada | Tercatat | Kursi pengemudi diberi label visual `P`; kursi penumpang mengikuti `seat_layout`. |
| Seat lock dan mode tamu terkunci | Sudah ada | Tercatat | Mencegah tamu membuat lock kursi tanpa akun. |
| Timer form pemesanan dan pembayaran | Sudah ada secara konsep/backend | Tercatat | Form pemesanan 30 menit, pembayaran 5 menit berdasarkan setting sistem. |
| Bagasi berat/ukuran + item | Sudah ada | Tercatat | Biaya tambahan mengikuti pengaturan sistem dan migration terbaru mengarah ke Rp3.000 per item tambahan. |
| Upload/preview foto bagasi | Sudah ada | Tercatat | Foto dikompresi di mobile dan ditampilkan pada detail pemesanan. |
| Titik jemput dan reverse geocoding | Sudah ada | Tercatat | Alamat dibuat lebih manusiawi, koordinat tetap disimpan untuk Maps. |
| Pembayaran Pakasir QRIS/transfer | Sudah ada | Tercatat | Untuk demo TA memakai mode simulasi lokal; transaksi nyata dan callback publik belum menjadi target saat ini. |
| Pembayaran tunai | Sudah ada | Tercatat | Admin dan pengemudi bisa mengonfirmasi tunai sesuai hak akses. Tiket diterbitkan setelah konfirmasi. |
| Auto tiket setelah payment valid | Sudah ada | Tercatat | QRIS/transfer via webhook/refresh; tunai via konfirmasi admin/pengemudi. |
| Tiket QR satu kali pakai | Sudah ada | Tercatat | QR untuk scan pengemudi; token mentah tidak perlu ditampilkan ke penumpang. |
| Informasi tiket penumpang | Sudah ditingkatkan | Tercatat | Tiket menampilkan informasi pendukung seperti pengemudi dan kendaraan, bukan token rahasia. |
| Manifest pengemudi | Sudah live dari backend | Tercatat | Menampilkan penumpang resmi termasuk cash pending yang perlu dikonfirmasi. |
| Foto bagasi multi foto | Sudah ada | Tercatat | Maksimal 3 foto per data bagasi, tetap mendukung data lama yang hanya satu foto. |
| Scan tiket pengemudi | Sudah ada | Tercatat | Scan mengubah status hadir/boarding dan diarahkan ke detail penumpang. |
| Status turun penumpang | Sudah ada | Tercatat | Pengemudi dapat menandai penumpang sudah turun dan barang aman. |
| Riwayat pengemudi | Sudah ada | Tercatat | Menampilkan perjalanan yang sedang/selesai sesuai data backend. |
| Profil dan foto profil | Sudah ada untuk penumpang/pengemudi/admin | Tercatat | Foto disimpan ke backend, session diperbarui, dan avatar punya fallback. |
| Notifikasi mobile | Sudah ada dengan polling + local notification | Tercatat | Belum FCM production. Permission notifikasi perangkat sudah dipakai di mobile. |
| Pengaturan bahasa mobile | Sudah ada di profil | Tercatat | Pilihan Indonesia/English tersimpan lokal di SharedPreferences. |
| Validasi form admin | Sudah ditingkatkan | Tercatat | Pesan validasi dibuat lebih jelas, termasuk unique, nomor HP/SIM, dan file gambar. |
| Bentrok jadwal pengemudi | Sudah dicegah | Tercatat | Satu pengemudi tidak boleh mendapat jadwal pada tanggal dan jam yang sama. |
| Rate limit | Sudah ada | Tercatat | Melindungi API dan web admin dari spam klik/request. |
| Dashboard admin | Sudah dibuat dan disederhanakan teksnya | Tercatat | Teks teknis yang tidak berguna untuk user dikurangi. |
| Pengajuan biaya pengemudi | Sudah ada | Tercatat | Pengemudi mengajukan biaya per jadwal; status awal menunggu verifikasi admin. |
| Verifikasi biaya operasional | Sudah ada | Tercatat | Rekap final hanya menghitung biaya yang sudah disetujui. |
| Backup database | Sudah ada command dan scheduler | Tercatat | Produksi masih perlu cron server. |

## Dokumen yang Sudah Diperbarui

- `STATUS_FITUR_PROJECT.md`: diperluas sebagai ringkasan status fitur terbaru.
- `task.md`: diperluas sebagai checklist teknis dan prioritas lanjutan.
- `RANGKUMAN_BELAJAR_SIDANG.md`: diperluas untuk bahan menjelaskan fitur terbaru saat sidang.
- `PRD.md`: diberi catatan bahwa dokumen ini adalah dokumen historis/snapshot awal dan bukan satu-satunya acuan status terbaru.
- `AUDIT_DOKUMENTASI_FITUR.md`: ditambahkan sebagai jejak audit dokumentasi.

## Dokumen yang Perlu Dibaca dengan Hati-Hati

`PRD.md` masih berguna untuk memahami rancangan awal, scope, dan konsep produk. Namun beberapa bagian di dalamnya adalah snapshot lama. Jika ada perbedaan antara PRD lama dan kode terbaru, gunakan `STATUS_FITUR_PROJECT.md`, `task.md`, dan dokumen audit ini sebagai acuan status terkini.

## Fitur yang Masih Perlu Uji Nyata atau Deployment

- Transaksi Pakasir nyata dari akun asli sampai callback publik masuk ke server tidak menjadi target demo saat ini. Mode simulasi lokal dipakai sebagai keputusan desain demo yang aman.
- HTTPS production di hosting/domain.
- Cron scheduler server untuk backup dan cleanup otomatis.
- Push notification FCM untuk perangkat real jika nanti dibutuhkan.
- Monitoring real-time admin untuk aktivitas scan dan perjalanan.
- Audit trail status pemesanan yang lebih lengkap.
- Laporan final siap cetak sesuai format operasional.
- E2E test di HP fisik dengan backend publik.

## Catatan untuk Sidang

Jika penguji bertanya apakah semua fitur sudah selesai, jawab dengan pembagian berikut:

> Fitur inti sudah berjalan: pemesanan, kursi, bagasi, pickup, pembayaran simulasi Pakasir, tiket QR, manifest pengemudi, dan admin dashboard. Bagian production-ready seperti callback Pakasir nyata, HTTPS domain, FCM, dan uji E2E perangkat fisik tidak menjadi target demo lokal saat ini.

Jika penguji bertanya kenapa masih ada beberapa dokumen lama, jawab:

> PRD dipakai sebagai dokumen rancangan awal. Setelah implementasi berjalan, status terbaru dipisahkan ke file status, task, rangkuman sidang, dan audit dokumentasi agar progres lebih mudah dibaca.
