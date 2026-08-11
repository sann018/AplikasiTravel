# Prompt Prototipe Pemesanan Loket RuslyTravel

## 1. Tujuan Dokumen

Dokumen ini berisi prompt siap salin untuk membuat **contoh tampilan** fitur pemesanan berbantuan petugas/loket dalam dua alternatif:

1. pemesanan loket melalui **web admin**; dan
2. pemesanan loket melalui **aplikasi mobile khusus petugas**.

Setiap alternatif disediakan dalam bentuk prompt untuk:

- prototipe HTML statis yang dapat dibuka di browser; dan
- desain visual berformat PNG.

Prototipe hanya digunakan sebagai bahan diskusi dengan dosen pembimbing. Prototipe **bukan implementasi fitur**, tidak terhubung ke backend, dan tidak menetapkan perubahan database maupun proses bisnis sebagai keputusan final.

## 2. Acuan Visual RuslyTravel

Gunakan acuan berikut pada seluruh prompt:

| Unsur | Acuan |
|---|---|
| Warna utama | Navy `#10233F` |
| Navy sekunder | `#18365F` |
| Warna aksen | Oranye `#F58A2A` |
| Latar halaman | `#F4F7FB` |
| Permukaan card | Putih `#FFFFFF` |
| Border | `#D8E1EC` |
| Teks sekunder | `#5D7090` |
| Berhasil/tersedia | Hijau `#1F9D67` |
| Peringatan | Kuning-oranye `#F8B34B` |
| Gagal/penuh | Merah `#D94F4F` |
| Font | Plus Jakarta Sans atau font sans-serif yang sangat mirip |
| Gaya | Bersih, profesional, ringan, konsisten dengan dasbor dan aplikasi RuslyTravel |

Ketentuan visual umum:

- gunakan Bahasa Indonesia yang jelas;
- card berbentuk persegi panjang dengan sudut membulat secukupnya;
- jangan menggunakan efek kaca, neon, atau bayangan berlebihan;
- tombol utama berwarna navy atau oranye sesuai hierarki;
- tombol sekunder menggunakan outline;
- aksi berbahaya menggunakan merah;
- label form selalu berada di luar input, bukan hanya menjadi placeholder;
- pesan bantuan dan validasi ditampilkan di bawah field agar dapat dibaca penuh;
- angka nominal menggunakan format Rupiah;
- tanggal menggunakan format Indonesia;
- tampilan harus tetap rapi pada zoom browser 100%; dan
- tandai prototipe dengan label kecil **“Konsep untuk Diskusi — Belum Diimplementasikan”**.

## 3. Data Contoh yang Digunakan

Gunakan data fiktif berikut agar seluruh hasil desain konsisten:

- petugas: **Admin RuslyTravel**;
- sumber pemesanan: **Loket**;
- rute: **Pontianak → Sanggau**;
- tanggal: **29 Juli 2026**;
- waktu keberangkatan: **16.00 WIB**;
- kendaraan: **Toyota Avanza — KB 4443 XYZ**;
- harga tiket: **Rp250.000 per kursi**;
- kursi terpilih: **A1**;
- nama penumpang: **Budi Santoso**;
- nomor HP: **081234567890**;
- titik jemput: **Kantor Rusly Travel Pontianak**;
- titik turun: **Terminal Sanggau**;
- metode pembayaran utama contoh: **Tunai di Loket**;
- status pembayaran: **Berhasil dikonfirmasi**;
- kode pemesanan: **TRAVS-20260729-LKT01**.

## 4. Prompt HTML Statis — Opsi Web Admin

Salin prompt berikut ke alat pembuat kode atau prototipe:

