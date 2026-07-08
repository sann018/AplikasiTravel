# Alur Kode Pemesanan, QR Tiket, dan QRIS

Dokumen ini menjelaskan bagaimana sistem Rusly Travel membuat kode pemesanan, QR Code tiket, dan pembayaran QRIS. Penjelasan dibuat sederhana agar mudah dipahami saat belajar kode maupun saat menjelaskan alur sistem di sidang.

## Ringkasan Singkat

Ada tiga kode/identitas penting yang sering terlihat mirip, tetapi fungsinya berbeda:

| Bagian | Dibuat oleh | Fungsi |
| --- | --- | --- |
| Kode pemesanan | Backend Rusly Travel | Identitas utama satu transaksi pemesanan |
| QR tiket | Backend Rusly Travel dan ditampilkan Flutter | Validasi tiket penumpang saat keberangkatan |
| QRIS | Pakasir | QR pembayaran agar penumpang bisa membayar tagihan |

Intinya:

1. Penumpang membuat pemesanan.
2. Backend membuat `kode_booking`.
3. Backend membuat data pembayaran.
4. Jika metode bayar QRIS atau transfer, backend membuat link pembayaran Pakasir.
5. Setelah pembayaran berhasil, backend menerbitkan tiket elektronik.
6. Flutter menampilkan QR tiket dari token unik tiket.
7. Pengemudi memindai QR tiket untuk validasi kehadiran penumpang.

## File Kode yang Berkaitan

Beberapa file utama yang menjadi sumber alur ini:

- `backend/app/Services/BookingService.php`
  Mengatur pembuatan pemesanan, kode pemesanan, aktivasi pembayaran Pakasir, sinkronisasi pembayaran, dan penerbitan tiket.

- `backend/app/Services/PakasirService.php`
  Mengatur komunikasi dengan Pakasir, pembuatan checkout URL, simulasi pembayaran, dan pengecekan status transaksi.

- `backend/app/Http/Controllers/Api/BookingController.php`
  Menerima request pemesanan dari aplikasi mobile dan membentuk response pemesanan, pembayaran, serta tiket.

- `backend/app/Http/Controllers/Api/DriverController.php`
  Mengatur scan QR tiket oleh pengemudi.

- `backend/database/migrations/2026_04_22_000200_create_transaksi_tables.php`
  Membuat tabel `pemesanan`, `pembayaran`, dan `tiket_elektronik`.

- `frontend/lib/features/ticket/travs_ticket_detail_page.dart`
  Menampilkan QR tiket di aplikasi mobile.

- `frontend/lib/core/services/travs_api_service.dart`
  Mengirim request pemesanan dan request scan tiket dari aplikasi Flutter ke backend.

## 1. Alur Pembuatan Kode Pemesanan

Kode pemesanan dibuat saat penumpang berhasil mengirim form pemesanan dari aplikasi mobile.

Pada sisi Flutter, aplikasi mengirim data pemesanan ke endpoint:

```text
POST /api/penumpang/bookings
```

Data yang dikirim berisi jadwal, kursi, penumpang, titik jemput, titik turun, barang bawaan, dan metode pembayaran.

Backend kemudian memproses data tersebut di `BookingService.php`. Saat data pemesanan dibuat, backend mengisi kolom:

```php
'kode_booking' => $this->generateBookingCode(),
```

Method pembuat kode berada di bagian akhir `BookingService.php`:

```php
private function generateBookingCode(): string
{
    do {
        $code = sprintf(
            'TRAVS-%s-%s',
            now()->format('Ymd'),
            Str::upper(Str::random(5)),
        );
    } while (Pemesanan::query()->where('kode_booking', $code)->exists());

    return $code;
}
```

### Format kode pemesanan

Format kode pemesanan adalah:

```text
TRAVS-YYYYMMDD-XXXXX
```

Contoh:

```text
TRAVS-20260625-XPQS1
```

Artinya:

- `TRAVS` adalah prefix aplikasi.
- `20260625` adalah tanggal kode dibuat dengan format tahun, bulan, dan hari.
- `XPQS1` adalah kode acak 5 karakter.

### Kenapa ada pengecekan ulang?

Kode pemesanan harus unik. Karena itu backend melakukan pengecekan:

```php
Pemesanan::query()->where('kode_booking', $code)->exists()
```

