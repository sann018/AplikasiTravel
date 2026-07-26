# 06 — Panduan Ubah Tampilan & Perbaiki Bug (WAJIB untuk Sidang) ⭐⭐⭐

> Ini bab paling penting. Saat sidang kamu akan diminta membuka kode dan **mengubah tampilan**
> (warna, ukuran, bentuk, posisi) atau **memperbaiki bug**. Bab ini melatih itu langkah demi langkah,
> lengkap dengan lokasi persis dan contoh perubahan.

---

## 0. Aturan Emas: Tentukan Dulu "Ini Admin atau Mobile?"

Setiap kali diminta ubah tampilan, tanya diri sendiri dulu:

| Yang diminta di layar... | Berarti... | Buka file di... |
|--------------------------|-----------|-----------------|
| Halaman **web admin** (buka di browser/laptop) | Blade + CSS | `backend/resources/views/` & `backend/resources/css/app.css` |
| Halaman **aplikasi HP** (buka di emulator/HP) | Flutter/Dart | `frontend/lib/` |

Salah dunia = salah file. Ini kesalahan paling umum. Pastikan dulu.

---

# BAGIAN A — MENGUBAH TAMPILAN WEB ADMIN

Tampilan admin dibangun dari dua jenis file:
- **File `.blade.php`** (`resources/views/admin/`) → mengatur **struktur/isi** halaman (teks, tabel,
  tombol, urutan).
- **File `app.css`** (`resources/css/app.css`) → mengatur **gaya**: warna, ukuran font, jarak,
  bentuk sudut, bayangan.

> **PENTING:** setelah mengubah `app.css`, kamu harus membangun ulang CSS agar perubahan terlihat:
> ```powershell
> cd backend
> npm run build      # atau: npm run dev (agar otomatis update saat diedit)
> ```
> Kalau `npm run dev` sedang jalan, cukup refresh browser.

## A.1 Mengubah Warna Admin

Semua warna admin didefinisikan di bagian paling atas `app.css` sebagai **variabel CSS**
(baris ~3-33). Ini "palet warna" utama. Ubah di sini = berubah di seluruh halaman admin.

```css
:root {
    --travs-white: #ffffff;
    --travs-surface: #f5f7fb;         /* warna latar halaman */
    --travs-primary: #10233f;         /* warna utama (biru tua/navy) */
    --travs-primary-soft: #18365f;
    --travs-accent: #f58a2a;          /* warna aksen (oranye) - tombol utama */
    --travs-success: #1f9d67;         /* hijau */
    --travs-danger: #b6473f;          /* merah */
    --travs-text: #1b2b41;            /* warna teks utama */
    --travs-border: #d7dee8;          /* warna garis/border */
}
```

**Contoh tugas sidang: "Ubah warna tombol utama dari oranye jadi hijau."**
1. Buka `backend/resources/css/app.css`.
2. Cari baris `--travs-accent: #f58a2a;`.
3. Ganti jadi `--travs-accent: #1f9d67;` (atau kode hijau yang kamu mau).
4. Jalankan `npm run build`, refresh browser. Semua tombol utama (`.travs-button`) berubah hijau.

> **Kenapa lewat variabel?** Karena tombol utama memakai `background: var(--travs-accent);`
> (lihat baris ~302-306). Jadi cukup ubah satu tempat, semua ikut.

**Kalau mau ubah warna SATU elemen saja** (bukan semua), ubah aturan CSS spesifiknya. Contoh warna
header dashboard di baris ~837:
```css
.travs-dashboard-hero {
    /* ... */
    background: #25396f;    /* ubah kode warna ini untuk header dashboard saja */
}
```

**Cara tahu kode warna (hex):** format `#RRGGBB`. Bisa cari di Google "color picker". Contoh:
merah `#FF0000`, hijau `#1F9D67`, biru `#10233F`.

## A.2 Mengubah Ukuran Font Admin

Ukuran font juga ada di `:root` sebagai variabel (baris ~23-32):
```css
--travs-font-body: 16px;            /* teks isi */
--travs-font-card-title: 20px;      /* judul kartu */
--travs-font-page-title: 28px;      /* judul halaman */
--travs-font-stat: 40px;            /* angka statistik besar */
```

**Contoh: "Perbesar judul halaman."** Ubah `--travs-font-page-title: 28px;` jadi `32px`.

