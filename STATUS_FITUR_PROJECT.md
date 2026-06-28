# Status Fitur Project AplikasiTravel

Tanggal status terbaru: 27 Juni 2026

## Snapshot Tambahan - 27 Juni 2026

### Perubahan biaya operasional dan bagasi

- Pengemudi sekarang dapat mengajukan biaya operasional dari jadwal yang ditugaskan melalui endpoint khusus pengemudi.
- Biaya yang diajukan pengemudi masuk dengan status awal `menunggu_verifikasi`, sehingga tidak langsung memengaruhi rekap final.
- Admin dapat menyetujui atau menolak pengajuan biaya operasional dari halaman Biaya Operasional.
- Rekap biaya dan statistik biaya operasional hanya menghitung biaya yang sudah `disetujui`.
- Biaya yang dicatat langsung oleh admin otomatis berstatus `disetujui` karena admin menjadi pihak verifikasi.
- Foto barang bawaan mendukung maksimal 3 foto per data bagasi, tetap kompatibel dengan foto lama yang hanya satu file.
- Foto barang bawaan ditampilkan pada detail pemesanan penumpang dan detail manifest pengemudi dalam grid kecil.

### Perubahan API dan data

- Endpoint baru pengemudi: `POST /api/pengemudi/trips/{jadwal}/expenses`.
- Endpoint verifikasi admin: `PATCH /admin/biaya-operasional/{biayaOperasional}/verifikasi`.
- Response manifest pengemudi menyertakan daftar `foto_urls` bagasi dan `whatsapp_url_penumpang`.
- Payload pemesanan mobile dapat mengirim `foto_urls` untuk mendukung multi foto bagasi.

### Hasil pengujian tambahan

- Backend: `php artisan test --filter=DriverOperationsTest` lulus, 14 test dan 75 assertion.
- Backend: `php artisan test --filter=AdminOperationsTest` lulus, 16 test dan 88 assertion.
- Flutter: `flutter analyze` lulus tanpa issue.
- Flutter: `flutter test` lulus, semua test passed.

## Snapshot Terbaru - 22 Juni 2026

### Fitur selesai dan baru ditambahkan

- Notifikasi Admin, Pengemudi, dan Penumpang sudah memakai data tabel `notifikasi` sebagai sumber status baca.
- Saat daftar notifikasi mobile benar-benar dibuka, item yang tampil otomatis menjadi terbaca dan badge diambil ulang dari backend.
- Polling latar tidak menandai notifikasi sebagai terbaca, sehingga badge tidak hilang sebelum pengguna membuka daftar.
- Web admin otomatis menandai notifikasi milik akun admin sebagai terbaca tanpa mengubah status notifikasi milik role lain.
- Riwayat notifikasi terbaca dapat dibersihkan berdasarkan 7, 30, 90 hari, atau seluruh notifikasi terbaca.
- Pembersihan dilindungi konfirmasi, validasi backend, rate limit API, pemeriksaan pemilik pada mobile, dan middleware role admin pada web.
- Notifikasi yang belum dibaca tidak ikut terhapus.
- Notifikasi operasional sekarang wajib aktif dan tidak lagi dapat dimatikan atau diedit dari Pengaturan Sistem.
- Tab, switch, placeholder, preview, dan editor template notifikasi sudah dihapus dari halaman Pengaturan.
- Admin menerima notifikasi untuk pemesanan baru, pembayaran tunai menunggu konfirmasi, pembayaran berhasil/gagal/kedaluwarsa, jadwal mendekati keberangkatan, serta kapasitas kursi atau bagasi hampir penuh.
- Peringatan kapasitas menggunakan batas 80% atau sisa maksimal 2 kursi dan mencegah notifikasi jadwal yang sama dibuat berulang kali.
- Admin fee Rusly Travel sudah dihapus. Total internal hanya berisi tiket dan biaya bagasi; biaya channel mengikuti Pakasir.
- Logo aplikasi menggunakan upload gambar, disimpan sebagai WEBP maksimal 512x512 px, lalu tampil di sidebar/navbar/login web admin dan aplikasi mobile.
- Dashboard admin dibuat lebih minimalis mengikuti pola halaman Pengaturan Sistem.
- Card statistik dashboard dapat diklik menuju halaman yang berkaitan.
- Filter halaman admin tetap memakai tombol `Terapkan Filter` agar request tidak dikirim pada setiap ketikan.

