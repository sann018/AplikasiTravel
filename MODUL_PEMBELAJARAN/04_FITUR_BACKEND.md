# 04 — Fitur Backend (Logika Bisnis + Potongan Kode)

> Tujuan bab ini: kamu paham **apa yang terjadi di balik layar** untuk tiap fitur, file mana yang
> bertanggung jawab, dan potongan kode kuncinya. Baca sambil membuka file aslinya di VS Code.

Format tiap fitur: **Apa fungsinya → File terkait → Alur singkat → Potongan kode penting.**

---

## Cara Membaca Route (Peta Alamat)

Sebelum masuk fitur, pahami dulu dua file "daftar alamat":

- `routes/api.php` — alamat untuk aplikasi mobile. Contoh baris:
  ```php
  Route::post('/penumpang/bookings', [BookingController::class, 'store'])
      ->middleware('throttle:travs-action');
  ```
  Artinya: kalau mobile mengirim `POST` ke `/api/penumpang/bookings`, jalankan method `store` di
  `BookingController`.

- `routes/web.php` — alamat untuk halaman web admin. Contoh:
  ```php
  Route::resource('jadwal', JadwalController::class);
  ```
  Artinya: buat otomatis alamat CRUD (index, create, store, edit, update, destroy) untuk jadwal.

Route mobile umumnya dilindungi middleware:
- `auth:sanctum` — harus login (punya token).
- `role:penumpang` / `role:pengemudi` — harus peran tertentu.

---

## MODUL 1 — Autentikasi (Login, Register, Verifikasi)

**Fungsi:** mendaftarkan akun baru, login, verifikasi email lewat OTP, reset password, dan mengelola
profil.

**File terkait:**
- Controller: `app/Http/Controllers/Api/AuthController.php`
- Model: `app/Models/User.php` (tabel `pengguna`)
- Middleware peran: `app/Http/Middleware/EnsureUserHasRole.php`
- Route: `routes/api.php` (grup `/api/auth/...`)

**Alur register (mobile):**
1. Mobile kirim `POST /api/auth/register` berisi nama, email, no_hp, password.
2. `AuthController` memvalidasi, membuat akun dengan `peran = 'penumpang'`, lalu mengirim kode OTP
   ke email.
3. Penumpang memasukkan OTP → akun terverifikasi → token login diberikan.

**Potongan kode penting — pembuatan token login (Sanctum):**
```php
// Setelah email terverifikasi, sistem membuat token untuk aplikasi mobile
$token = $user->createToken($request->device_name ?? 'mobile')->plainTextToken;

return response()->json([
    'success' => true,
    'data' => [
        'token' => $token,      // token ini disimpan di HP dan dikirim tiap request
        'user'  => [ /* data profil */ ],
    ],
]);
```

**Potongan kode penting — middleware penjaga peran** (`EnsureUserHasRole.php`):
```php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    $user = $request->user();

    // Kalau peran user tidak termasuk yang diizinkan, tolak dengan 403
    if (! $user || ! in_array($user->peran, $roles, true)) {
        abort(403, 'Anda tidak memiliki akses untuk tindakan ini.');
    }

    return $next($request);
}
```
Cara pakai di route: `->middleware('role:penumpang')`. Inilah yang membuat endpoint penumpang tidak
bisa diakses pengemudi, dan sebaliknya.

> **Poin sidang:** "Bagaimana sistem tahu seseorang penumpang atau pengemudi?" → Dari kolom `peran`
> di tabel `pengguna`, dicek oleh middleware `EnsureUserHasRole` pada setiap request API.

---

## MODUL 2 — Pengelolaan Rute (Admin)

**Fungsi:** admin menambah/mengubah/menghapus trayek antar kota beserta harga tiketnya.

**File terkait:**
- Controller: `app/Http/Controllers/Web/Admin/RuteController.php`
- Model: `app/Models/Rute.php`
- View: `resources/views/admin/rute/{index,create,edit}.blade.php`
- Route: `routes/web.php` (`Route::resource('rute', ...)`)

**Alur:** admin buka menu Rute → daftar rute ditampilkan → klik Tambah → isi form → tersimpan.

