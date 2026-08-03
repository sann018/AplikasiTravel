# Panduan Kode Sembilan Fitur Utama RuslyTravel

Dokumen ini menjelaskan implementasi aktual sembilan fitur utama RuslyTravel berdasarkan kode
backend Laravel, aplikasi Flutter, database, route aktif, dan pengujian otomatis per 28 Juli
2026. Potongan kode dipilih pada bagian yang mewakili logika utama. Beberapa bagian disingkat
dengan tanda `...` agar pembahasan tetap fokus.

> Catatan: nomor baris mengikuti kondisi project saat dokumen ini dibuat dan dapat bergeser
> setelah kode diperbarui. Tautan file tetap dapat dipakai untuk menemukan implementasinya.

## 1. Teknologi yang Menjadi Dasar Implementasi

| Bagian | Teknologi aktif | Peran |
|---|---|---|
| Aplikasi mobile | Flutter 3.44.6 dan Dart 3.12.2 | Antarmuka tamu, penumpang, dan pengemudi |
| Backend API dan web admin | Laravel 13.5.0 dan PHP 8.3.30 | Validasi, autentikasi, transaksi, API, dan Blade |
| Autentikasi API | Laravel Sanctum 4.3.1 | Token akses aplikasi mobile |
| Basis data | MySQL Community Server 8.4.3 | Penyimpanan data dan transaksi atomik |
| Real-time | Laravel Reverb 1.11.0 dan `web_socket_channel` | Sinyal perubahan ketersediaan kursi |
| Peta | `flutter_map`, OpenStreetMap, ArcGIS, Geolocator, Geocoding, Nominatim | Peta, GPS, geocoding, dan reverse geocoding |
| Pembayaran digital | Transaction API Pakasir | QRIS, Virtual Account, fee, total, dan verifikasi transaksi |
| QR tiket | `qr_flutter` dan `mobile_scanner` | Membentuk dan memindai QR tiket |
| Penyimpanan tiket | `RepaintBoundary` dan package `gal` | Mengubah card tiket menjadi PNG dan menyimpannya |

Arsitektur umumnya adalah:

```text
Flutter atau Blade
        ↓
Route API/Web + middleware
        ↓
Controller
        ↓
Service dan transaksi database
        ↓
Model Eloquent
        ↓
MySQL
```

Controller menerima permintaan, melakukan validasi awal, dan menyerahkan logika kompleks kepada
service. Backend menjadi sumber kebenaran untuk status, ketersediaan kursi, nominal pembayaran,
dan hak akses. Flutter hanya menampilkan data serta mengirim tindakan pengguna.

---

## 2. Autentikasi dan Verifikasi Email

### 2.1 Dibuat dengan apa?

Fitur ini dibuat menggunakan:

- Laravel Validation untuk validasi nama, Gmail, nomor HP, dan kata sandi;
- Laravel Hash untuk menyimpan hash OTP;
- Laravel Mail melalui SMTP untuk mengirim OTP;
- Laravel Sanctum untuk menerbitkan token setelah email terverifikasi;
- cast `hashed` pada model `User` untuk mengamankan kata sandi.

### 2.2 Alur fitur

```text
Isi registrasi
→ backend memvalidasi data
→ backend membuat OTP enam digit
→ hash OTP disimpan di database
→ OTP asli dikirim melalui SMTP
→ pengguna memasukkan OTP
→ backend membandingkan OTP dan masa berlaku
→ email_verified_at diisi
→ token Sanctum diterbitkan
```

### 2.3 Potongan validasi registrasi