### Perubahan database dan API

- Fitur notifikasi terbaru tidak membutuhkan migration baru karena tabel `notifikasi` sudah memiliki `pengguna_id`, `sudah_dibaca`, dan timestamp.
- Migration pengaturan sebelumnya menambahkan `system_settings`; migration lanjutan menghapus key `admin_fee` dari pengaturan sistem.
- `GET /api/notifications?mark_as_read=1` menandai hanya item yang ditampilkan kepada pemiliknya.
- `DELETE /api/notifications/history` membersihkan riwayat terbaca milik akun sesuai periode.
- `DELETE /admin/notifikasi/history` membersihkan log terbaca melalui hak akses admin.

### Perubahan tampilan

- Web admin memiliki bagian `Bersihkan Notifikasi Lama`, pilihan periode, dan konfirmasi sebelum menghapus.
- Status sudah/belum dibaca tetap terlihat pada setiap log admin.
- Mobile menampilkan notifikasi backend untuk Penumpang dan Pengemudi, badge unread dari backend, empty state, tombol hapus riwayat, pilihan periode, dan konfirmasi.

### Hasil pengujian

- Backend: `php artisan test` lulus, 131 test dan 648 assertion.
- Flutter: `flutter test` lulus, 15 test.
- Flutter: `flutter analyze` lulus tanpa issue.
- PHP lint controller dan service notifikasi lulus tanpa syntax error.
- Render Blade admin diverifikasi melalui feature test. Pemeriksaan visual browser lokal masih perlu dilakukan manual karena konektor browser sesi pengembangan tidak dapat dimulai.

### Sedang diperbaiki dan backlog

- Push notification produksi dengan FCM masih backlog; versi saat ini memakai data notifikasi backend dan refresh/polling aplikasi.
- Rekap Biaya akan dikembangkan menjadi laporan dengan export Excel/CSV.
- Menu Audit Sistem belum dibuat.
- Fitur hapus data lama untuk Pemesanan, Pembayaran, dan Biaya Operasional belum dibuat.
- Pengujian visual mobile pada beberapa ukuran HP dan pengujian browser admin tetap perlu dilakukan sebelum demo.

### Risiko dan catatan pengecekan

- Pilihan `Semua yang sudah dibaca` benar-benar menghapus seluruh riwayat terbaca; dialog konfirmasi wajib diperhatikan admin/user.
- Web admin adalah log global, sehingga role admin boleh membersihkan notifikasi terbaca lintas role. Notifikasi belum dibaca tetap dilindungi.
- Badge mobile diperbarui saat bundle beranda dimuat ulang; push real-time saat aplikasi benar-benar tertutup membutuhkan FCM.

Tanggal pembaruan: 16 Juni 2026

File ini merangkum kondisi fitur berdasarkan kode yang ada saat ini. Tujuannya supaya progres project mudah dibaca ulang dan mudah dijelaskan saat sidang.

## Sudah Dibuat dan Sudah Terhubung