**Potongan kode penting — validasi & simpan (pola CRUD Laravel):**
```php
public function store(Request $request)
{
    $validated = $request->validate([
        'kota_asal'   => ['required', 'string', 'max:255'],
        'kota_tujuan' => ['required', 'string', 'max:255'],
        'harga_tiket' => ['required', 'numeric', 'min:0'],
        'estimasi_durasi_menit' => ['nullable', 'integer', 'min:1'],
    ]);

    Rute::create($validated);

    return redirect()
        ->route('admin.rute.index')
        ->with('status', 'Rute berhasil ditambahkan.');
}
```
Pola ini (validasi → simpan → redirect dengan pesan) berulang di hampir semua CRUD admin
(kendaraan, pengemudi, jadwal, biaya). Kalau paham satu, paham semua.

---

## MODUL 3 — Pengelolaan Armada Kendaraan (Admin)

**Fungsi:** kelola data mobil (plat, merek, kapasitas kursi, foto, denah kursi).

**File terkait:**
- Controller: `Web/Admin/KendaraanController.php`
- Model: `app/Models/Kendaraan.php`
- View: `resources/views/admin/kendaraan/*`

**Hal menarik:** kolom `kapasitas_kursi` nanti dipakai saat membuat jadwal untuk **menghasilkan
kursi otomatis** di tabel `kursi_jadwal`.

---

## MODUL 4 — Pengelolaan Pengemudi (Admin)

**Fungsi:** admin membuat akun pengemudi (sekaligus akun `pengguna` dengan peran `pengemudi`),
menyimpan nomor SIM & foto.

**File terkait:**
- Controller: `Web/Admin/PengemudiController.php`
- Model: `app/Models/Pengemudi.php` (relasi ke `User`)
- View: `resources/views/admin/pengemudi/*`

**Alur:** saat admin membuat pengemudi, sistem membuat dua data sekaligus: baris di `pengguna`
(peran pengemudi) dan baris di `pengemudi` (data SIM). Keduanya terhubung lewat `pengguna_id`.

---

## MODUL 5 — Pengelolaan Jadwal Perjalanan (Admin) ⭐ Penting

**Fungsi:** menggabungkan rute + kendaraan + pengemudi + waktu menjadi satu jadwal, lalu
**membuat kursi otomatis** sesuai kapasitas kendaraan.

**File terkait:**
- Controller: `Web/Admin/JadwalController.php`
- Model: `app/Models/Jadwal.php`, `KursiJadwal.php`
- Service: `app/Services/ScheduleLifecycleService.php` (mengurus penutupan jadwal otomatis)
- View: `resources/views/admin/jadwal/*`

**Dua hal cerdas di modul ini:**

**a. Estimasi tiba dihitung otomatis** dari durasi rute:
```php
// JadwalController.php (ringkas)
$durasiRuteMenit = (int) $rute->estimasi_durasi_menit;
$validated['estimasi_tiba'] = $departureTime->copy()->addMinutes($durasiRuteMenit);
```

**b. Jadwal otomatis ditutup 1 jam sebelum berangkat** (biar tidak ada pesanan mepet), di
`ScheduleLifecycleService.php`:
```php
public function closeOpenSchedulesNearDeparture(): void
{
    // Jadwal berstatus "dibuka" yang keberangkatannya < 1 jam lagi
    // otomatis diubah jadi "ditutup" agar tidak menerima pemesanan baru.
    Jadwal::query()
        ->where('status', 'dibuka')
        ->where('waktu_berangkat', '<=', now()->addHour())
        ->update(['status' => 'ditutup', 'updated_at' => now()]);
}
```

> **Poin sidang:** status jadwal berjenjang: `draft` → `dibuka` → `ditutup` → `berangkat` →
> `selesai` (atau `dibatalkan`). Transisi ini dijaga ketat supaya tidak "loncat".

---

## MODUL 6 — Pemesanan (Booking) ⭐⭐ Paling Kompleks

**Fungsi:** inti aplikasi. Mengunci kursi, membuat pemesanan, mengelola batas waktu.

**File terkait:**
- Controller: `app/Http/Controllers/Api/BookingController.php`
- Service: `app/Services/BookingService.php` (otak utama), `BookingCalculator.php` (hitung harga)
- Model: `Pemesanan`, `DetailPemesanan`, `KursiJadwal`, `ItemBagasi`

**Konsep kunci: Seat Lock (Penguncian Kursi)**

Masalah yang dipecahkan: kalau dua orang memilih kursi yang sama bersamaan, siapa yang dapat? Solusi:
saat penumpang mulai mengisi form, kursi **dikunci sementara** (status `terkunci`) selama beberapa
menit. Orang lain tidak bisa mengambil kursi itu sampai kunci kadaluarsa atau dilepas.

