# Statistik Dasbor dan Laporan Admin RuslyTravel

Dokumen ini menjelaskan statistik yang aktif pada web admin RuslyTravel berdasarkan implementasi
tanggal 27 Juli 2026. Nilai statistik dihitung oleh controller Laravel, sedangkan Blade hanya
memformat dan menampilkan hasil. Pendekatan tersebut menjaga agar perhitungan tidak tersebar pada
lapisan tampilan.

## 1. Prinsip Perhitungan

1. Statistik memakai data transaksi yang sudah tersimpan, bukan angka hardcode.
2. Filter tanggal dan status diterapkan pada query sebelum agregasi.
3. Pendapatan RuslyTravel memakai `nominal_transaksi`, bukan
   `total_dibayar_penumpang`, karena biaya admin provider bukan pendapatan perusahaan.
4. Data historis lama yang belum mempunyai snapshot `nominal_transaksi` memakai
   `pemesanan.total_harga` sebagai fallback kompatibilitas.
5. Jumlah kunjungan rute dihitung saat halaman dibuka. Nilai tersebut tidak disimpan sebagai
   kolom pada tabel `rute`.

## 2. Dasbor: Pendapatan Minggu Ini

### Definisi

Pendapatan minggu ini adalah jumlah nominal transaksi berhasil sejak hari Senin pada minggu
berjalan sampai tanggal hari ini. Pembayaran hanya dihitung apabila pemesanannya berstatus
`terbayar` atau `selesai`.

### Rumus

```text
pendapatan_harian =
Σ nominal_transaksi pembayaran berhasil pada tanggal d

pendapatan_minggu_ini =
Σ pendapatan_harian, dari Senin sampai hari ini
```

Biaya admin Pakasir tidak dimasukkan ke pendapatan:

```text
pendapatan_ruslytravel = nominal_transaksi
total_dibayar_penumpang = nominal_transaksi + biaya_admin_provider
```

### Potongan kode controller

```php
$weekStartDate = $today->copy()->startOfWeek(Carbon::MONDAY);

$pendapatanRaw = Pembayaran::query()
    ->join('pemesanan', 'pemesanan.id', '=', 'pembayaran.pemesanan_id')
    ->selectRaw(
        'DATE(pembayaran.dibayar_pada) as tanggal, '
        .'SUM(COALESCE(pembayaran.nominal_transaksi, pemesanan.total_harga)) as total'
    )
    ->where('pembayaran.status_bayar', 'berhasil')
    ->whereIn('pemesanan.status_pemesanan', ['terbayar', 'selesai'])
    ->whereDate('pembayaran.dibayar_pada', '>=', $weekStartDate)
    ->whereDate('pembayaran.dibayar_pada', '<=', $today)
    ->groupByRaw('DATE(pembayaran.dibayar_pada)')
    ->pluck('total', 'tanggal');

$pendapatanMingguIni = (int) $pendapatanHarian->sum('total');
```

Sumber:
`backend/app/Http/Controllers/Web/Admin/DashboardController.php`

### Penjelasan

- `startOfWeek(Carbon::MONDAY)` menetapkan awal periode pada hari Senin.
- `status_bayar = berhasil` mencegah transaksi pending, gagal, dan kedaluwarsa ikut dihitung.
- status pemesanan `terbayar` atau `selesai` memastikan transaksi batal tidak masuk pendapatan.
- `COALESCE` memprioritaskan snapshot `nominal_transaksi` dan hanya memakai `total_harga` untuk
  data lama yang belum mempunyai snapshot.
- `groupByRaw(DATE(...))` membentuk rekap per hari.

### Potongan kode tampilan

```blade
<small>Pendapatan Minggu Ini</small>
<strong>
    Rp{{ number_format($stats['pendapatan_minggu_ini'], 0, ',', '.') }}
</strong>
<span>Nominal transaksi berhasil, tanpa biaya admin provider.</span>
```