Atau ubah langsung di aturan elemen. Contoh judul di topbar (baris ~630):
```css
.travs-topbar-title h3 {
    font-size: 1.15rem;    /* ubah angka ini; 1rem = 16px */
}
```
> `px` = ukuran tetap. `rem` = kelipatan ukuran dasar (1rem = 16px). `1.15rem` ≈ 18px.

## A.3 Mengubah Bentuk (Sudut Membulat & Bayangan)

**Sudut membulat** diatur oleh `border-radius`. Makin besar angka, makin bulat.
```css
.travs-card {
    border-radius: 22px;   /* kartu. Kecilkan jadi 8px untuk sudut lebih tajam */
}
.travs-button {
    border-radius: 16px;   /* tombol */
}
```

**Bayangan** diatur oleh `box-shadow`:
```css
--travs-shadow: 0 18px 40px rgba(16, 35, 63, 0.08);
/* format: geser-x geser-y blur warna. Angka terakhir (0.08) = kepekatan */
```

**Contoh: "Buat tombol lebih kotak."** Cari `.travs-button` (baris ~287), ubah `border-radius: 16px`
jadi `border-radius: 4px`.

## A.4 Mengubah Posisi & Tata Letak Admin

Tata letak besar admin memakai **CSS Grid**. Contoh kerangka utama (baris ~440):
```css
.travs-admin-shell {
    display: grid;
    grid-template-columns: 250px minmax(0, 1fr);   /* sidebar 250px | konten sisanya */
}
```
**Contoh: "Perlebar sidebar."** Ubah `250px` jadi `300px`.

Untuk **jarak antar elemen** pakai `gap`, `padding` (jarak dalam), `margin` (jarak luar):
```css
.travs-content {
    gap: 16px;       /* jarak antar item */
    padding: 16px;   /* jarak dari tepi */
}
```

**Memindahkan posisi elemen kiri/kanan** sering pakai `justify-content` (untuk flexbox):
```css
.travs-topbar {
    display: flex;
    justify-content: space-between;   /* kiri & kanan berjauhan */
    /* pilihan lain: flex-start (kiri), center (tengah), flex-end (kanan) */
}
```

## A.5 Mengubah Isi/Teks & Struktur Halaman Admin (Blade)

Kalau diminta **ubah teks judul, tambah kolom tabel, ubah label tombol** → itu di file `.blade.php`.

Contoh nyata dari `resources/views/admin/jadwal/index.blade.php`:
```blade
@section('page-title', 'Jadwal Perjalanan')
@section('page-description', 'Susun jadwal keberangkatan, ...')

<div class="travs-panel-title">
    <h3>Daftar Jadwal</h3>
    <p>Monitoring jadwal mencakup armada, pengemudi, kursi tersedia, ...</p>
</div>
<a href="{{ route('admin.jadwal.create') }}" class="travs-button">Tambah Jadwal</a>
```

**Contoh: "Ganti tulisan tombol 'Tambah Jadwal' jadi 'Buat Jadwal Baru'."**
Cari teks `Tambah Jadwal` di file itu, ganti jadi `Buat Jadwal Baru`. Simpan, refresh browser
(tidak perlu build ulang, karena Blade bukan CSS).

**Contoh: "Tambah kolom baru di tabel."** Di file yang sama, lihat bagian `<thead>` (header tabel)
dan `<tbody>` (isi). Tambah `<th>Judul Kolom</th>` di header dan `<td>{{ $item->kolom }}</td>` di isi.

**Cara membaca sintaks Blade:**
- `{{ $variabel }}` — menampilkan isi variabel.
- `@foreach (...) ... @endforeach` — perulangan (misalnya menampilkan tiap baris data).
- `@if (...) ... @endif` — kondisi.
- `{{ route('nama.route') }}` — membuat URL dari nama route.
- `class="travs-button"` — memakai gaya dari `app.css`.

## A.6 Mengganti Ikon Menu Admin

Menu sidebar memakai ikon dari **Feather Icons**. Lihat `resources/views/admin/layouts/app.blade.php`
(baris ~15-40):
```php
['label' => 'Jadwal Perjalanan', 'route' => 'admin.jadwal.index', 'icon' => 'calendar'],
```
**Contoh: "Ganti ikon menu Jadwal."** Ubah `'icon' => 'calendar'` jadi nama ikon Feather lain
(misalnya `'clock'`). Daftar nama ikon ada di situs feathericons.com.

