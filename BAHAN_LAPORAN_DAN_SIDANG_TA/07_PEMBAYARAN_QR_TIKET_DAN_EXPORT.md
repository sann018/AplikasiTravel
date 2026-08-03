# Pembayaran Pakasir, QR Tiket, dan Export Laporan

## 1. Metode Pembayaran

Metode baru yang dapat dipilih:

```text
qris
bri_va
bni_va
cimb_niaga_va
permata_va
atm_bersama_va
bnc_va
maybank_va
artha_graha_va
sampoerna_va
cash
```

Kode `transfer` dipertahankan hanya untuk membaca transaksi lama. Daftar metode berada pada:

[`backend/app/Support/PaymentMethodCatalog.php`](../backend/app/Support/PaymentMethodCatalog.php)

## 2. Pemisahan Konfirmasi Pemesanan dan Pembayaran

Pada konfirmasi pemesanan:

- booking dibuat dahulu;
- status `menunggu_pembayaran`;
- belum ada record provider;
- belum ada metode bayar;
- belum ada tiket.

Transaksi Pakasir baru dibuat melalui:

```http
PUT /api/penumpang/bookings/{booking}/payment
```

setelah penumpang memilih metode di halaman pembayaran.

## 3. Transaction Create Pakasir

### Potongan Kode

```php
public function createTransaction(
    string $method,
    string $orderId,
    int $amount,
): array {
    $response = $this->post(
        "transactioncreate/{$method}",
        [
            'project' => $this->project(),
            'order_id' => $orderId,
            'amount' => $this->normalizeAmount($amount),
            'api_key' => $this->apiKey(),
        ],
    );

    $payment = $response['payment'] ?? null;
    if (! is_array($payment)) {
        throw new PakasirException(
            'Response Pakasir tidak mengandung data pembayaran.',
        );
    }

    return $payment;
}
```

Sumber: [`backend/app/Services/PakasirService.php`](../backend/app/Services/PakasirService.php#L89)

### Fungsi

Membuat transaksi QRIS atau VA. `amount` hanya berisi nominal RuslyTravel, bukan fee buatan
aplikasi. API key dibaca dari konfigurasi backend.

### Contoh Request Konseptual

```json
{
  "project": "slug_project",
  "order_id": "TRX-20260726-ABCDE",
  "amount": 250000,
  "api_key": "***"
}
```

### Contoh Response

```json
{
  "payment": {
    "project": "slug_project",
    "order_id": "TRX-20260726-ABCDE",
    "amount": 250000,
    "fee": 2500,
    "total_payment": 252500,
    "payment_method": "qris",
    "payment_number": "000201010212...",
    "expired_at": "2026-07-26T03:41:00Z"
  }
}
```

Contoh di atas adalah bentuk kontrak, bukan bukti fee live. Nilai produksi/sandbox harus dilihat
dari raw response Pakasir.

## 4. Snapshot Pembayaran

```php
$payment->update([
    'order_id_gateway' =>
        (string) data_get($paymentPayload, 'order_id', $gatewayOrderId),
    'gateway_reference' =>
        (string) data_get($paymentPayload, 'payment_number', ''),
    'paid_channel' =>
        (string) data_get($paymentPayload, 'payment_method', $paymentMethod),
    'nominal_transaksi' => $providerAmount,
    'biaya_admin_provider' => $providerFee,
    'total_dibayar_penumpang' => $customerTotal,
    'payment_number' =>
        $paymentNumber !== '' ? $paymentNumber : null,
    'jumlah_bayar' => $customerTotal,
    'status_bayar' => 'menunggu',
    'expired_at' => $expiredAt,
]);
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L823)

### Makna

Snapshot mencegah transaksi lama berubah jika tarif provider atau harga rute berubah. Data utama:

```text
nominal_transaksi       → nilai jasa RuslyTravel
biaya_admin_provider    → biaya Pakasir
total_dibayar_penumpang → nominal + fee
payment_number          → QR string atau nomor VA
expired_at              → batas provider
```

## 5. Pencegahan Transaksi Ganda

Saat record pembayaran lokal sudah ada, service tidak membuat transaksi provider baru. Jika
request pertama timeout dan hasilnya tidak diketahui, sistem memanggil Transaction Detail untuk
mencari transaksi yang sama.

```php
if ($lockedBooking->pembayaran) {
    $needsGatewayRecovery =
        $this->pakasirService->handlesMethod($paymentMethod)
        && $lockedBooking->pembayaran->status_bayar === 'menunggu'
        && ! is_array(data_get($gatewayResponse, 'create_payment'));

    return [
        'booking' => $lockedBooking,
        'payment_created' => false,
        'needs_gateway_recovery' => $needsGatewayRecovery,
    ];
}
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L300)

