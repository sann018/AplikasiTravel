# POTONGAN KODE PROGRAM IMPLEMENTASI SISTEM RUSLYTRAVEL

Dokumen ini berisi potongan kode program yang disarankan untuk BAB IV bagian Implementasi Sistem. Potongan dipilih dari implementasi aktual aplikasi RuslyTravel per 29 Juli 2026 dan diprioritaskan pada aturan bisnis, keamanan, integritas transaksi, serta bagian yang berpotensi ditanyakan saat sidang.

> **Petunjuk penggunaan:** Gunakan informasi **file**, **method**, dan **rentang baris** untuk membuka kode asli di Visual Studio Code. Ambil screenshot dari kode asli agar nomor baris tetap terlihat. Potongan dalam dokumen ini diringkas untuk kebutuhan laporan; pemenggalan relasi, respons, dan format baris tidak mengubah aturan bisnis yang dijelaskan. Tanda komentar `// ...` menunjukkan bagian yang sengaja tidak ditampilkan.

## A. Hasil Analisis Kesesuaian Naskah

| No | Bagian Implementasi | Hasil Analisis Project | Tindakan pada Naskah |
|---:|---|---|---|
| 1 | Akses Tamu | Sesuai. Jadwal tanpa kursi disaring dan Tamu diarahkan masuk sebelum memesan. | Dapat dipertahankan. |
| 2 | Registrasi dan OTP | Sesuai. Backend memvalidasi Gmail, nomor HP 11–12 digit, kata sandi minimal 6 karakter, OTP enam digit, masa berlaku 15 menit, dan token Sanctum. | Dapat dipertahankan. |
| 3 | Penguncian Kursi | Sesuai. Menggunakan transaksi database, `lockForUpdate()`, kepemilikan kunci, dan batas kedaluwarsa. | Wajib ditampilkan. |
| 4 | Konfirmasi Pemesanan | Sesuai. Harga dihitung backend berdasarkan harga rute dikalikan jumlah kursi. Barang bawaan tidak menambah biaya. | Wajib ditampilkan. |
| 5 | Pembayaran Digital | Sesuai. Transaksi Pakasir dibuat melalui API dan webhook diverifikasi kembali melalui Transaction Detail. | Wajib ditampilkan. |
| 6 | Pembayaran Tunai | Sesuai. Pakasir tidak dipanggil, biaya provider nol, dan tiket diterbitkan setelah konfirmasi Pengemudi/Admin. | Potongan pendukung. |
| 7 | Tiket Elektronik | Sesuai. Satu `detail_pemesanan` memiliki satu tiket dan token QR UUID. | Wajib ditampilkan. |
| 8 | Manifes dan QR Pengemudi | Sesuai. Kepemilikan jadwal diperiksa dan pemindaian berulang ditolak. | Pemindaian QR wajib ditampilkan. |
| 9 | Biaya Operasional | Sesuai. Foto bukti/nota wajib diunggah, kategori berasal dari dropdown yang dikelola Admin, dan pencatatan langsung masuk rekap tanpa proses persetujuan/penolakan. | Dapat dipertahankan dengan penegasan bahwa bukti wajib. |
| 10 | Konflik Jadwal | **Naskah lama tidak sesuai.** Validasi tidak lagi berdasarkan tanggal yang sama atau `whereDate()`. Sistem memakai interval perjalanan, posisi kota, jeda dua jam, dan database lock. | Narasi dan fokus kode Gambar 4.15 wajib diperbaiki. |
| 11 | Dasbor Admin | Sesuai. Pendapatan memakai `nominal_transaksi` dan biaya provider tidak dianggap sebagai pendapatan. | Wajib ditampilkan. |
| 12 | Laporan dan Ekspor | Sesuai. Query yang sama dipakai untuk tampilan dan ekspor CSV/XLS SpreadsheetML. | Wajib ditampilkan. |
| 13 | Pengaturan Sistem | Sesuai. Implementasi menggunakan `SettingService` dan model `SystemSetting` pada tabel `pengaturan_sistem`. | Potongan pendukung. |

## B. Daftar Potongan Kode Utama

Delapan potongan pada bagian ini merupakan pilihan utama untuk dimasukkan ke BAB IV.

---

## PK-01 — Penguncian Kursi secara Atomik

**Modul:** Penumpang  
**Subbagian:** Pemilihan Jadwal dan Penguncian Kursi  
**Prioritas:** **Wajib**  
**Judul gambar kode yang disarankan:** *Potongan Kode Penguncian Kursi secara Atomik*  
**File:** `backend/app/Services/BookingService.php`  
**Method:** `lockSeatsForForm()` dan `ensureSeatsCanBeUsedByUser()`  
**Rentang kode utama:** baris 42–80 dan 1490–1519  
**Alasan wajib ditampilkan:** Membuktikan bahwa dua Penumpang tidak dapat memperoleh kursi yang sama pada waktu bersamaan.

```php
public function lockSeatsForForm(User $user, array $payload): array
{
    $scheduleId = (int) $payload['jadwal_id'];
    $seatNumbers = $this->normalizeSeatNumbers($payload['seat_numbers'] ?? []);
    $this->ensureSeatSelectionValid($seatNumbers);
    $this->releaseExpiredResources($scheduleId);

    return DB::transaction(function () use ($user, $scheduleId, $seatNumbers) {
        $schedule = Jadwal::query()
            ->lockForUpdate()
            ->findOrFail($scheduleId);

        $this->scheduleLifecycleService->closeSingleIfPastBookingWindow($schedule);
        $this->ensureScheduleOpen($schedule);

        $selectedSeats = $this->loadSeatRowsForUpdate(
            $schedule->id,
            $seatNumbers,
        );
        $this->ensureSeatRowsMatchSelection($selectedSeats, $seatNumbers);
        $this->ensureSeatsCanBeUsedByUser($selectedSeats, $user->id);

        $now = now();
        $formDeadline = $this->resolveFormLockDeadline($now);

        foreach ($selectedSeats as $seat) {
            $seat->update([
                'status_kursi' => 'terkunci',
                'dikunci_oleh' => $user->id,
                'locked_at' => $now,
                'lock_expires_at' => $formDeadline,
                'updated_at' => $now,
            ]);
        }

        return [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => $seatNumbers->all(),
            'lock_expires_at' => $formDeadline->toIso8601String(),
        ];
    });
}
```

Potongan pemeriksaan status dan kepemilikan kunci:

```php
private function ensureSeatsCanBeUsedByUser(
    Collection $selectedSeats,
    int $userId,
): void {
    foreach ($selectedSeats as $seat) {
        if ($seat->status_kursi === 'dipesan') {
            throw ValidationException::withMessages([
                'seat_numbers' => "Kursi {$seat->nomor_kursi} tidak tersedia.",
            ]);
        }

        if (
            $seat->status_kursi === 'terkunci'
            && (int) $seat->dikunci_oleh !== $userId
        ) {
            throw ValidationException::withMessages([
                'seat_numbers' =>
                    "Kursi {$seat->nomor_kursi} sedang dikunci pengguna lain.",
            ]);
        }

        $hasBooking = DetailPemesanan::query()
            ->where('kursi_jadwal_id', $seat->id)
            ->exists();

        if ($hasBooking) {
            throw ValidationException::withMessages([
                'seat_numbers' =>
                    "Kursi {$seat->nomor_kursi} sudah dipesan dalam pemesanan lain.",
            ]);
        }
    }
}
```

**Keterangan yang ditampilkan setelah potongan kode:**

1. `DB::transaction()` memastikan seluruh proses berhasil atau dibatalkan bersama-sama.
2. `lockForUpdate()` mengunci baris jadwal dan kursi selama transaksi berlangsung.
3. `dikunci_oleh` menyimpan identitas pemilik kunci.
4. `lock_expires_at` menentukan batas waktu pengisian form.
5. Kursi yang telah dipesan atau dikunci pengguna lain akan ditolak oleh backend.

---

## PK-02 — Pembuatan Pemesanan dan Perhitungan Harga di Backend

**Modul:** Penumpang  
**Subbagian:** Konfirmasi Pemesanan  
**Prioritas:** **Wajib**  
**Judul gambar kode yang disarankan:** *Potongan Kode Pembuatan Draft Pemesanan dan Perhitungan Harga*  
**File:** `backend/app/Services/BookingService.php` dan `backend/app/Services/BookingCalculator.php`  
**Method:** `createDraft()` dan `calculate()`  
**Rentang kode utama:** `BookingService.php` baris 135–201; `BookingCalculator.php` baris 9–25  
**Alasan wajib ditampilkan:** Membuktikan bahwa total harga tidak berasal dari nilai yang dikirim aplikasi mobile.