Sumber:
[AuthController.php](../backend/app/Http/Controllers/Api/AuthController.php#L24)

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
```

Isi kode tersebut:

1. `regex` email membatasi pendaftaran ke domain `@gmail.com`.
2. nomor HP harus seluruhnya angka dan panjangnya 11–12 digit;
3. email dan nomor HP harus unik;
4. kata sandi minimal 6 karakter, memiliki huruf, serta minimal satu angka atau simbol; batas teknisnya 72 karakter;
5. `confirmed` mengharuskan `password_confirmation` bernilai sama.

Validasi berada di backend sehingga request yang dibuat di luar Flutter tetap tunduk pada aturan
yang sama.

### 2.4 Pembuatan dan pengiriman OTP

Sumber:
[AuthController.php](../backend/app/Http/Controllers/Api/AuthController.php#L78) dan
[AccountVerificationCodeMail.php](../backend/app/Mail/AccountVerificationCodeMail.php#L8)

```php
$verificationCode = $this->generateVerificationCode();

$userData = [
    'nama' => trim($validated['nama']),
    'email' => $validated['email'],
    'no_hp' => trim($validated['no_hp']),
    'peran' => 'penumpang',
    'status_aktif' => true,
    'password' => $validated['password'],
    'otp_code' => Hash::make($verificationCode),
    'otp_expires_at' => now()->addMinutes(15),
];

$user = User::query()->create($userData);
Mail::to($user->email)->send(
    new AccountVerificationCodeMail($verificationCode)
);
```

OTP asli tidak disimpan. Database hanya menyimpan hasil `Hash::make()`. OTP berlaku 15 menit.
SMTP merupakan sarana pengiriman email, bukan metode login eksternal. Dengan demikian,
autentikasi tetap menggunakan akun lokal RuslyTravel.

### 2.5 Verifikasi OTP dan penerbitan token

Sumber:
[AuthController.php](../backend/app/Http/Controllers/Api/AuthController.php#L129)

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

Token baru diterbitkan setelah OTP cocok dan belum kedaluwarsa. OTP kemudian dihapus agar tidak
dapat dipakai ulang.

### 2.6 Data yang terlibat

- tabel `pengguna`: identitas, peran, hash kata sandi, OTP, dan waktu verifikasi;
- tabel `personal_access_tokens`: token Sanctum;
- tabel `password_reset_tokens`: token lupa kata sandi.

### 2.7 Pengujian terkait

- [AuthEmailVerificationTest.php](../backend/tests/Feature/AuthEmailVerificationTest.php)
- [SecurityAndTDTest.php](../backend/tests/Feature/SecurityAndTDTest.php)

Jawaban singkat untuk sidang:

> SMTP hanya digunakan untuk mengirim OTP. Akun, password, status verifikasi, dan token login
> tetap dikelola oleh backend RuslyTravel menggunakan Laravel Sanctum.

---

## 3. Pencarian Jadwal dan Pemesanan

### 3.1 Dibuat dengan apa?

Fitur pencarian menggunakan query Eloquent Laravel dengan filter asal, tujuan, dan tanggal.
Pembuatan pemesanan menggunakan `BookingController`, `BookingService`, transaksi MySQL, dan model
`Pemesanan`, `DetailPemesanan`, `KursiJadwal`, serta `ItemBagasi`.

### 3.2 Pencarian jadwal yang masih tersedia

Sumber:
[GuestController.php](../backend/app/Http/Controllers/Api/GuestController.php#L19)

```php
$query = Jadwal::query()
    ->with(['rute', 'kendaraan', 'pengemudi.pengguna', 'kursi'])
    ->where('status', 'dibuka')
    ->whereHas(
        'kursi',
        fn ($builder) => $builder->where('status_kursi', 'tersedia')
    )
    ->orderBy('waktu_berangkat');

if (! empty($validated['asal'])) {
    $query->whereHas(
        'rute',
        fn ($builder) =>
            $builder->where('kota_asal', 'like', '%'.$validated['asal'].'%')
    );
}

if (! empty($validated['tanggal'])) {
    $query->whereDate('waktu_berangkat', $validated['tanggal']);
}
```

Query hanya mengirim jadwal berstatus `dibuka` yang masih mempunyai kursi `tersedia`. Relasi
diambil menggunakan eager loading agar informasi rute, armada, pengemudi, dan kursi tidak
memerlukan query berulang untuk setiap jadwal.

### 3.3 Pembuatan draft pemesanan

Sumber:
[BookingService.php](../backend/app/Services/BookingService.php#L124)

```php
$booking = Pemesanan::query()->create([
    'pengguna_id' => $user->id,
    'jadwal_id' => $schedule->id,
    'kode_booking' => $this->generateBookingCode(),
    'jumlah_kursi' => $seatNumbers->count(),
    'batas_kunci_kursi' => $formDeadline,
    'batas_pembayaran' => $paymentDeadline,
    'lintang_jemput' => data_get($payload, 'pickup.latitude'),
    'bujur_jemput' => data_get($payload, 'pickup.longitude'),
    'alamat_jemput' => data_get($payload, 'pickup.address'),
    'catatan_jemput' => data_get($payload, 'pickup.note'),
    'lintang_turun' => data_get($payload, 'dropoff.latitude'),
    'bujur_turun' => data_get($payload, 'dropoff.longitude'),
    'alamat_turun' => data_get($payload, 'dropoff.address'),
    'catatan_turun' => data_get($payload, 'dropoff.note'),
    'subtotal_tiket' => $totals['subtotal_tiket'],
    'total_harga' => $totals['total_harga'],
    'status_pemesanan' => 'menunggu_pembayaran',
]);
```

Kode tersebut membuat snapshot pemesanan. `subtotal_tiket` dan `total_harga` disimpan saat
transaksi dibuat. Perubahan harga pada tabel `rute` setelahnya tidak mengubah harga historis
pemesanan lama.

### 3.4 Satu data penumpang untuk setiap kursi

```php
foreach ($selectedSeats as $seat) {
    $passenger = $passengers->firstWhere(
        'seat_number',
        $seat->nomor_kursi
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
```

Tabel `pemesanan` mewakili transaksi, sedangkan `detail_pemesanan` mewakili penumpang pada
masing-masing kursi. Karena itu, satu pemesanan yang memilih tiga kursi menghasilkan satu record
`pemesanan` dan tiga record `detail_pemesanan`.

### 3.5 Validasi konsistensi kursi dan penumpang

Sumber:
[BookingService.php](../backend/app/Services/BookingService.php#L1393)

```php
if ($passengers->count() !== $seatNumbers->count()) {
    throw ValidationException::withMessages([
        'passengers' =>
            'Jumlah data penumpang harus sama dengan jumlah kursi yang dipilih.',
    ]);
}

if ($passengerSeatNumbers->all() !== $seatNumbers->sort()->values()->all()) {
    throw ValidationException::withMessages([
        'passengers' =>
            'Data penumpang harus sesuai dengan nomor kursi yang dipilih.',
    ]);
}
```

Validasi ini mencegah kondisi, misalnya, pengguna memilih kursi 1 dan 2 tetapi mengirim data
penumpang untuk kursi 1 dan 3.

### 3.6 Pengujian terkait

- [BookingFlowTest.php](../backend/tests/Feature/BookingFlowTest.php)
- [SecurityAndTDTest.php](../backend/tests/Feature/SecurityAndTDTest.php)
- [PublicScheduleAvailabilityTest.php](../backend/tests/Feature/PublicScheduleAvailabilityTest.php)

---

## 4. Pemilihan dan Penguncian Kursi Real-Time

### 4.1 Konsep dua tahap

Penguncian kursi terdiri atas:

1. **Tahap pengisian form**: dimulai ketika penumpang menekan tombol lanjut ke konfirmasi.
   Kursi ditahan selama durasi `seat_lock_form_minutes`.
2. **Tahap pembayaran**: setelah draft pemesanan berhasil dibuat, batas kunci diperbarui menjadi
   batas pembayaran. Untuk tunai, batas waktu dihilangkan dan kursi ditahan sampai dikonfirmasi
   atau perjalanan selesai.

Mengetuk kursi pada Flutter baru memilih kursi secara lokal. Penguncian server terjadi saat
Flutter memanggil endpoint `lock-form`.

### 4.2 Pemanggilan lock dari Flutter

Sumber:
[travs_schedule_detail_page.dart](../frontend/lib/features/schedule/travs_schedule_detail_page.dart#L395)

```dart
final lockResult = await _apiService.lockSeatsForCheckout(
  token: widget.session!.token,
  scheduleId: detail.schedule.id,
  seatNumbers: _selectedSeatNumbers.toList()..sort(),
);

await Navigator.of(context).push(
  MaterialPageRoute(
    builder: (_) => TravSPaymentPreviewPage(
      detail: detail,
      selectedSeatNumbers: lockResult.seatNumbers,
      lockExpiresAt: lockResult.lockExpiresAt,
      // parameter lain disingkat
    ),
  ),
);
```

### 4.3 Transaksi atomik dan `lockForUpdate()`

Sumber:
[BookingService.php](../backend/app/Services/BookingService.php#L42)

```php
return DB::transaction(function () use (
    $user,
    $scheduleId,
    $seatNumbers
) {
    $schedule = Jadwal::query()
        ->lockForUpdate()
        ->findOrFail($scheduleId);

    $selectedSeats = KursiJadwal::query()
        ->where('jadwal_id', $schedule->id)
        ->whereIn('nomor_kursi', $seatNumbers)
        ->lockForUpdate()
        ->get();

    $this->ensureSeatsCanBeUsedByUser($selectedSeats, $user->id);
    $formDeadline = $this->resolveFormLockDeadline(now());

    foreach ($selectedSeats as $seat) {
        $seat->update([
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $user->id,
            'locked_at' => now(),
            'lock_expires_at' => $formDeadline,
        ]);
    }
});
```

`DB::transaction()` memastikan seluruh perubahan berhasil bersama-sama atau dibatalkan bersama.
`lockForUpdate()` membuat request kedua harus menunggu transaksi pertama selesai. Setelah
menunggu, request kedua membaca status terbaru dan akan ditolak jika kursi telah dikunci.

### 4.4 Penolakan kursi milik pengguna lain

Sumber:
[BookingService.php](../backend/app/Services/BookingService.php#L1490)

```php
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

Keamanan kursi ditentukan oleh transaksi dan validasi backend, bukan oleh warna pada Flutter.
Warna abu-abu hanya representasi visual dari status `terkunci`.

### 4.5 Event real-time setelah transaksi selesai

Sumber:
[KursiJadwalObserver.php](../backend/app/Observers/KursiJadwalObserver.php#L9) dan
[SeatAvailabilityChanged.php](../backend/app/Events/SeatAvailabilityChanged.php#L10)

```php
class KursiJadwalObserver implements ShouldHandleEventsAfterCommit
{
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
}

public function broadcastOn(): Channel
{
    return new Channel('jadwal.'.$this->scheduleId);
}

public function broadcastAs(): string
{
    return 'kursi.diperbarui';
}
```

Observer berjalan setelah commit agar perangkat lain tidak mengambil data yang belum selesai
disimpan. Event hanya membawa ID jadwal dan waktu perubahan, tanpa nama atau identitas penumpang.

### 4.6 Flutter menerima sinyal lalu mengambil data terbaru

Sumber:
[travs_seat_realtime_service.dart](../frontend/lib/core/services/travs_seat_realtime_service.dart#L83)

```dart
if (eventName == _settings.seatEvent &&
    message['channel']?.toString() == _channelName) {
  // Event hanya sinyal; data terbaru tetap diambil dari API.
  await _onSeatChanged();
}
```

Flutter tidak menjadikan isi WebSocket sebagai sumber data. Setelah menerima event, Flutter
memanggil API detail jadwal dan memperbarui denah. Polling setiap tiga detik tetap dipakai sebagai
cadangan jika Reverb tidak aktif.

### 4.7 Kegagalan Reverb tidak menggagalkan booking

Sumber:
[SeatAvailabilityRealtimeService.php](../backend/app/Services/SeatAvailabilityRealtimeService.php#L14)

```php
try {
    SeatAvailabilityChanged::dispatch(
        $scheduleId,
        now()->toIso8601String()
    );
} catch (Throwable $error) {
    Log::warning('Pembaruan kursi real-time gagal dikirim.', [
        'jadwal_id' => $scheduleId,
        'error' => $error->getMessage(),
    ]);
}
```

Reverb meningkatkan kecepatan pembaruan UI, sedangkan konsistensi data tetap dijamin MySQL.

### 4.8 Pengujian terkait

- [SeatRealtimeTest.php](../backend/tests/Feature/SeatRealtimeTest.php)
- `test_same_seat_cannot_be_locked_by_two_users()` pada
  [SecurityAndTDTest.php](../backend/tests/Feature/SecurityAndTDTest.php#L428)
- pengujian lock, release, expiry, dan draft pada
  [BookingFlowTest.php](../backend/tests/Feature/BookingFlowTest.php)

Jawaban singkat untuk sidang:

> Real-time mempunyai dua lapisan. MySQL `lockForUpdate()` menjamin keamanan kursi, sedangkan
> Laravel Reverb mempercepat perubahan tampilan pada perangkat lain.

---

## 5. Titik Jemput dan Titik Turun Berbasis Peta

### 5.1 Dibuat dengan apa?

- `flutter_map` untuk widget peta;
- tile OpenStreetMap untuk peta jalan;
- ArcGIS World Imagery sebagai opsi satelit;
- Geolocator untuk GPS dan izin lokasi;
- package Geocoding sebagai geocoding perangkat;
- Nominatim OpenStreetMap sebagai pencarian dan reverse geocoding fallback;
- Google Maps URL untuk membuka navigasi dari koordinat yang tersimpan.

### 5.2 Format masukan yang didukung

Sumber:
[travs_location_service.dart](../frontend/lib/core/services/travs_location_service.dart#L38)

```dart
Future<TravSPickupPoint> resolvePickupFromInput(String input) async {
  final query = input.trim();

  final directCoordinates = extractCoordinatesFromInput(query);
  if (directCoordinates != null) {
    return resolvePickupFromCoordinates(
      latitude: directCoordinates.latitude,
      longitude: directCoordinates.longitude,
    );
  }

  final inputUri = Uri.tryParse(query);
  if (inputUri != null && inputUri.hasScheme) {
    final resolvedUri = _isShortGoogleMapsUri(inputUri)
        ? await _expandShortGoogleMapsUri(inputUri)
        : inputUri;

    final linkCoordinates = extractCoordinatesFromInput(
      resolvedUri.toString(),
    );
    if (linkCoordinates != null) {
      return resolvePickupFromCoordinates(
        latitude: linkCoordinates.latitude,
        longitude: linkCoordinates.longitude,
      );
    }
  }

  return resolvePickupFromAddress(query);
}
```

Pengguna dapat:

- memilih titik langsung pada peta;
- memakai GPS perangkat;
- menulis alamat;
- menempel koordinat;
- menempel link Google Maps, termasuk link pendek yang dapat diperluas.

Semua masukan dinormalisasi menjadi `latitude`, `longitude`, dan `address` sebelum dikirim ke
backend.

### 5.3 Meminta izin dan membaca GPS

```dart
var permission = await Geolocator.checkPermission();
if (permission == LocationPermission.denied) {
  permission = await Geolocator.requestPermission();
}

final position = await Geolocator.getCurrentPosition(
  locationSettings: const LocationSettings(
    accuracy: LocationAccuracy.high,
  ),
);
```

Jika layanan lokasi mati atau izin ditolak permanen, aplikasi memberi pesan yang sesuai dan tidak
menghasilkan koordinat palsu.

### 5.4 Reverse geocoding dengan Nominatim

Sumber:
[travs_location_service.dart](../frontend/lib/core/services/travs_location_service.dart#L401)

```dart
final uri = Uri.https(
  'nominatim.openstreetmap.org',
  '/reverse',
  {
    'format': 'jsonv2',
    'lat': latitude.toString(),
    'lon': longitude.toString(),
    'zoom': '18',
    'addressdetails': '1',
  },
);

final response = await http.get(
  uri,
  headers: const {
    'Accept': 'application/json',
    'User-Agent': 'RuslyTravel Mobile App',
  },
);
```

Reverse geocoding mengubah koordinat menjadi nama jalan atau wilayah yang dapat dibaca manusia.
Jika geocoding perangkat gagal, Nominatim menjadi fallback.

### 5.5 Peta interaktif

Sumber:
[travs_pickup_map_picker_page.dart](../frontend/lib/features/pickup/travs_pickup_map_picker_page.dart#L305)

```dart
FlutterMap(
  mapController: _mapController,
  options: MapOptions(
    initialCenter: _selectedPoint,
    initialZoom: 15,
    minZoom: 3,
    maxZoom: 19,
    onTap: (_, point) => _selectPoint(point),
    onPositionChanged: _handleMapPositionChanged,
  ),
  children: [
    TileLayer(
      urlTemplate: _mapLayer == _TravSMapLayer.satellite
          ? 'https://server.arcgisonline.com/.../{z}/{y}/{x}'
          : 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
      userAgentPackageName: 'com.ruslytravel.app',
    ),
  ],
);
```

Ketika peta digeser, aplikasi menunggu sekitar 1,1 detik sebelum mencari alamat. Debounce ini
mencegah terlalu banyak request selama pengguna masih menggeser peta.

### 5.6 Validasi backend

Sumber:
[BookingController.php](../backend/app/Http/Controllers/Api/BookingController.php#L189)

```php
'pickup.latitude' => ['required', 'numeric', 'between:-90,90'],
'pickup.longitude' => ['required', 'numeric', 'between:-180,180'],
'pickup.address' => ['required', 'string', 'max:1000'],
'pickup.note' => ['required', 'string', 'max:500'],
'dropoff.latitude' => ['required', 'numeric', 'between:-90,90'],
'dropoff.longitude' => ['required', 'numeric', 'between:-180,180'],
'dropoff.address' => ['required', 'string', 'max:1000'],
'dropoff.note' => ['required', 'string', 'max:500'],
```

Backend tetap memeriksa batas koordinat walaupun Flutter telah melakukan validasi.

Jawaban singkat untuk sidang:

> Nama fitur yang tepat adalah “Penentuan Titik Jemput dan Titik Turun Berbasis Peta Digital dan
> Koordinat”. Peta merupakan antarmuka, sedangkan koordinat adalah data yang dipakai kembali
> untuk membuka navigasi.

---

## 6. Pencatatan Barang Bawaan

### 6.1 Dibuat dengan apa?

Flutter menggunakan `image_picker` untuk kamera atau galeri dan multipart HTTP untuk upload.
Laravel memvalidasi gambar, menyimpannya pada storage publik, lalu menyimpan metadata barang
dalam tabel `item_bagasi`.

### 6.2 Pemilihan dan upload foto

Sumber:
[travs_payment_preview_page.dart](../frontend/lib/features/payment/travs_payment_preview_page.dart#L646)

```dart
final photo = await _imagePicker.pickImage(
  source: source,
  imageQuality: 82,
  maxWidth: 1600,
  maxHeight: 1600,
);

final uploadedPhoto = await _apiService.uploadBaggagePhoto(
  token: widget.session.token,
  photo: photo,
);
```

Foto dikompresi pada kualitas 82 dan dibatasi dimensinya agar upload lebih ringan. Aplikasi
mengizinkan maksimal tiga foto.

### 6.3 Validasi file backend

Sumber:
[BookingController.php](../backend/app/Http/Controllers/Api/BookingController.php#L264)

```php
$validated = $request->validate([
    'foto' => [
        'required',
        'image',
        'mimes:jpg,jpeg,png,webp',
        'max:3072',
    ],
]);

$path = $validated['foto']->store('baggage', 'public');
```

Server hanya menerima gambar JPG, JPEG, PNG, atau WEBP maksimal 3 MB.

### 6.4 Penyimpanan data barang

Sumber:
[BookingService.php](../backend/app/Services/BookingService.php#L204)

```php
ItemBagasi::query()->create([
    'pemesanan_id' => $booking->id,
    'deskripsi_item' => (string) $item['deskripsi_item'],
    'posisi_barang' =>
        $this->normalizeBaggagePlacement(
            data_get($item, 'posisi_barang')
        ),
    'jumlah_item' => max(
        1,
        (int) data_get($item, 'jumlah_item', 1)
    ),
    'foto_url' => $fotoUrls[0] ?? null,
    'foto_urls' => $fotoUrls,
    'biaya_item' => 0,
]);
```

Pada implementasi aktif, barang bawaan merupakan catatan operasional. Nilai `biaya_item` selalu
nol dan tidak menambah `total_harga`. Field ukuran atau berat lama tetap dipertahankan untuk
kompatibilitas data, tetapi UI aktif hanya mengirim deskripsi dan foto.

### 6.5 Keterkaitan

```text
Form konfirmasi
→ upload foto
→ path foto dikembalikan API
→ payload pemesanan menyertakan path
→ ItemBagasi disimpan
→ data dibaca pada detail pemesanan dan manifes pengemudi
```

### 6.6 Pengujian terkait

Lihat pengujian upload, batas 3 MB, serta tidak adanya biaya otomatis pada
[BookingFlowTest.php](../backend/tests/Feature/BookingFlowTest.php).

---

## 7. Pembayaran Tunai dan Payment Gateway Pakasir

### 7.1 Prinsip alur

Pemesanan dibuat terlebih dahulu tanpa metode pembayaran. Transaksi Pakasir baru dibuat setelah
penumpang memilih QRIS atau Virtual Account pada halaman pembayaran.

```text
Konfirmasi pemesanan
→ booking menunggu pembayaran
→ pilih metode
   ├─ Tunai → data lokal, fee 0, tanpa Pakasir
   └─ QRIS/VA → Transaction Create Pakasir
→ simpan snapshot nominal, fee, total, nomor bayar, dan expiry
→ webhook/refresh memverifikasi Transaction Detail
→ status berhasil
→ tiket diterbitkan
```

### 7.2 Transaction Create Pakasir

Sumber:
[PakasirService.php](../backend/app/Services/PakasirService.php#L89)

```php
$response = $this->post("transactioncreate/{$method}", [
    'project' => $this->project(),
    'order_id' => $orderId,
    'amount' => $this->normalizeAmount($amount),
    'api_key' => $this->apiKey(),
]);

$payment = $response['payment'] ?? null;
if (! is_array($payment)) {
    throw new PakasirException(
        'Response Pakasir tidak mengandung data pembayaran.'
    );
}
```

`amount` hanya nominal transaksi RuslyTravel. Fee tidak dibuat sendiri dan API key tidak
dikirim kembali ke Flutter.

### 7.3 Snapshot nominal, fee, dan total

Sumber:
[BookingService.php](../backend/app/Services/BookingService.php#L792)

```php
$paymentPayload = $this->pakasirService->createTransaction(
    $paymentMethod,
    $gatewayOrderId,
    $nominalTransaction,
);

$providerAmount = $this->providerRupiah(
    $paymentPayload,
    'amount',
    false
);
$providerFee = $this->providerRupiah(
    $paymentPayload,
    'fee',
    true
);
$customerTotal = $providerAmount + $providerFee;

$payment->update([
    'nominal_transaksi' => $providerAmount,
    'biaya_admin_provider' => $providerFee,
    'total_dibayar_penumpang' => $customerTotal,
    'jumlah_bayar' => $customerTotal,
    'payment_number' => $paymentNumber,
    'expired_at' => $expiredAt,
]);
```

Makna kolom:

- `nominal_transaksi`: pendapatan transaksi RuslyTravel;
- `biaya_admin_provider`: fee yang dikembalikan Pakasir;
- `total_dibayar_penumpang`: nominal ditambah fee;
- `payment_number`: QR string atau nomor Virtual Account;
- `expired_at`: masa berlaku efektif pembayaran.

Jika Pakasir mengembalikan fee nol, backend menyimpan nol. Sistem tidak menggantinya dengan nilai
hardcode.

### 7.4 Sinkronisasi batas waktu admin dan provider

```php
$adminDeadline = $lockedBooking->batas_pembayaran
    ?? $this->resolvePaymentLockDeadline(now());

$expiredAt = $providerExpiredAt !== null
    && $providerExpiredAt->lessThan($adminDeadline)
        ? $providerExpiredAt
        : $adminDeadline;
```

Batas pembayaran digital memakai waktu yang lebih cepat antara batas admin dan masa berlaku
provider. Hal ini mencegah Pakasir memperpanjang aturan yang telah ditetapkan admin.

### 7.5 Pembayaran tunai

Sumber:
[BookingService.php](../backend/app/Services/BookingService.php#L352) dan
[BookingService.php](../backend/app/Services/BookingService.php#L552)

```php
Pembayaran::query()->create([
    'pemesanan_id' => $lockedBooking->id,
    'order_id_gateway' => null,
    'metode_bayar' => 'cash',
    'nominal_transaksi' => $lockedBooking->total_harga,
    'biaya_admin_provider' => 0,
    'total_dibayar_penumpang' => $lockedBooking->total_harga,
    'status_bayar' => 'menunggu',
    'expired_at' => null,
]);

$lockedBooking->update(['batas_pembayaran' => null]);
```

Tunai tidak memanggil Pakasir, tidak mempunyai QRIS/VA, tidak mempunyai fee provider, dan tidak
mempunyai hitung mundur provider. Status baru menjadi berhasil setelah pengemudi yang ditugaskan
atau admin mengonfirmasi pembayaran.

### 7.6 Webhook diverifikasi ulang

Sumber:
[PaymentGatewayController.php](../backend/app/Http/Controllers/Api/PaymentGatewayController.php#L20)
dan [BookingService.php](../backend/app/Services/BookingService.php#L691)

```php
if (! $this->pakasirService->validateProject($payload['project'])) {
    throw ValidationException::withMessages([
        'project' => 'Identitas layanan Pakasir tidak sesuai.',
    ]);
}

if ($bookingAmount !== (int) $payload['amount']) {
    throw ValidationException::withMessages([
        'amount' => 'Nominal pembayaran dari Pakasir tidak sesuai.',
    ]);
}

$transaction = $this->pakasirService->transactionDetail(
    $this->paymentOrderId($booking),
    $bookingAmount,
);

if (
    $this->pakasirService->isCompletedStatus($payload['status'])
    && $this->pakasirService->isCompletedStatus($transaction['status'])
) {
    return $this->syncPakasirPayment(
        $booking,
        $transaction,
        $payload
    );
}
```

Webhook tidak langsung dipercaya. Backend memeriksa project, order ID, nominal, metode, dan
status menggunakan Transaction Detail API.

### 7.7 Idempotensi

Pada `syncPakasirPayment()`, booking yang sudah `terbayar` atau `selesai` tidak menerbitkan tiket
ulang. Request pemilihan metode yang berulang juga memakai record pembayaran yang telah ada.

Pengujian lengkap tersedia pada
[PaymentGatewayTest.php](../backend/tests/Feature/PaymentGatewayTest.php), termasuk:

- QRIS dan BRI VA;
- tunai tanpa panggilan Pakasir;
- fee sandbox nol;
- fee dan total pelanggan;
- respons provider tidak lengkap;
- timeout dan recovery tanpa invoice kedua;
- webhook salah nominal;
- webhook berulang;
- transaksi kedaluwarsa dan gagal.

---

## 8. Tiket Elektronik Berbasis QR Code

### 8.1 Dari pemesanan menjadi e-ticket

Tiket tidak dibuat ketika booking baru dibuat. Tiket diterbitkan setelah pembayaran digital
terverifikasi atau pembayaran tunai dikonfirmasi.

Sumber:
[BookingService.php](../backend/app/Services/BookingService.php#L1166)

```php
foreach ($booking->detailPemesanan as $detail) {
    $detail->kursiJadwal?->update([
        'status_kursi' => 'dipesan',
        'dikunci_oleh' => null,
        'locked_at' => null,
        'lock_expires_at' => null,
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
```

Satu `DetailPemesanan` menghasilkan satu `TiketElektronik`. `updateOrCreate()` mencegah tiket
duplikat, sedangkan UUID menjadi token rahasia yang dimasukkan ke QR.

### 8.2 Mengirim token tiket ke Flutter

Sumber:
[BookingController.php](../backend/app/Http/Controllers/Api/BookingController.php#L504)

```php
'ticket' => $detail->tiketElektronik
    ? [
        'kode_qr' => $detail->tiketElektronik->kode_qr,
        'token' => $detail->tiketElektronik->token_unik_qr,
        'sudah_dipindai' =>
            $detail->tiketElektronik->sudah_dipindai,
    ]
    : null,
```

### 8.3 Konversi token menjadi gambar QR

Sumber:
[kartu_tiket.dart](../frontend/lib/features/ticket/bagian_detail_tiket/kartu_tiket.dart#L48)
dan
[qr_dan_perjalanan_tiket.dart](../frontend/lib/features/ticket/bagian_detail_tiket/qr_dan_perjalanan_tiket.dart#L3)

```dart
final ticket = passenger.ticket!;
final qrData = ticket.token.isNotEmpty
    ? ticket.token
    : ticket.kodeQr;

QrImageView(
  data: qrData,
  size: 220,
  backgroundColor: Colors.white,
  eyeStyle: const QrEyeStyle(
    eyeShape: QrEyeShape.square,
    color: TravSTheme.navy,
  ),
);
```

`QrImageView` mengubah string UUID menjadi matriks QR. Gambar QR bukan disimpan sebagai file di
database; gambar dibentuk kembali dari token saat halaman tiket dirender.

### 8.4 Pemindaian QR oleh pengemudi

Sumber:
[pemindai_tiket.dart](../frontend/lib/features/home/bagian_beranda/pemindai_tiket.dart#L29)
dan [DriverController.php](../backend/app/Http/Controllers/Api/DriverController.php#L104)

```dart
final result = await widget.apiService.scanDriverTicket(
  token: widget.token,
  ticketToken: rawValue,
);
```

```php
$ticket = TiketElektronik::query()
    ->lockForUpdate()
    ->with('detailPemesanan.pemesanan.jadwal.pengemudi')
    ->where('token_unik_qr', $payload['token'])
    ->first();

if ((int) $jadwal->pengemudi_id !== (int) $pengemudi->id) {
    return response()->json([
        'success' => false,
        'message' =>
            'Tiket ini bukan bagian dari manifes pengemudi aktif.',
    ], 403);
}

if ($booking->status_pemesanan !== 'terbayar') {
    // tiket ditolak
}

if ($ticket->sudah_dipindai) {
    // tiket ditolak agar tidak digunakan dua kali
}

$ticket->update([
    'sudah_dipindai' => true,
    'dipindai_pada' => now(),
]);
$detail->update(['status_kehadiran' => 'hadir']);
```

Validasi scan mencakup:

1. token ada di database;
2. tiket merupakan bagian dari jadwal pengemudi yang login;
3. jadwal belum selesai;
4. pemesanan sudah terbayar;
5. tiket belum pernah dipindai.

`lockForUpdate()` juga mencegah dua request scan bersamaan meloloskan token yang sama.

### 8.5 Mengunduh tiket menjadi PNG

Sumber:
[travs_ticket_detail_page.dart](../frontend/lib/features/ticket/travs_ticket_detail_page.dart#L85)

```dart
final boundary =
    _ticketCaptureKey.currentContext?.findRenderObject()
        as RenderRepaintBoundary?;

final image = await boundary!.toImage(pixelRatio: 3);
final byteData = await image.toByteData(
  format: ui.ImageByteFormat.png,
);
final bytes = byteData!.buffer.asUint8List();

await Gal.putImageBytes(
  bytes,
  album: 'RuslyTravel',
  name: fileName.replaceFirst(RegExp(r'\.png$'), ''),
);
```

Card tiket yang terlihat dirender menjadi gambar resolusi tinggi, lalu disimpan ke album
RuslyTravel. Sebelum menyimpan, aplikasi memeriksa dan meminta izin galeri.

### 8.6 Pengujian terkait

- tiket diterbitkan setelah pembayaran:
  [BookingFlowTest.php](../backend/tests/Feature/BookingFlowTest.php);
- validasi scan dan scan satu kali:
  [DriverOperationsTest.php](../backend/tests/Feature/DriverOperationsTest.php);
- token palsu:
  [SecurityAndTDTest.php](../backend/tests/Feature/SecurityAndTDTest.php);
- tampilan dan download tiket:
  [travs_ticket_download_test.dart](../frontend/test/travs_ticket_download_test.dart).

---

## 9. Perjalanan dan Manifes Penumpang

### 9.1 Hak akses perjalanan

Sumber:
[DriverController.php](../backend/app/Http/Controllers/Api/DriverController.php#L467)

```php
private function ensureDriverOwnsTrip(
    Request $request,
    Jadwal $jadwal
): Pengemudi {
    $pengemudi = $this->activeDriverFor($request);

    if ((int) $jadwal->pengemudi_id !== (int) $pengemudi->id) {
        abort(response()->json([
            'success' => false,
            'message' =>
                'Perjalanan ini bukan milik pengemudi aktif.',
        ], 403));
    }

    return $pengemudi;
}
```

Middleware memastikan peran pengguna adalah pengemudi. Controller kemudian memastikan jadwal
tersebut benar-benar ditugaskan kepada profil pengemudi yang sedang login.

### 9.2 Booking yang boleh masuk manifes

Sumber:
[DriverController.php](../backend/app/Http/Controllers/Api/DriverController.php#L530)

```php
return $query->where(function ($visibleQuery) {
    $visibleQuery
        ->whereIn('status_pemesanan', ['terbayar', 'selesai'])
        ->orWhere(function ($cashQuery) {
            $cashQuery
                ->where(
                    'status_pemesanan',
                    'menunggu_pembayaran'
                )
                ->whereHas(
                    'pembayaran',
                    fn ($paymentQuery) => $paymentQuery
                        ->where('metode_bayar', 'cash')
                        ->where('status_bayar', 'menunggu')
                );
        });
});
```

Manifes menampilkan:

- pemesanan terbayar atau selesai;
- pemesanan tunai yang masih menunggu konfirmasi pengemudi.

Pemesanan digital gagal, kedaluwarsa, atau dibatalkan tidak masuk manifes aktif.

### 9.3 Isi data manifes

Sumber:
[DriverController.php](../backend/app/Http/Controllers/Api/DriverController.php#L623)

Data yang diformat meliputi nama, nomor HP, WhatsApp, kursi, status kehadiran, titik jemput,
titik turun, barang bawaan, status pembayaran, dan tiket.

```php
return [
    'nama_penumpang' => $detail->nama_penumpang,
    'no_hp_penumpang' => $detail->no_hp_penumpang,
    'whatsapp_url_penumpang' =>
        $this->whatsappUrl($detail->no_hp_penumpang),
    'nomor_kursi' => (int) $detail->nomor_kursi,
    'status_kehadiran' => $detail->status_kehadiran,
    'pickup' => [
        'latitude' => (float) $booking->lintang_jemput,
        'longitude' => (float) $booking->bujur_jemput,
        'address' => $booking->alamat_jemput,
    ],
    'dropoff' => [
        'latitude' => (float) $booking->lintang_turun,
        'longitude' => (float) $booking->bujur_turun,
        'address' => $booking->alamat_turun,
    ],
    'baggage' => [
        'label' => $baggageLabel,
        'foto_urls' => $baggagePhotoUrls,
    ],
];
```

### 9.4 Transisi status perjalanan

Sumber:
[DriverController.php](../backend/app/Http/Controllers/Api/DriverController.php#L374)

```php
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

Status tidak dapat langsung diubah dari `dibuka` ke `selesai`. Pengemudi harus menjalankan
`Mulai Perjalanan` terlebih dahulu agar status menjadi `berangkat`.

### 9.5 Tombol Turun hanya aktif setelah Mulai Perjalanan

Sumber:
[detail_perjalanan_pengemudi.dart](../frontend/lib/features/home/bagian_beranda/detail_perjalanan_pengemudi.dart#L888)
dan
[detail_perjalanan_pengemudi.dart](../frontend/lib/features/home/bagian_beranda/detail_perjalanan_pengemudi.dart#L1420)

```dart
_ManifestPassengerTile(
  passenger: passenger,
  journeyStarted: _record.status == 'berangkat',
  onDropoff: passenger.canDropoff
      ? () => _handleDropoff(passenger)
      : null,
);

ElevatedButton.icon(
  onPressed: (isDroppingOff || !journeyStarted)
      ? null
      : onDropoff,
  label: Text(
    isDroppingOff ? 'Memproses...' : 'Turun',
  ),
);
```

`journeyStarted` bernilai benar hanya ketika status jadwal `berangkat`. Flutter memberi nilai
`null` pada `onPressed` sehingga tombol dinonaktifkan.

**Temuan audit yang harus dijelaskan dengan jujur:** endpoint `markPassengerDropoff()` telah
memeriksa bahwa penumpang berstatus `hadir`, tetapi belum melakukan pemeriksaan eksplisit bahwa
status jadwal harus `berangkat`. Karena itu, aturan “Mulai Perjalanan sebelum Turun” saat ini
terdapat pada lapisan UI. Penguatan validasi backend dapat dilakukan pada sesi terpisah agar
request langsung ke API juga tunduk pada aturan yang sama.

### 9.6 Pencatatan biaya operasional

Sumber:
[DriverController.php](../backend/app/Http/Controllers/Api/DriverController.php#L791)

```php
if (
    ! in_array(
        $jadwal->status,
        ['dibuka', 'ditutup', 'berangkat'],
        true
    )
) {
    return response()->json([
        'success' => false,
        'message' =>
            'Biaya hanya dapat dicatat pada jadwal tugas yang sedang aktif.',
    ], 422);
}

$expense = BiayaOperasionalPerjalanan::query()->create([
    'jadwal_id' => $jadwal->id,
    'jenis_biaya' => $validated['jenis_biaya'],
    'nominal' => $validated['nominal'],
    'tanggal_biaya' => $validated['tanggal'],
    'keterangan' => $validated['keterangan'],
    'bukti_url' => $buktiPath,
    'created_by' => $request->user()->id,
    'status_verifikasi' => 'disetujui',
]);
```

Pengemudi hanya dapat mencatat biaya untuk jadwal miliknya yang masih aktif. Foto bukti wajib
JPG, PNG, atau WEBP maksimal 3 MB.

### 9.7 Pengujian terkait

Lihat [DriverOperationsTest.php](../backend/tests/Feature/DriverOperationsTest.php), yang menguji
manifes, kepemilikan jadwal, Mulai Perjalanan, pembayaran tunai, QR, pickup, dropoff, dan biaya
operasional.

---

## 10. Pengelolaan Data Operasional Admin dan Laporan

### 10.1 Pembuatan jadwal dan estimasi tiba

Sumber:
[JadwalController.php](../backend/app/Http/Controllers/Web/Admin/JadwalController.php#L300)

```php
$rute = Rute::query()->findOrFail($validated['rute_id']);
$durasiRuteMenit = (int) $rute->estimasi_durasi_menit;
$departureTime = Carbon::parse($validated['waktu_berangkat']);

$validated['estimasi_tiba'] = $departureTime
    ->copy()
    ->addMinutes($durasiRuteMenit);
```

Admin tidak menentukan estimasi tiba secara terpisah. Backend menghitungnya dari waktu berangkat
ditambah durasi rute, termasuk jika hasilnya melewati tengah malam.

### 10.2 Validasi bentrok pengemudi

Sumber:
[DriverAvailabilityService.php](../backend/app/Services/DriverAvailabilityService.php#L23)

```php
return Jadwal::query()
    ->where('pengemudi_id', $driverId)
    ->whereIn(
        'status',
        ['draft', 'dibuka', 'ditutup', 'berangkat']
    )
    ->whereDate(
        'waktu_berangkat',
        $departureTime->toDateString()
    )
    ->when(
        $currentJadwal,
        fn ($query) =>
            $query->whereKeyNot($currentJadwal->id)
    )
    ->first();
```

Aturan aktif sekarang bersifat **satu tugas aktif per pengemudi per tanggal**, bukan sekadar
deteksi interval jam yang bertumpang tindih. Jadwal berstatus selesai tidak lagi dianggap
konflik.

### 10.3 Validasi bentrok kendaraan

Sumber:
[JadwalController.php](../backend/app/Http/Controllers/Web/Admin/JadwalController.php#L392)

```php
return Jadwal::query()
    ->where('kendaraan_id', $vehicleId)
    ->whereIn(
        'status',
        $this->driverAvailabilityService
            ->activeScheduleStatuses()
    )
    ->whereDate(
        'waktu_berangkat',
        $departureTime->toDateString()
    )
    ->when(
        $currentJadwal,
        fn ($query) =>
            $query->whereKeyNot($currentJadwal->id)
    )
    ->first();
```

Kendaraan yang sama tidak boleh dipakai dua jadwal aktif pada tanggal yang sama.

### 10.4 Pembuatan denah kursi otomatis

Sumber:
[JadwalController.php](../backend/app/Http/Controllers/Web/Admin/JadwalController.php#L497)

```php
for ($seatNumber = 1; $seatNumber <= $kapasitas; $seatNumber++) {
    KursiJadwal::query()->firstOrCreate(
        [
            'jadwal_id' => $jadwal->id,
            'nomor_kursi' => $seatNumber,
        ],
        [
            'status_kursi' =>
                $closedSeats->contains($seatNumber)
                    ? 'nonaktif'
                    : 'tersedia',
        ],
    );
}
```

Kursi dibuat per jadwal berdasarkan kapasitas armada. Kursi nonaktif juga berlaku per jadwal,
bukan mengubah kapasitas permanen kendaraan.

### 10.5 Pendapatan dashboard

Sumber:
[DashboardController.php](../backend/app/Http/Controllers/Web/Admin/DashboardController.php#L18)

```php
$pendapatanRaw = Pembayaran::query()
    ->join(
        'pemesanan',
        'pemesanan.id',
        '=',
        'pembayaran.pemesanan_id'
    )
    ->selectRaw(
        'DATE(pembayaran.dibayar_pada) as tanggal,
         SUM(COALESCE(
             pembayaran.nominal_transaksi,
             pemesanan.total_harga
         )) as total'
    )
    ->where('pembayaran.status_bayar', 'berhasil')
    ->whereIn(
        'pemesanan.status_pemesanan',
        ['terbayar', 'selesai']
    );
```

Pendapatan menggunakan `nominal_transaksi`, bukan `total_dibayar_penumpang`. Fee Pakasir tidak
dianggap sebagai pendapatan RuslyTravel.

### 10.6 Rute paling banyak dikunjungi

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

Statistik dihitung dari pemesanan selesai. Tidak diperlukan kolom total kunjungan pada tabel
`rute` karena nilai dapat berubah dan lebih aman dihitung dari transaksi sumber.

### 10.7 Filter laporan dipakai ulang oleh tampilan dan export

Sumber:
[LaporanController.php](../backend/app/Http/Controllers/Web/Admin/LaporanController.php#L246)

```php
if ($request->filled('status')) {
    $query->where(
        'status_pemesanan',
        $request->string('status')
    );
}

if ($request->filled('tanggal_mulai')) {
    $query->whereDate(
        'created_at',
        '>=',
        $request->date('tanggal_mulai')
    );
}

if ($request->filled('tanggal_selesai')) {
    $query->whereDate(
        'created_at',
        '<=',
        $request->date('tanggal_selesai')
    );
}
```

Method query yang sama dipanggil oleh halaman laporan dan fungsi export. Karena itu, status serta
rentang tanggal yang dipilih admin menghasilkan data layar dan file yang konsisten.

### 10.8 Export CSV dan XLS

Sumber:
[LaporanController.php](../backend/app/Http/Controllers/Web/Admin/LaporanController.php#L370)

```php
return match ($format) {
    'xls' => $this->downloadExcelXml(
        $rows,
        "{$filename}.xls",
        $baseName,
        $request,
        $meta
    ),
    default => $this->downloadCsv(
        $rows,
        "{$filename}.csv"
    ),
};
```

CSV menggunakan pemisah titik koma dan BOM UTF-8 agar teks Indonesia terbaca baik oleh Excel.
XLS dibentuk menggunakan SpreadsheetML dan dilengkapi AutoFilter:

```php
echo '<AutoFilter x:Range="R6C1:R'
    .$lastDataRow
    .'C'
    .$columnCount
    .'" xmlns="urn:schemas-microsoft-com:office:excel"/>';
```

### 10.9 Pengujian terkait

- konflik pengemudi dan kendaraan:
  [SecurityAndTDTest.php](../backend/tests/Feature/SecurityAndTDTest.php);
- manajemen admin dan export:
  [AdminOperationsTest.php](../backend/tests/Feature/AdminOperationsTest.php);
- pendapatan dan pemisahan fee:
  [PaymentRevenueReportTest.php](../backend/tests/Feature/PaymentRevenueReportTest.php).

---

## 11. Validasi dan Fitur Pendukung yang Berpotensi Ditanyakan

### 11.1 Pembatasan route berdasarkan peran

Sumber:
[api.php](../backend/routes/api.php)

```php
Route::middleware([
    'auth:sanctum',
    'role:penumpang',
])->group(function () {
    // endpoint pemesanan penumpang
});

Route::middleware([
    'auth:sanctum',
    'role:pengemudi',
])->group(function () {
    // endpoint manifes, QR, dan perjalanan
});
```

Token yang valid belum tentu boleh mengakses semua endpoint. Middleware `role` membatasi fitur
berdasarkan peran, lalu controller memeriksa kepemilikan data.

### 11.2 Penutupan pemesanan satu jam sebelum berangkat

Sumber:
[ScheduleLifecycleService.php](../backend/app/Services/ScheduleLifecycleService.php)

```php
private const BOOKING_CLOSE_HOURS_BEFORE_DEPARTURE = 1;

$latestBookableDeparture = now()->copy()->addHours(
    self::BOOKING_CLOSE_HOURS_BEFORE_DEPARTURE
);

Jadwal::query()
    ->where('status', 'dibuka')
    ->where('waktu_berangkat', '<=', $latestBookableDeparture)
    ->update(['status' => 'ditutup']);
```

Pada implementasi lengkap, query juga memperhatikan hari agar jadwal hari lain tidak ditutup
terlalu dini.

### 11.3 Scheduler setiap menit

Sumber:
[console.php](../backend/routes/console.php)

```php
Schedule::command('booking:auto-cancel-expired')
    ->everyMinute();
Schedule::command('travs:close-near-departure-schedules')
    ->everyMinute();
Schedule::command('notifications:departure-reminders')
    ->everyFifteenMinutes();
```

Scheduler melepaskan kunci kedaluwarsa, membatalkan pembayaran yang habis, menutup jadwal, dan
mengirim pengingat.

### 11.4 Countdown aman ketika aplikasi kembali dari background

Sumber:
[hitung_mundur.dart](../frontend/lib/features/payment/bagian_pembayaran/hitung_mundur.dart)

```dart
@override
void didChangeAppLifecycleState(AppLifecycleState state) {
  if (state == AppLifecycleState.resumed) {
    _updateRemaining();
    _startTimer();
  }
}

Duration _calculateRemaining() {
  final difference =
      _effectiveDeadline!.difference(DateTime.now());
  return difference.isNegative
      ? Duration.zero
      : difference;
}
```

Countdown tidak hanya mengurangi variabel setiap detik. Nilainya dihitung kembali dari timestamp
backend sehingga tetap benar setelah aplikasi lama berada di background dan tidak pernah negatif.

### 11.5 Rate limiting

Sumber:
[AppServiceProvider.php](../backend/app/Providers/AppServiceProvider.php#L32)

```php
RateLimiter::for('travs-auth', function (Request $request) {
    return Limit::perMinute(8)
        ->by($this->rateLimitKey($request));
});

RateLimiter::for(
    'travs-verification-resend',
    function (Request $request) {
        return Limit::perMinute(3)
            ->by($this->emailRateLimitKey($request));
    }
);
```

Rate limiter mengurangi brute force, spam OTP, klik berulang, dan request upload berlebihan.

### 11.6 Pembatalan otomatis dan pelepasan kursi

Sumber:
[BookingService.php](../backend/app/Services/BookingService.php#L246) dan
[BookingService.php](../backend/app/Services/BookingService.php#L1330)

Ketika pembayaran melewati batas:

1. backend mencoba mengecek status Pakasir lebih dahulu;
2. jika belum berhasil, status booking menjadi `dibatalkan`;
3. pembayaran menjadi `kedaluwarsa`;
4. detail kursi dilepas;
5. kursi kembali `tersedia`.

Langkah pengecekan provider sebelum pembatalan mencegah pembayaran yang sebenarnya berhasil
dianggap kedaluwarsa hanya karena webhook terlambat.

---

## 12. Koreksi Narasi Implementasi BAB IV

Beberapa kalimat pada narasi BAB IV perlu memakai rumusan berikut agar sama dengan kode:

1. **Seat lock**  
   Jangan menulis “server mengunci kursi segera ketika kursi disentuh”. Tuliskan:
   “Kursi dipilih terlebih dahulu pada antarmuka, kemudian dikunci secara atomik pada backend
   ketika penumpang melanjutkan ke halaman konfirmasi.”

2. **Batas pembayaran digital**  
   Jangan menulis bahwa countdown selalu mengikuti provider. Tuliskan:
   “Batas pembayaran efektif menggunakan waktu yang lebih cepat antara konfigurasi admin dan
   masa berlaku transaksi Pakasir.”

3. **Konflik jadwal**  
   Implementasi sekarang menolak pengemudi atau kendaraan yang sudah mempunyai jadwal aktif pada
   tanggal yang sama. Ini lebih ketat daripada sekadar mendeteksi jam yang bertumpang tindih.

4. **Barang bawaan**  
   Implementasi aktif menyimpan deskripsi dan maksimal tiga foto tanpa menghitung biaya otomatis.
   Field berat dan ukuran lama masih ada untuk kompatibilitas, tetapi bukan input utama UI saat
   ini.

5. **Pembayaran digital**  
   Fee berasal dari Pakasir. Total pelanggan yang ditampilkan backend adalah nominal transaksi
   ditambah fee respons provider. Fee nol pada sandbox harus tetap dilaporkan sebagai nilai
   provider, bukan diganti hardcode.

6. **Tombol Turun**  
   Flutter menonaktifkan tombol sampai status perjalanan `berangkat`. Dokumen tidak boleh
   mengklaim aturan ini telah berlapis penuh sampai validasi status yang sama ditambahkan pada
   endpoint backend.

---

## 13. Ringkasan Hubungan Fitur dengan Tabel

| Fitur | Tabel utama |
|---|---|
| Autentikasi dan OTP | `pengguna`, `personal_access_tokens`, `password_reset_tokens` |
| Jadwal dan pemesanan | `jadwal`, `rute`, `kendaraan`, `pengemudi`, `pemesanan` |
| Seat lock | `kursi_jadwal`, `detail_pemesanan`, `pengaturan_sistem` |
| Titik jemput/turun | kolom lokasi pada `pemesanan` |
| Barang bawaan | `item_bagasi`, `pemesanan` |
| Pembayaran | `pembayaran`, `pemesanan` |
| Tiket QR | `tiket_elektronik`, `detail_pemesanan` |
| Manifes | gabungan `jadwal`, `pemesanan`, `detail_pemesanan`, `item_bagasi`, dan `pembayaran` |
| Laporan admin | agregasi tabel transaksi dan operasional, bukan tabel rekap terpisah |

---

## 14. Daftar File Utama untuk Dipelajari

### Backend

1. [AuthController.php](../backend/app/Http/Controllers/Api/AuthController.php)
2. [BookingController.php](../backend/app/Http/Controllers/Api/BookingController.php)
3. [BookingService.php](../backend/app/Services/BookingService.php)
4. [PakasirService.php](../backend/app/Services/PakasirService.php)
5. [PaymentGatewayController.php](../backend/app/Http/Controllers/Api/PaymentGatewayController.php)
6. [DriverController.php](../backend/app/Http/Controllers/Api/DriverController.php)
7. [JadwalController.php](../backend/app/Http/Controllers/Web/Admin/JadwalController.php)
8. [DriverAvailabilityService.php](../backend/app/Services/DriverAvailabilityService.php)
9. [LaporanController.php](../backend/app/Http/Controllers/Web/Admin/LaporanController.php)
10. [DashboardController.php](../backend/app/Http/Controllers/Web/Admin/DashboardController.php)
11. [SeatAvailabilityChanged.php](../backend/app/Events/SeatAvailabilityChanged.php)
12. [KursiJadwalObserver.php](../backend/app/Observers/KursiJadwalObserver.php)
13. [SeatAvailabilityRealtimeService.php](../backend/app/Services/SeatAvailabilityRealtimeService.php)
14. [api.php](../backend/routes/api.php)
15. [console.php](../backend/routes/console.php)

### Flutter

1. [travs_auth_service.dart](../frontend/lib/core/services/travs_auth_service.dart)
2. [travs_schedule_detail_page.dart](../frontend/lib/features/schedule/travs_schedule_detail_page.dart)
3. [travs_seat_realtime_service.dart](../frontend/lib/core/services/travs_seat_realtime_service.dart)
4. [travs_payment_preview_page.dart](../frontend/lib/features/payment/travs_payment_preview_page.dart)
5. [travs_location_service.dart](../frontend/lib/core/services/travs_location_service.dart)
6. [travs_pickup_map_picker_page.dart](../frontend/lib/features/pickup/travs_pickup_map_picker_page.dart)
7. [travs_ticket_detail_page.dart](../frontend/lib/features/ticket/travs_ticket_detail_page.dart)
8. [kartu_tiket.dart](../frontend/lib/features/ticket/bagian_detail_tiket/kartu_tiket.dart)
9. [qr_dan_perjalanan_tiket.dart](../frontend/lib/features/ticket/bagian_detail_tiket/qr_dan_perjalanan_tiket.dart)
10. [pemindai_tiket.dart](../frontend/lib/features/home/bagian_beranda/pemindai_tiket.dart)
11. [detail_perjalanan_pengemudi.dart](../frontend/lib/features/home/bagian_beranda/detail_perjalanan_pengemudi.dart)

---

## 15. Jawaban Ringkas yang Dapat Digunakan saat Sidang

**Mengapa kursi tidak dapat dipesan dua pengguna?**  
Karena backend memakai transaksi database dan `lockForUpdate()` pada baris kursi. Reverb hanya
memperbarui UI lebih cepat dan bukan pengaman utama.

**Mengapa satu pemesanan dapat mempunyai beberapa tiket?**  
Karena satu `Pemesanan` mempunyai banyak `DetailPemesanan`, dan setiap detail penumpang mempunyai
satu `TiketElektronik`.

**Apa isi QR tiket?**  
QR berisi UUID unik, bukan data pribadi lengkap. Backend mencari UUID tersebut dan memvalidasi
kepemilikan jadwal, status pembayaran, serta riwayat pemindaian.

**Mengapa fee Pakasir tidak menjadi pendapatan?**  
Karena fee merupakan biaya provider yang dibayar penumpang. Pendapatan RuslyTravel memakai
`nominal_transaksi`, sedangkan total pelanggan memakai `total_dibayar_penumpang`.

**Mengapa webhook masih diperiksa dengan Transaction Detail API?**  
Untuk memastikan project, order ID, nominal, metode, dan status benar-benar sesuai dengan
transaksi yang tercatat di Pakasir.

**Apa perbedaan geocoding dan reverse geocoding?**  
Geocoding mengubah alamat menjadi koordinat. Reverse geocoding mengubah koordinat menjadi alamat.

**Mengapa data laporan tidak dihitung di Blade?**  
Controller melakukan query, filter, dan agregasi. Blade hanya menampilkan hasil agar perhitungan
tidak tersebar dan tidak mudah berbeda dengan hasil export.

**Mengapa harga pemesanan lama tidak berubah saat harga rute berubah?**  
Karena `subtotal_tiket` dan `total_harga` disimpan sebagai snapshot pada saat booking dibuat.

---

## 16. Hasil Verifikasi Dokumen

Isi panduan ini diperiksa terhadap source code aktif dan pengujian otomatis pada 28 Juli 2026.
Pengujian yang dijalankan meliputi:

- autentikasi dan verifikasi email;
- alur pemesanan, seat lock, lokasi, serta barang bawaan;
- sinyal ketersediaan kursi melalui Reverb;
- QRIS, Virtual Account, tunai, webhook, fee, dan total pembayaran;
- otorisasi pengemudi, manifes, QR tiket, kehadiran, turun, dan biaya operasional;
- keamanan akses berdasarkan peran serta pencegahan kursi ganda;
- pemisahan pendapatan, biaya provider, dan total yang dibayar penumpang.

Hasilnya adalah **98 test lulus dengan 651 assertion**. Selain itu, pemeriksaan tautan menemukan
**90 tautan source lokal dan seluruhnya valid**. Pengujian ini membuktikan perilaku yang telah
memiliki test otomatis, tetapi tidak menggantikan pengujian manual antarmuka pada perangkat.