## A.7 Membedakan Kelas Bootstrap 5 vs Kelas Kustom `travs-` (PENTING) ⭐

Ini pertanyaan yang **sangat mungkin ditanya penguji**: "Tampilan admin ini pakai Bootstrap atau
bikin sendiri?" Jawaban jujurnya: **campuran keduanya (hybrid)**. Kamu harus paham bedanya supaya
tahu file mana yang diubah.

### Fakta dari kode
- Bootstrap **versi 5.3.3** memang terpasang. Bukti:
  - `backend/package.json` → ada `"bootstrap": "^5.3.3"`
  - `backend/resources/css/app.css` baris 1 → `@import 'bootstrap/dist/css/bootstrap.min.css';`
  - `backend/resources/js/app.js` baris 1 → `import * as bootstrap from 'bootstrap';`
- Semua di-gabung (bundling) memakai **Vite** (lihat `@vite([...])` di `layouts/app.blade.php`).
- Ikon memakai **Feather Icons** (bukan ikon Bootstrap).

### Yang MEMANG memakai Bootstrap 5
Bootstrap dipakai terutama untuk **kerangka form dan tata letak di dalam halaman**:

| Fungsi | Kelas Bootstrap | Contoh lokasi |
|--------|-----------------|---------------|
| Grid (baris & kolom) | `row`, `g-3`, `col-md-6`, `col-12 col-lg-6`, `col-xl-4` | form kendaraan, pengaturan, banner |
| Input form | `form-control`, `form-select`, `is-invalid` | hampir semua form |
| Grup input (satuan) | `input-group`, `input-group-text` | pengaturan (kursi, menit, Rp) |
| Tombol | `btn`, `btn-primary`, `btn-outline-danger`, `btn-sm`, `btn-lg` | login, banner |
| Toggle/switch | `form-check`, `form-switch`, `form-check-input` | pengaturan |
| Utility (jarak/susun) | `d-flex`, `gap-3`, `flex-column`, `w-100`, `mb-2`, `rounded-3` | banyak tempat |

### Yang TIDAK memakai Bootstrap (kelas kustom `travs-`)
Bagian tampilan yang jadi "ciri khas" aplikasi ini **dibuat sendiri** di `app.css`, bukan komponen
bawaan Bootstrap:

| Bagian | Kelas kustom | Bukan pakai... |
|--------|--------------|----------------|
| Sidebar & menu | `travs-sidebar`, `travs-nav-link` | (bukan `navbar` Bootstrap) |
| Topbar/header atas | `travs-topbar`, `travs-sticky-header` | (bukan `navbar` Bootstrap) |
| Kartu/panel | `travs-card`, `travs-page-frame`, `travs-panel` | (bukan `.card` Bootstrap) |
| Tabel data | `travs-table` | (bukan `.table` Bootstrap) |
| Tombol khas | `travs-button`, `travs-button-secondary` | (bukan `.btn` Bootstrap) |
| Label status | `travs-badge`, `travs-badge-success/warning/danger` | (bukan `.badge` Bootstrap) |
| Notifikasi/alert | `travs-alert`, `travs-alert-success` | (bukan `.alert` Bootstrap) |
| Dashboard | `travs-dashboard-hero`, `travs-overview-card` | dibuat sendiri |

### Aturan praktis: dari mana nama kelasnya, ke situ ubahnya
- Kelas **berawalan `travs-`** → didefinisikan di `backend/resources/css/app.css`. Kalau mau ubah
  gayanya, cari kelas itu di `app.css`.
- Kelas **Bootstrap** (`row`, `col-*`, `btn`, `form-control`, `d-flex`, dll) → gayanya dari
  Bootstrap. Untuk mengubahnya biasanya cukup **ganti kelasnya di file `.blade.php`** (misalnya
  `col-md-6` jadi `col-md-4` untuk mengubah lebar kolom), bukan mengubah file Bootstrap.

**Contoh: "Ubah form jadi 3 kolom sejajar."** Di file Blade, cari `col-md-6` (2 kolom), ganti jadi
`col-md-4` (3 kolom). Ini murni mengubah kelas grid Bootstrap di Blade, tanpa menyentuh `app.css`.

**Contoh: "Kolom di HP jangan menumpuk aneh."** Perhatikan pola `col-12 col-lg-6` artinya: di layar
kecil (HP) 1 kolom penuh (`col-12`), di layar besar 2 kolom (`col-lg-6`). Ini konsep **responsive
grid** Bootstrap.