```php
$booking = DB::transaction(function () use (
    $user,
    $payload,
    $scheduleId,
    $seatNumbers,
    $passengers,
) {
    $schedule = Jadwal::query()
        ->with(['rute', 'kendaraan'])
        ->lockForUpdate()
        ->findOrFail($scheduleId);

    $selectedSeats = $this->loadSeatRowsForUpdate(
        $schedule->id,
        $seatNumbers,
    );
    $this->ensureSeatRowsMatchSelection($selectedSeats, $seatNumbers);
    $this->ensureSeatsCanBeUsedByUser($selectedSeats, $user->id);
    $this->ensureActiveFormLocksOwnedByUser(
        $selectedSeats,
        $user->id,
        now(),
    );

    $totals = $this->calculator->calculate(
        $schedule,
        $seatNumbers->count(),
    );

    $booking = Pemesanan::query()->create([
        'pengguna_id' => $user->id,
        'jadwal_id' => $schedule->id,
        'kode_booking' => $this->generateBookingCode(),
        'jumlah_kursi' => $seatNumbers->count(),
        'batas_pembayaran' => $this->resolvePaymentLockDeadline(
            now(),
            $schedule,
        ),
        'lintang_jemput' => data_get($payload, 'pickup.latitude'),
        'bujur_jemput' => data_get($payload, 'pickup.longitude'),
        'alamat_jemput' => data_get($payload, 'pickup.address'),
        'lintang_turun' => data_get($payload, 'dropoff.latitude'),
        'bujur_turun' => data_get($payload, 'dropoff.longitude'),
        'alamat_turun' => data_get($payload, 'dropoff.address'),
        'subtotal_tiket' => $totals['subtotal_tiket'],
        'total_harga' => $totals['total_harga'],
        'status_pemesanan' => 'menunggu_pembayaran',
    ]);

    foreach ($selectedSeats as $seat) {
        $passenger = $passengers->firstWhere(
            'seat_number',
            $seat->nomor_kursi,
        );

        DetailPemesanan::query()->create([
            'pemesanan_id' => $booking->id,
            'kursi_jadwal_id' => $seat->id,
            'nomor_kursi' => $seat->nomor_kursi,
            'nama_penumpang' => data_get($passenger, 'nama_penumpang'),
            'no_hp_penumpang' => data_get($passenger, 'no_hp_penumpang'),
            'status_kehadiran' => 'menunggu',
        ]);
    }

    return $booking;
});
```

Perhitungan harga pada backend:

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

Pencatatan barang bawaan tanpa biaya tambahan:

```php
ItemBagasi::query()->create([
    'pemesanan_id' => $booking->id,
    'deskripsi_item' => (string) $item['deskripsi_item'],
    'jumlah_item' => max(1, (int) data_get($item, 'jumlah_item', 1)),
    'foto_url' => $fotoUrls[0] ?? null,
    'foto_urls' => $fotoUrls,
    // Barang bawaan dicatat untuk operasional tanpa biaya otomatis.
    'biaya_item' => 0,
]);
```

**Keterangan yang ditampilkan setelah potongan kode:**

1. Kepemilikan dan masa berlaku kunci diperiksa ulang sebelum pemesanan dibuat.
2. Kode booking dibuat oleh backend.
3. Lokasi jemput dan turun disimpan pada pemesanan.
4. Satu `detail_pemesanan` dibuat untuk setiap kursi.
5. Harga tiket diambil dari rute dan dikalikan jumlah kursi.
6. Barang bawaan dicatat untuk manifes dan tidak mengubah total harga.

---

## PK-03 — Pembuatan dan Verifikasi Pembayaran Digital Pakasir

**Modul:** Penumpang  
**Subbagian:** Pembayaran Digital  
**Prioritas:** **Wajib**  
**Judul gambar kode yang disarankan:** *Potongan Kode Integrasi dan Verifikasi Pembayaran Pakasir*  
**File:** `backend/app/Services/PakasirService.php`, `backend/app/Services/BookingService.php`, dan `backend/app/Http/Controllers/Api/PaymentGatewayController.php`  
**Method:** `createTransaction()`, `transactionDetail()`, `activatePakasirPayment()`, dan `handlePakasirWebhook()`  
**Rentang kode utama:** `PakasirService.php` baris 89–149; `BookingService.php` baris 738–760 dan 791–866  
**Alasan wajib ditampilkan:** Membuktikan bahwa transaksi digital dibuat melalui provider dan webhook tidak langsung dipercaya.

Pembuatan dan pengecekan transaksi melalui API Pakasir:

```php
public function createTransaction(
    string $method,
    string $orderId,
    int $amount,
): array {
    if (! $this->canCreateTransaction($method)) {
        throw new PakasirException(
            "Metode pembayaran Pakasir {$method} tidak didukung.",
        );
    }

    $response = $this->post("transactioncreate/{$method}", [
        'project' => $this->project(),
        'order_id' => $orderId,
        'amount' => $this->normalizeAmount($amount),
        'api_key' => $this->apiKey(),
    ]);

    $payment = $response['payment'] ?? null;
    if (! is_array($payment)) {
        throw new PakasirException(
            'Response Pakasir tidak mengandung data pembayaran.',
        );
    }

    return $payment;
}

public function transactionDetail(string $orderId, int $amount): array
{
    $response = $this->get('transactiondetail', [
        'project' => $this->project(),
        'order_id' => $orderId,
        'amount' => $this->normalizeAmount($amount),
        'api_key' => $this->apiKey(),
    ]);

    $transaction = $response['transaction'] ?? null;
    if (! is_array($transaction)) {
        throw new PakasirException(
            'Response detail transaksi Pakasir tidak valid.',
        );
    }

    return $transaction;
}
```

Penyimpanan nominal, biaya provider, dan total pelanggan:

```php
$paymentPayload = $this->pakasirService->createTransaction(
    $paymentMethod,
    $gatewayOrderId,
    $nominalTransaction,
);

$this->ensureGatewayCreationMatches(
    $paymentMethod,
    $gatewayOrderId,
    $nominalTransaction,
    $paymentPayload,
);

$providerAmount = $this->providerRupiah($paymentPayload, 'amount', false);
$providerFee = $this->providerRupiah($paymentPayload, 'fee', true);
$this->providerRupiah($paymentPayload, 'total_payment', false);
$customerTotal = $providerAmount + $providerFee;

$payment->update([
    'order_id_gateway' => data_get(
        $paymentPayload,
        'order_id',
        $gatewayOrderId,
    ),
    'nominal_transaksi' => $providerAmount,
    'biaya_admin_provider' => $providerFee,
    'total_dibayar_penumpang' => $customerTotal,
    'jumlah_bayar' => $customerTotal,
    'status_bayar' => 'menunggu',
]);
```

Verifikasi ulang webhook melalui Transaction Detail:

```php
$bookingAmount = $booking->pembayaran?->nominal_transaksi
    ?? $this->rupiah($booking->total_harga);

if ($bookingAmount !== (int) $payload['amount']) {
    throw ValidationException::withMessages([
        'amount' =>
            'Nominal pembayaran dari Pakasir tidak sesuai dengan total pemesanan.',
    ]);
}

$transaction = $this->pakasirService->transactionDetail(
    $this->paymentOrderId($booking),
    $bookingAmount,
);
$this->ensureGatewayTransactionMatches($booking, $transaction);

$webhookStatus = (string) $payload['status'];
$transactionStatus = (string) data_get($transaction, 'status');

// Tiket dibuat jika webhook dan Transaction Detail sama-sama completed.
if (
    $this->pakasirService->isCompletedStatus($webhookStatus)
    && $this->pakasirService->isCompletedStatus($transactionStatus)
) {
    return $this->syncPakasirPayment(
        $booking,
        $transaction,
        $payload,
    );
}
```

**Keterangan yang ditampilkan setelah potongan kode:**

1. `order_id`, nominal, metode, dan API key dikirim ke Pakasir melalui service backend.
2. Nominal disimpan sebagai integer rupiah.
3. `nominal_transaksi`, `biaya_admin_provider`, dan `total_dibayar_penumpang` disimpan terpisah.
4. Webhook dibandingkan dengan transaksi lokal.
5. Backend memanggil Transaction Detail untuk memeriksa status dan nominal terbaru.
6. Tiket hanya diterbitkan jika status webhook dan Transaction Detail sama-sama selesai.

---

## PK-04 — Penerbitan Tiket Elektronik dan Token QR Unik

