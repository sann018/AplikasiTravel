# REKOMENDASI POTONGAN KODE WAJIB BAB IV RUSLYTRAVEL

**Tanggal analisis:** 29 Juli 2026  
**Dokumen yang dianalisis:** `TA_Ihsan Al Husna__V1 - Copy.docx`  
**Sifat analisis:** Baca-saja. Dokumen Word dan kode aplikasi tidak diubah.

## 1. Tujuan Analisis

Analisis ini bertujuan memilih logika program yang paling penting untuk ditampilkan pada BAB IV Laporan Tugas Akhir RuslyTravel. Pemilihan tidak didasarkan pada panjang kode, tetapi pada kemampuan potongan kode dalam membuktikan:

1. kontribusi utama sistem;
2. pengamanan konsistensi data;
3. pencegahan proses ganda;
4. pengelolaan transaksi;
5. hubungan antara backend, database, dan aplikasi mobile; serta
6. keputusan teknis yang berpotensi ditanyakan oleh dosen penguji.

Kode CRUD biasa, formatter, navigasi, serta tampilan visual murni tidak perlu dijadikan potongan kode utama.

## 2. Kesimpulan Pemilihan

BAB IV disarankan memuat maksimal delapan kelompok potongan kode utama.

| Prioritas | Logika | Keputusan | Alasan |
|---:|---|---|---|
| 1 | Penguncian kursi secara atomik | Wajib | Merupakan kontribusi utama untuk mencegah pemesanan kursi ganda. |
| 2 | Pengelolaan batas waktu dua tahap | Wajib | Menjelaskan timer form, timer pembayaran, pembatalan, dan pelepasan kursi. |
| 3 | Sinkronisasi kursi secara real-time | Wajib | Membuktikan perubahan kursi dapat terlihat pada perangkat pengguna lain. |
| 4 | Validasi konflik jadwal | Wajib | Mencegah pengemudi atau kendaraan digunakan pada interval perjalanan yang bertabrakan. |
| 5 | Verifikasi pembayaran Pakasir | Wajib | Membuktikan webhook tidak langsung dipercaya dan transaksi diproses secara idempoten. |
| 6 | Penerbitan tiket dan token UUID | Wajib | Menjelaskan asal kode unik untuk setiap tiket penumpang. |
| 7 | Verifikasi QR dan pencegahan pemindaian ganda | Wajib | Membuktikan QR diverifikasi oleh backend dan tidak dapat digunakan berulang. |
| 8 | Urutan status operasional perjalanan | Wajib | Membuktikan status perjalanan tidak dapat diloncati. |

## 3. Penguncian Kursi secara Atomik

### 3.1 Tujuan

Logika ini memastikan satu kursi pada satu jadwal hanya dapat dikunci atau dipesan oleh satu pengguna, termasuk ketika dua pengguna memilih kursi yang sama dalam waktu hampir bersamaan.

### 3.2 Sumber kode

`backend/app/Services/BookingService.php`

- `lockSeatsForForm()`: baris 42 dan seterusnya;
- `ensureSeatsCanBeUsedByUser()`: baris 1490 dan seterusnya;
- `loadSeatRowsForUpdate()`: baris 1571 dan seterusnya.

### 3.3 Potongan kode utama

```php
return DB::transaction(function () use ($user, $scheduleId, $seatNumbers) {
    $schedule = Jadwal::query()
        ->lockForUpdate()
        ->findOrFail($scheduleId);

    $selectedSeats = $this->loadSeatRowsForUpdate(
        $schedule->id,
        $seatNumbers,
    );

    $this->ensureSeatsCanBeUsedByUser(
        $selectedSeats,
        $user->id,
    );

    foreach ($selectedSeats as $seat) {
        $seat->update([
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $user->id,
            'locked_at' => $now,
            'lock_expires_at' => $formDeadline,
        ]);
    }
});
```

Validasi kursi yang sudah digunakan pengguna lain:

```php
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
```

### 3.4 Penjelasan logika

1. `DB::transaction()` menyatukan proses pemeriksaan dan perubahan status kursi.
2. `lockForUpdate()` mengunci baris database selama transaksi berjalan.
3. Request pengguna kedua harus menunggu transaksi pengguna pertama selesai.
4. Setelah transaksi pertama selesai, pengguna kedua membaca status kursi terbaru.
5. Kursi yang sudah `terkunci` oleh pengguna lain atau berstatus `dipesan` ditolak oleh backend.
6. Keamanan kursi tidak bergantung pada warna atau kondisi tombol di Flutter.