Constraint database `UNIQUE pembayaran.pemesanan_id` dan `UNIQUE order_id_gateway` menjadi
lapisan tambahan terhadap request paralel.

## 6. Hosted Checkout dalam Implementasi

Setelah Transaction Create berhasil, backend juga membentuk `checkout_url`:

```php
$checkoutUrl = $this->pakasirService->checkoutUrl(
    $gatewayOrderId,
    $providerAmount,
    $paymentMethod === PaymentMethodCatalog::QRIS,
    $this->pakasirService->merchantReturnUrl(),
    $paymentMethod,
);
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L815)

### Penjelasan yang Tepat

Implementasi aktif menggunakan Direct API untuk membuat transaksi dan memakai hosted URL sebagai
halaman lanjutan bagi `order_id`, nominal, dan metode yang sama. Kode tidak memanggil
Transaction Create kedua saat tombol lanjutan dibuka.

Namun secara integrasi, pola ini masih menggabungkan data Direct API dengan halaman hosted.
Apabila standar arsitektur akhir mengharuskan **API penuh tanpa hosted checkout**, Flutter harus
menampilkan QR string atau nomor VA langsung dan `checkout_url` tidak dipakai. Kondisi sekarang
harus disebut apa adanya saat sidang.

## 7. Pembayaran Tunai

```php
if ($paymentMethod === PaymentMethodCatalog::CASH) {
    $lockedBooking->update(['batas_pembayaran' => null]);
    $lockedBooking->detailPemesanan
        ->pluck('kursiJadwal')
        ->filter()
        ->each(fn (KursiJadwal $seat) => $seat->update([
            'lock_expires_at' => null,
        ]));
}
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L373)

Tunai:

- tidak memanggil Pakasir;
- tidak mempunyai QR/VA provider;
- fee nol;
- total sama dengan nominal;
- tidak mempunyai countdown provider;
- menunggu konfirmasi saat penumpang dijemput.

## 8. Webhook dan Verifikasi Ulang

### Validasi Controller

```php
$validated = $request->validate([
    'amount' => ['required', 'integer', 'min:1'],
    'order_id' => ['required', 'string', 'max:100'],
    'project' => ['required', 'string', 'max:100'],
    'status' => [
        'required',
        Rule::in([
            'completed',
            'pending',
            'expired',
            'failed',
            'cancelled',
            'canceled',
        ]),
    ],
    'payment_method' => [
        'required',
        Rule::in(PaymentMethodCatalog::directGatewayCodes()),
    ],
]);
```

Sumber:
[`backend/app/Http/Controllers/Api/PaymentGatewayController.php`](../backend/app/Http/Controllers/Api/PaymentGatewayController.php#L19)

### Validasi Service

```php
if (! $this->pakasirService->validateProject($payload['project'])) {
    throw ValidationException::withMessages([
        'project' => 'Identitas layanan Pakasir tidak sesuai.',
    ]);
}

if ($bookingAmount !== (int) $payload['amount']) {
    throw ValidationException::withMessages([
        'amount' => 'Nominal pembayaran tidak sesuai.',
    ]);
}

$transaction = $this->pakasirService->transactionDetail(
    $this->paymentOrderId($booking),
    $bookingAmount,
);

if (
    $this->pakasirService->isCompletedStatus($payload['status'])
    && $this->pakasirService->isCompletedStatus(
        data_get($transaction, 'status'),
    )
) {
    return $this->syncPakasirPayment(
        $booking,
        $transaction,
        $payload,
    );
}
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L684)

### Mengapa Diverifikasi Ulang?

Webhook datang dari request eksternal. Memeriksa project, order ID, amount, metode, dan Transaction
Detail mencegah payload palsu menjadikan booking terbayar.

## 9. Idempotensi Webhook

```php
if (
    in_array(
        $lockedBooking->status_pemesanan,
        ['terbayar', 'selesai'],
        true,
    )
) {
    $payment->update([
        'webhook_verified_at' => now(),
        'gateway_response' =>
            $this->pakasirService->mergeGatewayResponse(
                $payment->gateway_response,
                ['duplicate_success_webhook' => true],
            ),
    ]);

    return $this->freshBookingWithRelations($lockedBooking->id);
}
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L1025)