**Modul:** Penumpang  
**Subbagian:** Tiket Elektronik  
**Prioritas:** **Wajib**  
**Judul gambar kode yang disarankan:** *Potongan Kode Penerbitan Tiket Elektronik*  
**File utama:** `backend/app/Services/BookingService.php`  
**Method utama:** `finalizePaidBooking()`  
**Rentang kode utama:** baris 1167–1189  
**Alasan wajib ditampilkan:** Membuktikan bahwa tiket diterbitkan setelah pembayaran berhasil dan setiap penumpang pada setiap kursi memperoleh token QR yang berbeda.

### A. Dari Mana Kode Unik Tiket Berasal?

Tiket elektronik tidak langsung dibuat dalam bentuk gambar QR pada backend. Backend terlebih dahulu membuat dan menyimpan dua nilai berikut.

| Nilai | Sumber pembentukan | Fungsi |
|---|---|---|
| `kode_qr` | Gabungan teks `TRAVS`, `kode_booking`, dan `nomor_kursi` | Kode identitas tiket yang masih dapat dibaca manusia dan dicari oleh admin. |
| `token_unik_qr` | `Str::uuid()->toString()` dari Laravel | Nilai unik yang dimasukkan ke dalam gambar QR dan digunakan backend saat verifikasi tiket. |

Rumus pembentukan `kode_qr` pada program adalah:

```text
TRAVS-{kode_booking}-{nomor_kursi}
```

Sementara itu, nilai yang benar-benar menjadi isi QR adalah `token_unik_qr`. Token tersebut berbentuk UUID, misalnya:

```text
550e8400-e29b-41d4-a716-446655440000
```

Contoh di atas hanya menunjukkan bentuk UUID, bukan token tiket yang digunakan pada database aplikasi.

Perbedaan keduanya perlu dipahami:

1. `kode_qr` menunjukkan keterkaitan tiket dengan kode pemesanan dan nomor kursi.
2. `token_unik_qr` menjadi kunci verifikasi yang sulit ditebak.
3. Backend mencari tiket berdasarkan `token_unik_qr`, bukan berdasarkan gambar QR.
4. Gambar QR hanya merupakan representasi visual dari token tersebut.

### B. Sumber Pemicu Penerbitan Tiket

Method `finalizePaidBooking()` dipanggil setelah pembayaran dinyatakan berhasil melalui salah satu jalur berikut:

1. pembayaran digital Pakasir telah diverifikasi dan disinkronkan;
2. pembayaran tunai telah dikonfirmasi oleh admin atau pengemudi; atau
3. simulasi pembayaran lokal berhasil pada lingkungan pengembangan.

Untuk pembayaran digital yang diterima ketika pemesanan sudah dibatalkan atau data penumpangnya sudah dilepas, sistem tidak menerbitkan tiket. Pemeriksaan ini mencegah pembayaran terlambat menghasilkan tiket yang tidak lagi memiliki kursi.

Potongan pemanggilan setelah pembayaran digital berhasil:

```php
$lockedBooking->update([
    'status_pemesanan' => 'terbayar',
]);

$this->finalizePaidBooking($lockedBooking);
```

Potongan pemanggilan setelah pembayaran tunai dikonfirmasi:

```php
$lockedBooking->update([
    'status_pemesanan' => 'terbayar',
    'batas_pembayaran' => null,
]);

$this->finalizePaidBooking($lockedBooking);
```

**Sumber kode:**  
`backend/app/Services/BookingService.php`, method `confirmCashPaymentForActor()` dan `syncPakasirPayment()`.

### C. Pembuatan Satu Tiket untuk Setiap Penumpang

```php
/** Mengunci kursi dan menerbitkan satu tiket untuk setiap penumpang. */
private function finalizePaidBooking(Pemesanan $booking): void
{
    foreach ($booking->detailPemesanan as $detail) {
        $detail->kursiJadwal?->update([
            'status_kursi' => 'dipesan',
            'dikunci_oleh' => null,
            'locked_at' => null,
            'lock_expires_at' => null,
            'updated_at' => now(),
        ]);

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
    }

    $this->createPaymentSuccessNotification($booking);
}
```

**Penjelasan potongan kode:**

1. `foreach ($booking->detailPemesanan as $detail)` memproses seluruh penumpang pada pemesanan.
2. Setiap baris `detail_pemesanan` mewakili satu penumpang pada satu kursi.
3. Status kursi diubah dari `terkunci` menjadi `dipesan`.
4. `updateOrCreate()` mencari tiket berdasarkan `detail_pemesanan_id`. Apabila tiket sudah ada, baris yang sama diperbarui dan tiket baru tidak dibuat.
5. `kode_qr` dibentuk dari kode pemesanan dan nomor kursi.

   Kode pemesanan terlebih dahulu dibuat oleh method `generateBookingCode()`:

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

   Bentuk kode pemesanan yang dihasilkan:

   ```text
   TRAVS-{tanggal YYYYMMDD}-{lima karakter acak}
   ```

   Contoh:

   ```text
   TRAVS-20260729-K9P2Q
   ```

   Setelah pembayaran berhasil, kode tersebut digabungkan dengan nomor kursi:

   ```php
   'kode_qr' => 'TRAVS-'.$booking->kode_booking.'-'.$detail->nomor_kursi,
   ```

   Jika `kode_booking` adalah `TRAVS-20260729-K9P2Q` dan nomor kursinya `2`, bentuk `kode_qr` yang tersimpan berdasarkan kode aktif saat ini adalah:

   ```text
   TRAVS-TRAVS-20260729-K9P2Q-2
   ```

   Susunan informasinya adalah:

   ```text
   TRAVS- | TRAVS-20260729-K9P2Q | -2
   awalan   kode pemesanan          nomor kursi
   ```

   Perlu dicatat bahwa awalan `TRAVS-` muncul dua kali karena `kode_booking` sudah mempunyai awalan tersebut, kemudian method penerbitan tiket menambahkannya kembali. Hal ini tidak mengganggu proses pemindaian karena QR diverifikasi menggunakan `token_unik_qr`, tetapi bentuk kode yang lebih ringkas pada pengembangan berikutnya dapat menggunakan:

   ```php
   'kode_qr' => $booking->kode_booking.'-'.$detail->nomor_kursi,
   ```

   Dengan bentuk hasil:

   ```text
   TRAVS-20260729-K9P2Q-2
   ```

   **Sumber kode:** `backend/app/Services/BookingService.php`, method `generateBookingCode()` baris 1794–1805 dan `finalizePaidBooking()` baris 1167–1189.

6. `Str::uuid()->toString()` membuat token unik untuk tiket yang belum memiliki token.
7. Operator `?:` mempertahankan token dan waktu penerbitan lama apabila method dipanggil ulang.
8. Setelah seluruh tiket selesai diproses, sistem membuat notifikasi pembayaran berhasil.

Oleh karena itu, apabila satu pemesanan berisi tiga kursi, tabel `detail_pemesanan` mempunyai tiga baris penumpang dan method ini menghasilkan tiga tiket dengan tiga UUID yang berbeda.

### D. Jaminan Relasi dan Keunikan pada Database

**File:** `backend/database/migrations/2026_04_22_000200_create_transaksi_tables.php`  
**Rentang kode:** baris 76–86

```php
Schema::create('tiket_elektronik', function (Blueprint $table) {
    $table->id();
    $table->foreignId('detail_pemesanan_id')
        ->constrained('detail_pemesanan')
        ->cascadeOnDelete();
    $table->string('kode_qr');
    $table->string('token_unik_qr', 100)->unique();
    $table->boolean('sudah_dipindai')->default(false);
    $table->dateTime('dipindai_pada')->nullable();
    $table->dateTime('diterbitkan_pada')->nullable();
    $table->timestamps();

    $table->unique('detail_pemesanan_id');
});
```

Database memberikan dua pengamanan:

1. `token_unik_qr` memiliki indeks `unique`, sehingga dua tiket tidak dapat menyimpan token yang sama.
2. `detail_pemesanan_id` juga memiliki indeks `unique`, sehingga satu penumpang pada satu kursi hanya dapat mempunyai satu tiket.

Relasi modelnya adalah:

```php
// Model DetailPemesanan
public function tiketElektronik(): HasOne
{
    return $this->hasOne(TiketElektronik::class, 'detail_pemesanan_id');
}

// Model TiketElektronik
public function detailPemesanan(): BelongsTo
{
    return $this->belongsTo(DetailPemesanan::class, 'detail_pemesanan_id');
}
```