### 3.5 Pertanyaan yang mungkin diajukan

**Pertanyaan:** Mengapa menggunakan `lockForUpdate()`?

**Jawaban:** Karena pemeriksaan status biasa masih memiliki celah apabila dua request membaca kursi tersedia secara bersamaan. `lockForUpdate()` di dalam transaksi database memastikan hanya satu request yang dapat memeriksa dan mengubah baris kursi pada satu waktu.

## 4. Pengelolaan Batas Waktu Dua Tahap

### 4.1 Tahap pertama: pengisian form

Ketika pengguna memilih kursi, backend membuat batas waktu pengisian form berdasarkan pengaturan admin:

```php
private function resolveFormLockDeadline($now)
{
    return $now->copy()->addMinutes(
        (int) $this->settingService->get(
            'seat_lock_form_minutes',
            30,
        ),
    );
}
```

Nilai batas tersebut disimpan pada:

```text
kursi_jadwal.lock_expires_at
```

Pada tahap ini belum ada pemesanan permanen. Kursi hanya ditahan selama pengguna mengisi biodata, lokasi, dan barang bawaan.

### 4.2 Tahap kedua: menunggu pembayaran

Setelah form dinyatakan valid, `createDraft()` membuat pemesanan dan batas pembayaran:

```php
$paymentDeadline = $this->resolvePaymentLockDeadline(
    $now,
    $schedule,
);

$booking = Pemesanan::query()->create([
    'batas_kunci_kursi' => $formDeadline,
    'batas_pembayaran' => $paymentDeadline,
    'status_pemesanan' => 'menunggu_pembayaran',
]);
```

Kunci kursi kemudian diperpanjang sampai batas pembayaran:

```php
$seat->update([
    'status_kursi' => 'terkunci',
    'dikunci_oleh' => $user->id,
    'locked_at' => $now,
    'lock_expires_at' => $paymentDeadline,
]);
```

### 4.3 Ketika waktu habis

Method `releaseExpiredResources()` dan `expirePendingBooking()` menangani sumber daya yang kedaluwarsa:

```php
if (
    ! $booking->batas_pembayaran
    || $booking->batas_pembayaran->isFuture()
) {
    return 0;
}

$booking->update([
    'status_pemesanan' => 'dibatalkan',
]);

if (
    $booking->pembayaran
    && $booking->pembayaran->status_bayar === 'menunggu'
) {
    $booking->pembayaran->update([
        'status_bayar' => 'kedaluwarsa',
    ]);
}
```

### 4.4 Alur dua tahap

```text
Pengguna memilih kursi
→ kunci tahap form dibuat
→ pengguna mengisi data
→ backend memeriksa kepemilikan dan masa berlaku kunci
→ pemesanan dibuat
→ batas kunci diganti menjadi batas pembayaran
→ pembayaran berhasil: kursi menjadi dipesan
→ pembayaran kedaluwarsa: pemesanan dibatalkan dan kursi dilepas
```

### 4.5 Hal penting untuk dijelaskan

- Timer Flutter hanya menampilkan sisa waktu.
- Backend tetap membandingkan timestamp dengan waktu server.
- Manipulasi timer pada perangkat tidak dapat memperpanjang sesi.
- Kunci form yang belum mempunyai `detail_pemesanan` cukup dilepas.
- Pemesanan yang sudah memasuki tahap pembayaran harus dibatalkan dan status pembayarannya diubah menjadi `kedaluwarsa`.
- Pembayaran tunai menjadi pengecualian karena setelah metode tunai dipilih, batas pembayaran dan `lock_expires_at` dihapus sampai pembayaran dikonfirmasi petugas.

## 5. Sinkronisasi Kursi secara Real-Time

### 5.1 Tujuan

Logika ini membuat perubahan status kursi pada pengguna pertama dapat diketahui pengguna kedua tanpa membuka ulang halaman.

### 5.2 Alur

```text
Pengguna pertama mengunci kursi
→ data kursi_jadwal diperbarui
→ observer dijalankan setelah transaksi selesai
→ Laravel Reverb mengirim event kursi.diperbarui
→ Flutter pengguna kedua menerima event
→ Flutter mengambil seat map terbaru melalui REST API
→ kursi berubah menjadi terkunci
→ kursi tidak dapat dipilih pengguna kedua
```

### 5.3 Sumber kode