```text
Buat satu prototipe HTML statis yang dapat langsung dibuka di browser untuk fitur tambahan “Pemesanan Berbantuan Petugas” pada web admin RuslyTravel.

TUJUAN
Prototipe ini dipakai untuk menjelaskan kepada dosen pembimbing bagaimana petugas loket membantu calon penumpang yang datang langsung dan akan segera berangkat. Ini hanya simulasi antarmuka. Jangan membuat backend, database, autentikasi nyata, API, atau transaksi pembayaran nyata.

OUTPUT
- Buat satu file bernama prototipe_pemesanan_loket_web.html.
- Gabungkan HTML, CSS, dan JavaScript ringan dalam satu file.
- Jangan memakai framework build, npm, backend, atau server.
- Hindari ketergantungan eksternal. Gunakan ikon SVG sederhana atau karakter ikon yang tetap rapi.
- Semua data hanya data contoh di sisi browser.

IDENTITAS VISUAL
- Ikuti tampilan web admin RuslyTravel.
- Warna utama navy #10233F.
- Aksen oranye #F58A2A.
- Background #F4F7FB.
- Card putih, border #D8E1EC, teks sekunder #5D7090.
- Hijau #1F9D67 untuk berhasil/tersedia dan merah #D94F4F untuk gagal/penuh.
- Gunakan Plus Jakarta Sans atau fallback sans-serif.
- Pertahankan sidebar navy, topbar putih, card persegi panjang, dan hierarki tombol yang profesional.
- Tampilkan label kecil “Konsep untuk Diskusi — Belum Diimplementasikan”.

STRUKTUR HALAMAN
1. Sidebar web admin di kiri dengan logo Rusly Travel dan menu:
   - Dasbor
   - Armada Kendaraan
   - Pengemudi
   - Rute Perjalanan
   - Manajemen Jadwal
   - Pemesanan
   - Monitor Tiket
   - Biaya Operasional
   - Laporan Pemesanan
   - Laporan Transaksi
   - Pengaturan
   Menu “Pemesanan” dibuat aktif dan berwarna oranye.

2. Topbar berisi ikon notifikasi, tanggal 29 Jul 2026, avatar A, dan nama Admin RuslyTravel.

3. Area konten memiliki:
   - link “← Kembali ke Daftar Pemesanan”;
   - judul “Tambah Pemesanan Loket”;
   - deskripsi “Bantu calon penumpang membuat pemesanan langsung di loket.”;
   - badge “Sumber: Loket”;
   - badge “Petugas: Admin RuslyTravel”.

4. Buat stepper horizontal lima tahap:
   - 1 Pilih Jadwal
   - 2 Pilih Kursi
   - 3 Data Penumpang
   - 4 Pembayaran
   - 5 Selesai

5. Tahap Pilih Jadwal:
   - field Tanggal Keberangkatan;
   - field Rute;
   - tombol “Cari Jadwal”;
   - card hasil jadwal Pontianak → Sanggau, 29 Juli 2026, 16.00 WIB, Toyota Avanza — KB 4443 XYZ, harga Rp250.000, tersedia 3 dari 6 kursi;
   - tombol “Pilih Jadwal”.

6. Tahap Pilih Kursi:
   - tampilkan denah 6 kursi;
   - tersedia = hijau muda;
   - dipilih = navy dengan outline oranye;
   - dikunci pengguna lain = abu-abu;
   - sudah dipesan = merah muda;
   - nonaktif = abu-abu bergaris;
   - sediakan legenda yang mudah dibaca;
   - tampilkan informasi “Kursi dipilih: A1” dan subtotal Rp250.000.

7. Tahap Data Penumpang:
   - buat pilihan berbentuk tab atau segmented control:
     a. “Cari Akun Penumpang”; dan
     b. “Penumpang Belum Memiliki Akun”.
   - Jelaskan bahwa pilihan tanpa akun masih merupakan rancangan yang perlu persetujuan.
   - Pada “Cari Akun Penumpang”, sediakan pencarian email atau nomor HP.
   - Pada “Penumpang Belum Memiliki Akun”, sediakan Nama Lengkap, Nomor HP, Email opsional, Alamat Jemput, Alamat Turun, Catatan, dan Barang Bawaan opsional.
   - Nama hanya menerima huruf dan spasi.
   - Nomor HP menerima 11–12 digit.
   - Email menerima domain valid seperti gmail.com, yahoo.com, atau polnep.ac.id.
   - Semua keterangan validasi berada di bawah input dan dapat dibaca penuh.
   - Tambahkan tombol outline “Pilih dari Peta” pada alamat jemput dan turun, tetapi tidak perlu membuat peta nyata.

8. Tahap Pembayaran:
   - pilihan “Tunai di Loket”, “QRIS”, dan “Virtual Account”;
   - pilih Tunai di Loket sebagai contoh aktif;
   - tampilkan Harga Tiket Rp250.000, Biaya Admin Provider Rp0, Total Dibayar Rp250.000;
   - tombol utama “Konfirmasi Pembayaran Tunai”;
   - tampilkan dialog konfirmasi sebelum status dibuat berhasil;
   - jangan memanggil payment gateway nyata.

9. Tahap Selesai:
   - banner hijau “Pemesanan Berhasil Dibuat”;
   - kode TRAVS-20260729-LKT01;
   - ringkasan jadwal, penumpang, kursi A1, metode Tunai di Loket, total Rp250.000;
   - pilihan penyerahan tiket berupa “Cetak Tiket”, “Kirim melalui WhatsApp”, dan “Unduh PNG”;
   - tombol “Lihat Detail Pemesanan” dan “Buat Pemesanan Baru”.

10. Tambahkan card audit kecil pada ringkasan:
   - Dibuat oleh: Admin RuslyTravel
   - Kanal pemesanan: Loket
   - Waktu pencatatan: 29 Jul 2026, 15.00 WIB
   Card ini menunjukkan kebutuhan pencatatan sumber transaksi tanpa menetapkan desain database.

INTERAKSI STATIS
- Tombol Berikutnya dan Sebelumnya mengubah tahap yang aktif.
- Kursi dapat dipilih secara visual.
- Segmented control akun dapat diganti.
- Metode pembayaran dapat dipilih.
- Validasi frontend sederhana menampilkan pesan di bawah field.
- Tombol konfirmasi membuka modal, lalu menampilkan tahap berhasil.
- Seluruh tombol harus memiliki focus state dan dapat dipakai dengan keyboard.
- Jangan membuat fetch, AJAX, endpoint, penyimpanan database, atau credential.

RESPONSIVE
- Desain utama pada 1440 × 900 dan zoom 100%.
- Sidebar berubah menjadi tombol menu pada lebar tablet atau mobile.
- Form dua kolom pada desktop dan satu kolom pada layar sempit.
- Tidak boleh ada overflow horizontal, teks terpotong, atau tombol bertabrakan.

HASIL YANG DIINGINKAN
Hasil harus tampak seperti fitur baru yang menyatu dengan web admin RuslyTravel saat ini, tetapi tetap jelas bahwa halaman tersebut hanya prototipe untuk membandingkan opsi pemesanan loket.
```

