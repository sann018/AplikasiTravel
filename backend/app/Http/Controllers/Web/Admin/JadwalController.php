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
    ) {
    }

    public function index(Request $request): View
    {
        $this->scheduleLifecycleService->closeOpenSchedulesNearDeparture();

        $query = Jadwal::query()
            ->with(['rute', 'kendaraan', 'pengemudi.pengguna'])
            ->withCount([
                'pemesanan',
                'kursi as kursi_tersedia' => fn ($builder) => $builder->where('status_kursi', 'tersedia'),
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
            ->orderBy('waktu_berangkat')
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
            ->where('status_verifikasi', 'terverifikasi')
            ->orderBy('nomor_sim')
            ->get();
        $availabilityDeparture = $this->parseAvailabilityDeparture($request->old('waktu_berangkat'));

        return view('admin.jadwal.create', [
            'kendaraanOptions' => Kendaraan::query()
                ->where('status_kendaraan', 'aktif')
                ->orderBy('nomor_plat')
                ->get(),
            'pengemudiOptions' => $pengemudiOptions,
            'pengemudiAvailability' => $this->driverAvailabilityService
                ->availabilityForOptions($pengemudiOptions, $availabilityDeparture),
            'ruteOptions' => Rute::query()
                ->where('status_aktif', true)
                ->orderBy('kota_asal')
                ->get(),
        ]);
    }

    public function edit(Request $request, Jadwal $jadwal): View
    {
        $this->scheduleLifecycleService->closeSingleIfPastBookingWindow($jadwal);
        $jadwal->load(['kendaraan', 'rute', 'pengemudi']);
        $pengemudiOptions = Pengemudi::query()
            ->with('pengguna')
            ->where('status', 'aktif')
            ->where('status_verifikasi', 'terverifikasi')
            ->orderBy('nomor_sim')
            ->get();
        $availabilityDeparture = $this->parseAvailabilityDeparture(
            $request->old('waktu_berangkat') ?: $jadwal->waktu_berangkat?->format('Y-m-d H:i'),
        );

        return view('admin.jadwal.edit', [
            'jadwal' => $jadwal,
            'kendaraanOptions' => Kendaraan::query()
                ->where('status_kendaraan', 'aktif')
                ->orderBy('nomor_plat')
                ->get(),
            'pengemudiOptions' => $pengemudiOptions,
            'pengemudiAvailability' => $this->driverAvailabilityService
                ->availabilityForOptions($pengemudiOptions, $availabilityDeparture, $jadwal),
            'ruteOptions' => Rute::query()
                ->where('status_aktif', true)
                ->orderBy('kota_asal')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request);

        DB::transaction(function () use ($payload) {
            $jadwal = Jadwal::query()->create($payload);
            $this->syncSeatMap($jadwal->fresh('kendaraan'));
        });

        return redirect()
            ->route('admin.jadwal.index')
            ->with('status', 'Jadwal perjalanan berhasil dibuat dan kursi otomatis digenerate.');
    }

    public function update(Request $request, Jadwal $jadwal): RedirectResponse
    {
        $payload = $this->validatedPayload($request, $jadwal);

        DB::transaction(function () use ($jadwal, $payload) {
            $lockedJadwal = Jadwal::query()
                ->lockForUpdate()
                ->findOrFail($jadwal->id);

            $vehicleChanged = (int) $lockedJadwal->kendaraan_id !== (int) $payload['kendaraan_id'];
            $driverChanged = (int) $lockedJadwal->pengemudi_id !== (int) $payload['pengemudi_id'];
            $timeChanged = $lockedJadwal->waktu_berangkat?->format('Y-m-d H:i') !==
                ($payload['waktu_berangkat'] ? date('Y-m-d H:i', strtotime($payload['waktu_berangkat'])) : null);

            $lockedJadwal->update($payload);

            if ($vehicleChanged) {
                $lockedJadwal->load('kendaraan');
                $this->syncSeatMap($lockedJadwal);
            }

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
        $validated = $request->validate([
            'kendaraan_id' => ['required', 'exists:kendaraan,id'],
            'rute_id' => ['required', 'exists:rute,id'],
            'pengemudi_id' => ['required', 'exists:pengemudi,id'],
            'waktu_berangkat' => ['required', 'date'],
            'estimasi_tiba' => ['nullable', 'date', 'after:waktu_berangkat'],
            'status' => ['required', Rule::in(['draft', 'dibuka', 'ditutup', 'berangkat', 'selesai', 'dibatalkan'])],
        ]);

        $pengemudi = Pengemudi::query()->findOrFail($validated['pengemudi_id']);

        if ($pengemudi->status !== 'aktif' || $pengemudi->status_verifikasi !== 'terverifikasi') {
            throw ValidationException::withMessages([
                'pengemudi_id' => 'Pengemudi harus aktif dan sudah diverifikasi sebelum dimasukkan ke jadwal.',
            ]);
        }

        $conflictingSchedule = $this->driverAvailabilityService->conflictingSchedule(
            (int) $validated['pengemudi_id'],
            Carbon::parse($validated['waktu_berangkat']),
            $currentJadwal,
        );

        if ($conflictingSchedule) {
            throw ValidationException::withMessages([
                'pengemudi_id' => $this->driverAvailabilityService->conflictMessage($conflictingSchedule),
            ]);
        }

        return $validated;
    }

    private function parseAvailabilityDeparture(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function syncSeatMap(Jadwal $jadwal): void
    {
        $kapasitas = (int) $jadwal->kendaraan->kapasitas_kursi;
        $now = now();

        for ($seatNumber = 1; $seatNumber <= $kapasitas; $seatNumber++) {
            KursiJadwal::query()->firstOrCreate(
                [
                    'jadwal_id' => $jadwal->id,
                    'nomor_kursi' => $seatNumber,
                ],
                [
                    'status_kursi' => 'tersedia',
                    'updated_at' => $now,
                ],
            );
        }

        KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('nomor_kursi', '>', $kapasitas)
            ->where('status_kursi', 'tersedia')
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
        foreach ($jadwal->pemesanan as $booking) {
            if (! in_array($booking->status_pemesanan, ['menunggu_pembayaran', 'terbayar'], true)) {
                continue;
            }

            Notifikasi::query()->create([
                'pengguna_id' => $booking->pengguna_id,
                'judul' => 'Jadwal perjalanan diperbarui',
                'pesan' => 'Jadwal '.$this->routeLabel($jadwal).' untuk booking '.$booking->kode_booking.' diperbarui. Silakan cek detail pemesanan Anda.',
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