Blade tidak melakukan query atau menentukan transaksi yang sah. Blade hanya mengubah integer
menjadi format Rupiah.

## 3. Dasbor: Rute Paling Banyak Dikunjungi

### Definisi

Rute paling banyak dikunjungi ditentukan dari pemesanan yang perjalanannya telah berstatus
`selesai`. Sistem menampilkan dua ukuran:

- **jumlah pemesanan selesai**, yaitu banyak booking pada rute tersebut;
- **jumlah penumpang**, yaitu total kursi pada seluruh booking selesai.

Satu pemesanan yang memuat tiga kursi dihitung sebagai satu pemesanan dan tiga penumpang.

### Rumus

```text
jumlah_pemesanan_rute = COUNT(pemesanan selesai)
jumlah_penumpang_rute = SUM(jumlah_kursi pemesanan selesai)

rute_terpopuler =
rute dengan jumlah_penumpang_rute tertinggi
```

### Potongan kode controller

```php
$ruteTerpopulerRecord = Pemesanan::query()
    ->join('jadwal', 'jadwal.id', '=', 'pemesanan.jadwal_id')
    ->join('rute', 'rute.id', '=', 'jadwal.rute_id')
    ->where('pemesanan.status_pemesanan', 'selesai')
    ->select(['rute.id', 'rute.kota_asal', 'rute.kota_tujuan'])
    ->selectRaw('COUNT(pemesanan.id) as jumlah_pemesanan')
    ->selectRaw('SUM(pemesanan.jumlah_kursi) as jumlah_penumpang')
    ->groupBy('rute.id', 'rute.kota_asal', 'rute.kota_tujuan')
    ->orderByDesc('jumlah_penumpang')
    ->orderBy('rute.id')
    ->first();
```

Sumber:
`backend/app/Http/Controllers/Web/Admin/DashboardController.php`

### Penjelasan

- relasi query mengikuti `pemesanan → jadwal → rute`;
- pemesanan menunggu pembayaran, terbayar tetapi belum berangkat, dan dibatalkan tidak dianggap
  sebagai kunjungan selesai;
- `COUNT(pemesanan.id)` menunjukkan jumlah transaksi booking;
- `SUM(pemesanan.jumlah_kursi)` menunjukkan jumlah penumpang;
- `orderByDesc('jumlah_penumpang')` memilih rute dengan volume penumpang paling tinggi;
- `orderBy('rute.id')` menjadi penentu konsisten apabila dua rute mempunyai jumlah sama.

### Potongan kode tampilan

```blade
<small>Rute Paling Banyak Dikunjungi</small>
<strong>{{ $ruteTerpopuler['label'] }}</strong>
<span>
    {{ number_format($ruteTerpopuler['jumlah_pemesanan'], 0, ',', '.') }}
    pemesanan selesai
    &middot;
    {{ number_format($ruteTerpopuler['jumlah_penumpang'], 0, ',', '.') }}
    penumpang
</span>
```

### Mengapa tidak menambah kolom pada tabel `rute`?

Jumlah kunjungan adalah data turunan, bukan atribut tetap sebuah rute. Jika angka disimpan pada
tabel `rute`, sistem harus memperbaruinya ketika booking selesai, dibatalkan, dikoreksi, atau
dipulihkan. Kegagalan salah satu pembaruan dapat membuat angka tidak sesuai dengan data transaksi.

Query agregasi menghasilkan nilai langsung dari sumber transaksi sehingga:

- tidak membutuhkan migration;
- tidak mengubah ERD;
- tidak menggandakan data;
- selalu mengikuti status pemesanan terbaru;
- definisinya dapat diaudit melalui query.

## 4. Biaya Operasional: Total Biaya Periode

### Definisi dan rumus

```text
total_biaya_periode =
Σ nominal biaya operasional yang sesuai filter aktif
```

### Potongan kode