### Makna

Webhook sukses berulang hanya memperbarui catatan audit. Tiket tidak diterbitkan ulang karena
`updateOrCreate()` dan pengecekan status.

## 10. Penerbitan Tiket QR

```php
TiketElektronik::query()->updateOrCreate(
    ['detail_pemesanan_id' => $detail->id],
    [
        'kode_qr' =>
            'TRAVS-'.$booking->kode_booking.'-'.$detail->nomor_kursi,
        'token_unik_qr' =>
            $detail->tiketElektronik?->token_unik_qr
            ?: Str::uuid()->toString(),
        'diterbitkan_pada' =>
            $detail->tiketElektronik?->diterbitkan_pada
            ?: now(),
    ],
);
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L1154)

### Fungsi

Satu tiket diterbitkan untuk satu `detail_pemesanan`. Token UUID dipertahankan jika tiket sudah ada
sehingga webhook ulang tidak mengganti QR.

## 11. Menampilkan QR di Flutter

```dart
QrImageView(
  data: data,
  size: 220,
  backgroundColor: Colors.white,
  eyeStyle: const QrEyeStyle(
    eyeShape: QrEyeShape.square,
    color: TravSTheme.navy,
  ),
)
```

Sumber:
[`frontend/lib/features/ticket/bagian_detail_tiket/qr_dan_perjalanan_tiket.dart`](../frontend/lib/features/ticket/bagian_detail_tiket/qr_dan_perjalanan_tiket.dart#L3)

Flutter hanya mengubah token menjadi gambar. Validitas token tetap ditentukan backend.

## 12. Verifikasi QR oleh Pengemudi

```php
$ticket = TiketElektronik::query()
    ->lockForUpdate()
    ->with('detailPemesanan.pemesanan.jadwal')
    ->where('token_unik_qr', $payload['token'])
    ->first();

if ($ticket->sudah_dipindai) {
    return response()->json([
        'success' => false,
        'message' => 'Tiket sudah pernah dipindai.',
    ], 409);
}

$ticket->update([
    'sudah_dipindai' => true,
    'dipindai_pada' => now(),
]);
$detail->update([
    'status_kehadiran' => 'hadir',
]);
```

Sumber:
[`backend/app/Http/Controllers/Api/DriverController.php`](../backend/app/Http/Controllers/Api/DriverController.php#L104)

### Pemeriksaan Lengkap

Sebelum update, service memeriksa:

- token ditemukan;
- tiket mempunyai detail;
- jadwal ditugaskan kepada pengemudi aktif;
- jadwal belum selesai;
- pemesanan berstatus terbayar;
- QR belum dipindai.

## 13. Unduh Tiket sebagai Gambar

```dart
final boundary =
    _ticketCaptureKey.currentContext?.findRenderObject()
        as RenderRepaintBoundary?;
final image = await boundary.toImage(pixelRatio: 3);
final byteData = await image.toByteData(
  format: ui.ImageByteFormat.png,
);