**Sumber kode:**  
`backend/app/Models/DetailPemesanan.php` dan `backend/app/Models/TiketElektronik.php`.

### E. Pengiriman Data Tiket dari Backend ke Flutter

Setelah tiket tersimpan, endpoint detail pemesanan mengirimkan kode, token, dan status pemindaian untuk setiap penumpang.

**File:** `backend/app/Http/Controllers/Api/BookingController.php`  
**Rentang kode:** baris 504–516

```php
'passengers' => $booking->detailPemesanan->map(fn ($detail) => [
    'seat_number' => $detail->nomor_kursi,
    'nama_penumpang' => $detail->nama_penumpang,
    'no_hp_penumpang' => $detail->no_hp_penumpang,
    'status_kehadiran' => $detail->status_kehadiran,
    'ticket' => $detail->tiketElektronik
        ? [
            'kode_qr' => $detail->tiketElektronik->kode_qr,
            'token' => $detail->tiketElektronik->token_unik_qr,
            'sudah_dipindai' => $detail->tiketElektronik->sudah_dipindai,
        ]
        : null,
])->all(),
```

Struktur tersebut membuat tiket tetap melekat pada pemilik dan kursinya. Flutter tidak membuat token baru karena token harus selalu berasal dari backend sebagai sumber data utama.

### F. Konversi Token Menjadi Gambar QR pada Flutter

Flutter membaca `kode_qr` dan `token` dari response API melalui model `TravSBookingTicketInfo`.

```dart
factory TravSBookingTicketInfo.fromJson(Map<String, dynamic> json) {
  return TravSBookingTicketInfo(
    kodeQr: json['kode_qr'] as String? ?? '-',
    token: json['token'] as String? ?? '',
    sudahDipindai: json['sudah_dipindai'] as bool? ?? false,
  );
}
```

**Sumber kode:**  
`frontend/lib/core/models/kontrak_pemesanan/model_perjalanan_pemesanan.dart`, baris 87–104.

Pada card tiket, aplikasi mengutamakan token UUID sebagai data QR. `kode_qr` hanya digunakan sebagai nilai cadangan apabila token dari API kosong.

```dart
final ticket = passenger.ticket!;
final qrData = ticket.token.isNotEmpty ? ticket.token : ticket.kodeQr;
```

**Sumber kode:**  
`frontend/lib/features/ticket/bagian_detail_tiket/kartu_tiket.dart`, baris 53–57.

Nilai tersebut kemudian diubah menjadi gambar QR oleh package `qr_flutter`.

```dart
QrImageView(
  data: data,
  size: 220,
  backgroundColor: Colors.white,
  eyeStyle: const QrEyeStyle(
    eyeShape: QrEyeShape.square,
    color: TravSTheme.navy,
  ),
  dataModuleStyle: const QrDataModuleStyle(
    dataModuleShape: QrDataModuleShape.square,
    color: TravSTheme.navy,
  ),
)
```

**Sumber kode:**  
`frontend/lib/features/ticket/bagian_detail_tiket/qr_dan_perjalanan_tiket.dart`, baris 3–29.

Dengan demikian, backend bertugas membuat dan menyimpan identitas unik tiket, sedangkan Flutter hanya menampilkan token tersebut dalam bentuk QR. Gambar QR tidak disimpan sebagai berkas di tabel `tiket_elektronik`.

### G. Alur Lengkap Tiket Elektronik

```text
Pembayaran digital terverifikasi / pembayaran tunai dikonfirmasi
                              ↓
Status pemesanan diubah menjadi terbayar
                              ↓
BookingService menjalankan finalizePaidBooking()
                              ↓
Setiap detail penumpang dan kursi diproses satu per satu
                              ↓
Backend membentuk kode_qr dan token_unik_qr berupa UUID
                              ↓
Database menjamin satu tiket per detail penumpang
dan satu token tidak dapat dipakai oleh tiket lain
                              ↓
BookingController mengirim kode dan token melalui API
                              ↓
Flutter mengubah token menjadi gambar QR dengan qr_flutter
                              ↓
Pengemudi memindai QR
                              ↓
Backend mencari tiket berdasarkan token_unik_qr
                              ↓
Jika valid, tiket ditandai sudah dipindai
dan status kehadiran penumpang menjadi hadir
```

### H. Keterkaitan dengan Verifikasi Tiket

Token hasil pemindaian dikirim kembali ke endpoint pengemudi. Backend mencari tiket menggunakan:

```php
->where('token_unik_qr', $payload['token'])
```

Setelah kepemilikan jadwal, status pemesanan, dan status tiket dinyatakan valid, backend memperbarui:

```php
$ticket->update([
    'sudah_dipindai' => true,
    'dipindai_pada' => $now,
]);

$detail->update([
    'status_kehadiran' => 'hadir',
]);
```

Apabila QR yang sama dipindai kembali, nilai `sudah_dipindai` menyebabkan permintaan ditolak. Penjelasan pemeriksaan ini dilanjutkan pada **PK-05 — Verifikasi QR dan Pencegahan Pemindaian Ganda**.

---

## PK-05 — Verifikasi QR dan Pencegahan Pemindaian Ganda

**Modul:** Pengemudi  
**Subbagian:** Pemindaian QR Code dan Operasional Perjalanan  
**Prioritas:** **Wajib**  
**Judul gambar kode yang disarankan:** *Potongan Kode Verifikasi dan Penguncian Tiket QR*  
**File:** `backend/app/Http/Controllers/Api/DriverController.php`  
**Method:** `scanTicket()`  
**Rentang kode utama:** baris 106–181  
**Alasan wajib ditampilkan:** Membuktikan bahwa tiket palsu, tiket jadwal lain, tiket belum dibayar, dan tiket yang sudah dipakai akan ditolak.

```php
public function scanTicket(Request $request): JsonResponse
{
    $payload = $request->validate([
        'token' => ['required', 'string', 'max:160'],
    ]);

    $pengemudi = $this->activeDriverFor($request);

    return DB::transaction(function () use ($payload, $pengemudi) {
        $ticket = TiketElektronik::query()
            ->lockForUpdate()
            ->with([
                'detailPemesanan.pemesanan.jadwal.rute',
                'detailPemesanan.pemesanan.jadwal.pengemudi',
            ])
            ->where('token_unik_qr', $payload['token'])
            ->first();

        if (! $ticket || ! $ticket->detailPemesanan) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket tidak ditemukan.',
            ], 404);
        }

        $detail = $ticket->detailPemesanan;
        $booking = $detail->pemesanan;
        $jadwal = $booking?->jadwal;

        if (
            ! $booking
            || ! $jadwal
            || (int) $jadwal->pengemudi_id !== (int) $pengemudi->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Tiket ini bukan bagian dari manifes pengemudi aktif.',
            ], 403);
        }

        if ($booking->status_pemesanan !== 'terbayar') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Tiket belum aktif karena pemesanan belum berstatus terbayar.',
            ], 422);
        }

        if ($ticket->sudah_dipindai) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket sudah pernah dipindai.',
            ], 409);
        }

        $now = now();
        $ticket->update([
            'sudah_dipindai' => true,
            'dipindai_pada' => $now,
        ]);
        $detail->update([
            'status_kehadiran' => 'hadir',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tiket valid dan berhasil dipindai.',
        ]);
    });
}
```

**Keterangan yang ditampilkan setelah potongan kode:**

1. Baris tiket dikunci agar dua pemindaian bersamaan tidak sama-sama berhasil.
2. Token dicari pada kolom `token_unik_qr`.
3. `pengemudi_id` tiket harus sama dengan Pengemudi yang sedang aktif.
4. Pemesanan harus berstatus terbayar.
5. `sudah_dipindai` mencegah penggunaan QR Code lebih dari satu kali.
6. Pemindaian berhasil mengubah `status_kehadiran` menjadi `hadir`.

---

## PK-06 — Validasi Konflik Pengemudi dan Kendaraan

**Modul:** Admin  
**Subbagian:** Manajemen Jadwal  
**Prioritas:** **Wajib**  
**Judul gambar kode yang disarankan:** *Potongan Kode Validasi Interval dan Sumber Daya Jadwal*  
**File:** `backend/app/Http/Controllers/Web/Admin/JadwalController.php` dan `backend/app/Services/DriverAvailabilityService.php`  
**Method:** `store()`, `ensureScheduleResourcesAvailable()`, `conflictingResourceSchedule()`, dan `syncSeatMap()`  
**Rentang kode utama:** `JadwalController.php` baris 255–274, 409–483, dan 580–617; `DriverAvailabilityService.php` baris 10–31, 187–220, dan 244–323  
**Alasan wajib ditampilkan:** Membuktikan bahwa Pengemudi/kendaraan tidak dapat dipakai pada interval bertabrakan dan tidak dapat muncul di kota yang salah.

