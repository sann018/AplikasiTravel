# Potongan Kode Fitur Utama

Setiap bagian memuat lokasi sumber, potongan kode, fungsi, dan keterkaitan. Potongan sengaja
dipilih singkat agar mudah dijelaskan saat sidang.

## 1. Penguncian Kursi saat Masuk Konfirmasi

### Potongan Backend

```php
return DB::transaction(function () use ($user, $scheduleId, $seatNumbers) {
    $schedule = Jadwal::query()
        ->lockForUpdate()
        ->findOrFail($scheduleId);

    $selectedSeats = $this->loadSeatRowsForUpdate(
        $schedule->id,
        $seatNumbers,
    );
    $this->ensureSeatsCanBeUsedByUser($selectedSeats, $user->id);

    $now = now();
    $formDeadline = $this->resolveFormLockDeadline($now);

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

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L42)

### Fungsi

Mencegah dua pengguna mendapatkan kursi yang sama. `DB::transaction()` membuat perubahan
bersifat atomik, sedangkan `lockForUpdate()` mencegah request lain mengubah baris yang sama sampai
transaksi pertama selesai.

### Keterkaitan

Dipanggil endpoint `POST /api/penumpang/bookings/lock-form` sebelum Flutter membuka halaman
konfirmasi.

## 2. Penolakan Kursi yang Dikunci Pengguna Lain

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

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L1478)

### Makna

Status visual di Flutter bukan pengamanan utama. Sekalipun pengguna memanggil endpoint secara
manual, backend tetap menolak kursi milik sesi lain.

## 3. Kursi Terkunci Berwarna Abu-Abu

```dart
'terkunci' => (
  background: const Color(0xFF94A3B8),
  foreground: Colors.white,
  border: Colors.white,
),
```

Sumber:
[`frontend/lib/features/schedule/bagian_detail_jadwal/widget_kursi.dart`](../frontend/lib/features/schedule/bagian_detail_jadwal/widget_kursi.dart#L46)

### Fungsi

Menerjemahkan status `terkunci` dari API menjadi warna abu-abu. `enabled` juga menentukan apakah
`onTap` dipasang, sehingga kursi tidak sekadar berubah warna tetapi tidak dapat dipilih.

## 4. Validasi Sesi Form sebelum Membuat Pemesanan

```php
$hasInvalidLock = $selectedSeats->contains(
    function (KursiJadwal $seat) use ($userId, $now) {
        return $seat->status_kursi !== 'terkunci'
            || (int) $seat->dikunci_oleh !== $userId
            || $seat->lock_expires_at === null
            || $seat->lock_expires_at->lessThanOrEqualTo($now);
    }
);

if ($hasInvalidLock) {
    throw ValidationException::withMessages([
        'seat_numbers' =>
            'Sesi konfirmasi pemesanan telah berakhir. '
            .'Kursi sudah dilepas, silakan pilih jadwal dan kursi kembali.',
    ]);
}
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L1520)

### Makna

Pembuatan booking hanya boleh memakai kunci yang masih aktif dan dimiliki pengguna yang sama.
Kursi berstatus tersedia tidak dianggap cukup karena sesi lama dapat sudah dilepas.

### Fakta Alur

Pada tahap form belum ada record `pemesanan`. Karena itu, sesi form yang habis hanya melepaskan
kunci kursi; tidak ada booking yang dapat diberi status dibatalkan. Record pemesanan baru dibuat
setelah form valid dikirim.

## 5. Pembuatan Pemesanan dan Perpanjangan Kunci

```php
$totals = $this->calculator->calculate(
    $schedule,
    $seatNumbers->count(),
);
$paymentDeadline = $this->resolvePaymentLockDeadline($now, $schedule);

$booking = Pemesanan::query()->create([
    'pengguna_id' => $user->id,
    'jadwal_id' => $schedule->id,
    'kode_booking' => $this->generateBookingCode(),
    'jumlah_kursi' => $seatNumbers->count(),
    'batas_pembayaran' => $paymentDeadline,
    'subtotal_tiket' => $totals['subtotal_tiket'],
    'total_harga' => $totals['total_harga'],
    'status_pemesanan' => 'menunggu_pembayaran',
]);

$seat->update([
    'status_kursi' => 'terkunci',
    'lock_expires_at' => $paymentDeadline,
]);
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L118)

### Fungsi

Mengubah fase dari “kunci form” menjadi “booking menunggu pembayaran”. Metode bayar masih belum
dipilih dan tiket belum dibuat.

## 6. Pelepasan Kunci Form yang Kedaluwarsa

```php
$expiredFormLockIds = KursiJadwal::query()
    ->where('status_kursi', 'terkunci')
    ->whereNotNull('lock_expires_at')
    ->where('lock_expires_at', '<=', $now)
    ->doesntHave('detailPemesanan')
    ->pluck('id');