await Gal.putImageBytes(
  byteData!.buffer.asUint8List(),
  album: 'RuslyTravel',
  name: fileName,
);
```

Sumber:
[`frontend/lib/features/ticket/travs_ticket_detail_page.dart`](../frontend/lib/features/ticket/travs_ticket_detail_page.dart#L85)

### Fungsi

Card tiket aktif dirender pada resolusi tiga kali dan disimpan sebagai PNG. Nama file memuat kode
booking serta nama penumpang. Jika akses galeri belum diberikan, aplikasi meminta izin dan
menawarkan membuka pengaturan perangkat.

## 14. Filter Laporan

```php
if ($request->filled('tanggal_mulai')) {
    $query->whereDate(
        'created_at',
        '>=',
        $request->date('tanggal_mulai'),
    );
}

if ($request->filled('tanggal_selesai')) {
    $query->whereDate(
        'created_at',
        '<=',
        $request->date('tanggal_selesai'),
    );
}
```

Sumber:
[`backend/app/Http/Controllers/Web/Admin/LaporanController.php`](../backend/app/Http/Controllers/Web/Admin/LaporanController.php#L246)

Tanggal selesai divalidasi harus sama atau setelah tanggal mulai. Query yang sama dipakai oleh
tampilan dan export sehingga isi file mengikuti filter.

## 15. Export CSV

```php
return response()->streamDownload(function () use ($rows) {
    $handle = fopen('php://output', 'w');
    fwrite($handle, "\xEF\xBB\xBF");

    foreach ($rows as $row) {
        fputcsv($handle, $row, ';');
    }

    fclose($handle);
}, $filename);
```

Sumber:
[`backend/app/Http/Controllers/Web/Admin/LaporanController.php`](../backend/app/Http/Controllers/Web/Admin/LaporanController.php#L382)

Byte Order Mark membantu Excel membaca UTF-8. Pemisah titik koma sesuai kebiasaan regional.

## 16. Export XLS dan Filter Kolom

```php
echo '<AutoFilter x:Range="R6C1:R'
    .$lastDataRow
    .'C'
    .$columnCount
    .'" xmlns="urn:schemas-microsoft-com:office:excel"/>';
```

Sumber:
[`backend/app/Http/Controllers/Web/Admin/LaporanController.php`](../backend/app/Http/Controllers/Web/Admin/LaporanController.php#L398)

### Fungsi

Backend membentuk SpreadsheetML yang dapat dibuka Excel. File memuat:

- judul laporan;
- periode;
- nama admin pencetak;
- tanggal cetak;
- format uang;
- ringkasan;
- freeze pane;
- AutoFilter pada header kolom.

## 17. Struktur Response API Pembayaran

Response detail pemesanan mengirim field utama berikut:

```json
{
  "payment": {
    "method": "qris",
    "method_label": "QRIS",
    "method_family": "qris",
    "status": "menunggu",
    "nominal_transaksi": 250000,
    "biaya_admin_provider": 2500,
    "total_dibayar_penumpang": 252500,
    "payment_number": "000201010212...",
    "checkout_url": "https://app.pakasir.com/pay/...",
    "expired_at": "2026-07-26T03:41:00Z",
    "paid_at": null
  }
}
```

Sumber format:
[`backend/app/Http/Controllers/Api/BookingController.php`](../backend/app/Http/Controllers/Api/BookingController.php#L361)

Field alias lama masih dikirim pada beberapa bagian untuk kompatibilitas mobile, tetapi Flutter
baru harus memakai tiga snapshot nominal tersebut.

## Pembaruan Lingkungan Uji Lokal — 27 Juli 2026

Untuk LDPlayer, API dan Reverb dapat diteruskan menggunakan ADB:

```powershell
.\tool\connect_android_backend.ps1 -Port 8000
.\tool\connect_android_backend.ps1 -Port 6001
flutter run
```

Untuk pengujian tiket QR melalui Flutter Web:

```powershell
flutter run -d chrome `
  --dart-define=API_BASE_URL=http://127.0.0.1:8000
```

Chrome dan web admin memakai `127.0.0.1`. LDPlayer memakai `127.0.0.1` setelah ADB reverse.
Pengujian lokal ini tidak memerlukan ngrok. Integrasi pembayaran dan validitas QR tetap berasal
dari backend; Reverb hanya dipakai untuk perubahan ketersediaan kursi.