- Login, register, logout, restore session mobile.
- Tampilan login memisahkan akses penumpang, pengemudi, dan tamu agar role tidak tercampur.
- Edit profil mobile untuk nama, email, dan nomor HP.
- Edit profil pengemudi mencakup foto profil, foto SIM, dan ganti password.
- Forgot/reset password dengan email reset ketika mailer server sudah dikonfigurasi.
- Token mobile sudah punya waktu kedaluwarsa.
- API lama `/api/...` tetap aktif, dan API versi baru `/api/v1/...` sudah tersedia.
- Rate limit API untuk baca data, auth, aksi booking, upload foto, aksi pengemudi, dan webhook Pakasir.
- Rate limit web admin untuk login, baca data, dan aksi perubahan data.
- Beranda mobile berbasis role: tamu, penumpang, dan pengemudi.
- Profil mobile penumpang dan pengemudi sudah memiliki pengaturan notifikasi perangkat dan pilihan bahasa Indonesia/English.
- Daftar rute dan daftar jadwal dengan konsep bookmark card.
- Foto kendaraan/armada sudah tampil pada daftar jadwal mobile penumpang/tamu, detail jadwal, dan ringkasan pilih kursi.
- Detail jadwal dengan denah kursi mobil top-view.
- Mode tamu/non-login hanya bisa melihat data, tidak bisa memilih kursi atau mengisi pemesanan.
- Pemilihan kursi penumpang dengan lock kursi.
- Checkout penumpang berisi data penumpang, titik jemput, bagasi, dan metode pembayaran.
- Titik jemput GPS/map picker dengan reverse geocoding koordinat menjadi alamat.
- Upload foto barang bawaan maksimal 3 foto per data bagasi.
- Hitung biaya bagasi berdasarkan aturan berat/ukuran dan jumlah item.
- Detail pemesanan dan tombol menuju tiket.
- Tiket elektronik QR setelah pembayaran valid.
- Tampilan tiket tidak perlu memperlihatkan token rahasia mentah; informasi pendukung seperti jadwal, pengemudi, dan kendaraan ditampilkan agar penumpang lebih terbantu.
- Manifest pengemudi live dari backend.
- Scan QR tiket satu kali pakai oleh pengemudi.
- Aksi pengemudi untuk pickup penumpang dan update status perjalanan dasar.
- Aksi pengemudi untuk menandai penurunan penumpang setelah tiket discan.
- Pengemudi dapat mengonfirmasi pembayaran tunai pada manifest untuk pemesanan cash yang memang menjadi bagian jadwalnya.
- Scan tiket diarahkan ke detail penumpang (identitas, alamat jemput, bagasi, dan link maps).
- Catatan pengeluaran pengemudi + upload bukti nota tersimpan per perjalanan dan menunggu verifikasi admin sebelum masuk rekap final.
- Tab notifikasi pengemudi dengan badge unread dan tombol scan via FAB tengah.
- Admin web untuk dashboard, kendaraan, pengemudi, rute, jadwal, pemesanan, pembayaran, biaya operasional, laporan, notifikasi, dan pengaturan.
- Admin armada kendaraan sudah mendukung upload foto mobil, thumbnail foto di tabel, preview foto maksimal 500x500, dan dropdown tipe kendaraan agar input lebih rapi.
- Admin jadwal menolak penugasan pengemudi pada tanggal dan jam yang sama agar satu pengemudi tidak mendapat dua trip bersamaan.
- Admin pembayaran dapat mengonfirmasi pembayaran tunai dan menerbitkan tiket digital.
- Validasi form admin dibuat lebih jelas untuk input unik, nomor HP, nomor SIM, dan tipe file gambar.
- Notifikasi database untuk pembayaran berhasil, tugas pengemudi, dan perubahan jadwal.
- Endpoint API mobile polling notifikasi (daftar, unread count, tandai dibaca, tandai semua dibaca).
- Polling service Flutter + flutter_local_notifications untuk menampilkan notifikasi sistem Android tanpa Firebase.
- Pakasir backend untuk create transaksi, checkout URL, refresh status, simulasi lokal, dan webhook reconcile.
- Backup database harian via custom artisan command `travs:backup-database` dengan retention 7 file.
- Panduan demo lokal tanpa hosting (ngrok, simulasi Pakasir, polling notif, backup) di `PANDUAN_DEMO_LOKAL.md`.
- Test backend dan Flutter sudah tersedia untuk alur penting.
- Audit dokumentasi fitur terbaru tersedia di `AUDIT_DOKUMENTASI_FITUR.md`.

## Pembaruan Terakhir - 16 Juni 2026