## 5. Prompt HTML Statis — Opsi Mobile Petugas

Salin prompt berikut ke alat pembuat kode atau prototipe:

```text
Buat satu prototipe HTML statis responsif yang menyerupai aplikasi mobile Android untuk fitur “Pemesanan Loket” pada mode petugas RuslyTravel.

TUJUAN
Prototipe ini menggambarkan alternatif apabila pelayanan loket dilakukan melalui perangkat mobile milik petugas, bukan melalui web admin. Ini hanya simulasi UI untuk diskusi dengan dosen pembimbing. Jangan membuat backend, database, autentikasi, API, payment gateway, atau penerbitan tiket nyata.

OUTPUT
- Buat satu file bernama prototipe_pemesanan_loket_mobile.html.
- Gabungkan HTML, CSS, dan JavaScript ringan dalam satu file.
- Tampilkan frame aplikasi berukuran dasar 390 × 844, tetapi tetap responsif.
- Semua interaksi hanya berlangsung di browser menggunakan data contoh.
- Gunakan ikon SVG sederhana dan jangan bergantung pada aset eksternal.

IDENTITAS VISUAL
- Ikuti aplikasi mobile RuslyTravel.
- Warna navy #10233F, navy sekunder #18365F, oranye #F58A2A.
- Background #F4F7FB, card putih, border #D8E1EC.
- Hijau #1F9D67 untuk berhasil/tersedia, merah #D94F4F untuk gagal/penuh.
- Gunakan Plus Jakarta Sans atau font sans-serif yang sangat mirip.
- Card berbentuk persegi panjang dengan sudut membulat secukupnya.
- Tombol utama besar, mudah dijangkau jempol, dan memiliki tinggi minimal 48 px.
- Tampilkan label “Mode Petugas” dan “Konsep — Belum Diimplementasikan”.

ALUR LAYAR
Buat prototipe beberapa layar di dalam satu file dan izinkan navigasi antarlayar:

1. Beranda Petugas
   - header “RuslyTravel Loket”;
   - nama petugas Admin RuslyTravel;
   - card aksi utama “Buat Pemesanan Loket”;
   - card ringkas “Pemesanan Hari Ini” dan “Keberangkatan Terdekat”;
   - bottom navigation: Beranda, Pemesanan, Tiket, Profil.

2. Pilih Jadwal
   - app bar dengan tombol kembali dan judul “Pilih Jadwal”;
   - filter tanggal dan rute;
   - card Pontianak → Sanggau;
   - 29 Juli 2026 • 16.00 WIB;
   - Toyota Avanza — KB 4443 XYZ;
   - Rp250.000;
   - “3 kursi tersedia”;
   - tombol “Pilih”.

3. Pilih Kursi
   - tampilkan denah 6 kursi dan legenda;
   - tersedia hijau muda;
   - dipilih navy dengan outline oranye;
   - dikunci abu-abu;
   - dipesan merah muda;
   - pilih kursi A1;
   - sticky bottom summary “1 kursi • Rp250.000” dan tombol “Lanjut”.

4. Data Penumpang
   - field Nama Lengkap, Nomor HP, Email opsional;
   - pilihan “Cari akun” atau “Belum memiliki akun”;
   - beri catatan bahwa opsi tanpa akun masih memerlukan persetujuan;
   - Nama hanya huruf dan spasi;
   - Nomor HP 11–12 digit;
   - Email menggunakan format domain valid dan tidak dibatasi Gmail;
   - label dan keterangan validasi berada di luar input;
   - alamat titik jemput dan titik turun;
   - tombol outline “Pilih dari Peta”;
   - barang bawaan opsional.

5. Pembayaran
   - card ringkasan rute, kursi A1, nama Budi Santoso;
   - pilihan Tunai di Loket, QRIS, dan Virtual Account;
   - Tunai di Loket dipilih;
   - rincian Harga Tiket Rp250.000, Biaya Admin Rp0, Total Rp250.000;
   - tombol “Konfirmasi Pembayaran Tunai”;
   - tampilkan bottom sheet konfirmasi.

6. Pemesanan Berhasil
   - ikon centang hijau;
   - judul “Pemesanan Berhasil Dibuat”;
   - kode TRAVS-20260729-LKT01;
   - ringkasan penumpang, jadwal, kursi, dan pembayaran;
   - tombol “Kirim Tiket via WhatsApp”;
   - tombol outline “Unduh Tiket”;
   - link “Kembali ke Beranda Petugas”.

INTERAKSI STATIS
- Navigasi langkah menggunakan tombol Lanjut dan Kembali.
- Kursi dapat dipilih secara visual.
- Pilihan akun dan metode pembayaran dapat berubah.
- Validasi frontend sederhana ditampilkan dekat field.
- Bottom sheet konfirmasi dapat dibuka dan ditutup.
- Tidak boleh ada fetch, AJAX, API, penyimpanan credential, atau transaksi nyata.

KUALITAS TAMPILAN
- Tidak ada teks terpotong.
- Tidak ada keyboard virtual palsu yang menutupi tombol.
- Gunakan safe area, jarak antarkomponen yang konsisten, dan sticky action yang tidak menutup konten.
- Hasil akhir harus terlihat sebagai alternatif mobile petugas, bukan sebagai halaman penumpang biasa.
```