- `backend/app/Observers/KursiJadwalObserver.php`;
- `backend/app/Events/SeatAvailabilityChanged.php`;
- `backend/app/Services/SeatAvailabilityRealtimeService.php`;
- `frontend/lib/core/services/travs_seat_realtime_service.dart`;
- `frontend/lib/features/schedule/travs_schedule_detail_page.dart`.

### 5.4 Potongan backend

```php
public function updated(KursiJadwal $seat): void
{
    if (! $seat->wasChanged([
        'status_kursi',
        'dikunci_oleh',
        'locked_at',
        'lock_expires_at',
    ])) {
        return;
    }

    $this->realtimeService->publish((int) $seat->jadwal_id);
}
```

Event hanya membawa ID jadwal dan waktu perubahan:

```php
public function broadcastWith(): array
{
    return [
        'jadwal_id' => $this->scheduleId,
        'diperbarui_pada' => $this->changedAt,
    ];
}
```

### 5.5 Potongan Flutter

```dart
if (eventName == _settings.seatEvent &&
    message['channel']?.toString() == _channelName) {
  await _onSeatChanged();
}
```

Setelah seat map terbaru diterima:

```dart
_selectedSeatNumbers.removeWhere(
  (seatNumber) => !availableSeatNumbers.contains(seatNumber),
);
```

Pengguna hanya dapat menekan kursi yang masih tersedia:

```dart
if (seat.status != 'tersedia') {
  return;
}
```

### 5.6 Penjelasan yang tepat

WebSocket mempercepat pembaruan tampilan, tetapi tidak menjadi sumber keamanan utama. Sumber kebenaran tetap database dan validasi backend. Apabila Reverb tidak aktif, polling REST API setiap tiga detik menjadi jalur cadangan.

## 6. Validasi Konflik Jadwal

### 6.1 Tujuan

Validasi ini mencegah pengemudi atau kendaraan digunakan pada dua perjalanan yang waktunya saling bertabrakan.

### 6.2 Sumber kode

- `backend/app/Services/DriverAvailabilityService.php`;
- `backend/app/Http/Controllers/Web/Admin/JadwalController.php`.

### 6.3 Potongan kode konflik interval

```php
return Jadwal::query()
    ->where($resourceColumn, $resourceId)
    ->whereIn('status', $this->activeScheduleStatuses())
    ->where('waktu_berangkat', '<', $arrivalTime)
    ->where(function ($query) use ($departureTime) {
        $query
            ->where('estimasi_tiba', '>', $departureTime)
            ->orWhereNull('estimasi_tiba');
    });
```

Rumus interval yang digunakan:

```text
jadwal_lama.mulai < jadwal_baru.selesai
dan
jadwal_lama.selesai > jadwal_baru.mulai
```

Apabila kedua kondisi benar, jadwal dinyatakan bertabrakan.

### 6.4 Pengamanan request bersamaan

```php
private function lockScheduleResources(array $payload): void
{
    Kendaraan::query()
        ->whereKey((int) $payload['kendaraan_id'])
        ->lockForUpdate()
        ->firstOrFail();

    Pengemudi::query()
        ->whereKey((int) $payload['pengemudi_id'])
        ->lockForUpdate()
        ->firstOrFail();
}
```

Urutan penguncian selalu kendaraan lalu pengemudi agar dua transaksi tidak saling mengambil sumber daya dengan urutan yang berbeda.

### 6.5 Pemeriksaan tambahan

Sistem juga memeriksa:

1. estimasi waktu tiba;
2. jeda operasional minimal dua jam;
3. status perjalanan yang masih aktif;
4. kesinambungan kota tujuan jadwal sebelumnya dengan kota asal jadwal berikutnya;
5. posisi pengemudi dan kendaraan ketika perjalanan sebelumnya masih berstatus `berangkat`.

### 6.6 Catatan untuk laporan

Narasi laporan tidak cukup hanya menyebut “tanggal yang sama”. Implementasi aktif menggunakan interval keberangkatan sampai estimasi tiba, jeda operasional, dan kesinambungan posisi.

## 7. Verifikasi Pembayaran Pakasir

### 7.1 Tujuan

Logika ini membuktikan bahwa data webhook dari provider tidak langsung dipercaya dan pembayaran berulang tidak menghasilkan tiket ganda.

### 7.2 Sumber kode