KursiJadwal::query()
    ->whereKey($expiredFormLockIds)
    ->update([
        'status_kursi' => 'tersedia',
        'dikunci_oleh' => null,
        'locked_at' => null,
        'lock_expires_at' => null,
    ]);
```

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L240)

### Makna

`doesntHave('detailPemesanan')` membatasi cleanup pada kunci form yang belum berubah menjadi
booking. Kunci pembayaran diproses melalui alur pembatalan booking.

## 7. Pembatalan Otomatis Pembayaran Kedaluwarsa

```php
$booking = Pemesanan::query()
    ->lockForUpdate()
    ->with(['detailPemesanan', 'pembayaran'])
    ->find($bookingId);

$booking->detailPemesanan()->delete();
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

Sumber: [`backend/app/Services/BookingService.php`](../backend/app/Services/BookingService.php#L1318)

### Fungsi

Membatalkan booking yang melewati batas pembayaran, mengembalikan kursi menjadi tersedia, dan
mengubah pembayaran pending menjadi kedaluwarsa.

### Dampak Data

Implementasi aktif menghapus `detail_pemesanan` ketika booking kedaluwarsa. Induk pemesanan dan
pembayaran tetap tersimpan untuk riwayat, tetapi detail nama/kursi tidak dipertahankan. Fakta ini
perlu disebut jika penguji menanyakan histori booking batal.

## 8. Scheduler Batas Waktu

```php
Schedule::command('booking:auto-cancel-expired')->everyMinute();
Schedule::command('travs:close-near-departure-schedules')->everyMinute();
Schedule::command('notifications:departure-reminders')
    ->everyFifteenMinutes();
```

Sumber: [`backend/routes/console.php`](../backend/routes/console.php#L39)

### Fungsi

- setiap menit: melepaskan kunci form dan membatalkan pembayaran kedaluwarsa;
- setiap menit: menutup jadwal mendekati keberangkatan;
- setiap 15 menit: memproses pengingat satu jam sebelum keberangkatan.

Scheduler harus berjalan pada environment deployment. Tanpa worker cron `schedule:run`, cleanup
masih dapat terjadi saat endpoint tertentu dipanggil, tetapi tidak seproaktif scheduler.

## 9. Countdown yang Aman terhadap Background

```dart
@override
void didChangeAppLifecycleState(AppLifecycleState state) {
  if (state == AppLifecycleState.resumed) {
    _updateRemaining();
    _startTimer();
  }
}

Duration _calculateRemaining() {
  final deadline = _effectiveDeadline;
  if (deadline == null) return Duration.zero;

  final difference = deadline.difference(DateTime.now());
  return difference.isNegative ? Duration.zero : difference;
}
```

Sumber:
[`frontend/lib/features/payment/bagian_pembayaran/hitung_mundur.dart`](../frontend/lib/features/payment/bagian_pembayaran/hitung_mundur.dart#L57)

### Fungsi

Menghitung ulang dari timestamp API ketika aplikasi kembali aktif, menghentikan timer saat nol,
dan membatalkan timer pada `dispose()`.

## 10. Konsep Titik Jemput dan Turun

### 10.1 GPS dan Izin

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

Sumber:
[`frontend/lib/core/services/travs_location_service.dart`](../frontend/lib/core/services/travs_location_service.dart#L59)

### 10.2 Reverse Geocoding

```dart
final placemarks = await placemarkFromCoordinates(
  latitude,
  longitude,
);
```

Jika layanan native gagal, aplikasi meminta Nominatim:

```dart
final uri = Uri.https(
  'nominatim.openstreetmap.org',
  '/reverse',
  {
    'format': 'jsonv2',
    'lat': latitude.toString(),
    'lon': longitude.toString(),
  },
);
```

Sumber:
[`frontend/lib/core/services/travs_location_service.dart`](../frontend/lib/core/services/travs_location_service.dart#L119)

### 10.3 Pemilihan Koordinat pada Peta

```dart
FlutterMap(
  options: MapOptions(
    initialCenter: _selectedPoint,
    onTap: (_, point) => _selectPoint(point),
    onPositionChanged: _handleMapPositionChanged,
  ),
  children: [
    TileLayer(
      urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    ),
  ],
)
```

Sumber:
[`frontend/lib/features/pickup/travs_pickup_map_picker_page.dart`](../frontend/lib/features/pickup/travs_pickup_map_picker_page.dart#L287)

### Konsep yang Digunakan

- **geolocation**: mengambil posisi perangkat;
- **latitude/longitude**: menyimpan koordinat titik;
- **reverse geocoding**: koordinat menjadi alamat;
- **forward geocoding**: nama kota/tujuan menjadi koordinat;
- **map picker**: pengguna mengoreksi titik secara visual;
- **fallback manual**: alamat tetap dapat diperjelas melalui catatan.

Sistem tidak menghitung optimasi rute atau jarak jalan penumpang. Koordinat dipakai sebagai
referensi pickup/dropoff dan dapat dibuka pengemudi pada Google Maps.

## 11. Validasi Konflik Pengemudi

```php
return Jadwal::query()
    ->where('pengemudi_id', $driverId)
    ->whereIn('status', [
        'draft',
        'dibuka',
        'ditutup',
        'berangkat',
    ])
    ->whereDate(
        'waktu_berangkat',
        $departureTime->toDateString(),
    )
    ->when(
        $currentJadwal,
        fn ($query) => $query->whereKeyNot($currentJadwal->id),
    )
    ->first();
```

Sumber:
[`backend/app/Services/DriverAvailabilityService.php`](../backend/app/Services/DriverAvailabilityService.php#L18)

### Makna

Pengemudi ditolak jika mempunyai jadwal aktif lain pada **tanggal yang sama**. Implementasi ini
lebih ketat daripada sekadar waktu yang sama; dua jadwal berbeda jam pada hari yang sama tetap
dianggap konflik.

## 12. Validasi Konflik Kendaraan

```php
return Jadwal::query()
    ->where('kendaraan_id', $vehicleId)
    ->whereIn(
        'status',
        $this->driverAvailabilityService->activeScheduleStatuses(),
    )
    ->whereDate(
        'waktu_berangkat',
        $departureTime->toDateString(),
    )
    ->when(
        $currentJadwal,
        fn ($query) => $query->whereKeyNot($currentJadwal->id),
    )
    ->first();
```

Sumber:
[`backend/app/Http/Controllers/Web/Admin/JadwalController.php`](../backend/app/Http/Controllers/Web/Admin/JadwalController.php#L390)

### Keterkaitan

Dipanggil setelah validasi kendaraan aktif, rute memiliki durasi, dan pengemudi aktif serta
terverifikasi. Jika konflik ditemukan, error ditempatkan pada field kendaraan/pengemudi.

## 13. Penutupan Booking Satu Jam sebelum Berangkat

```php
$latestBookableDeparture = $now->copy()->addHours(1);

Jadwal::query()
    ->where('status', 'dibuka')
    ->whereDate('waktu_berangkat', $today)
    ->where('waktu_berangkat', '<=', $latestBookableDeparture)
    ->update(['status' => 'ditutup']);
```

Sumber:
[`backend/app/Services/ScheduleLifecycleService.php`](../backend/app/Services/ScheduleLifecycleService.php#L9)

### Fungsi

Mencegah pemesanan terlalu dekat dengan keberangkatan dan mempertahankan jadwal sebagai arsip
berstatus ditutup.

## 14. Transisi Status Perjalanan Pengemudi

```php
$allowed = [
    'dibuka' => ['berangkat'],
    'ditutup' => ['berangkat'],
    'berangkat' => ['selesai'],
];

if (! in_array($target, $allowed[$lockedTrip->status] ?? [], true)) {
    abort(response()->json([
        'success' => false,
        'message' => 'Status perjalanan tidak dapat diloncati.',
    ], 422));
}
```

Sumber:
[`backend/app/Http/Controllers/Api/DriverController.php`](../backend/app/Http/Controllers/Api/DriverController.php#L374)

### Fungsi

Menjadikan status sebagai finite-state workflow sederhana. Pengemudi tidak dapat mengubah jadwal
dari dibuka langsung menjadi selesai.

## 15. Menyembunyikan Jadwal Penuh dari Beranda

```dart
final availableSchedules = schedules
    .where((schedule) => schedule.sisaKursi > 0)
    .toList();
```

Sumber:
[`frontend/lib/features/home/bagian_beranda/halaman_informasi.dart`](../frontend/lib/features/home/bagian_beranda/halaman_informasi.dart#L423)

### Catatan

API bootstrap masih dapat mengirim jadwal dengan `sisa_kursi = 0`; penyaringan beranda dilakukan
di Flutter. Detail jadwal tetap mempunyai penjaga `isSoldOut` agar tidak dapat dilanjutkan.

## 16. Pemesanan Terbaru dan Lama

```dart
bool _isOldBooking(TravSBookingResult booking) {
  // Pesanan batal yang jadwalnya masih akan datang tetap termasuk terbaru.
  // Riwayat lama hanya berisi perjalanan selesai atau jadwal yang telah lewat.
  return _isCompletedTicket(booking);
}
```

Sumber:
[`frontend/lib/features/home/bagian_beranda/halaman_informasi.dart`](../frontend/lib/features/home/bagian_beranda/halaman_informasi.dart#L557)

### Makna

Pembagian bukan berdasarkan “berhasil atau gagal”, melainkan relevansi waktu perjalanan. Booking
batal yang baru dibuat tidak otomatis dianggap riwayat lama.

## 17. Validasi Akun di Backend

```php
'email' => [
    'required',
    'email',
    'max:100',
    'regex:/^[^@\s]+@gmail\.com$/i',
    Rule::unique('pengguna', 'email'),
],
'no_hp' => [
    'required',
    'string',
    'regex:/^[0-9]{11,12}$/',
    Rule::unique('pengguna', 'no_hp'),
],
'password' => [
    'required',
    'string',
    'min:6',
    'max:72',
    'regex:/^(?=.*[A-Za-z])(?=.*(?:[0-9]|[^A-Za-z0-9\s]))\S+$/',
    'confirmed',
],
```

Sumber:
[`backend/app/Http/Controllers/Api/AuthController.php`](../backend/app/Http/Controllers/Api/AuthController.php#L38)

### Makna

Email dibatasi pada Gmail, nomor HP hanya 11–12 angka, dan kata sandi minimal 6 karakter harus
mempunyai huruf serta angka atau simbol. Batas teknis 72 karakter mengikuti pemrosesan hash.
Constraint `unique` mencegah dua akun memakai identitas login/kontak yang sama.
Validasi Flutter membantu UX, tetapi aturan backend tetap menjadi pengaman utama.

## 18. Notifikasi Jadwal Mendekati dan Telah Berangkat

```php
public function scheduleNearDeparture(Jadwal $schedule): void
{
    $this->notifyAdmins(
        'Jadwal mendekati keberangkatan',
        "Jadwal {$route} akan berangkat pada {$departure}. "
            .'Pastikan armada, pengemudi, dan manifes sudah siap.',
        'perjalanan',
        $schedule->id,
    );
}

public function scheduleDeparted(Jadwal $schedule): void
{
    $this->notifyAdmins(
        'Armada telah berangkat',
        "{$vehicle} pada jadwal {$route} telah berangkat.",
        'perjalanan',
        $schedule->id,
    );
}
```

Sumber:
[`backend/app/Services/AdminOperationalNotificationService.php`](../backend/app/Services/AdminOperationalNotificationService.php#L86)

### Keterkaitan

Pengingat mendekati keberangkatan dipanggil scheduler. Notifikasi armada berangkat dipanggil tepat
ketika pengemudi mengubah status ke `berangkat`.

## 19. Pencatatan Biaya Operasional Pengemudi

```php
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
    'jenis_biaya' => ['required', 'string', 'max:100'],
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
```

Sumber:
[`backend/app/Http/Controllers/Api/DriverController.php`](../backend/app/Http/Controllers/Api/DriverController.php#L791)

### Fungsi

Memastikan biaya hanya dicatat oleh pengemudi yang ditugaskan pada jadwal aktif. Nominal minimal
Rp1, deskripsi harus jelas, dan bukti gambar maksimal 3 MB.

## 20. Event Perubahan Kursi Real-Time

```php
class SeatAvailabilityChanged implements ShouldBroadcastNow
{
    public function broadcastOn(): Channel
    {
        return new Channel('jadwal.'.$this->scheduleId);
    }

    public function broadcastAs(): string
    {
        return 'kursi.diperbarui';
    }

    public function broadcastWith(): array
    {
        return [
            'jadwal_id' => $this->scheduleId,
            'diperbarui_pada' => $this->changedAt,
        ];
    }
}
```

Sumber:
`backend/app/Events/SeatAvailabilityChanged.php`

Event menggunakan `ShouldBroadcastNow`, sehingga pengiriman sinyal kursi tidak menunggu queue.
Payload hanya memuat ID jadwal dan waktu perubahan.

## 21. Reverb Tidak Membatalkan Booking

```php
try {
    SeatAvailabilityChanged::dispatch(
        $scheduleId,
        now()->toIso8601String(),
    );
} catch (Throwable $error) {
    Log::warning('Pembaruan kursi real-time gagal dikirim.', [
        'jadwal_id' => $scheduleId,
        'error' => $error->getMessage(),
    ]);
}
```

Sumber:
`backend/app/Services/SeatAvailabilityRealtimeService.php`

Blok `try-catch` memisahkan kegagalan komunikasi realtime dari keberhasilan transaksi booking.

## 22. Flutter Mengambil Ulang Data dari API

```dart
if (eventName == _settings.seatEvent &&
    message['channel']?.toString() == _channelName) {
  // Event hanya menjadi sinyal; data kursi terbaru tetap diambil dari API.
  await _onSeatChanged();
}
```

Sumber:
`frontend/lib/core/services/travs_seat_realtime_service.dart`

Penjelasan card statistik beserta query lengkap berada pada
[`10_STATISTIK_DASBOR_DAN_LAPORAN.md`](10_STATISTIK_DASBOR_DAN_LAPORAN.md).