```php
$query = $this->filteredQuery($request);
$rekapRecords = (clone $query)
    ->orderByDesc('tanggal_biaya')
    ->get();

'total_biaya' => (int) $rekapRecords->sum('nominal'),
```

Sumber:
`backend/app/Http/Controllers/Web/Admin/BiayaOperasionalController.php`

Filter pencarian, rute, tanggal mulai, dan tanggal selesai sudah diterapkan oleh
`filteredQuery()` sebelum data dijumlahkan. Oleh sebab itu, card mengikuti filter yang sama dengan
tabel.

## 5. Biaya Operasional: Jumlah Transaksi

### Definisi dan rumus

```text
jumlah_transaksi_biaya =
COUNT(catatan biaya yang sesuai filter aktif)
```

### Potongan kode

```php
'jumlah_transaksi' => $rekapRecords->count(),
```

Nilai ini menghitung catatan biaya, bukan jumlah jadwal dan bukan jumlah rute. Apabila satu jadwal
mempunyai tiga catatan biaya, ketiganya dihitung sebagai tiga transaksi biaya.

## 6. Biaya Operasional: Rute dengan Biaya Tertinggi

### Rumus

```text
total_biaya_rute = Σ nominal biaya pada setiap rute
rute_biaya_tertinggi = arg max(total_biaya_rute)
```

### Potongan kode

```php
$rekap = $rekapRecords
    ->groupBy(fn (BiayaOperasionalPerjalanan $item) =>
        optional($item->jadwal?->rute)->kota_asal
        .' - '.
        optional($item->jadwal?->rute)->kota_tujuan
    )
    ->map(function (Collection $items, string $routeLabel) {
        return [
            'route' => $routeLabel !== ' - ' ? $routeLabel : 'Tanpa rute',
            'total' => (float) $items->sum('nominal'),
            'count' => $items->count(),
        ];
    })
    ->values();

$highestCostRoute = $rekap->sortByDesc('total')->first();
```

Data dikelompokkan berdasarkan nama rute, lalu nominal setiap kelompok dijumlahkan. Card
menampilkan kelompok dengan total terbesar dalam periode aktif.

## 7. Laporan Pemesanan

Seluruh card menggunakan salinan query yang sama. Artinya status dan rentang tanggal yang dipilih
admin diterapkan secara konsisten.

### Potongan kode

```php
$query = $this->bookingQuery($request);

$summary = [
    'total_booking' => (clone $query)->count(),
    'terbayar' => (clone $query)
        ->where('status_pemesanan', 'terbayar')
        ->count(),
    'dibatalkan' => (clone $query)
        ->where('status_pemesanan', 'dibatalkan')
        ->count(),
    'selesai' => (clone $query)
        ->where('status_pemesanan', 'selesai')
        ->count(),
    'total_kursi' => (clone $query)->sum('jumlah_kursi'),
    'total_nilai' => (int) (clone $query)->sum('total_harga'),
];
```

Sumber:
`backend/app/Http/Controllers/Web/Admin/LaporanController.php`

### Arti masing-masing card

| Card | Perhitungan | Makna |
|---|---|---|
| Total Pemesanan | `COUNT seluruh query)` | Semua booking yang sesuai filter |
| Terbayar | `COUNT status = terbayar` | Booking dengan pembayaran berhasil yang belum selesai |
| Dibatalkan | `COUNT status = dibatalkan` | Booking yang dibatalkan oleh batas waktu atau proses sah lain |
| Selesai | `COUNT status = selesai` | Booking dengan perjalanan yang telah selesai |
| Total Nilai Pemesanan | `SUM(total_harga)` | Snapshot nilai booking sesuai filter |

`Total Nilai Pemesanan` merupakan nilai historis booking, bukan pendapatan kas terkonfirmasi.
Pendapatan perusahaan tetap dibaca dari pembayaran berhasil pada laporan transaksi.