Transaksi pembuatan jadwal dan database lock:

```php
public function store(Request $request): RedirectResponse
{
    $payload = $this->validatedPayload($request);
    $closedSeatNumbers = $payload['kursi_ditutup'] ?? [];
    unset($payload['kursi_ditutup']);

    DB::transaction(function () use ($payload, $closedSeatNumbers) {
        // Baris kendaraan dan pengemudi dikunci untuk mencegah
        // dua admin menyimpan sumber daya yang sama secara bersamaan.
        $this->lockScheduleResources($payload);
        $this->ensureScheduleResourcesAvailable($payload);

        $jadwal = Jadwal::query()->create($payload);
        $this->syncSeatMap(
            $jadwal->fresh('kendaraan'),
            $closedSeatNumbers,
        );
        $this->notifyAssignedDriver($jadwal);
    });

    return redirect()
        ->route('admin.jadwal.index')
        ->with(
            'status',
            'Jadwal perjalanan berhasil dibuat dan kursi otomatis digenerate.',
        );
}
```

Pemeriksaan benturan interval:

```php
private const MINIMUM_TURNAROUND_MINUTES = 120;
private const LOCATION_CONTINUITY_WINDOW_MINUTES = 1440;

public function activeScheduleStatuses(): array
{
    return ['draft', 'dibuka', 'ditutup', 'berangkat'];
}

private function conflictingResourceSchedule(
    string $resourceColumn,
    int $resourceId,
    CarbonInterface $departureTime,
    CarbonInterface $arrivalTime,
    ?Jadwal $currentJadwal,
): ?Jadwal {
    // Dua interval bertabrakan jika jadwal lama mulai sebelum jadwal baru
    // selesai dan jadwal lama selesai setelah jadwal baru dimulai.
    return Jadwal::query()
        ->with('rute')
        ->where($resourceColumn, $resourceId)
        ->whereIn('status', $this->activeScheduleStatuses())
        ->where('waktu_berangkat', '<', $arrivalTime)
        ->where(function ($query) use ($departureTime) {
            $query
                ->where('estimasi_tiba', '>', $departureTime)
                ->orWhereNull('estimasi_tiba');
        })
        ->when(
            $currentJadwal,
            fn ($query) => $query->whereKeyNot($currentJadwal->id),
        )
        ->orderBy('waktu_berangkat')
        ->get()
        ->first(function (Jadwal $schedule) use (
            $departureTime,
            $arrivalTime,
        ) {
            $scheduleArrival = $this->scheduleArrival($schedule);

            return $schedule->waktu_berangkat !== null
                && $scheduleArrival !== null
                && $schedule->waktu_berangkat->lessThan($arrivalTime)
                && $scheduleArrival->greaterThan($departureTime);
        });
}
```

Pemeriksaan posisi kota dan jeda perjalanan:

```php
$previousDestination = trim(
    (string) $previousSchedule->rute?->kota_tujuan,
);
$candidateOrigin = trim((string) $candidateRoute->kota_asal);

if (! $this->sameLocation($previousDestination, $candidateOrigin)) {
    return "{$resourceLabel} diperkirakan masih berada di "
        ."{$previousDestination} dari perjalanan sebelumnya. "
        ."Jadwal baru harus berangkat dari {$previousDestination}, "
        ."bukan {$candidateOrigin}.";
}

if ($gapAfterPrevious < self::MINIMUM_TURNAROUND_MINUTES) {
    return "{$resourceLabel} membutuhkan jeda minimal 2 jam "
        ."setelah tiba di {$previousDestination} sebelum ditugaskan kembali.";
}

$candidateDestination = trim((string) $candidateRoute->kota_tujuan);
$nextOrigin = trim((string) $nextSchedule->rute?->kota_asal);

if (! $this->sameLocation($candidateDestination, $nextOrigin)) {
    return "Setelah jadwal baru, {$resourceLabel} akan berada di "
        ."{$candidateDestination}, sedangkan jadwal berikutnya "
        ."berangkat dari {$nextOrigin}.";
}
```

Pembuatan kursi berdasarkan kapasitas kendaraan:

```php
private function syncSeatMap(
    Jadwal $jadwal,
    array $closedSeatNumbers = [],
): void {
    $kapasitas = (int) $jadwal->kendaraan->kapasitas_kursi;
    $closedSeats = collect($closedSeatNumbers);

    for ($seatNumber = 1; $seatNumber <= $kapasitas; $seatNumber++) {
        KursiJadwal::query()->firstOrCreate(
            [
                'jadwal_id' => $jadwal->id,
                'nomor_kursi' => $seatNumber,
            ],
            [
                'status_kursi' => $closedSeats->contains($seatNumber)
                    ? 'nonaktif'
                    : 'tersedia',
                'updated_at' => now(),
            ],
        );
    }
}
```

**Keterangan yang ditampilkan setelah potongan kode:**

1. Validasi menggunakan interval `[waktu_berangkat, estimasi_tiba)`, bukan sekadar tanggal yang sama.
2. Pengemudi dan kendaraan diperiksa dengan service yang sama.
3. Posisi kota dibandingkan dengan tujuan perjalanan sebelumnya dan asal perjalanan berikutnya.
4. Jeda operasional minimum adalah dua jam.
5. Kontinuitas kota diterapkan untuk jadwal yang berjarak paling lama 24 jam. Setelah itu sistem menganggap perpindahan di luar jadwal memungkinkan karena tidak ada atribut pelacakan lokasi.
6. Status `berangkat` tetap mengunci sumber daya sampai perjalanan diselesaikan.
7. Baris kendaraan dan Pengemudi dikunci kembali di dalam transaksi.
8. Data `kursi_jadwal` dibentuk sesuai kapasitas kendaraan.

### Perbaikan wajib narasi Gambar 4.15

Gunakan narasi berikut:

> Backend memvalidasi Pengemudi dan kendaraan berdasarkan interval waktu perjalanan, bukan hanya tanggal yang sama. Sistem menolak interval yang bertabrakan, memeriksa kesinambungan posisi kota berdasarkan rute sebelumnya dan berikutnya, serta menerapkan jeda operasional minimal dua jam. Pemeriksaan dilakukan kembali di dalam transaksi setelah baris kendaraan dan Pengemudi dikunci untuk mencegah dua penyimpanan bersamaan. Setelah jadwal berhasil dibuat, sistem membentuk data `kursi_jadwal` sesuai kapasitas kendaraan dan mengirim notifikasi tugas kepada Pengemudi.

---

## PK-07 — Perhitungan Statistik Dasbor Admin

**Modul:** Admin  
**Subbagian:** Dasbor Statistik  
**Prioritas:** **Wajib**  
**Judul gambar kode yang disarankan:** *Potongan Kode Perhitungan Pendapatan dan Rute Terpopuler*  
**File:** `backend/app/Http/Controllers/Web/Admin/DashboardController.php`  
**Method:** `index()`  
**Rentang kode utama:** baris 18–62  
**Alasan wajib ditampilkan:** Menjelaskan sumber angka statistik dan membuktikan bahwa biaya Pakasir tidak dianggap sebagai pendapatan travel.

```php
$today = today();
$weekStartDate = $today->copy()->startOfWeek(Carbon::MONDAY);

// Pendapatan memakai nominal transaksi RuslyTravel, tanpa fee Pakasir.
$pendapatanRaw = Pembayaran::query()
    ->join(
        'pemesanan',
        'pemesanan.id',
        '=',
        'pembayaran.pemesanan_id',
    )
    ->selectRaw(
        'DATE(pembayaran.dibayar_pada) as tanggal, '
        .'SUM(COALESCE(pembayaran.nominal_transaksi, '
        .'pemesanan.total_harga)) as total',
    )
    ->where('pembayaran.status_bayar', 'berhasil')
    ->whereIn('pemesanan.status_pemesanan', ['terbayar', 'selesai'])
    ->whereDate('pembayaran.dibayar_pada', '>=', $weekStartDate)
    ->whereDate('pembayaran.dibayar_pada', '<=', $today)
    ->groupByRaw('DATE(pembayaran.dibayar_pada)')
    ->pluck('total', 'tanggal');

$pendapatanHarian = collect(range(0, 6))->map(
    function (int $offset) use (
        $weekStartDate,
        $today,
        $pendapatanRaw,
    ) {
        $tanggal = $weekStartDate->copy()->addDays($offset);

        return [
            'date' => $tanggal,
            'label' => $tanggal->translatedFormat('D'),
            'total' => (int) (
                $pendapatanRaw[$tanggal->toDateString()] ?? 0
            ),
            'is_future' => $tanggal->isAfter($today),
        ];
    },
);

$pendapatanMingguIni = (int) $pendapatanHarian->sum('total');

$ruteTerpopulerRecord = Pemesanan::query()
    ->join('jadwal', 'jadwal.id', '=', 'pemesanan.jadwal_id')
    ->join('rute', 'rute.id', '=', 'jadwal.rute_id')
    ->where('pemesanan.status_pemesanan', 'selesai')
    ->select(['rute.id', 'rute.kota_asal', 'rute.kota_tujuan'])
    ->selectRaw('COUNT(pemesanan.id) as jumlah_pemesanan')
    ->selectRaw('SUM(pemesanan.jumlah_kursi) as jumlah_penumpang')
    ->groupBy('rute.id', 'rute.kota_asal', 'rute.kota_tujuan')
    ->orderByDesc('jumlah_penumpang')
    ->first();
```