**Potongan kode penting — mengunci kursi dengan aman (anti rebutan):**
```php
// BookingService.php — lockSeatsForForm (ringkas)
return DB::transaction(function () use ($user, $scheduleId, $seatNumbers) {
    $schedule = Jadwal::query()
        ->lockForUpdate()   // <- kunci baris database agar tidak ada dua proses bersamaan
        ->findOrFail($scheduleId);

    $selectedSeats = $this->loadSeatRowsForUpdate($schedule->id, $seatNumbers);
    $this->ensureSeatsCanBeUsedByUser($selectedSeats, $user->id); // pastikan kursi belum dipesan orang lain

    $formDeadline = $this->resolveFormLockDeadline(now());

    foreach ($selectedSeats as $seat) {
        $seat->update([
            'status_kursi'    => 'terkunci',
            'dikunci_oleh'    => $user->id,
            'lock_expires_at' => $formDeadline,  // kunci akan kadaluarsa otomatis
        ]);
    }
    // ...
});
```
Dua pengaman penting:
1. `DB::transaction` + `lockForUpdate()` — mencegah dua orang mengunci kursi sama secara bersamaan.
2. Kolom `kursi_jadwal_id` di `detail_pemesanan` dibuat **UNIQUE** — sebagai jaring pengaman
   terakhir, satu kursi tidak mungkin masuk dua pemesanan.

**Potongan kode penting — membuat pemesanan (`createDraft`):**
```php
$totals = $this->calculator->calculate($schedule, $seatNumbers->count());

$booking = Pemesanan::query()->create([
    'pengguna_id'      => $user->id,
    'jadwal_id'        => $schedule->id,
    'kode_booking'     => $this->generateBookingCode(),
    'jumlah_kursi'     => $seatNumbers->count(),
    'alamat_jemput'    => data_get($payload, 'pickup.address'),
    'alamat_turun'     => data_get($payload, 'dropoff.address'),
    'subtotal_tiket'   => $totals['subtotal_tiket'],
    'total_harga'      => $totals['total_harga'],   // = subtotal (tanpa bagasi/diskon)
    'status_pemesanan' => 'menunggu_pembayaran',
]);
```

**Potongan kode penting — perhitungan harga (`BookingCalculator.php`):**
```php
public function calculate(Jadwal $jadwal, int $seatCount): array
{
    $subtotalTiket = (float) $jadwal->rute->harga_tiket * $seatCount;

    return [
        'subtotal_tiket' => $subtotalTiket,
        'total_harga'    => $subtotalTiket,  // total = subtotal tiket
    ];
}
```
> **Poin sidang:** harga selalu dihitung di backend (bukan dikirim dari HP) supaya nominal tidak
> bisa dimanipulasi pengguna. Ini prinsip keamanan penting.

---

## MODUL 7 — Pembayaran (QRIS / Transfer / Tunai) ⭐⭐

**Fungsi:** memproses pembayaran lewat gateway Pakasir (QRIS/transfer) atau tunai (bayar di mobil).

**File terkait:**
- Controller: `Api/BookingController.php` (simulate, refresh status), `Api/PaymentGatewayController.php` (webhook)
- Service: `BookingService.php` (proses status), `PakasirService.php` (komunikasi ke Pakasir)

**Tiga metode pembayaran:**
- **QRIS / Transfer** → lewat Pakasir. Penumpang diarahkan ke halaman checkout Pakasir.
- **Tunai (cash)** → dibayar di kendaraan, dikonfirmasi pengemudi/admin.

**Alur QRIS (aman):**
1. Penumpang pilih QRIS → backend membuat transaksi di Pakasir → dapat URL checkout.
2. Penumpang bayar di halaman Pakasir.
3. Pakasir mengirim **webhook** (notifikasi) ke `POST /api/payment/pakasir/webhook`.
4. Backend **tidak langsung percaya** isi webhook. Ia **menanyakan ulang** status asli ke Pakasir
   (re-fetch) untuk mencegah pemalsuan.
5. Kalau benar berhasil → status jadi `terbayar` → tiket QR diterbitkan.