## 6. Prompt Desain PNG — Opsi Web Admin

Salin prompt berikut ke alat pembuat gambar/desain UI:

```text
Buat desain UI/UX high-fidelity berformat PNG untuk halaman web admin RuslyTravel bernama “Tambah Pemesanan Loket”. Ukuran artboard 1440 × 900 piksel, tampilan browser zoom 100%, sudut pandang lurus tanpa perspektif.

Desain harus mengikuti identitas RuslyTravel: sidebar navy #10233F, aksen oranye #F58A2A, background abu terang #F4F7FB, card putih, border #D8E1EC, teks sekunder #5D7090, hijau #1F9D67, merah #D94F4F, font Plus Jakarta Sans. Gaya profesional, bersih, ringan, dan serasi dengan dasbor administrasi transportasi. Jangan gunakan glassmorphism, neon, gradient berlebihan, atau bayangan tebal.

Tampilkan:
- sidebar kiri dengan menu Pemesanan aktif berwarna oranye;
- topbar dengan notifikasi, tanggal 29 Jul 2026, avatar A, dan Admin RuslyTravel;
- link “← Kembali ke Daftar Pemesanan”;
- judul “Tambah Pemesanan Loket”;
- deskripsi “Bantu calon penumpang membuat pemesanan langsung di loket.”;
- badge “Sumber: Loket” dan “Petugas: Admin RuslyTravel”;
- label kecil “Konsep untuk Diskusi — Belum Diimplementasikan”;
- stepper 1 Pilih Jadwal, 2 Pilih Kursi, 3 Data Penumpang, 4 Pembayaran, 5 Selesai;
- area utama dua kolom.

Kolom kiri menampilkan card jadwal Pontianak → Sanggau, 29 Juli 2026, 16.00 WIB, Toyota Avanza — KB 4443 XYZ, harga Rp250.000 dan 3 kursi tersedia. Di bawahnya tampilkan denah 6 kursi dengan A1 terpilih. Gunakan warna hijau muda untuk tersedia, navy dan outline oranye untuk dipilih, abu-abu untuk dikunci, merah muda untuk dipesan.

Kolom kanan menampilkan form Data Penumpang dengan segmented control “Cari Akun Penumpang” dan “Penumpang Belum Memiliki Akun”. Tampilkan field Nama Lengkap: Budi Santoso, Nomor HP: 081234567890, Email opsional, Titik Jemput, Titik Turun, dan Barang Bawaan opsional. Label harus berada di atas input dan teks bantuan di bawah input. Tampilkan catatan kecil bahwa opsi tanpa akun masih memerlukan persetujuan.

Bagian bawah menampilkan card ringkasan: Kursi A1, Harga Tiket Rp250.000, Metode Tunai di Loket, Biaya Admin Rp0, Total Rp250.000, serta tombol outline “Simpan Draft” dan tombol utama navy “Lanjut ke Pembayaran”.

Pastikan semua teks Bahasa Indonesia terbaca dengan benar, tidak menghasilkan teks acak, tidak terpotong, dan tidak salah eja. Hasil harus tampak seperti screenshot nyata dari fitur tambahan yang menyatu dengan web admin RuslyTravel.

NEGATIVE PROMPT:
foto perangkat, tangan manusia, perspektif miring, mockup laptop, dark mode, warna ungu dominan, gradient neon, glassmorphism, teks acak, teks buram, card terlalu bulat, tombol bertabrakan, sidebar terpotong, font terlalu kecil, tabel padat, logo merek lain, data pribadi nyata.
```