**Keterangan yang ditampilkan setelah potongan kode:**

1. Hanya pembayaran `berhasil` dan pemesanan `terbayar`/`selesai` yang menjadi pendapatan.
2. `nominal_transaksi` adalah pendapatan RuslyTravel tanpa biaya provider.
3. Rekap mingguan menggunakan periode Senin sampai Minggu.
4. Rute terpopuler hanya berasal dari perjalanan selesai.
5. `COUNT(pemesanan.id)` dan `SUM(jumlah_kursi)` dipisahkan karena satu pemesanan dapat berisi beberapa Penumpang.

---

## PK-08 — Filter dan Ekspor Laporan Transaksi

**Modul:** Admin  
**Subbagian:** Laporan dan Ekspor  
**Prioritas:** **Wajib**  
**Judul gambar kode yang disarankan:** *Potongan Kode Filter dan Ekspor Laporan Transaksi*  
**File:** `backend/app/Http/Controllers/Web/Admin/LaporanController.php`  
**Method:** `transactionQuery()`, `exportTransaksi()`, dan `downloadReport()`  
**Rentang kode utama:** baris 187–243, 325–358, dan 370–395  
**Alasan wajib ditampilkan:** Membuktikan bahwa tampilan dan file ekspor memakai filter dan sumber data yang sama.

Query filter transaksi:

```php
private function transactionQuery(Request $request)
{
    $tanggalSelesaiRules = ['nullable', 'date'];
    if ($request->filled('tanggal_mulai')) {
        $tanggalSelesaiRules[] = 'after_or_equal:tanggal_mulai';
    }

    $request->validate([
        'status' => [
            'nullable',
            Rule::in(['menunggu', 'berhasil', 'gagal', 'kedaluwarsa']),
        ],
        'metode_bayar' => [
            'nullable',
            Rule::in(array_keys(PaymentMethodCatalog::adminOptions())),
        ],
        'tanggal_mulai' => ['nullable', 'date'],
        'tanggal_selesai' => $tanggalSelesaiRules,
    ]);

    $query = Pembayaran::query()->with([
        'pemesanan.pengguna',
        'pemesanan.jadwal.rute',
    ]);

    if ($request->filled('status')) {
        $query->where('status_bayar', $request->string('status'));
    }
    if ($request->filled('metode_bayar')) {
        $query->where(
            'metode_bayar',
            strtolower((string) $request->string('metode_bayar')),
        );
    }
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

    return $query;
}
```

Penyusunan data ekspor:

```php
public function exportTransaksi(Request $request)
{
    // Query yang sama digunakan oleh tampilan dan ekspor.
    $transactions = $this->transactionQuery($request)
        ->latest('id')
        ->get();

    $rows = [[
        'No',
        'Kode Transaksi',
        'Kode Pemesanan',
        'Penumpang',
        'Rute',
        'Metode Bayar',
        'Status Bayar',
        'Pendapatan RuslyTravel',
        'Biaya Admin Pakasir',
        'Dibayar Penumpang',
        'Channel',
        'Waktu Dibuat',
        'Waktu Dibayar',
    ]];

    foreach ($transactions as $index => $transaction) {
        $rows[] = [
            (string) ($index + 1),
            $transaction->order_id_gateway ?: '-',
            $transaction->pemesanan?->kode_booking ?: '-',
            $transaction->pemesanan?->pengguna?->nama ?: '-',
            $transaction->pemesanan
                ? $transaction->pemesanan->jadwal->rute->kota_asal
                    .' - '
                    .$transaction->pemesanan->jadwal->rute->kota_tujuan
                : '-',
            PaymentMethodCatalog::label(
                strtolower($transaction->metode_bayar),
            ),
            ucwords(str_replace(
                '_',
                ' ',
                $transaction->status_bayar,
            )),
            (string) (
                $transaction->nominal_transaksi
                ?? (int) $transaction->pemesanan?->total_harga
            ),
            (string) ($transaction->biaya_admin_provider ?? 0),
            $transaction->total_dibayar_penumpang === null
                ? ''
                : (string) $transaction->total_dibayar_penumpang,
            $transaction->paid_channel ?: '-',
            $transaction->created_at->format('Y-m-d H:i:s'),
            $transaction->dibayar_pada?->format('Y-m-d H:i:s') ?: '-',
        ];
    }

    return $this->downloadReport(
        $rows,
        'laporan-transaksi',
        $request,
    );
}
```

Pemilihan format CSV atau XLS:

```php
private function downloadReport(
    array $rows,
    string $baseName,
    Request $request,
    array $meta = [],
) {
    $format = strtolower((string) $request->query('format', 'csv'));
    $format = in_array($format, ['csv', 'xls'], true)
        ? $format
        : 'csv';
    $filename = $baseName.'-'.now()->format('Ymd-His');

    return match ($format) {
        'xls' => $this->downloadExcelXml(
            $rows,
            "{$filename}.xls",
            $baseName,
            $request,
            $meta,
        ),
        default => $this->downloadCsv(
            $rows,
            "{$filename}.csv",
        ),
    };
}
```

**Keterangan yang ditampilkan setelah potongan kode:**

1. Nilai status, metode, dan tanggal divalidasi sebelum digunakan.
2. `transactionQuery()` dipanggil oleh halaman laporan dan fungsi ekspor.
3. Pendapatan, biaya Admin Pakasir, dan total pembayaran ditempatkan pada kolom berbeda.
4. CSV dibuat menggunakan `fputcsv()`.
5. XLS menggunakan SpreadsheetML dengan format uang dan `AutoFilter`.

## C. Potongan Kode Pendukung

Potongan berikut dapat dimasukkan apabila jumlah halaman laporan masih memungkinkan.

---

## PK-09 — Penyaringan Jadwal dan Pembatasan Akses Tamu

**Modul:** Tamu  
**Prioritas:** Opsional  
**File:** `frontend/lib/features/home/bagian_beranda/halaman_informasi.dart` dan `frontend/lib/features/schedule/travs_schedule_detail_page.dart`  
**Bagian:** `_availablePublicSchedules()` dan aksi utama detail jadwal  
**Rentang kode:** `halaman_informasi.dart` baris 419–424; `travs_schedule_detail_page.dart` baris 388–395

```dart
List<TravSSchedule> _availablePublicSchedules(
  List<TravSSchedule> schedules,
) {
  // Kursi nol berarti jadwal tidak dapat dipilih Penumpang atau Tamu.
  return schedules
      .where((schedule) => schedule.sisaKursi > 0)
      .toList(growable: false);
}
```

```dart
onPrimaryAction: () async {
  // Tamu harus masuk sebelum memilih kursi agar penguncian
  // tercatat pada pengguna yang benar.
  if (widget.selectedRole == AppRolePreview.guest ||
      widget.session == null) {
    Navigator.of(context).pop('open_login');
    return;
  }

  // Proses pemilihan kursi Penumpang dilanjutkan di sini.
},
```

**Keterangan:** Jadwal penuh tidak ditampilkan pada rekomendasi publik. Tamu tetap dapat melihat detail dan denah kursi, tetapi harus masuk sebelum memesan.

---

## PK-10 — Registrasi, Verifikasi OTP, Token, dan Middleware Peran

**Modul:** Penumpang  
**Prioritas:** Sangat disarankan  
**File:** `backend/app/Http/Controllers/Api/AuthController.php`, `backend/app/Models/User.php`, dan `backend/app/Http/Middleware/EnsureUserHasRole.php`  
**Method:** `register()`, `verifyEmail()`, `casts()`, dan `handle()`  
**Rentang kode:** `AuthController.php` baris 38–100 dan 129–170