- `backend/app/Services/PakasirService.php`;
- `backend/app/Services/BookingService.php`;
- `backend/app/Http/Controllers/Api/PaymentGatewayController.php`.

### 7.3 Potongan pembuatan transaksi

```php
public function createTransaction(
    string $method,
    string $orderId,
    int $amount,
): array {
    $response = $this->post("transactioncreate/{$method}", [
        'project' => $this->project(),
        'order_id' => $orderId,
        'amount' => $this->normalizeAmount($amount),
        'api_key' => $this->apiKey(),
    ]);

    return $response['payment'];
}
```

### 7.4 Potongan verifikasi webhook

```php
$transaction = $this->pakasirService->transactionDetail(
    $this->paymentOrderId($booking),
    $bookingAmount,
);

$this->ensureGatewayTransactionMatches(
    $booking,
    $transaction,
);

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

### 7.5 Pemeriksaan yang dilakukan

1. project Pakasir harus sesuai konfigurasi;
2. order ID harus cocok dengan pembayaran lokal;
3. metode pembayaran harus sesuai;
4. nominal harus sama dengan snapshot transaksi;
5. status webhook harus sesuai dengan Transaction Detail API;
6. proses dijalankan dalam transaksi database;
7. webhook sukses yang diterima berulang tidak membuat tiket baru;
8. pembayaran yang masuk setelah booking dibatalkan tidak menerbitkan tiket.

### 7.6 Pertanyaan yang mungkin diajukan

**Pertanyaan:** Mengapa webhook perlu diverifikasi ulang?

**Jawaban:** Karena webhook merupakan data masuk dari luar sistem. Backend melakukan validasi identitas proyek, transaksi, metode, nominal, dan status, kemudian memeriksa ulang ke Transaction Detail API sebelum mengubah pemesanan menjadi terbayar.

## 8. Penerbitan Tiket dan Token UUID

### 8.1 Tujuan

Menjelaskan asal kode unik tiket dan hubungan satu tiket dengan satu penumpang pada satu kursi.

### 8.2 Sumber kode

- `backend/app/Services/BookingService.php`;
- `backend/database/migrations/2026_04_22_000200_create_transaksi_tables.php`;
- `backend/app/Http/Controllers/Api/BookingController.php`;
- `frontend/lib/features/ticket/bagian_detail_tiket/kartu_tiket.dart`;
- `frontend/lib/features/ticket/bagian_detail_tiket/qr_dan_perjalanan_tiket.dart`.

### 8.3 Potongan penerbitan tiket

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

### 8.4 Perbedaan kode

| Field | Fungsi |
|---|---|
| `kode_qr` | Identitas tiket yang dapat dibaca manusia dan dicari admin. |
| `token_unik_qr` | UUID yang menjadi isi gambar QR dan digunakan untuk verifikasi. |

Flutter tidak membuat token baru. Flutter hanya mengubah token dari API menjadi gambar:

```dart
final qrData = ticket.token.isNotEmpty
    ? ticket.token
    : ticket.kodeQr;
```

```dart
QrImageView(
  data: data,
  size: 220,
)
```

### 8.5 Jaminan database

```php
$table->string('token_unik_qr', 100)->unique();
$table->unique('detail_pemesanan_id');
```

Artinya:

1. satu token tidak dapat dimiliki dua tiket;
2. satu `detail_pemesanan` hanya dapat memiliki satu tiket;
3. pemesanan dengan tiga kursi menghasilkan tiga detail penumpang dan tiga UUID.

### 8.6 Catatan bentuk kode

Kode pemesanan aktif memiliki bentuk:

```text
TRAVS-20260729-K9P2Q
```

Penerbitan tiket menambahkan awalan `TRAVS-` dan nomor kursi:

```text
TRAVS-TRAVS-20260729-K9P2Q-2
```

Awalan `TRAVS-` muncul dua kali karena `kode_booking` sudah memiliki awalan tersebut. Hal ini tidak merusak pemindaian karena QR diverifikasi menggunakan UUID, tetapi perlu diketahui apabila penguji memperhatikan bentuk `kode_qr`.

## 9. Verifikasi QR dan Pencegahan Pemindaian Ganda

### 9.1 Sumber kode

`backend/app/Http/Controllers/Api/DriverController.php`, method `scanTicket()`.

### 9.2 Potongan kode

```php
$ticket = TiketElektronik::query()
    ->lockForUpdate()
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

### 9.3 Validasi lengkap

Backend memeriksa:

1. token ditemukan pada database;
2. tiket mempunyai detail penumpang;
3. tiket berasal dari jadwal yang ditugaskan kepada pengemudi aktif;
4. jadwal belum selesai;
5. pemesanan sudah terbayar;
6. tiket belum pernah dipindai;
7. pemindaian dilakukan secara atomik menggunakan `lockForUpdate()`.

### 9.4 Alur

```text
Kamera membaca gambar QR
→ Flutter memperoleh token
→ token dikirim ke backend
→ backend mencari token_unik_qr
→ kepemilikan jadwal dan pembayaran diperiksa
→ status pemindaian diperiksa
→ tiket ditandai sudah dipindai
→ status kehadiran menjadi hadir
```

## 10. Urutan Status Operasional Perjalanan

### 10.1 Tujuan

Mencegah pengemudi meloncati urutan proses perjalanan.

### 10.2 Potongan kode

```php
$allowed = [
    'dibuka' => ['berangkat'],
    'ditutup' => ['berangkat'],
    'berangkat' => ['selesai'],
];

if (! in_array(
    $target,
    $allowed[$lockedTrip->status] ?? [],
    true,
)) {
    abort(response()->json([
        'success' => false,
        'message' => 'Status perjalanan tidak dapat diloncati.',
    ], 422));
}
```

### 10.3 Urutan status

```text
dibuka/ditutup → berangkat → selesai
```

Status `selesai` tidak dapat dipilih langsung dari `dibuka` atau `ditutup`.

### 10.4 Tombol penumpang turun

Flutter menonaktifkan tombol turun sebelum perjalanan berstatus `berangkat`:

```dart
onPressed: (isDroppingOff || !journeyStarted)
    ? null
    : onDropoff,
```

Namun, endpoint backend `markPassengerDropoff()` belum memeriksa secara langsung bahwa jadwal sudah berstatus `berangkat`. Oleh karena itu, laporan belum boleh menyatakan bahwa aturan tombol turun telah diamankan penuh oleh backend.

## 11. Potongan Kode Pendukung

Kode berikut dapat dimasukkan apabila halaman laporan masih tersedia. Jika tidak, cukup dijelaskan secara naratif atau dipindahkan ke lampiran.

### 11.1 Perhitungan harga oleh backend

Menjelaskan bahwa subtotal dan total tidak dipercaya dari input Flutter, tetapi dihitung ulang berdasarkan jadwal dan jumlah kursi.

Sumber:

- `backend/app/Services/BookingService.php`;
- `backend/app/Services/BookingCalculator.php`.

### 11.2 Registrasi, OTP, dan hak akses

Menjelaskan:

- validasi akun;
- hashing kata sandi;
- OTP email;
- masa berlaku OTP;
- penerbitan token setelah verifikasi;
- pembatasan endpoint berdasarkan peran.

Sumber:

- `backend/app/Http/Controllers/Api/AuthController.php`;
- `backend/app/Models/User.php`;
- `backend/app/Http/Middleware/EnsureUserHasRole.php`.

### 11.3 Titik jemput dan titik turun

Kode dapat digunakan untuk menjelaskan hubungan:

```text
GPS/klik peta/link Google Maps
→ koordinat lintang dan bujur
→ reverse geocoding Nominatim
→ alamat
→ disimpan pada pemesanan
→ dibuka pengemudi melalui navigasi eksternal
```

Bagian ini lebih tepat dijelaskan sebagai integrasi layanan lokasi daripada algoritma utama.

### 11.4 Pencatatan barang bawaan

Tampilkan hanya jika ingin membuktikan:

- validasi maksimal foto;
- normalisasi URL foto;
- hubungan barang dengan pemesanan;
- akses data barang pada manifes pengemudi.

CRUD barang biasa tidak perlu ditampilkan.

### 11.5 Laporan dan ekspor

Layak dimasukkan apabila ingin membuktikan tampilan dan file ekspor memakai query filter yang sama serta pendapatan tidak memasukkan biaya admin Pakasir.

## 12. Kode yang Tidak Perlu Menjadi Potongan Utama

Jangan memenuhi BAB IV dengan:

1. CRUD kendaraan, pengemudi, dan rute biasa;
2. widget card dan susunan tampilan;
3. warna, padding, ukuran font, dan icon;
4. formatter tanggal atau Rupiah;
5. navigasi halaman;
6. controller yang hanya mengambil daftar data;
7. statistik dasbor sederhana;
8. model yang hanya berisi `$fillable`;
9. kode upload biasa tanpa validasi penting;
10. seluruh isi method yang panjang.