- Menambahkan kolom foto pada tabel `Armada Kendaraan` di dashboard admin.
- Foto armada ditampilkan sebagai thumbnail ukuran logo dan bisa diklik untuk preview.
- Preview foto dibatasi agar tidak memenuhi layar, maksimal sekitar 500x500.
- Form tambah/edit armada memakai dropdown `Tipe Kendaraan`, bukan input teks bebas.
- Pilihan tipe kendaraan mencakup tipe kendaraan pribadi/travel seperti MPV, SUV, Sedan, Hatchback, City Car, Minibus, Van, Double Cabin, Station Wagon, Travel Executive, Travel Regular, dan Travel Shuttle.
- Validasi backend untuk tipe kendaraan diarahkan ke pilihan dropdown agar data lebih konsisten.
- Upload foto kendaraan tetap divalidasi sebagai gambar JPG, JPEG, PNG, atau WEBP.
- Model jadwal Flutter sekarang membaca `foto_url` kendaraan dari API.
- Kartu jadwal penumpang/tamu memakai foto kendaraan asli dari backend jika tersedia.
- Detail jadwal dan ringkasan kendaraan saat pilih kursi ikut menampilkan foto mobil.
- Dashboard pengemudi tetap memakai `kendaraan_foto_url` yang sudah dikirim backend, sehingga foto armada bisa muncul di kartu informasi perjalanan.

Verifikasi pembaruan terakhir:

- `flutter analyze` lulus tanpa issue.
- `flutter test` lulus, 11 test passed.
- PHP lint untuk controller dan Blade armada lulus tanpa syntax error.

## Audit Dokumentasi - 16 Juni 2026

- Dokumentasi fitur lintas chat/kode sudah diaudit dan dirangkum di `AUDIT_DOKUMENTASI_FITUR.md`.
- `PRD.md` diberi catatan sebagai dokumen rancangan historis agar tidak membingungkan dengan status implementasi terbaru.
- Status fitur yang sudah tersambung, fitur yang masih butuh deployment, dan bahan belajar sidang dipisahkan agar lebih mudah dibaca.

## Sudah Ada, Tapi Perlu Uji Nyata / Deployment

- Transaksi Pakasir nyata dari akun asli sampai callback publik masuk ke server. Dapat diuji lokal via ngrok (lihat `PANDUAN_DEMO_LOKAL.md`).
- HTTPS production. Kode siap diarahkan ke HTTPS melalui konfigurasi server, tetapi ini harus dipasang di hosting/domain.
- Backup database otomatis harian. Command `travs:backup-database` sudah tersedia dan dijadwalkan harian via Laravel Scheduler. Pada server produksi tinggal pasang cron job.
- Pemisahan environment development, staging, dan production. `.env.example` sudah membantu, tetapi server staging/production perlu disiapkan terpisah.

## Belum Dibuat Penuh

- Push notification FCM ke perangkat real. Sebagai pengganti untuk demo lokal, sistem sudah memakai polling + flutter_local_notifications yang menampilkan notifikasi sistem Android saat app aktif. Migrasi ke FCM tinggal menambah service di backend karena endpoint push token sudah tersedia.
- Dashboard admin khusus monitoring exception pembayaran yang lebih mendalam.
- Monitoring admin untuk hasil scan QR dan aktivitas pengemudi secara real-time.
- Halaman/admin audit trail status yang lengkap per pemesanan.
- Laporan final yang benar-benar siap cetak sesuai format operasional Rusly Travel.
- Test end-to-end di perangkat fisik Android dengan backend publik dan payment gateway nyata.

## Catatan Keamanan yang Sudah Diterapkan

- Query utama memakai Eloquent/query builder sehingga input user tidak digabung langsung ke SQL mentah.
- Validasi input dilakukan di backend pada endpoint API dan web admin.
- Password memakai cast `hashed` Laravel, sehingga tersimpan dalam bentuk hash.
- File `.env` tidak boleh dipush dan `.env.example` hanya berisi contoh konfigurasi.
- Error API dibuat konsisten agar detail teknis tidak tampil ke user.
- Operasi penting seperti booking, lock kursi, pembayaran, dan tiket memakai service layer agar logika bisnis tidak tersebar.

## Catatan untuk Sidang

Jika ditanya kenapa masih ada fitur yang belum production-ready, jawab dengan jujur:

> Fitur inti pemesanan, pembayaran, tiket, admin, dan pengemudi sudah dibuat. Notifikasi mobile sudah berjalan menggunakan polling + local notification yang menampilkan notif sistem Android saat app aktif. Bagian yang masih perlu tahap deployment adalah integrasi layanan eksternal seperti Pakasir nyata (dapat diuji via ngrok), HTTPS domain, dan push notification FCM, karena membutuhkan akun/hosting/server publik. Panduan demo lokal tersedia di `PANDUAN_DEMO_LOKAL.md`.