## 7. Prompt Desain PNG — Opsi Mobile Petugas

Salin prompt berikut ke alat pembuat gambar/desain UI:

```text
Buat desain UI/UX high-fidelity berformat PNG berupa tiga artboard aplikasi Android RuslyTravel untuk alternatif “Pemesanan Loket melalui Mobile Petugas”. Setiap artboard berukuran 390 × 844 piksel dan disusun berdampingan dengan jarak yang rapi. Tampilan lurus tanpa foto perangkat, tangan, atau perspektif.

Gunakan identitas RuslyTravel: navy #10233F, navy sekunder #18365F, oranye #F58A2A, background #F4F7FB, card putih, border #D8E1EC, teks sekunder #5D7090, hijau #1F9D67, merah #D94F4F, font Plus Jakarta Sans. Card berbentuk persegi panjang dengan sudut membulat secukupnya, tombol utama besar dan mudah dijangkau, tata letak bersih, profesional, serta konsisten dengan aplikasi transportasi RuslyTravel.

ARTBOARD 1 — PILIH JADWAL DAN KURSI
- app bar dengan tombol kembali, judul “Pemesanan Loket”, badge “Mode Petugas”;
- label kecil “Konsep — Belum Diimplementasikan”;
- stepper ringkas “1 Jadwal • 2 Kursi • 3 Data • 4 Bayar” dengan langkah Kursi aktif;
- card Pontianak → Sanggau;
- 29 Juli 2026 • 16.00 WIB;
- Toyota Avanza — KB 4443 XYZ;
- 3 kursi tersedia;
- denah 6 kursi dengan A1 dipilih;
- legenda tersedia, dipilih, dikunci, dipesan;
- sticky footer “1 kursi • Rp250.000” dan tombol oranye “Lanjut”.

ARTBOARD 2 — DATA PENUMPANG DAN PEMBAYARAN
- judul “Data Penumpang”;
- segmented control “Cari Akun” dan “Belum Memiliki Akun”, opsi kedua aktif;
- catatan “Opsi tanpa akun masih menunggu persetujuan.”;
- Nama Lengkap: Budi Santoso;
- Nomor HP: 081234567890;
- Email opsional;
- Titik Jemput: Kantor Rusly Travel Pontianak;
- Titik Turun: Terminal Sanggau;
- label form berada di luar input dan keterangan validasi tampil penuh;
- card metode pembayaran dengan Tunai di Loket aktif, QRIS dan Virtual Account tidak aktif;
- Harga Tiket Rp250.000, Biaya Admin Rp0, Total Rp250.000;
- tombol oranye “Konfirmasi Pembayaran Tunai”.

ARTBOARD 3 — PEMESANAN BERHASIL
- ikon centang hijau;
- judul “Pemesanan Berhasil Dibuat”;
- kode TRAVS-20260729-LKT01;
- card tiket mini berisi Pontianak → Sanggau, 29 Jul 2026, 16.00 WIB, Budi Santoso, Kursi A1;
- metode Tunai di Loket dan total Rp250.000;
- informasi audit kecil “Dicatat oleh Admin RuslyTravel • Kanal Loket”;
- tombol oranye “Kirim Tiket via WhatsApp”;
- tombol outline navy “Unduh Tiket”;
- link “Kembali ke Beranda Petugas”.

Pastikan seluruh teks Bahasa Indonesia terbaca, tidak acak, tidak terpotong, dan tidak salah eja. Hasil harus terlihat sebagai rancangan aplikasi mobile petugas, bukan aplikasi penumpang biasa.

NEGATIVE PROMPT:
foto smartphone, tangan manusia, perspektif 3D, mockup device, dark mode, warna neon, glassmorphism, teks acak, teks buram, font kecil, tombol tertutup, keyboard menutupi layar, card terlalu bulat, logo perusahaan lain, data pribadi nyata.
```

