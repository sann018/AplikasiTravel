# Logika Perhitungan Matematika

Dokumen ini memisahkan nilai yang dihitung sistem, nilai yang berasal dari provider, dan nilai
yang hanya diformat untuk tampilan. Semua nominal transaksi baru diproses backend sebagai integer
rupiah agar tidak terkena kesalahan pecahan floating point.

## 1. Subtotal Tiket

### Rumus

```text
subtotal_tiket = harga_tiket_per_kursi × jumlah_kursi
total_harga    = subtotal_tiket
```

Barang bawaan saat ini dicatat untuk kebutuhan operasional dan tidak menambah harga.

### Potongan Kode

```php
public function calculate(Jadwal $jadwal, int $seatCount): array
{
    $ticketPrice = (int) $jadwal->rute->harga_tiket;
    $subtotalTiket = $ticketPrice * $seatCount;

    return [
        'subtotal_tiket' => $subtotalTiket,
        'total_harga' => $subtotalTiket,
    ];
}
```

Sumber: [`backend/app/Services/BookingCalculator.php`](../backend/app/Services/BookingCalculator.php#L17)

### Makna

Harga tidak diterima dari input Flutter. Backend mengambil harga rute dari relasi jadwal, sehingga
pengguna tidak dapat menurunkan total dengan memodifikasi payload. Nilai disimpan pada pemesanan
sebagai snapshot. Jika harga rute berubah kemudian, pemesanan lama tetap memakai nilai historis.

### Keterkaitan

`BookingService::createDraft()` memanggil kalkulator ini sebelum membuat `pemesanan` dan
`detail_pemesanan`.

## 2. Tarif Historis per Penumpang

### Rumus

```text
tarif_per_penumpang = subtotal_tiket_historis ÷ jumlah_kursi
```

### Potongan Kode

```php
$passengerCount = max(1, (int) $booking->jumlah_kursi);
$historicalFare = (float) $booking->subtotal_tiket / $passengerCount;
```

Sumber:
[`backend/resources/views/admin/jadwal/partials/daftar-pemesanan.blade.php`](../backend/resources/views/admin/jadwal/partials/daftar-pemesanan.blade.php#L25)

### Makna

Pembagi minimum satu mencegah pembagian dengan nol pada data lama yang tidak konsisten. Rumus
memakai subtotal saat booking dibuat, bukan harga rute saat ini.

### Catatan Arsitektur

Perhitungan ini masih dilakukan pada Blade untuk keperluan tampilan detail jadwal. Secara
arsitektur, kalkulasi tersebut lebih baik dipindahkan ke backend/view-model jika kelak dilakukan
refactor, tetapi pada laporan implementasi harus dijelaskan sesuai kondisi sekarang.

## 3. Estimasi Waktu Tiba

### Rumus

```text
estimasi_tiba = waktu_berangkat + estimasi_durasi_menit
```

### Potongan Kode

```php
$departureTime = Carbon::parse($validated['waktu_berangkat']);

// Estimasi tiba dihitung ulang di backend agar admin tidak perlu
// mengisi manual dan tanggal otomatis maju jika perjalanan lewat tengah malam.
$validated['estimasi_tiba'] = $departureTime->copy()->addMinutes($durasiRuteMenit);
```

Sumber:
[`backend/app/Http/Controllers/Web/Admin/JadwalController.php`](../backend/app/Http/Controllers/Web/Admin/JadwalController.php#L338)

### Makna

Durasi disimpan dalam menit agar penjumlahan tetap sederhana. `Carbon` menangani perpindahan hari,
misalnya berangkat pukul 23.00 dengan durasi 180 menit menghasilkan tanggal tiba pada hari
berikutnya.

## 4. Konversi Durasi Menit menjadi Jam-Menit

### Rumus

```text
jam          = menit div 60
sisa_menit   = menit mod 60
```

### Potongan Kode

```php
$hours = intdiv($minutes, 60);
$remainingMinutes = $minutes % 60;

if ($hours > 0 && $remainingMinutes > 0) {
    return "{$hours} jam {$remainingMinutes} menit";
}
```

Sumber: [`backend/app/Models/Rute.php`](../backend/app/Models/Rute.php#L41)

### Makna

Database tetap menyimpan satuan menit, sedangkan accessor model membentuk label yang mudah dibaca.
Contoh 405 menit menjadi 6 jam 45 menit.

## 5. Nominal, Fee, dan Total Pembayaran Digital

### Rumus Implementasi Aktif

```text
nominal_transaksi       = payment.amount
biaya_admin_provider    = payment.fee
total_dibayar_penumpang = payment.amount + payment.fee
```

### Potongan Kode

```php
$providerAmount = $this->providerRupiah($paymentPayload, 'amount', false);
$providerFee = $this->providerRupiah($paymentPayload, 'fee', true);
$this->providerRupiah($paymentPayload, 'total_payment', false);
// Total pelanggan memakai nominal ditambah fee asli dari Pakasir.
$customerTotal = $providerAmount + $providerFee;

$payment->update([
    'nominal_transaksi' => $providerAmount,
    'biaya_admin_provider' => $providerFee,
    'total_dibayar_penumpang' => $customerTotal,
    'jumlah_bayar' => $customerTotal,
]);
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L808)

### Contoh

```text
payment.amount = Rp250.000
payment.fee    = Rp  2.500

nominal_transaksi       = Rp250.000
biaya_admin_provider    = Rp  2.500
total_dibayar_penumpang = Rp252.500
```

### Makna

Fee tidak dibuat dengan tarif hardcode. Angkanya harus berasal dari response Pakasir. Backend
memvalidasi keberadaan `total_payment`, tetapi total pelanggan aktif dibentuk dari `amount + fee`
sesuai keputusan bahwa fee ditanggung penumpang.

### Batas Eksternal

Jika halaman provider masih menunjukkan biaya admin nol, pengaturan **Fee By Merchant** pada
proyek Pakasir harus diubah agar fee ditanggung customer. Kode tidak boleh mengarang fee untuk
menutupi konfigurasi provider.

## 6. Pembayaran Tunai

### Rumus

```text
biaya_admin_provider    = 0
total_dibayar_penumpang = nominal_transaksi
```

### Potongan Kode

```php
'nominal_transaksi' => $this->rupiah($lockedBooking->total_harga),
'biaya_admin_provider' => $paymentMethod === PaymentMethodCatalog::CASH ? 0 : null,
'total_dibayar_penumpang' => $paymentMethod === PaymentMethodCatalog::CASH
    ? $this->rupiah($lockedBooking->total_harga)
    : null,
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L345)

### Makna

Nilai tunai disiapkan ketika metode dipilih, tetapi status tetap `menunggu`. Pembayaran baru sah
setelah dikonfirmasi pengemudi atau admin.

## 7. Pendapatan RuslyTravel

### Rumus

```text
pendapatan_perusahaan = nominal_transaksi
```

Fee provider tidak dimasukkan ke pendapatan.

### Potongan Kode

```php
'nilai_berhasil' => (int) $successfulPayments
    ->sum(fn ($payment) =>
        $payment->nominal_transaksi
        ?? (int) $payment->pemesanan?->total_harga
    ),
'biaya_admin_provider' => (int) $successfulPayments
    ->sum('biaya_admin_provider'),
```

Sumber:
[`backend/app/Http/Controllers/Web/Admin/LaporanController.php`](../backend/app/Http/Controllers/Web/Admin/LaporanController.php#L114)

### Makna

`total_dibayar_penumpang` merupakan beban penumpang, bukan omzet perusahaan. Fallback
`pemesanan.total_harga` hanya dipakai untuk transaksi lama yang belum mempunyai snapshot nominal.

## 8. Pendapatan Bulanan dan Tahunan

### Rumus

```text
pendapatan_bulan = Σ nominal transaksi berhasil pada bulan aktif
pendapatan_tahun = Σ nominal transaksi berhasil pada tahun aktif
```

### Potongan Kode

```php
'pendapatan_tahun_ini' => (int) (clone $periodRevenueQuery)
    ->whereYear('pembayaran.dibayar_pada', $today->year)
    ->sum(DB::raw(
        'COALESCE(pembayaran.nominal_transaksi, pemesanan.total_harga)'
    )),
'pendapatan_bulan_ini' => (int) (clone $periodRevenueQuery)
    ->whereYear('pembayaran.dibayar_pada', $today->year)
    ->whereMonth('pembayaran.dibayar_pada', $today->month)
    ->sum(DB::raw(
        'COALESCE(pembayaran.nominal_transaksi, pemesanan.total_harga)'
    )),
```

Sumber:
[`backend/app/Http/Controllers/Web/Admin/LaporanController.php`](../backend/app/Http/Controllers/Web/Admin/LaporanController.php#L122)

### Makna

Query dasar hanya mengambil pembayaran `berhasil` dengan pemesanan `terbayar` atau `selesai`.
Dengan demikian booking batal, pending, dan fee provider tidak masuk pendapatan.

## 9. Surplus/Defisit Perjalanan

### Rumus

```text
saldo_tercatat = pendapatan_jadwal_terkonfirmasi - biaya_operasional_final
```

### Potongan Kode

```php
$confirmedRevenue = (int) $successfulBookings
    ->sum(fn ($booking) =>
        $booking->pembayaran->nominal_transaksi
        ?? (int) $booking->total_harga
    );
$operationalCost = (int) $jadwal->biayaOperasional->sum('nominal');

'recorded_balance' => $confirmedRevenue - $operationalCost,
```

Sumber:
[`backend/app/Http/Controllers/Web/Admin/JadwalController.php`](../backend/app/Http/Controllers/Web/Admin/JadwalController.php#L145)

### Interpretasi

- hasil positif: surplus tercatat;
- hasil nol: seimbang;
- hasil negatif: defisit tercatat.

Istilah “tercatat” penting karena angka hanya mencakup pembayaran dan biaya yang masuk ke sistem.

## 10. Rute Paling Banyak Dikunjungi

### Rumus

```text
jumlah_penumpang_rute = Σ jumlah_kursi dari pemesanan selesai
jumlah_pemesanan_rute = COUNT pemesanan selesai
rute_terpopuler        = arg max(jumlah_penumpang_rute)
```

### Potongan Kode

```php
$ruteTerpopulerRecord = Pemesanan::query()
    ->join('jadwal', 'jadwal.id', '=', 'pemesanan.jadwal_id')
    ->join('rute', 'rute.id', '=', 'jadwal.rute_id')
    ->where('pemesanan.status_pemesanan', 'selesai')
    ->selectRaw('COUNT(pemesanan.id) as jumlah_pemesanan')
    ->selectRaw('SUM(pemesanan.jumlah_kursi) as jumlah_penumpang')
    ->groupBy('rute.id', 'rute.kota_asal', 'rute.kota_tujuan')
    ->orderByDesc('jumlah_penumpang')
    ->first();
```

Sumber:
[`backend/app/Http/Controllers/Web/Admin/DashboardController.php`](../backend/app/Http/Controllers/Web/Admin/DashboardController.php#L46)

### Makna

Sistem menyajikan jumlah booking dan jumlah penumpang secara terpisah. Satu booking dengan tiga
kursi menyumbang satu pemesanan dan tiga penumpang. Rincian seluruh rumus card statistik terdapat
pada [`10_STATISTIK_DASBOR_DAN_LAPORAN.md`](10_STATISTIK_DASBOR_DAN_LAPORAN.md).

## 11. Ketersediaan Kursi

### Rumus Ringkas

```text
kursi_tersedia = COUNT(status_kursi = tersedia)
kursi_dipesan  = COUNT(status_kursi = dipesan)
kursi_nonaktif = COUNT(status_kursi = nonaktif)
```

Backend mengirim jumlah tersedia, sedangkan mobile menggunakan nilai tersebut untuk
menyembunyikan jadwal penuh di beranda.

Sumber:

- [`backend/app/Http/Controllers/Api/AppBootstrapController.php`](../backend/app/Http/Controllers/Api/AppBootstrapController.php#L68)
- [`frontend/lib/features/home/bagian_beranda/halaman_informasi.dart`](../frontend/lib/features/home/bagian_beranda/halaman_informasi.dart#L423)

## 12. Countdown

### Rumus

```text
sisa_waktu = max(0, deadline_API - waktu_perangkat_saat_ini)
```

### Potongan Kode

```dart
final difference = deadline.difference(DateTime.now());
return difference.isNegative ? Duration.zero : difference;
```

Sumber:
[`frontend/lib/features/payment/bagian_pembayaran/hitung_mundur.dart`](../frontend/lib/features/payment/bagian_pembayaran/hitung_mundur.dart#L95)

### Makna

Flutter tidak mengurangi sebuah angka lokal terus-menerus sebagai sumber kebenaran. Setiap update
menghitung selisih dari timestamp. Ketika aplikasi kembali dari background, nilai dihitung ulang.
Angka negatif dipaksa menjadi nol.

## 13. Rumus yang Tidak Aktif

Kode persentase okupansi masih berada di controller lama:

```text
persentase_okupansi = kursi_dipesan / total_kursi × 100%
```

Namun route laporan okupansi tidak aktif. Rumus tersebut tidak boleh diklaim sebagai fitur laporan
aktif dalam laporan Tugas Akhir saat ini.

## 14. Sinyal Real-Time Tidak Mengubah Rumus Kursi

WebSocket tidak menghitung ketersediaan kursi. Event hanya memberi tahu aplikasi bahwa data
berubah. Nilai kursi tetap dihitung dari status terbaru yang dikembalikan API:

```text
event Reverb diterima
→ GET data kursi terbaru
→ UI menggambar ulang status kursi
```

Dengan demikian tidak ada rumus atau nilai kursi yang dipercaya langsung dari payload event.