**Potongan kode penting — verifikasi ulang webhook (`BookingService.php`):**
```php
// Backend tidak percaya status dari payload webhook mentah.
// Ia mengambil status SEBENARNYA langsung dari API Pakasir yang terautentikasi.
$transaction = $this->pakasirService->transactionDetail(
    $this->paymentOrderId($booking),
    $booking->total_harga,
);

// Cek nominal juga harus cocok, mencegah manipulasi
if ((int) round($booking->total_harga) !== (int) round($payload['amount'])) {
    throw ValidationException::withMessages([
        'amount' => 'Nominal pembayaran tidak sesuai dengan total pemesanan.',
    ]);
}

if ($this->pakasirService->isCompletedStatus($transaction['status'])) {
    return $this->syncPakasirPayment($booking, $transaction, $payload);
}
```

> **Poin sidang (Pakasir sandbox):** "Pakasir saat ini masih mode sandbox/percobaan, jadi belum ada
> uang sungguhan. Verifikasi keaslian webhook dilakukan dengan menanyakan ulang status ke Pakasir.
> Saat nanti pindah ke mode live, tinggal ganti kredensial di file `.env` dan menambah verifikasi
> tanda tangan (signature)."

---

## MODUL 8 — Bagasi

**Fungsi:** mencatat barang bawaan penumpang (untuk keperluan manifest, tanpa biaya tambahan).

**File terkait:** `BookingController.php` (validasi & simpan), Model `ItemBagasi.php`.

**Catatan penting:** barang bawaan **hanya dicatat**, tidak menambah harga. Dulu ada rencana biaya
bagasi (`biaya_bagasi`), tapi **sudah dihapus** karena tidak dipakai. Ini contoh "perbedaan
rancangan awal dan implementasi akhir".

---

## MODUL 9 — Tiket Elektronik / QR Code ⭐

**Fungsi:** menerbitkan tiket ber-QR untuk tiap penumpang begitu pembayaran berhasil.

**File terkait:** `BookingService.php` (method `finalizePaidBooking`), Model `TiketElektronik.php`.

**Potongan kode penting — menerbitkan tiket (anti duplikat):**
```php
private function finalizePaidBooking(Pemesanan $booking): void
{
    foreach ($booking->detailPemesanan as $detail) {
        // Kursi resmi jadi "dipesan"
        $detail->kursiJadwal?->update(['status_kursi' => 'dipesan']);

        // Terbitkan tiket. updateOrCreate memastikan tidak ada tiket ganda
        // walau proses ini terpanggil lebih dari sekali.
        TiketElektronik::query()->updateOrCreate(
            ['detail_pemesanan_id' => $detail->id],
            [
                'kode_qr'         => 'TRAVS-'.$booking->kode_booking.'-'.$detail->nomor_kursi,
                'token_unik_qr'   => $detail->tiketElektronik?->token_unik_qr ?: Str::uuid()->toString(),
                'diterbitkan_pada'=> now(),
            ],
        );
    }
}
```
`token_unik_qr` adalah kode acak unik yang jadi isi QR. Saat pengemudi scan, token inilah yang
dicocokkan.

---

## MODUL 10 — Manifest & Operasi Pengemudi ⭐

**Fungsi:** pengemudi melihat daftar penumpang, scan QR, tandai jemput/turun, konfirmasi tunai.

**File terkait:** `app/Http/Controllers/Api/DriverController.php`.

**Potongan kode penting — scan QR (anti scan ganda):**
```php
public function scanTicket(Request $request): JsonResponse
{
    $payload = $request->validate(['token' => ['required', 'string', 'max:160']]);

    return DB::transaction(function () use ($payload, ...) {
        $ticket = TiketElektronik::query()
            ->lockForUpdate()  // kunci agar tidak discan dua kali bersamaan
            ->where('token_unik_qr', $payload['token'])
            ->first();

        // Tolak kalau tiket sudah pernah discan
        if ($ticket->sudah_dipindai) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket sudah pernah dipindai.',
            ], 409);
        }

        $ticket->update(['sudah_dipindai' => true, 'dipindai_pada' => now()]);
        $detail->update(['status_kehadiran' => 'hadir']);
        // ...
    });
}
```

Pengemudi juga hanya bisa mengakses perjalanan miliknya sendiri, dijaga oleh:
```php
private function ensureDriverOwnsTrip(Request $request, Jadwal $jadwal): Pengemudi
{
    $pengemudi = $this->activeDriverFor($request);
    if ((int) $jadwal->pengemudi_id !== (int) $pengemudi->id) {
        abort(response()->json(['success' => false,
            'message' => 'Perjalanan ini bukan milik pengemudi aktif.'], 403));
    }
    return $pengemudi;
}
```

---