## 13. Temuan pada Laporan Word

### 13.1 Penjelasan Gambar 4.14 tidak sesuai

Caption Gambar 4.14 menyatakan halaman Dasbor, tetapi penjelasannya membahas form pembuatan jadwal dan konflik pengemudi. Isi tersebut tampak terduplikasi dari Gambar 4.15.

### 13.2 Penjelasan konflik jadwal terlalu sederhana

Laporan masih menyebut pengemudi dan kendaraan tidak dapat digunakan pada tanggal yang sama. Implementasi aktif sebenarnya menggunakan:

- interval keberangkatan sampai estimasi tiba;
- jeda operasional minimal dua jam;
- status jadwal aktif;
- kesinambungan kota;
- penguncian sumber daya ketika jadwal disimpan.

### 13.3 Penjelasan batas pembayaran belum lengkap

Batas pembayaran digital tidak hanya mengikuti provider. Backend memilih waktu yang lebih awal antara:

```text
batas pembayaran dari pengaturan admin
dan
expired_at dari Pakasir
```

Provider tidak diperbolehkan memperpanjang batas waktu yang sudah ditentukan aplikasi.

### 13.4 Validasi tombol turun belum lengkap di backend

Tombol Flutter sudah dinonaktifkan sebelum perjalanan dimulai. Namun, endpoint backend belum memeriksa langsung status `berangkat`. Bagian ini perlu dicatat sebagai temuan dan tidak boleh diklaim sudah aman sepenuhnya.

### 13.5 Kesalahan penulisan

Terdapat teks:

```text
transaksi bais data
```

Seharusnya:

```text
transaksi basis data
```

### 13.6 Penomoran pengujian

Heading `Pengujian Sistem` sebaiknya diberi nomor `4.3` karena subbagian berikutnya sudah menggunakan nomor `4.3.1`.

## 14. Struktur BAB IV yang Disarankan

Setelah `4.2.4 Implementasi Modul Admin`, tambahkan:

```text
4.2.5 Implementasi Logika Fitur Utama

a. Penguncian Kursi Atomik dan Sinkronisasi Real-Time
b. Pengelolaan Batas Waktu Dua Tahap
c. Validasi Konflik Jadwal
d. Verifikasi Pembayaran Digital
e. Penerbitan Tiket Elektronik
f. Verifikasi QR Code
g. Pengendalian Status Operasional Perjalanan
```

Setiap subbagian sebaiknya berisi:

1. tujuan logika;
2. potongan kode sekitar 15–30 baris;
3. penjelasan input, proses, dan output;
4. alasan pemilihan teknik;
5. kondisi gagal yang ditangani;
6. keterkaitan dengan database;
7. keterkaitan dengan aplikasi mobile atau web admin.

## 15. Urutan Presentasi Saat Sidang

Urutan penjelasan yang disarankan:

1. permasalahan pemesanan ganda;
2. transaksi database dan `lockForUpdate()`;
3. batas waktu dua tahap;
4. perubahan kursi pada perangkat pengguna kedua;
5. validasi konflik jadwal;
6. verifikasi pembayaran;
7. penerbitan UUID tiket;
8. pemindaian QR dan anti-penggunaan ulang;
9. urutan operasional pengemudi.

Urutan tersebut membentuk satu rangkaian proses bisnis:

```text
Jadwal valid dibuat
→ penumpang memilih kursi
→ kursi dikunci
→ pemesanan dibuat
→ pembayaran diverifikasi
→ tiket diterbitkan
→ QR dipindai
→ perjalanan dijalankan
→ status perjalanan diselesaikan
```

## 16. Kesimpulan

Potongan kode yang paling kuat bukan kode yang paling panjang, melainkan kode yang membuktikan bahwa sistem mampu menjaga konsistensi proses utama. Untuk RuslyTravel, fokus utama harus berada pada:

- transaksi dan penguncian kursi;
- pengelolaan waktu;
- validasi konflik sumber daya;
- integritas pembayaran;
- keunikan dan validasi tiket;
- urutan operasional perjalanan.

Tujuh sampai delapan kelompok kode sudah mencukupi untuk BAB IV. Potongan lain lebih tepat ditempatkan pada lampiran atau dijelaskan secara naratif agar pembahasan tetap fokus dan mudah dipertanggungjawabkan saat sidang.