### Cara menjawab saat sidang (contoh kalimat)
> "Tampilan web admin memakai **Bootstrap 5.3.3** sebagai kerangka dasar, khususnya untuk **sistem
> grid, komponen form, tombol, dan utility class**. Namun bagian tampilan khas seperti **sidebar,
> topbar, tabel, kartu, dan dashboard saya buat sendiri** dengan CSS kustom berawalan `travs-` di
> file `app.css`. Keduanya digabung memakai Vite. Ikon memakai Feather Icons."

> **Tips:** cara cepat membuktikan ke penguji — buka satu file Blade, tunjuk `class="row g-3"` (itu
> Bootstrap) lalu tunjuk `class="travs-card"` (itu kustom). Dua-duanya ada dalam satu halaman.

---

# BAGIAN B — MENGUBAH TAMPILAN APLIKASI MOBILE (FLUTTER)

Di Flutter, **tampilan dan kode jadi satu** di file `.dart`. Tidak ada file CSS terpisah. Warna
global ada di `travs_theme.dart`, sedangkan tata letak tiap halaman ada di file halamannya.

> **PENTING:** setelah mengubah kode Dart, kalau aplikasi sedang jalan (`flutter run`), tekan tombol
> **`r`** di terminal untuk **Hot Reload** (perubahan langsung terlihat tanpa restart). Kalau ubah
> warna tema kadang perlu **`R`** (Hot Restart).

## B.1 Mengubah Warna Aplikasi Mobile

Buka `frontend/lib/core/theme/travs_theme.dart`. Semua warna ada di atas (baris ~5-18):
```dart
class TravSTheme {
  static const Color navy = Color(0xFF10233F);     // warna utama
  static const Color orange = Color(0xFFF58A2A);   // warna aksen/tombol
  static const Color success = Color(0xFF1F9D67);  // hijau
  static const Color danger  = Color(0xFFD94F4F);  // merah
  static const Color background = Color(0xFFF4F7FB);
}
```

**Format warna Dart:** `Color(0xFFRRGGBB)`. `0xFF` artinya buram penuh, lalu 6 digit kode hex.
Contoh oranye `0xFFF58A2A`.

**Contoh: "Ubah warna tombol utama aplikasi jadi biru."**
1. Buka `travs_theme.dart`.
2. Cari `static const Color orange = Color(0xFFF58A2A);`.
3. Ganti jadi `Color(0xFF1565C0);` (biru).
4. Hot restart (`R`). Semua tombol yang pakai `orange` berubah biru.

> Kenapa berubah semua? Karena tombol utama didefinisikan di `elevatedButtonTheme` (baris ~168)
> pakai `backgroundColor: orange`.

## B.2 Mengubah Ukuran Font Mobile

Ukuran font default ada di `travs_theme.dart` bagian `textTheme` (baris ~44-124):
```dart
titleLarge: GoogleFonts.plusJakartaSans(
  fontSize: 20,                    // ubah angka ini
  fontWeight: FontWeight.w700,     // ketebalan: w400 normal, w700 tebal, w800 sangat tebal
),
bodyMedium: GoogleFonts.plusJakartaSans(
  fontSize: 15,
),
```

**Contoh ubah ukuran di satu widget saja** (di dalam file halaman):
```dart
Text(
  'Halo',
  style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),  // ukuran & tebal khusus
)
```

## B.3 Mengubah Ukuran, Bentuk, Posisi Elemen Mobile

Di Flutter, ukuran/jarak/posisi diatur lewat **widget pembungkus**. Yang paling sering:

**Jarak/padding** — `Padding` atau properti `padding`:
```dart
Padding(
  padding: const EdgeInsets.all(16),   // jarak 16 di semua sisi
  child: Text('Isi'),
)
// EdgeInsets.symmetric(horizontal: 20, vertical: 12) → kiri-kanan 20, atas-bawah 12
// EdgeInsets.only(left: 8, top: 4) → sisi tertentu saja
```

**Jarak antar elemen** — `SizedBox`:
```dart
Column(children: [
  Text('Atas'),
  const SizedBox(height: 16),   // spasi 16 ke bawah
  Text('Bawah'),
])
```