Validasi dan pembuatan akun Penumpang:

```php
$validated = $request->validate([
    'nama' => ['required', 'string', 'max:100'],
    'email' => [
        'required',
        'email',
        'max:100',
        'regex:/^[^@\s]+@gmail\.com$/i',
        Rule::unique('pengguna', 'email')->ignore($pendingUser?->id),
    ],
    'no_hp' => [
        'required',
        'string',
        'regex:/^[0-9]{11,12}$/',
        Rule::unique('pengguna', 'no_hp')->ignore($pendingUser?->id),
    ],
    'password' => [
        'required',
        'string',
        'min:6',
        'max:72',
        'regex:/^(?=.*[A-Za-z])(?=.*(?:[0-9]|[^A-Za-z0-9\s]))\S+$/',
        'confirmed',
    ],
]);

$verificationCode = $this->generateVerificationCode();
$user = User::query()->create([
    'nama' => trim($validated['nama']),
    'email' => $validated['email'],
    'no_hp' => trim($validated['no_hp']),
    'peran' => 'penumpang',
    'status_aktif' => true,
    'password' => $validated['password'],
    // Database menyimpan hash OTP, bukan kode aslinya.
    'otp_code' => Hash::make($verificationCode),
    'otp_expires_at' => now()->addMinutes(15),
]);

Mail::to($user->email)->send(
    new AccountVerificationCodeMail($verificationCode),
);
```

Verifikasi OTP dan penerbitan token Sanctum:

```php
$isExpired = ! $user->otp_expires_at
    || $user->otp_expires_at->isPast();
$isInvalid = ! $user->otp_code
    || ! Hash::check($validated['code'], $user->otp_code);

if ($isExpired || $isInvalid) {
    throw ValidationException::withMessages([
        'code' => ['Kode verifikasi tidak valid atau sudah kedaluwarsa.'],
    ]);
}

$user->forceFill([
    'email_verified_at' => now(),
    'otp_code' => null,
    'otp_expires_at' => null,
])->save();

$token = $user
    ->createToken($validated['device_name'] ?? 'travs-mobile')
    ->plainTextToken;
```

Hash kata sandi pada model:

```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'password' => 'hashed',
        'status_aktif' => 'boolean',
    ];
}
```

Pembatasan endpoint berdasarkan peran:

```php
public function handle(
    Request $request,
    Closure $next,
    ...$roles,
): Response {
    $user = $request->user();

    if (! $user || ! in_array($user->peran, $roles, true)) {
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }

    return $next($request);
}
```

**Keterangan:** Akun selalu dibuat sebagai Penumpang. OTP dan kata sandi tidak disimpan dalam bentuk asli. Token baru diterbitkan setelah OTP valid, sedangkan middleware mengembalikan HTTP 403 untuk peran yang tidak diizinkan.

---

## PK-11 — Pemrosesan Pembayaran Tunai

**Modul:** Penumpang dan Pengemudi  
**Prioritas:** Disarankan  
**File:** `backend/app/Services/BookingService.php` dan `backend/app/Http/Controllers/Api/DriverController.php`  
**Method:** `confirmCashPaymentForActor()` dan `confirmPassengerCash()`  
**Rentang kode:** `BookingService.php` baris 552–641

```php
private function confirmCashPaymentForActor(
    Pemesanan $booking,
    User $actor,
    string $source,
): Pemesanan {
    return DB::transaction(function () use ($booking, $actor, $source) {
        $lockedBooking = Pemesanan::query()
            ->lockForUpdate()
            ->with([
                'detailPemesanan.tiketElektronik',
                'detailPemesanan.kursiJadwal',
                'jadwal.pengemudi',
                'pembayaran',
            ])
            ->findOrFail($booking->id);

        if ($source === 'driver_cash_confirm') {
            $driverProfile = $actor->pengemudiProfile()->first();

            if (
                ! $driverProfile
                || (int) $lockedBooking->jadwal?->pengemudi_id
                    !== (int) $driverProfile->id
            ) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'Pembayaran tunai ini bukan bagian dari jadwal pengemudi aktif.',
                ]);
            }
        }

        $payment = $lockedBooking->pembayaran;
        if (! $payment || $payment->metode_bayar !== 'cash') {
            throw ValidationException::withMessages([
                'payment' =>
                    'Proses ini hanya digunakan untuk pembayaran tunai.',
            ]);
        }

        $payment->update([
            'order_id_gateway' => null,
            'nominal_transaksi' => $lockedBooking->total_harga,
            'biaya_admin_provider' => 0,
            'total_dibayar_penumpang' => $lockedBooking->total_harga,
            'status_bayar' => 'berhasil',
            'webhook_verified_at' => null,
            'dibayar_pada' => now(),
            'expired_at' => null,
        ]);

        $lockedBooking->update([
            'status_pemesanan' => 'terbayar',
            'batas_pembayaran' => null,
        ]);

        $this->finalizePaidBooking($lockedBooking);

        return $this->freshBookingWithRelations($lockedBooking->id);
    });
}
```

**Keterangan:** Pembayaran tunai tidak memiliki order ID, fee, webhook, atau deadline provider. Pengemudi hanya dapat mengonfirmasi pembayaran pada jadwalnya sendiri dan tiket baru dibuat setelah status pembayaran berhasil.

---

## PK-12 — Kepemilikan Perjalanan dan Urutan Status

**Modul:** Pengemudi  
**Prioritas:** Sangat disarankan  
**File:** `backend/app/Http/Controllers/Api/DriverController.php`  
**Method:** `ensureDriverOwnsTrip()` dan `updateTripStatus()`  
**Rentang kode:** baris 376–403 dan 485–497

```php
private function ensureDriverOwnsTrip(
    Request $request,
    Jadwal $jadwal,
): Pengemudi {
    $pengemudi = $this->activeDriverFor($request);

    if ((int) $jadwal->pengemudi_id !== (int) $pengemudi->id) {
        abort(response()->json([
            'success' => false,
            'message' => 'Perjalanan ini bukan milik pengemudi aktif.',
        ], 403));
    }

    return $pengemudi;
}
```

```php
$lockedTrip = Jadwal::query()
    ->lockForUpdate()
    ->findOrFail($jadwal->id);

$allowed = [
    'dibuka' => ['berangkat'],
    'ditutup' => ['berangkat'],
    'berangkat' => ['selesai'],
];

$target = $payload['status'];
if (! in_array($target, $allowed[$lockedTrip->status] ?? [], true)) {
    abort(response()->json([
        'success' => false,
        'message' => 'Status perjalanan tidak dapat diloncati.',
    ], 422));
}

$lockedTrip->update(['status' => $target]);
```

**Keterangan:** Backend membandingkan `pengemudi_id` jadwal dengan profil Pengemudi aktif. Perjalanan hanya dapat berubah dari dibuka/ditutup menjadi berangkat, kemudian dari berangkat menjadi selesai.

---

## PK-13 — Pencatatan Biaya Operasional dengan Bukti Wajib

**Modul:** Pengemudi  
**Prioritas:** Disarankan  
**File:** `backend/app/Http/Controllers/Api/DriverController.php` dan `backend/app/Services/OperationalExpenseCategoryService.php`  
**Method:** `storeOperationalExpense()` dan `categories()`  
**Rentang kode:** cari method `storeOperationalExpense()`; `OperationalExpenseCategoryService.php` baris 10–42 dan 158–175  
**Status naskah:** **Sesuai dengan implementasi aktif.**