Jika kode acak ternyata sudah pernah dipakai, sistem akan membuat kode baru lagi. Selain itu, pada database kolom `kode_booking` juga dibuat `unique`, sehingga database ikut menjaga agar tidak ada kode pemesanan yang sama.

## 2. Data yang Dibuat Saat Pemesanan

Saat pemesanan dibuat, backend menyimpan beberapa data utama:

### A. Data pemesanan

Tersimpan di tabel `pemesanan`, berisi:

- pengguna yang memesan,
- jadwal yang dipilih,
- kode pemesanan,
- jumlah kursi,
- batas pembayaran,
- alamat jemput,
- alamat turun,
- subtotal tiket,
- biaya bagasi,
- total harga,
- status pemesanan.

Status awal pemesanan adalah:

```text
menunggu_pembayaran
```

### B. Data penumpang per kursi

Tersimpan di tabel `detail_pemesanan`, berisi:

- nomor kursi,
- nama penumpang,
- nomor HP penumpang,
- status kehadiran.

Status awal kehadiran adalah:

```text
menunggu
```

### C. Data barang bawaan

Tersimpan di tabel `item_bagasi`, berisi:

- deskripsi barang,
- metode hitung,
- berat,
- ukuran,
- jumlah item,
- foto barang,
- biaya item.

### D. Data pembayaran

Tersimpan di tabel `pembayaran`, berisi:

- metode bayar,
- jumlah bayar,
- status bayar,
- order id gateway,
- response gateway,
- batas pembayaran.

Status awal pembayaran adalah:

```text
menunggu
```

## 3. Alur Pembayaran QRIS

QRIS pada sistem ini tidak dibuat langsung oleh kode Flutter atau Laravel. QRIS dibuat dan dikelola oleh Pakasir.

Backend hanya membuat link pembayaran dan menyimpan data transaksi yang dikembalikan oleh Pakasir.

### Metode yang ditangani Pakasir

Di `PakasirService.php`, metode yang dianggap memakai Pakasir adalah:

```php
public function handlesMethod(string $paymentMethod): bool
{
    return in_array($paymentMethod, ['qris', 'transfer'], true);
}
```

Artinya:

- `qris` diproses melalui Pakasir.
- `transfer` juga diarahkan ke alur Pakasir.
- `cash` tidak diproses Pakasir karena dibayar langsung/tunai.

### Pembuatan link pembayaran Pakasir

Saat metode bayar adalah QRIS atau transfer, `BookingService.php` memanggil:

```php
return $this->activatePakasirPayment($booking, $paymentMethod);
```

Di dalam method `activatePakasirPayment`, sistem membuat checkout URL:

```php
$checkoutUrl = $this->pakasirService->checkoutUrl(
    $booking->kode_booking,
    $booking->total_harga,
    $qrisOnly,
    $this->pakasirService->merchantReturnUrl(),
);
```

Checkout URL ini adalah link yang akan dibuka oleh penumpang untuk melakukan pembayaran.

Format URL Pakasir dibentuk di `PakasirService.php`:

```php
return 'https://app.pakasir.com/pay/'
    .rawurlencode($this->project())
    .'/'.$this->normalizeAmount($amount)
    .'?'.http_build_query($query);
```

Contoh konsep URL:

```text
https://app.pakasir.com/pay/app-travs/311500?order_id=TRAVS-20260625-XPQS1&qris_only=1
```

Keterangan:

- `app-travs` adalah nama project Pakasir.
- `311500` adalah nominal pembayaran.
- `order_id` menggunakan kode pemesanan.
- `qris_only=1` menandakan halaman pembayaran difokuskan ke QRIS.

## 4. Data QRIS yang Disimpan Sistem

Saat QRIS dibuat, backend menyimpan data pembayaran ke tabel `pembayaran`.

Data yang penting:

| Kolom | Fungsi |
| --- | --- |
| `order_id_gateway` | Nomor transaksi/order pada gateway |
| `gateway_reference` | Nomor referensi pembayaran |
| `paid_channel` | Channel pembayaran |
| `jumlah_bayar` | Total pembayaran |
| `status_bayar` | Status pembayaran |
| `gateway_response` | Data ringkas dari Pakasir |
| `expired_at` | Batas waktu pembayaran |

Status pembayaran digital tetap mengikuti Pakasir. Jadi admin tidak boleh asal mengubah status QRIS secara manual, karena status tersebut harus sesuai dengan data gateway.

## 5. Sinkronisasi Status Pembayaran QRIS