**Ukuran kotak** — `SizedBox` atau `Container`:
```dart
SizedBox(width: 200, height: 50, child: ...)
```

**Sudut membulat & warna kotak** — `Container` dengan `decoration`:
```dart
Container(
  decoration: BoxDecoration(
    color: TravSTheme.navy,
    borderRadius: BorderRadius.circular(16),   // sudut membulat
  ),
  child: ...,
)
```

**Posisi (rata kiri/tengah/kanan)** — di dalam `Row`/`Column` pakai `mainAxisAlignment` &
`crossAxisAlignment`:
```dart
Row(
  mainAxisAlignment: MainAxisAlignment.spaceBetween,  // kiri & kanan berjauhan
  children: [ ... ],
)
// pilihan: start (kiri), center (tengah), end (kanan), spaceBetween, spaceAround
```

**Contoh tugas sidang: "Perbesar jarak antar kartu di beranda."**
Cari `SizedBox(height: ...)` di antara kartu-kartu pada file halaman, perbesar angkanya.

## B.4 Mengubah Bentuk Tombol Mobile

Bentuk tombol global ada di `travs_theme.dart` (baris ~168-181):
```dart
elevatedButtonTheme: ElevatedButtonThemeData(
  style: ElevatedButton.styleFrom(
    backgroundColor: orange,
    foregroundColor: Colors.white,             // warna teks tombol
    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),  // ukuran tombol
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(18),  // kebulatan sudut tombol
    ),
  ),
),
```
**Contoh: "Buat tombol lebih bulat/kapsul."** Ubah `BorderRadius.circular(18)` jadi `circular(30)`.

## B.5 Mengubah Teks di Aplikasi Mobile

Cari teksnya langsung di file halaman pakai Ctrl+F. Contoh dari halaman tiket:
```dart
Text(
  booking.isPaid
      ? 'Tiket belum diterbitkan untuk pemesanan ini.'
      : 'QR tiket belum aktif karena pembayaran belum terverifikasi.',
)
```
Ganti teks di dalam tanda kutip, lalu hot reload (`r`).

---

# BAGIAN C — MEMPERBAIKI BUG (Cara Berpikir & Contoh)

Saat sidang, "perbaiki bug" biasanya berupa masalah kecil. Yang penting: **cara berpikir yang
tenang dan runtut**. Ikuti langkah ini:

## C.1 Langkah Umum Debug

1. **Pahami gejalanya.** Apa yang salah? (contoh: tombol tidak jalan, data tidak muncul, warna
   salah, error muncul).
2. **Tentukan dunia.** Admin (Laravel) atau mobile (Flutter)?
3. **Cari titiknya.** Pakai Ctrl+Shift+F (cari di semua file) untuk mencari teks/label yang terkait.
4. **Baca kode di sekitar titik itu.** Pahami alurnya.
5. **Ubah, lalu uji.** Ubah kecil, jalankan, lihat hasilnya.
6. **Jangan panik kalau salah.** Kembalikan (Ctrl+Z) dan coba cara lain.

## C.2 Contoh Bug & Solusinya

**Contoh 1 — "Teks salah/typo di halaman admin."**
- Cari teksnya di `resources/views/admin/...`, perbaiki, refresh. Selesai.

**Contoh 2 — "Tombol di aplikasi tidak melakukan apa-apa."**
- Cari widget tombolnya di file halaman. Lihat properti `onPressed:`. Kalau `onPressed: null`,
  tombol memang dimatikan. Isi dengan fungsi yang seharusnya dipanggil.
```dart
ElevatedButton(
  onPressed: null,          // <- ini bikin tombol mati
  onPressed: _submitForm,   // <- perbaikannya: panggil fungsi
  child: Text('Kirim'),
)
```

**Contoh 3 — "Data tidak muncul di halaman admin."**
- Cek Controller: apakah data dikirim ke view? Contoh:
```php
return view('admin.rute.index', [
    'rute' => Rute::paginate(10),   // pastikan variabel dikirim
]);
```
- Cek view: apakah variabelnya dipakai dengan nama yang sama (`$rute`)?

**Contoh 4 — "Validasi form kurang ketat."**
- Cek `$request->validate([...])` di Controller. Tambah aturan. Contoh menambah wajib isi:
```php
'kota_asal' => ['required', 'string', 'max:255'],  // required = wajib
```