```php
public function storeOperationalExpense(
    Request $request,
    Jadwal $jadwal,
): JsonResponse {
    $pengemudi = $this->activeDriverFor($request);

    if ((int) $jadwal->pengemudi_id !== (int) $pengemudi->id) {
        return response()->json([
            'success' => false,
            'message' =>
                'Jadwal ini bukan tugas pengemudi yang sedang login.',
        ], 403);
    }

    if (! in_array(
        $jadwal->status,
        ['dibuka', 'ditutup', 'berangkat'],
        true,
    )) {
        return response()->json([
            'success' => false,
            'message' =>
                'Biaya hanya dapat dicatat pada jadwal tugas yang sedang aktif.',
        ], 422);
    }

    $validated = $request->validate([
        'jenis_biaya' => [
            'required',
            'string',
            'max:60',
            Rule::in($this->expenseCategoryService->categories()),
        ],
        'nominal' => ['required', 'numeric', 'min:1'],
        'tanggal' => ['required', 'date'],
        'keterangan' => ['required', 'string', 'min:10', 'max:1000'],
        'foto_bukti_nota' => [
            'required',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:3072',
        ],
    ]);

    $proofPath = $validated['foto_bukti_nota']
        ->store('biaya-operasional', 'public');

    try {
        $expense = BiayaOperasionalPerjalanan::query()->create([
            'jadwal_id' => $jadwal->id,
            'jenis_biaya' => $validated['jenis_biaya'],
            'nominal' => $validated['nominal'],
            'tanggal_biaya' => $validated['tanggal'],
            'keterangan' => $validated['keterangan'],
            'bukti_url' => $proofPath,
            'created_by' => $request->user()->id,
            // Langsung final tanpa alur konfirmasi atau penolakan.
            'status_verifikasi' => 'disetujui',
        ]);
    } catch (Throwable $exception) {
        Storage::disk('public')->delete($proofPath);
        throw $exception;
    }

    return response()->json([
        'success' => true,
        'message' => 'Biaya operasional berhasil dicatat.',
        'data' => [
            'id' => $expense->id,
            'status' => 'tercatat',
            'bukti_url' => $this->publicStorageUrl($expense->bukti_url),
        ],
    ], 201);
}
```

Sumber kategori dropdown:

```php
private const DEFAULT_CATEGORIES = [
    'BBM',
    'Tol',
    'Parkir',
    'Makan Pengemudi',
    'Perawatan Darurat',
    'Lainnya',
];

public function categories(): array
{
    $decoded = json_decode(
        (string) $this->settingService->get(
            self::SETTING_KEY,
            '[]',
        ),
        true,
    );

    $categories = is_array($decoded)
        ? $this->normalizeMany($decoded)
        : [];

    return $categories !== []
        ? $categories
        : self::DEFAULT_CATEGORIES;
}
```

Penyimpanan kategori yang ditambahkan Admin:

```php
private function persist(array $categories, int $adminId): void
{
    // Daftar kategori disimpan pada pengaturan_sistem agar web dan mobile
    // membaca satu sumber tanpa menambah tabel bisnis baru.
    SystemSetting::query()->updateOrCreate(
        ['kunci' => self::SETTING_KEY],
        [
            'kelompok' => 'operational',
            'nilai' => json_encode(
                $categories,
                JSON_UNESCAPED_UNICODE,
            ),
            'tipe' => 'text',
            'deskripsi' =>
                'Daftar kategori pengeluaran yang dapat dipilih admin dan pengemudi.',
            'diperbarui_oleh' => $adminId,
        ],
    );

    $this->settingService->clearCache();
}
```

### Perbaikan wajib narasi Gambar 4.13

Gunakan narasi berikut:

> Gambar 4.13 menampilkan pencatatan biaya operasional oleh Pengemudi melalui pilihan kategori, nominal, tanggal, keterangan, serta foto bukti/nota yang wajib diambil dari kamera atau dipilih dari galeri. Kategori pengeluaran berasal dari dropdown yang dapat dikelola Admin sehingga pilihan seperti BBM, tol, parkir, konsumsi, dan kategori lainnya tetap fleksibel. Backend memvalidasi bukti sebagai gambar JPG, PNG, atau WebP berukuran maksimal 3 MB dan memastikan jadwal merupakan tugas Pengemudi yang sedang aktif. Data langsung disimpan sebagai catatan final pada tabel `biaya_operasional_perjalanan` tanpa proses persetujuan atau penolakan, kemudian bukti dapat dibuka dan disertakan dalam rekap Admin.

**Fokus kode:** validasi kategori dropdown, nominal, tanggal, keterangan, foto bukti wajib, kepemilikan jadwal, penyimpanan `bukti_url`, pembersihan file jika database gagal, dan pencatatan final tanpa approval.

---

## PK-14 — Pembacaan Pengaturan Sistem

**Modul:** Admin  
**Prioritas:** Disarankan  
**File:** `backend/app/Services/SettingService.php`, `backend/app/Models/SystemSetting.php`, dan `backend/app/Http/Controllers/Web/Admin/SettingsController.php`  
**Method:** `get()`, `records()`, dan `updateBooking()`  
**Rentang kode:** `SettingService.php` baris 9–34 dan 118–134; `SettingsController.php` baris 107–118

```php
class SettingService
{
    private const CACHE_KEY = 'system_settings';

    public function all(): array
    {
        return $this->records()
            ->mapWithKeys(fn (array $setting) => [
                $setting['key'] => $this->castValue(
                    $setting['value'],
                    $setting['type'],
                ),
            ])
            ->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    private function records(): Collection
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => SystemSetting::query()
                ->orderBy('kelompok')
                ->orderBy('kunci')
                ->get()
                ->map(fn (SystemSetting $setting) => [
                    'key' => $setting->kunci,
                    'value' => $setting->nilai,
                    'type' => $setting->tipe,
                ]),
        );
    }
}
```

Validasi pengaturan pemesanan:

```php
public function updateBooking(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'auto_cancel_booking' => ['required', 'boolean'],
        'max_seat_per_booking' => ['required', 'integer', 'min:1'],
        'seat_lock_form_minutes' => ['required', 'integer', 'min:1'],
        'seat_lock_payment_minutes' => ['required', 'integer', 'min:1'],
    ]);

    return $this->saveGroup(
        'booking',
        $validated,
        $request,
        'Pengaturan pemesanan berhasil disimpan.',
    );
}
```

**Keterangan:** Service membaca tabel `pengaturan_sistem`, mengubah nilai sesuai tipe data, menyimpannya dalam cache, dan mengembalikan nilai bawaan ketika kunci belum tersedia. Backend tetap menjadi penentu batas kursi, waktu form, dan waktu pembayaran.

## D. Urutan Screenshot yang Disarankan untuk BAB IV

Apabila hanya tersedia ruang untuk delapan gambar kode, gunakan urutan berikut:

| Urutan | Kode | Potongan yang Ditampilkan | Alasan |
|---:|---|---|---|
| 1 | PK-01 | Transaksi dan `lockForUpdate()` kursi | Integritas pemesanan bersamaan |
| 2 | PK-02 | `createDraft()` dan `calculate()` | Harga dan pemesanan ditentukan backend |
| 3 | PK-03 | Transaction Create dan verifikasi Transaction Detail | Keamanan pembayaran digital |
| 4 | PK-04 | `finalizePaidBooking()` | Tiket unik per Penumpang |
| 5 | PK-05 | `scanTicket()` | Pencegahan tiket palsu dan pemindaian ganda |
| 6 | PK-06 | Interval konflik dan posisi kota | Integritas jadwal operasional |
| 7 | PK-07 | Pendapatan dan rute terpopuler | Validitas statistik Dasbor |
| 8 | PK-08 | `transactionQuery()` dan pilihan CSV/XLS | Konsistensi laporan dan ekspor |

Jika halaman masih tersedia, tambahkan PK-10 untuk autentikasi dan PK-12 untuk pembatasan akses Pengemudi.

## E. Format Keterangan Gambar Kode

Gunakan format konsisten berikut di bawah setiap screenshot:

> **Gambar 4.x Potongan Kode [Nama Implementasi]**  
> Potongan kode tersebut menunjukkan bahwa [aturan bisnis utama]. Implementasi menggunakan [mekanisme penting] untuk [tujuan keamanan/integritas]. Dengan demikian, [hasil yang diperoleh sistem].

Contoh untuk penguncian kursi:

> **Gambar 4.x Potongan Kode Penguncian Kursi secara Atomik**  
> Potongan kode tersebut menunjukkan bahwa pemilihan kursi diproses dalam transaksi database dan baris kursi dikunci menggunakan `lockForUpdate()`. Backend memeriksa status serta kepemilikan kunci sebelum memperbarui `status_kursi`, `dikunci_oleh`, dan `lock_expires_at`. Dengan demikian, hanya satu Penumpang yang dapat mengunci kursi yang sama pada waktu bersamaan.

## F. Checklist Pengambilan Screenshot dari Visual Studio Code

- Tampilkan nama file pada tab editor.
- Aktifkan nomor baris.
- Gunakan ukuran huruf yang tetap terbaca setelah dimasukkan ke laporan.
- Sembunyikan minimap dan panel terminal agar area kode lebih luas.
- Ambil hanya method atau blok utama yang dibahas.
- Jangan menampilkan `.env`, API key, password, token autentikasi, atau data pribadi.
- Gunakan tema dan skala tampilan yang sama untuk seluruh gambar kode.
- Pastikan keterangan gambar menyebut tujuan bisnis, bukan hanya mengulang sintaks program.
