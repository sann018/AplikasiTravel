<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Kendaraan;
use App\Models\KursiJadwal;
use App\Models\Notifikasi;
use App\Models\Pengemudi;
use App\Models\Rute;
use App\Services\DriverAvailabilityService;
use App\Services\NotificationTemplateService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JadwalController extends Controller
{
    public function __construct(
        private readonly DriverAvailabilityService $driverAvailabilityService,
        private readonly ScheduleLifecycleService $scheduleLifecycleService,
        private readonly NotificationTemplateService $notificationTemplate,
    ) {}

    public function index(Request $request): View
    {
        $this->scheduleLifecycleService->closeOpenSchedulesNearDeparture();

        // Filter dijaga sebelum tanggal dibaca agar URL tidak valid tidak memicu error server.
        $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['draft', 'dibuka', 'ditutup', 'berangkat', 'selesai', 'dibatalkan'])],
            'tanggal' => ['nullable', 'date'],
        ], [
            'status.in' => 'Status jadwal tidak valid.',
            'tanggal.date' => 'Tanggal jadwal tidak valid.',
        ]);

        $query = Jadwal::query()
            ->with(['rute', 'kendaraan', 'pengemudi.pengguna'])
            ->withCount([
                'pemesanan',
                'kursi as kursi_tersedia' => fn ($builder) => $builder->where('status_kursi', 'tersedia'),
                'kursi as kursi_dipesan' => fn ($builder) => $builder->where('status_kursi', 'dipesan'),
                'kursi as kursi_terkunci' => fn ($builder) => $builder->where('status_kursi', 'terkunci'),
                'kursi as kursi_nonaktif' => fn ($builder) => $builder->where('status_kursi', 'nonaktif'),
            ]);

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->whereHas('rute', function ($ruteQuery) use ($search) {
                        $ruteQuery
                            ->where('kota_asal', 'like', "%{$search}%")
                            ->orWhere('kota_tujuan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('kendaraan', fn ($kendaraanQuery) => $kendaraanQuery->where('nomor_plat', 'like', "%{$search}%"))
                    ->orWhereHas('pengemudi.pengguna', fn ($penggunaQuery) => $penggunaQuery->where('nama', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('waktu_berangkat', $request->date('tanggal'));
        }

        $jadwal = $query
            // Jadwal keberangkatan terbaru ditampilkan lebih dahulu.
            ->orderByDesc('waktu_berangkat')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $editingJadwal = $request->filled('edit')
            ? Jadwal::query()->with(['kendaraan', 'rute', 'pengemudi'])->findOrFail($request->integer('edit'))
            : null;

        return view('admin.jadwal.index', [
            'jadwal' => $jadwal,
            'stats' => [
                'total' => Jadwal::query()->count(),
                'dibuka' => Jadwal::query()->where('status', 'dibuka')->count(),
                'ditutup' => Jadwal::query()->where('status', 'ditutup')->count(),
                'berangkat' => Jadwal::query()->where('status', 'berangkat')->count(),
                'selesai' => Jadwal::query()->where('status', 'selesai')->count(),
            ],
            'filters' => $request->only(['search', 'status', 'tanggal']),
        ]);
    }

    public function create(Request $request): View
    {
        $pengemudiOptions = Pengemudi::query()
            ->with('pengguna')
            ->where('status', 'aktif')
            ->orderBy('nomor_sim')
            ->get();
        $kendaraanOptions = Kendaraan::query()
            ->where('status_kendaraan', 'aktif')
            ->orderBy('nomor_plat')
            ->get();
        $availabilityDeparture = $this->parseAvailabilityDeparture($request->old('waktu_berangkat'));
        $availabilityArrival = $this->availabilityArrival(
            $availabilityDeparture,
            $request->old('rute_id') ? (int) $request->old('rute_id') : null,
        );

        return view('admin.jadwal.create', [
            'kendaraanOptions' => $kendaraanOptions,
            'kendaraanAvailability' => $this->driverAvailabilityService
                ->vehicleAvailabilityForOptions(
                    $kendaraanOptions,
                    $availabilityDeparture,
                    $availabilityArrival,
                ),
            'pengemudiOptions' => $pengemudiOptions,
            'pengemudiAvailability' => $this->driverAvailabilityService
                ->availabilityForOptions(
                    $pengemudiOptions,
                    $availabilityDeparture,
                    $availabilityArrival,
                ),
            'ruteOptions' => Rute::query()
                ->where('status_aktif', true)
                ->orderBy('kota_asal')
                ->get(),
        ]);
    }

    public function show(Jadwal $jadwal): View
    {
        $jadwal->load([
            'rute',
            'kendaraan',
            'pengemudi.pengguna',
            'kursi',
            'pemesanan' => fn ($query) => $query
                ->with([
                    'pengguna',
                    'pembayaran',
                    'detailPemesanan.kursiJadwal',
                ])
                ->latest(),
            'biayaOperasional' => fn ($query) => $query
                ->finalized()
                ->with('pencatat')
                ->latest('tanggal_biaya'),
        ]);

        // Detail jadwal hanya memakai pemesanan yang pembayarannya berhasil.
        $successfulBookings = $jadwal->pemesanan
            ->whereIn('status_pemesanan', ['terbayar', 'selesai'])
            ->filter(fn ($booking) => $booking->pembayaran?->status_bayar === 'berhasil');
        $successfulPassengers = $successfulBookings->flatMap->detailPemesanan;
        // Pendapatan jadwal hanya menghitung nominal milik RuslyTravel.
        $confirmedRevenue = (int) $successfulBookings
            ->sum(fn ($booking) => $booking->pembayaran->nominal_transaksi ?? (int) $booking->total_harga);
        $operationalCost = (int) $jadwal->biayaOperasional->sum('nominal');

        return view('admin.jadwal.show', [
            'jadwal' => $jadwal,
            'successfulBookings' => $successfulBookings,
            'summary' => [
                'bookings' => [
                    'total' => $successfulBookings->count(),
                    'terbayar' => $successfulBookings->where('status_pemesanan', 'terbayar')->count(),
                    'selesai' => $successfulBookings->where('status_pemesanan', 'selesai')->count(),
                ],
                'passengers' => [
                    'total' => $successfulPassengers->count(),
                    'menunggu' => $successfulPassengers
                        ->where('status_kehadiran', 'menunggu')
                        ->whereNull('diturunkan_pada')
                        ->count(),
                    'hadir' => $successfulPassengers
                        ->where('status_kehadiran', 'hadir')
                        ->whereNull('diturunkan_pada')
                        ->count(),
                    'tidak_hadir' => $successfulPassengers->where('status_kehadiran', 'tidak_hadir')->count(),
                    'turun' => $successfulPassengers->whereNotNull('diturunkan_pada')->count(),
                ],
                'seats' => [
                    'total' => $jadwal->kursi->count(),
                    'tersedia' => $jadwal->kursi->where('status_kursi', 'tersedia')->count(),
                    'dipesan' => $jadwal->kursi->where('status_kursi', 'dipesan')->count(),
                    'nonaktif' => $jadwal->kursi->where('status_kursi', 'nonaktif')->count(),
                ],
                'confirmed_revenue' => $confirmedRevenue,
                'operational_cost' => $operationalCost,
                'recorded_balance' => $confirmedRevenue - $operationalCost,
            ],
        ]);
    }

    public function edit(Request $request, Jadwal $jadwal): View
    {
        $this->scheduleLifecycleService->closeSingleIfPastBookingWindow($jadwal);
        $jadwal->load(['kendaraan', 'rute', 'pengemudi', 'kursi']);
        $pengemudiOptions = Pengemudi::query()
            ->with('pengguna')
            ->where('status', 'aktif')
            ->orderBy('nomor_sim')
            ->get();
        $kendaraanOptions = Kendaraan::query()
            ->where('status_kendaraan', 'aktif')
            ->orderBy('nomor_plat')
            ->get();
        $availabilityDeparture = $this->parseAvailabilityDeparture(
            $request->old('waktu_berangkat') ?: $jadwal->waktu_berangkat?->format('Y-m-d H:i'),
        );
        $availabilityArrival = $this->availabilityArrival(
            $availabilityDeparture,
            (int) ($request->old('rute_id') ?: $jadwal->rute_id),
        );

        return view('admin.jadwal.edit', [
            'jadwal' => $jadwal,
            'kendaraanOptions' => $kendaraanOptions,
            'kendaraanAvailability' => $this->driverAvailabilityService
                ->vehicleAvailabilityForOptions(
                    $kendaraanOptions,
                    $availabilityDeparture,
                    $availabilityArrival,
                    $jadwal,
                ),
            'pengemudiOptions' => $pengemudiOptions,
            'pengemudiAvailability' => $this->driverAvailabilityService
                ->availabilityForOptions(
                    $pengemudiOptions,
                    $availabilityDeparture,
                    $availabilityArrival,
                    $jadwal,
                ),
            'ruteOptions' => Rute::query()
                ->where('status_aktif', true)
                ->orderBy('kota_asal')
                ->get(),
            'closedSeatNumbers' => $this->closedSeatNumbersFromRequestOrSchedule($request, $jadwal),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request);

        $closedSeatNumbers = $payload['kursi_ditutup'] ?? [];
        unset($payload['kursi_ditutup']);

        DB::transaction(function () use ($payload, $closedSeatNumbers) {
            // Penguncian baris sumber daya menutup celah dua admin menyimpan
            // kendaraan/pengemudi yang sama secara bersamaan.
            $this->lockScheduleResources($payload);
            $this->ensureScheduleResourcesAvailable($payload);

            $jadwal = Jadwal::query()->create($payload);
            $this->syncSeatMap($jadwal->fresh('kendaraan'), $closedSeatNumbers);

            // Jadwal baru langsung memberi tahu pengemudi yang dipilih admin,
            // supaya tugas perjalanan muncul di notifikasi aplikasi pengemudi.
            $this->notifyAssignedDriver($jadwal);
        });

        return redirect()
            ->route('admin.jadwal.index')
            ->with('status', 'Jadwal perjalanan berhasil dibuat dan kursi otomatis digenerate.');
    }

    public function update(Request $request, Jadwal $jadwal): RedirectResponse
    {
        $payload = $this->validatedPayload($request, $jadwal);

        $closedSeatNumbers = $payload['kursi_ditutup'] ?? [];
        unset($payload['kursi_ditutup']);

        DB::transaction(function () use ($jadwal, $payload, $closedSeatNumbers) {
            $lockedJadwal = Jadwal::query()
                ->lockForUpdate()
                ->findOrFail($jadwal->id);
            $this->lockScheduleResources($payload);
            $this->ensureScheduleResourcesAvailable($payload, $lockedJadwal);

            // Status selesai adalah kondisi akhir perjalanan. Penguncian di
            // backend mencegah status diputar balik melalui request manual.
            if ($lockedJadwal->status === 'selesai' && $payload['status'] !== 'selesai') {
                throw ValidationException::withMessages([
                    'status' => 'Jadwal yang sudah selesai tidak dapat diubah ke status lain.',
                ]);
            }

            $vehicleChanged = (int) $lockedJadwal->kendaraan_id !== (int) $payload['kendaraan_id'];
            $driverChanged = (int) $lockedJadwal->pengemudi_id !== (int) $payload['pengemudi_id'];
            $timeChanged = $lockedJadwal->waktu_berangkat?->format('Y-m-d H:i') !==
                ($payload['waktu_berangkat'] ? date('Y-m-d H:i', strtotime($payload['waktu_berangkat'])) : null);

            $lockedJadwal->update($payload);

            $lockedJadwal->load('kendaraan');
            $this->syncSeatMap($lockedJadwal, $closedSeatNumbers);

            if ($driverChanged) {
                $this->notifyAssignedDriver($lockedJadwal);
            }

            if ($driverChanged || $timeChanged || $vehicleChanged) {
                $lockedJadwal->load('pemesanan');
                $this->notifyPassengersScheduleChanged($lockedJadwal);
            }
        });

        return redirect()
            ->route('admin.jadwal.index')
            ->with('status', 'Jadwal perjalanan berhasil diperbarui.');
    }

    public function destroy(Jadwal $jadwal): RedirectResponse
    {
        try {
            $jadwal->delete();
        } catch (QueryException) {
            return back()->with('error', 'Jadwal tidak dapat dihapus karena sudah terhubung dengan pemesanan atau data operasional.');
        }

        return back()->with('status', 'Jadwal perjalanan berhasil dihapus.');
    }

    private function validatedPayload(Request $request, ?Jadwal $currentJadwal = null): array
    {
        // Status berangkat dipakai bersama tombol Mulai Perjalanan. Status
        // dibatalkan tidak lagi dipilih dari form, tetapi enum lama tetap ada
        // di database agar data dan migration sebelumnya tetap kompatibel.
        $allowedStatuses = ['draft', 'dibuka', 'ditutup', 'berangkat', 'selesai'];

        $validated = $request->validate([
            'kendaraan_id' => ['required', 'exists:kendaraan,id'],
            'rute_id' => ['required', 'exists:rute,id'],
            'pengemudi_id' => ['required', 'exists:pengemudi,id'],
            'waktu_berangkat' => ['required', 'date'],
            'status' => ['required', Rule::in($allowedStatuses)],
            'kursi_ditutup' => ['nullable', 'array'],
            'kursi_ditutup.*' => ['integer', 'min:1'],
        ], [
            'kendaraan_id.required' => 'Kendaraan wajib dipilih.',
            'kendaraan_id.exists' => 'Kendaraan yang dipilih tidak ditemukan.',
            'rute_id.required' => 'Rute perjalanan wajib dipilih.',
            'rute_id.exists' => 'Rute perjalanan yang dipilih tidak ditemukan.',
            'pengemudi_id.required' => 'Pengemudi wajib dipilih.',
            'pengemudi_id.exists' => 'Pengemudi yang dipilih tidak ditemukan.',
            'waktu_berangkat.required' => 'Waktu berangkat wajib diisi.',
            'waktu_berangkat.date' => 'Format waktu berangkat tidak valid.',
            'status.required' => 'Status jadwal wajib dipilih.',
            'status.in' => 'Status jadwal yang dipilih tidak valid.',
            'kursi_ditutup.array' => 'Kursi yang ditutup harus berupa daftar nomor kursi.',
            'kursi_ditutup.*.integer' => 'Nomor kursi yang ditutup harus berupa angka.',
            'kursi_ditutup.*.min' => 'Nomor kursi yang ditutup tidak valid.',
        ]);

        $rute = Rute::query()->findOrFail($validated['rute_id']);
        $durasiRuteMenit = (int) $rute->estimasi_durasi_menit;

        if ($durasiRuteMenit <= 0) {
            throw ValidationException::withMessages([
                'rute_id' => 'Durasi perjalanan pada rute ini belum diisi. Silakan lengkapi durasi rute terlebih dahulu.',
            ]);
        }

        $departureTime = Carbon::parse($validated['waktu_berangkat']);

        // Estimasi tiba dihitung ulang di backend agar admin tidak perlu
        // mengisi manual dan tanggal otomatis maju jika perjalanan lewat tengah malam.
        $validated['estimasi_tiba'] = $departureTime->copy()->addMinutes($durasiRuteMenit);

        $kendaraan = Kendaraan::query()->findOrFail($validated['kendaraan_id']);
        if ($kendaraan->status_kendaraan !== 'aktif') {
            throw ValidationException::withMessages([
                'kendaraan_id' => 'Kendaraan harus berstatus aktif sebelum dimasukkan ke jadwal.',
            ]);
        }

        $closedSeats = $this->normalizeClosedSeats($validated['kursi_ditutup'] ?? [], (int) $kendaraan->kapasitas_kursi);
        $this->ensureClosedSeatsCanBeApplied($closedSeats, $currentJadwal);
        $validated['kursi_ditutup'] = $closedSeats;

        $pengemudi = Pengemudi::query()->findOrFail($validated['pengemudi_id']);

        if ($pengemudi->status !== 'aktif') {
            throw ValidationException::withMessages([
                'pengemudi_id' => 'Pengemudi harus berstatus aktif sebelum dimasukkan ke jadwal.',
            ]);
        }

        $this->ensureScheduleResourcesAvailable($validated, $currentJadwal);

        return $validated;
    }

    private function ensureScheduleResourcesAvailable(
        array $payload,
        ?Jadwal $currentJadwal = null,
    ): void {
        $departureTime = Carbon::parse($payload['waktu_berangkat']);
        $arrivalTime = Carbon::parse($payload['estimasi_tiba']);
        $conflictingSchedule = $this->driverAvailabilityService->conflictingSchedule(
            (int) $payload['pengemudi_id'],
            $departureTime,
            $arrivalTime,
            $currentJadwal,
        );

        if ($conflictingSchedule) {
            throw ValidationException::withMessages([
                'pengemudi_id' => $this->driverAvailabilityService->conflictMessage($conflictingSchedule),
            ]);
        }

        $conflictingVehicleSchedule = $this->driverAvailabilityService->conflictingVehicleSchedule(
            (int) $payload['kendaraan_id'],
            $departureTime,
            $arrivalTime,
            $currentJadwal,
        );

        if ($conflictingVehicleSchedule) {
            throw ValidationException::withMessages([
                'kendaraan_id' => $this->driverAvailabilityService
                    ->vehicleConflictMessage($conflictingVehicleSchedule),
            ]);
        }

        $candidateRoute = Rute::query()->findOrFail((int) $payload['rute_id']);
        $driverContinuityIssue = $this->driverAvailabilityService->driverContinuityIssue(
            (int) $payload['pengemudi_id'],
            $candidateRoute,
            $departureTime,
            $arrivalTime,
            $currentJadwal,
        );

        if ($driverContinuityIssue) {
            throw ValidationException::withMessages([
                'pengemudi_id' => $driverContinuityIssue,
            ]);
        }

        $vehicleContinuityIssue = $this->driverAvailabilityService->vehicleContinuityIssue(
            (int) $payload['kendaraan_id'],
            $candidateRoute,
            $departureTime,
            $arrivalTime,
            $currentJadwal,
        );

        if ($vehicleContinuityIssue) {
            throw ValidationException::withMessages([
                'kendaraan_id' => $vehicleContinuityIssue,
            ]);
        }
    }

    private function lockScheduleResources(array $payload): void
    {
        // Urutan lock selalu kendaraan lalu pengemudi supaya transaksi
        // pembuatan dan perubahan jadwal tidak saling menunggu terbalik.
        Kendaraan::query()
            ->whereKey((int) $payload['kendaraan_id'])
            ->lockForUpdate()
            ->firstOrFail();
        Pengemudi::query()
            ->whereKey((int) $payload['pengemudi_id'])
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function parseAvailabilityDeparture(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        // Nilai tanggal dari filter boleh kosong atau tidak valid, jadi parser
        // mengembalikan null agar halaman tetap bisa menampilkan daftar jadwal.
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function availabilityArrival(?Carbon $departureTime, ?int $routeId): ?Carbon
    {
        if (! $departureTime || ! $routeId) {
            return null;
        }

        $durationMinutes = (int) Rute::query()
            ->whereKey($routeId)
            ->value('estimasi_durasi_menit');

        return $durationMinutes > 0
            ? $departureTime->copy()->addMinutes($durationMinutes)
            : null;
    }

    private function normalizeClosedSeats(array $seatNumbers, int $capacity): array
    {
        $closedSeats = collect($seatNumbers)
            ->map(fn ($seatNumber) => (int) $seatNumber)
            ->filter(fn (int $seatNumber) => $seatNumber >= 1 && $seatNumber <= $capacity)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (count($closedSeats) >= $capacity) {
            throw ValidationException::withMessages([
                'kursi_ditutup' => 'Minimal harus ada satu kursi yang tetap bisa dipesan.',
            ]);
        }

        return $closedSeats;
    }

    private function ensureClosedSeatsCanBeApplied(array $closedSeats, ?Jadwal $currentJadwal = null): void
    {
        if (! $currentJadwal || empty($closedSeats)) {
            return;
        }

        $currentJadwal->loadMissing('kursi.detailPemesanan');
        $blockedSeats = $currentJadwal->kursi
            ->filter(function (KursiJadwal $seat) use ($closedSeats) {
                return in_array((int) $seat->nomor_kursi, $closedSeats, true)
                    && (
                        in_array($seat->status_kursi, ['terkunci', 'dipesan'], true)
                        || $seat->detailPemesanan !== null
                    );
            })
            ->pluck('nomor_kursi')
            ->sort()
            ->values()
            ->all();

        if (! empty($blockedSeats)) {
            throw ValidationException::withMessages([
                'kursi_ditutup' => 'Kursi '.implode(', ', $blockedSeats).' sudah dikunci atau dipesan, sehingga tidak bisa ditutup.',
            ]);
        }
    }

    private function closedSeatNumbersFromRequestOrSchedule(Request $request, Jadwal $jadwal): array
    {
        if ($request->old('kursi_ditutup') !== null) {
            return collect($request->old('kursi_ditutup', []))
                ->map(fn ($seatNumber) => (int) $seatNumber)
                ->filter(fn (int $seatNumber) => $seatNumber > 0)
                ->values()
                ->all();
        }

        return $jadwal->kursi
            ->where('status_kursi', 'nonaktif')
            ->pluck('nomor_kursi')
            ->map(fn ($seatNumber) => (int) $seatNumber)
            ->values()
            ->all();
    }

    private function syncSeatMap(Jadwal $jadwal, array $closedSeatNumbers = []): void
    {
        $kapasitas = (int) $jadwal->kendaraan->kapasitas_kursi;
        $closedSeats = collect($closedSeatNumbers)
            ->map(fn ($seatNumber) => (int) $seatNumber)
            ->filter(fn (int $seatNumber) => $seatNumber >= 1 && $seatNumber <= $kapasitas)
            ->unique()
            ->values();
        $now = now();

        for ($seatNumber = 1; $seatNumber <= $kapasitas; $seatNumber++) {
            $seat = KursiJadwal::query()->firstOrCreate(
                [
                    'jadwal_id' => $jadwal->id,
                    'nomor_kursi' => $seatNumber,
                ],
                [
                    'status_kursi' => $closedSeats->contains($seatNumber) ? 'nonaktif' : 'tersedia',
                    'updated_at' => $now,
                ],
            );

            // Kursi yang ditutup berlaku per jadwal, bukan permanen di armada.
            // Kursi yang sudah dipesan/dikunci tidak diubah agar data penumpang tetap aman.
            if (! in_array($seat->status_kursi, ['terkunci', 'dipesan'], true) && ! $seat->detailPemesanan()->exists()) {
                $seat->update([
                    'status_kursi' => $closedSeats->contains($seatNumber) ? 'nonaktif' : 'tersedia',
                    'updated_at' => $now,
                ]);
            }
        }

        KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('nomor_kursi', '>', $kapasitas)
            ->whereIn('status_kursi', ['tersedia', 'nonaktif'])
            ->doesntHave('detailPemesanan')
            ->delete();
    }

    private function notifyAssignedDriver(Jadwal $jadwal): void
    {
        $driverUserId = $jadwal->pengemudi?->pengguna_id;
        if (! $driverUserId) {
            return;
        }

        Notifikasi::query()->create([
            'pengguna_id' => $driverUserId,
            'judul' => 'Tugas perjalanan baru',
            'pesan' => 'Anda ditugaskan pada rute '.$this->routeLabel($jadwal).' untuk keberangkatan '.$jadwal->waktu_berangkat?->format('d/m/Y H:i').'.',
            'jenis' => 'perjalanan',
            'referensi_id' => $jadwal->id,
        ]);
    }

    private function notifyPassengersScheduleChanged(Jadwal $jadwal): void
    {
        if (! $this->notificationTemplate->enabled('notif_schedule_changed', true)) {
            return;
        }

        foreach ($jadwal->pemesanan as $booking) {
            if (! in_array($booking->status_pemesanan, ['menunggu_pembayaran', 'terbayar'], true)) {
                continue;
            }

            // Pemesanan loket tanpa akun tidak memiliki kotak notifikasi pengguna.
            if ($booking->pengguna_id === null) {
                continue;
            }

            $message = $this->notificationTemplate->renderFromSetting(
                'notif_schedule_changed_template',
                'Jadwal {rute} berubah menjadi {tanggal_baru} pukul {jam_baru}.',
                [
                    'nama' => $booking->namaKontakPemesan(),
                    'rute' => $this->routeLabel($jadwal),
                    'tanggal_baru' => $jadwal->waktu_berangkat?->translatedFormat('d F Y') ?? '-',
                    'jam_baru' => $jadwal->waktu_berangkat?->format('H.i') ?? '-',
                    'kode_booking' => $booking->kode_booking,
                ],
            );

            Notifikasi::query()->create([
                'pengguna_id' => $booking->pengguna_id,
                'judul' => 'Jadwal perjalanan diperbarui',
                'pesan' => $message,
                'jenis' => 'perjalanan',
                'referensi_id' => $booking->id,
            ]);
        }
    }

    private function routeLabel(Jadwal $jadwal): string
    {
        $jadwal->loadMissing('rute');

        return $jadwal->rute
            ? $jadwal->rute->kota_asal.' - '.$jadwal->rute->kota_tujuan
            : 'perjalanan';
    }
}