**Contoh 5 — "Warna status tidak sesuai."**
- Di admin, status biasanya pakai class badge. Cari `travs-badge-success/warning/danger` di view,
  sesuaikan kondisinya.

## C.3 Bug yang Sudah Pernah Diperbaiki di Project Ini (Bahan Cerita Sidang)

Project ini sudah melewati proses debugging nyata. Kamu bisa menceritakan ini sebagai bukti
pemahaman:

1. **Kolom `biaya_bagasi` & `diskon` dihapus** — fitur tidak dipakai, total pemesanan disederhanakan
   jadi sama dengan subtotal tiket. (File: migration drop, `BookingCalculator.php`, model `Pemesanan`.)
2. **Pembayaran susulan pada pemesanan batal** — ditambah pengaman agar pembayaran yang telat masuk
   tidak membuat status "terbayar" tanpa tiket; admin diberi tahu untuk refund. (`BookingService.php`.)
3. **Pengemudi bisa tandai jemput setelah scan** — sebelumnya urutan kaku, diperbaiki jadi fleksibel.
   (`DriverController.php`.)
4. **Kursi tunai tidak menggantung** — kursi tunai yang tak dikonfirmasi otomatis dilepas saat
   perjalanan selesai. (`DriverController.php`.)
5. **Deadline booking/pembayaran dipisahkan** — lock form dan batas pembayaran diperiksa backend
   agar draft kedaluwarsa tidak tetap menunggu pembayaran. (`BookingService.php`.)
6. **Nominal laporan diperjelas** — nilai tiket, biaya provider, dan total bayar penumpang disimpan
   terpisah sebagai snapshot. (`Pembayaran`, `BookingService.php`, `LaporanController.php`.)
7. **Detail jadwal ditambahkan** — admin mendapat ringkasan jadwal, penumpang, pendapatan, dan biaya
   tanpa menggantikan halaman detail pemesanan atau biaya operasional. (`JadwalController.php` dan
   `admin/jadwal/show.blade.php`.)
8. **Notifikasi keberangkatan diperluas** — tersedia pengingat sebelum berangkat serta notifikasi
   ketika pengemudi benar-benar memulai perjalanan. (`DepartureReminderService.php`,
   `AdminOperationalNotificationService.php`.)

> **Poin sidang:** kalau ditanya "pernah menemukan bug?", ceritakan salah satu di atas dengan
> bahasa sederhana: apa masalahnya, di file apa, dan bagaimana solusinya.

---

## C.4 Perintah Penting Saat Uji Coba

**Admin (Laravel):**
```powershell
cd backend
npm run dev            # auto-build CSS/JS saat diedit
php artisan serve      # jalankan server
php artisan test       # jalankan semua test (harusnya semua hijau)
```

**Mobile (Flutter):**
```powershell
cd frontend
flutter run            # jalankan aplikasi
# saat berjalan: tekan r (hot reload), R (hot restart), q (keluar)
flutter analyze        # cek error kode tanpa menjalankan
```

---

## Ringkasan Cepat "Kalau Diminta X, Buka Y"

| Permintaan | File | Cara |
|-----------|------|------|
| Ubah warna admin | `backend/resources/css/app.css` (`:root`) | ubah `--travs-*`, lalu `npm run build` |
| Ubah warna mobile | `frontend/lib/core/theme/travs_theme.dart` | ubah `Color(0xFF...)`, hot restart |
| Ubah teks admin | `backend/resources/views/admin/...blade.php` | cari & ganti teks, refresh |
| Ubah teks mobile | `frontend/lib/features/.../*.dart` | cari & ganti teks, hot reload |
| Ubah ukuran font admin | `app.css` (`--travs-font-*` atau elemen) | ubah `px`/`rem` |
| Ubah ukuran font mobile | `travs_theme.dart` (`fontSize`) atau di widget | ubah angka `fontSize` |
| Ubah posisi/jarak admin | `app.css` (`gap`, `padding`, `margin`, grid) | sesuaikan nilai |
| Ubah posisi/jarak mobile | file halaman (`Padding`, `SizedBox`, `mainAxisAlignment`) | sesuaikan widget |
| Ubah bentuk sudut admin | `app.css` (`border-radius`) | ubah `px` |
| Ubah bentuk sudut mobile | `travs_theme.dart` / widget (`BorderRadius.circular`) | ubah angka |

Lanjut ke `07_MATERI_BAB4_BAB5.md` untuk bahan laporan.