## 8. Laporan Transaksi

### Query transaksi berhasil

```php
$successfulTransactions = (clone $query)
    ->where('status_bayar', 'berhasil')
    ->whereHas(
        'pemesanan',
        fn ($booking) =>
            $booking->whereIn('status_pemesanan', ['terbayar', 'selesai'])
    );

$successfulPayments = (clone $successfulTransactions)->get();
```

Transaksi hanya dianggap sah untuk pendapatan apabila pembayaran berhasil dan booking masih berada
pada status bisnis yang sah.

### Pendapatan Tahun Ini

```php
'pendapatan_tahun_ini' => (int) (clone $periodRevenueQuery)
    ->whereYear('pembayaran.dibayar_pada', $today->year)
    ->sum(DB::raw(
        'COALESCE(pembayaran.nominal_transaksi, pemesanan.total_harga)'
    )),
```

Rumus:

```text
pendapatan_tahun_ini =
Σ nominal transaksi sah pada tahun kalender berjalan
```

### Pendapatan Bulan Ini

```php
'pendapatan_bulan_ini' => (int) (clone $periodRevenueQuery)
    ->whereYear('pembayaran.dibayar_pada', $today->year)
    ->whereMonth('pembayaran.dibayar_pada', $today->month)
    ->sum(DB::raw(
        'COALESCE(pembayaran.nominal_transaksi, pemesanan.total_harga)'
    )),
```

Rumus:

```text
pendapatan_bulan_ini =
Σ nominal transaksi sah pada bulan dan tahun berjalan
```

### Transaksi Berhasil

```php
'berhasil' => $successfulPayments->count(),
```

Nilai mengikuti seluruh filter aktif, termasuk status, metode pembayaran, dan rentang tanggal.

### Pendapatan Filter Aktif

```php
'nilai_berhasil' => (int) $successfulPayments->sum(
    fn ($payment) =>
        $payment->nominal_transaksi
        ?? (int) $payment->pemesanan?->total_harga
),
```

Card ini menjumlahkan pendapatan dari transaksi berhasil yang berada di dalam filter laporan.
`biaya_admin_provider` dan `total_dibayar_penumpang` tidak dimasukkan.

## 9. Alur Data Statistik

```text
Database
   ↓
Query dasar + filter
   ↓
Agregasi COUNT / SUM / GROUP BY
   ↓
Array summary pada controller
   ↓
Blade memformat angka
   ↓
Card statistik web admin
```

Pemisahan tersebut membuat tanggung jawab setiap lapisan jelas:

- database menyimpan fakta transaksi;
- controller menentukan aturan agregasi;
- Blade hanya menyajikan hasil;
- CSS mengatur tampilan tanpa mengubah nilai.

## 10. Jawaban Singkat untuk Sidang

**Mengapa jumlah kunjungan tidak menjadi kolom tabel rute?**

> Jumlah kunjungan merupakan hasil agregasi transaksi yang dapat berubah mengikuti status
> pemesanan. Sistem menghitungnya menggunakan `COUNT` dan `SUM` dari pemesanan selesai sehingga
> tidak menggandakan data dan tidak membutuhkan sinkronisasi kolom rekap.

**Mengapa rute populer memakai perjalanan selesai?**

> Status selesai membuktikan bahwa perjalanan benar-benar dilaksanakan. Booking pending,
> dibatalkan, atau baru terbayar belum dapat dinyatakan sebagai kunjungan yang selesai.

**Mengapa pendapatan tidak memakai total dibayar penumpang?**

> Total dibayar penumpang dapat memuat biaya admin provider. Pendapatan RuslyTravel memakai
> snapshot `nominal_transaksi`, sedangkan biaya provider disajikan terpisah.

**Apakah Blade menghitung statistik?**

> Tidak. Controller menghitung statistik dari query database. Blade hanya memformat angka dan
> menampilkannya pada card.