Setelah penumpang membayar, sistem perlu tahu apakah pembayaran sudah berhasil.

Ada dua cara utama:

### A. Webhook Pakasir

Pakasir mengirim pemberitahuan ke backend melalui endpoint webhook.

Backend memprosesnya di:

```php
BookingService::handlePakasirWebhook()
```

Alurnya:

1. Backend mengecek apakah Pakasir aktif.
2. Backend memastikan project Pakasir sesuai.
3. Backend mencari pemesanan berdasarkan `order_id`.
4. Backend mencocokkan nominal pembayaran.
5. Backend meminta detail transaksi terbaru ke Pakasir.
6. Jika status transaksi `completed`, pembayaran disinkronkan menjadi berhasil.

### B. Refresh status pembayaran

Aplikasi juga dapat meminta backend mengecek ulang status pembayaran.

Endpoint ini digunakan saat aplikasi ingin menyegarkan status:

```text
POST /api/penumpang/bookings/{booking}/payment/refresh
```

Backend menjalankan:

```php
refreshGatewayPaymentStatus()
```

Tujuannya agar status pembayaran di aplikasi tidak hanya bergantung pada tampilan lokal.

## 6. Jika Pembayaran QRIS Berhasil

Jika Pakasir menyatakan transaksi berhasil, backend menjalankan:

```php
syncPakasirPayment()
```

Di dalam proses ini:

1. Status pembayaran diubah menjadi:

```text
berhasil
```

2. Status pemesanan diubah menjadi:

```text
terbayar
```

3. Backend menjalankan:

```php
$this->finalizePaidBooking($lockedBooking);
```

Method ini bertugas:

- mengubah kursi dari terkunci menjadi dipesan,
- menerbitkan tiket elektronik,
- membuat notifikasi pembayaran berhasil.

## 7. Alur Pembuatan QR Code Tiket

QR tiket dibuat setelah pembayaran dianggap sah.

Pada `BookingService.php`, tiket dibuat di method:

```php
finalizePaidBooking()
```

Kode pentingnya:

```php
TiketElektronik::query()->updateOrCreate(
    ['detail_pemesanan_id' => $detail->id],
    [
        'kode_qr' => 'TRAVS-'.$booking->kode_booking.'-'.$detail->nomor_kursi,
        'token_unik_qr' => $detail->tiketElektronik?->token_unik_qr ?: Str::uuid()->toString(),
        'diterbitkan_pada' => $detail->tiketElektronik?->diterbitkan_pada ?: now(),
    ],
);
```

### Data QR tiket

Tiket elektronik punya dua kode penting:

| Field | Fungsi |
| --- | --- |
| `kode_qr` | Kode tiket yang mudah dibaca manusia |
| `token_unik_qr` | Token unik rahasia yang dipakai untuk isi QR |

Contoh `kode_qr`:

```text
TRAVS-TRAVS-20260625-XPQS1-A4
```

Contoh `token_unik_qr`:

```text
066f5571-9abc-4e38-a112-1b87ef89190c
```

### Kenapa QR tiket memakai token?

QR tiket tidak hanya memakai kode pemesanan, tetapi memakai token unik berbentuk UUID.

Alasannya:

- kode pemesanan masih bisa terbaca oleh manusia,
- token UUID lebih sulit ditebak,
- setiap kursi/penumpang punya tiket sendiri,
- scan tiket bisa lebih aman karena backend mencari tiket berdasarkan token.

## 8. Tampilan QR Tiket di Flutter

Di aplikasi Flutter, QR tiket ditampilkan pada file:

```text
frontend/lib/features/ticket/travs_ticket_detail_page.dart
```

Kode penting:

```dart
final qrData = ticket.token.isNotEmpty ? ticket.token : ticket.kodeQr;
```

Artinya:

- jika token tersedia, QR akan memakai token,
- jika token kosong, baru memakai `kode_qr` sebagai fallback.

Dengan cara ini, QR yang dipindai pengemudi biasanya berisi `token_unik_qr`, bukan kode pemesanan biasa.

## 9. Alur Scan QR Tiket oleh Pengemudi

Saat pengemudi memindai QR tiket, aplikasi Flutter mengirim token ke backend:

```text
POST /api/pengemudi/tickets/scan
```

Body request:

```json
{
  "token": "token_unik_qr"
}
```

Backend memprosesnya di:

```php
DriverController::scanTicket()
```

Alur validasinya:

1. Backend mencari tiket berdasarkan `token_unik_qr`.
2. Jika tiket tidak ditemukan, response gagal.
3. Backend mengecek apakah tiket memang bagian dari manifest pengemudi yang sedang login.
4. Backend mengecek apakah pemesanan sudah `terbayar`.
5. Backend mengecek apakah tiket sudah pernah dipindai.
6. Jika valid, tiket ditandai sudah dipindai.
7. Status kehadiran penumpang diubah menjadi `hadir`.

Kode intinya:

```php
$ticket = TiketElektronik::query()
    ->where('token_unik_qr', $payload['token'])
    ->first();
```

Jika tiket valid:

```php
$ticket->update([
    'sudah_dipindai' => true,
    'dipindai_pada' => $now,
]);

$detail->update([
    'status_kehadiran' => 'hadir',
]);
```

## 10. Perbedaan QRIS dan QR Tiket

Bagian ini penting karena QRIS dan QR tiket sama-sama berbentuk QR, tetapi tujuannya berbeda.

| Pembeda | QRIS | QR Tiket |
| --- | --- | --- |
| Fungsi | Membayar tagihan | Validasi keberangkatan |
| Dibuat oleh | Pakasir | Backend Rusly Travel |
| Dipakai oleh | Penumpang/e-wallet | Pengemudi saat scan tiket |
| Isi utama | Data pembayaran gateway | Token unik tiket |
| Status akhir | Pembayaran berhasil/gagal | Tiket sudah dipindai/belum |

Jadi:

- QRIS adalah QR untuk membayar.
- QR tiket adalah QR untuk membuktikan bahwa penumpang punya tiket valid.

## 11. Contoh Alur Lengkap

Contoh sederhana:

1. Penumpang memilih jadwal Pontianak - Sanggau.
2. Penumpang memilih kursi A4.
3. Penumpang mengisi titik jemput, titik turun, data penumpang, dan barang bawaan.
4. Penumpang memilih metode bayar QRIS.
5. Backend membuat kode pemesanan:

```text
TRAVS-20260625-XPQS1
```

6. Backend membuat pembayaran dengan status:

```text
menunggu
```

7. Backend membuat link Pakasir.
8. Flutter membuka halaman Pakasir.
9. Penumpang membayar QRIS.
10. Backend menerima status berhasil dari Pakasir atau melakukan refresh status.
11. Backend mengubah pemesanan menjadi:

```text
terbayar
```

12. Backend membuat tiket elektronik untuk kursi A4.
13. Flutter menampilkan QR tiket.
14. Pengemudi memindai QR tiket.
15. Backend mengubah status kehadiran menjadi:

```text
hadir
```

## 12. Keamanan Alur

Beberapa pengamanan yang terlihat pada kode:

1. Kode pemesanan dibuat unik.
2. Database menjaga `kode_booking` agar tidak duplikat.
3. QR tiket memakai token UUID yang lebih sulit ditebak.
4. Scan tiket hanya boleh dilakukan oleh pengemudi yang memiliki jadwal tersebut.
5. Tiket hanya valid jika pemesanan sudah `terbayar`.
6. Tiket yang sudah dipindai tidak bisa dipakai ulang.
7. Status pembayaran digital mengikuti Pakasir, bukan diubah manual sembarangan.
8. Backend tetap menjadi sumber utama status pemesanan dan pembayaran.

## 13. Catatan untuk Penjelasan Sidang

Jika ditanya dosen, jawaban sederhananya bisa seperti ini:

> Kode pemesanan dibuat otomatis oleh backend dengan format TRAVS, tanggal, dan kode acak. Setelah penumpang memilih metode QRIS, sistem membuat link pembayaran ke Pakasir menggunakan kode pemesanan sebagai order ID. QRIS sendiri dibuat oleh Pakasir. Setelah pembayaran berhasil, backend menerbitkan tiket elektronik per kursi. QR tiket yang ditampilkan di aplikasi berisi token unik, bukan hanya kode pemesanan, agar lebih aman saat dipindai oleh pengemudi.

## 14. Catatan Batasan

Berdasarkan kode yang dianalisis:

- QRIS bergantung pada konfigurasi Pakasir di backend.
- Mode simulasi pembayaran dipakai untuk pengujian lokal.
- Status pembayaran QRIS tetap perlu disinkronkan dari Pakasir.
- QR tiket baru aktif setelah pembayaran berhasil atau pembayaran tunai dikonfirmasi sesuai alur sistem.