## MODUL 11 — Notifikasi

**Fungsi:** memberi tahu pengguna/admin tentang kejadian penting (pemesanan baru, pembayaran
berhasil, jadwal mendekati keberangkatan, kapasitas hampir penuh).

**File terkait:**
- Controller: `Api/NotificationController.php` (untuk mobile), `Web/Admin/NotifikasiController.php`
- Service: `AdminOperationalNotificationService.php`, `NotificationTemplateService.php`,
  `NotificationHistoryService.php`, `DepartureReminderService.php`
- Model: `Notifikasi.php`

**Potongan kode — kirim notifikasi ke admin (anti-dobel):**
```php
Notifikasi::query()->updateOrCreate(
    ['pengguna_id' => $adminId, 'judul' => $title, 'jenis' => $type, 'referensi_id' => $referenceId],
    ['pesan' => $message, 'sudah_dibaca' => false],
);
```
`updateOrCreate` mencegah notifikasi sama dibuat berkali-kali.

---

## MODUL 12 — Laporan (Admin)

**Fungsi:** menampilkan & mengekspor laporan pemesanan dan transaksi.

**File terkait:**
- Controller: `Web/Admin/LaporanController.php`
- View: `resources/views/admin/laporan/{pemesanan,transaksi,okupansi}.blade.php`

**Fitur:** filter berdasarkan tanggal/status, dan **ekspor CSV**. Cocok untuk demo di sidang.

---

## MODUL 13 — Biaya Operasional (Admin & Pengemudi)

**Fungsi:** mencatat pengeluaran per perjalanan (bensin, tol, dll) beserta bukti foto.

**File terkait:**
- Controller: `Web/Admin/BiayaOperasionalController.php`, dan di `DriverController.php` (sopir input)
- Model: `BiayaOperasionalPerjalanan.php`

---

## MODUL 14 — Pengaturan Sistem & Banner (Admin)

**Fungsi:** mengubah nama aplikasi, logo, aturan booking (batas kursi, durasi kunci), metode bayar
aktif, dan mengelola banner promosi di beranda mobile.

**File terkait:**
- Controller: `Web/Admin/SettingsController.php`, `HomeBannerController.php`
- Service: `SettingService.php` (pembaca setting, pakai cache)
- Model: `SystemSetting.php`, `HomeBanner.php`

**Potongan kode — membaca setting di mana saja:**
```php
$maxKursi = (int) $this->settingService->get('max_seat_per_booking', 4);
```
Nilai setting disimpan di tabel `pengaturan_sistem` dan bisa diubah admin tanpa mengubah kode.
Nama class `SystemSetting` serta key konfigurasi seperti `max_seat_per_booking` tetap dipertahankan
untuk kompatibilitas kode dan API; yang distandardisasi hanya nama tabel dan kolom database.

---

## Ringkasan Peta Fitur → File (Cheat Sheet)

| Fitur | Controller | Service | Model utama |
|-------|-----------|---------|-------------|
| Auth | `Api/AuthController` | - | `User` |
| Rute | `Web/Admin/RuteController` | - | `Rute` |
| Kendaraan | `Web/Admin/KendaraanController` | - | `Kendaraan` |
| Pengemudi | `Web/Admin/PengemudiController` | - | `Pengemudi`, `User` |
| Jadwal | `Web/Admin/JadwalController` | `ScheduleLifecycleService` | `Jadwal`, `KursiJadwal` |
| Pemesanan | `Api/BookingController` | `BookingService`, `BookingCalculator` | `Pemesanan`, `DetailPemesanan` |
| Pembayaran | `Api/PaymentGatewayController` | `BookingService`, `PakasirService` | `Pembayaran` |
| Tiket QR | `Api/BookingController` | `BookingService` | `TiketElektronik` |
| Manifest/Sopir | `Api/DriverController` | `BookingService` | `Jadwal`, `DetailPemesanan` |
| Notifikasi | `Api/NotificationController` | `AdminOperationalNotificationService` | `Notifikasi` |
| Laporan | `Web/Admin/LaporanController` | - | `Pemesanan`, `Pembayaran` |
| Biaya operasional | `Web/Admin/BiayaOperasionalController` | - | `BiayaOperasionalPerjalanan` |
| Pengaturan | `Web/Admin/SettingsController` | `SettingService` | `SystemSetting` |

Lanjut ke `05_FITUR_FLUTTER.md` untuk memahami sisi aplikasi mobile.
