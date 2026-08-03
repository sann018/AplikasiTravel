# Modul 09 — Memahami Statistik Dasbor dan Laporan

Modul ini membantu menelusuri asal angka pada card web admin. Prinsip utamanya adalah:

```text
query di controller menghitung nilai
Blade hanya menampilkan nilai
CSS hanya mengatur tampilan
```

## 1. Peta File

| Bagian | Controller | Blade |
|---|---|---|
| Dasbor | `backend/app/Http/Controllers/Web/Admin/DashboardController.php` | `backend/resources/views/admin/dashboard/index.blade.php` |
| Biaya Operasional | `backend/app/Http/Controllers/Web/Admin/BiayaOperasionalController.php` | `backend/resources/views/admin/biaya/index.blade.php` |
| Laporan Pemesanan | `backend/app/Http/Controllers/Web/Admin/LaporanController.php` | `backend/resources/views/admin/laporan/pemesanan.blade.php` |
| Laporan Transaksi | `backend/app/Http/Controllers/Web/Admin/LaporanController.php` | `backend/resources/views/admin/laporan/transaksi.blade.php` |

## 2. Pendapatan Minggu Ini

```php
$pendapatanRaw = Pembayaran::query()
    ->join('pemesanan', 'pemesanan.id', '=', 'pembayaran.pemesanan_id')
    ->where('pembayaran.status_bayar', 'berhasil')
    ->whereIn('pemesanan.status_pemesanan', ['terbayar', 'selesai'])
    ->whereDate('pembayaran.dibayar_pada', '>=', $weekStartDate)
    ->whereDate('pembayaran.dibayar_pada', '<=', $today)
    ->groupByRaw('DATE(pembayaran.dibayar_pada)')
    ->pluck('total', 'tanggal');
```

Cara membacanya:

1. ambil pembayaran;
2. hubungkan dengan pemesanan;
3. pilih pembayaran berhasil;
4. abaikan booking yang tidak sah;
5. batasi dari Senin sampai hari ini;
6. kelompokkan per tanggal;
7. jumlahkan untuk memperoleh pendapatan minggu ini.

Fee Pakasir tidak dihitung karena query menjumlahkan `nominal_transaksi`.

## 3. Rute Paling Banyak Dikunjungi

```php
->where('pemesanan.status_pemesanan', 'selesai')
->selectRaw('COUNT(pemesanan.id) as jumlah_pemesanan')
->selectRaw('SUM(pemesanan.jumlah_kursi) as jumlah_penumpang')
->groupBy('rute.id', 'rute.kota_asal', 'rute.kota_tujuan')
->orderByDesc('jumlah_penumpang')
```

Perbedaan dua angka:

- `jumlah_pemesanan`: banyak transaksi booking selesai;
- `jumlah_penumpang`: banyak kursi/penumpang dari transaksi tersebut.

Contoh:

```text
Booking A = 2 kursi
Booking B = 3 kursi

Jumlah pemesanan = 2
Jumlah penumpang = 5
```

Angka ini tidak disimpan di tabel `rute`. Sistem menghitungnya ketika dasbor dibuka agar selalu
sesuai dengan data pemesanan.

## 4. Statistik Biaya Operasional

```php
$rekapRecords = (clone $query)->get();

'total_biaya' => (int) $rekapRecords->sum('nominal'),
'jumlah_transaksi' => $rekapRecords->count(),
```

- Total Biaya Periode: jumlah seluruh nominal sesuai filter.
- Jumlah Transaksi: jumlah catatan biaya sesuai filter.

Rute dengan biaya tertinggi dihitung dengan:

```php
$rekap = $rekapRecords
    ->groupBy(/* nama rute */)
    ->map(fn ($items) => [
        'total' => $items->sum('nominal'),
        'count' => $items->count(),
    ]);

$highestCostRoute = $rekap->sortByDesc('total')->first();
```

## 5. Statistik Laporan Pemesanan

```php
$summary = [
    'total_booking' => (clone $query)->count(),
    'terbayar' => (clone $query)
        ->where('status_pemesanan', 'terbayar')->count(),
    'dibatalkan' => (clone $query)
        ->where('status_pemesanan', 'dibatalkan')->count(),
    'selesai' => (clone $query)
        ->where('status_pemesanan', 'selesai')->count(),
    'total_nilai' => (int) (clone $query)->sum('total_harga'),
];
```

Kata `(clone $query)` penting. Setiap card memperoleh salinan query dasar agar penambahan kondisi
pada satu card tidak mengubah card lainnya.

## 6. Statistik Laporan Transaksi

Transaksi sah:

```php
$successfulTransactions = (clone $query)
    ->where('status_bayar', 'berhasil')
    ->whereHas('pemesanan', fn ($booking) =>
        $booking->whereIn('status_pemesanan', ['terbayar', 'selesai'])
    );
```

Ringkasan:

```php
'pendapatan_tahun_ini' => /* nominal transaksi pada tahun ini */,
'pendapatan_bulan_ini' => /* nominal transaksi pada bulan ini */,
'berhasil' => $successfulPayments->count(),
'nilai_berhasil' => (int) $successfulPayments->sum(
    fn ($payment) =>
        $payment->nominal_transaksi
        ?? (int) $payment->pemesanan?->total_harga
),
```

Perbedaannya:

- Pendapatan Tahun Ini tidak mengikuti filter tanggal halaman.
- Pendapatan Bulan Ini memakai bulan kalender berjalan.
- Transaksi Berhasil mengikuti filter halaman.
- Pendapatan Filter Aktif juga mengikuti filter halaman.

## 7. Hubungan dengan Database

Tidak ada tabel statistik khusus. Data berasal dari:

```text
pembayaran → pendapatan dan jumlah transaksi
pemesanan → status, nilai booking, jumlah kursi
jadwal → penghubung pemesanan dengan rute
rute → nama asal dan tujuan
biaya_operasional_perjalanan → nominal biaya
```

Keuntungan menghitung langsung:

- tidak ada data duplikat;
- tidak memerlukan migration;
- hasil mengikuti perubahan status;
- rumus mudah diperiksa saat sidang.

## 8. Latihan Sidang

1. Buka `DashboardController.php`, tunjukkan kondisi pembayaran berhasil.
2. Jelaskan perbedaan `COUNT(pemesanan.id)` dan `SUM(jumlah_kursi)`.
3. Tunjukkan bahwa fee provider tidak dijumlahkan sebagai pendapatan.
4. Ubah filter laporan, lalu jelaskan mengapa card dan tabel berubah bersama.
5. Jelaskan alasan tidak membuat kolom `jumlah_kunjungan` pada tabel `rute`.

Jawaban ringkas:

> Statistik merupakan data turunan. Sistem menghitungnya melalui query agregasi dari transaksi
> yang sudah tersimpan. Karena itu database tetap menyimpan fakta utama, sedangkan controller
> membentuk ringkasan ketika halaman diminta.