## 8. Perbandingan Prototipe untuk Diskusi

| Aspek | Opsi Web Admin | Opsi Mobile Petugas |
|---|---|---|
| Perangkat | Komputer/laptop loket | HP/tablet milik petugas |
| Kesesuaian dengan sistem saat ini | Tinggi karena operasional admin sudah berbasis web | Memerlukan mode atau peran petugas baru pada aplikasi mobile |
| Kecepatan input data panjang | Lebih nyaman | Lebih ringkas, tetapi input panjang kurang nyaman |
| Dampak terhadap judul berbasis mobile | Perlu dijelaskan sebagai dasbor pendukung web | Lebih dekat dengan fokus berbasis mobile |
| Dampak logika/backend | Tetap memerlukan alur pemesanan berbantuan petugas | Tetap memerlukan alur pemesanan berbantuan petugas |
| Rekomendasi operasional | Lebih cocok untuk loket tetap | Cocok apabila petugas sering berpindah tempat |

## 9. Batasan yang Wajib Dipertahankan dalam Prototipe

Prototipe tidak boleh dianggap menyelesaikan keputusan berikut:

1. apakah penumpang loket wajib mempunyai akun;
2. apakah admin atau petugas diperbolehkan membuat pemesanan atas nama penumpang;
3. apakah pembayaran loket hanya tunai atau juga digital;
4. apakah tiket dicetak, dikirim melalui WhatsApp, atau disimpan pada akun;
5. apakah perlu peran pengguna baru bernama petugas loket;
6. apakah perlu kolom baru untuk sumber pemesanan dan petugas pencatat;
7. apakah judul penelitian perlu menyebut dasbor web; dan
8. apakah fitur ini masuk ruang lingkup penelitian akhir.

Keputusan tersebut tetap harus dikonsultasikan dengan dosen pembimbing sebelum implementasi kode dilakukan.

## 10. Checklist Penilaian Hasil Prototipe

Gunakan checklist berikut setelah HTML atau PNG berhasil dibuat:

- [ ] Tampilan mengikuti warna dan gaya RuslyTravel.
- [ ] Opsi web dan mobile dapat dibedakan dengan jelas.
- [ ] Alur jadwal → kursi → penumpang → pembayaran → tiket terlihat.
- [ ] Sumber pemesanan dan petugas pencatat terlihat pada ringkasan.
- [ ] Label dan validasi form tidak disembunyikan di dalam placeholder.
- [ ] Status kursi dapat dibedakan tanpa hanya mengandalkan warna.
- [ ] Nominal, biaya admin, dan total tidak tercampur.
- [ ] Tidak ada data pribadi nyata.
- [ ] Tidak ada API key, secret, endpoint, atau credential.
- [ ] Prototipe diberi tanda bahwa fitur belum diimplementasikan.
- [ ] Teks Bahasa Indonesia tidak terpotong dan mudah dibaca.
- [ ] Tampilan web rapi pada 1440 × 900 dan zoom 100%.
- [ ] Tampilan mobile rapi pada 390 × 844.
