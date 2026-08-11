<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\Rute;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DriverAvailabilityService
{
    /**
     * Dua jam dipakai sebagai jeda operasional
     */
    private const MINIMUM_TURNAROUND_MINUTES = 120;

    /**
     * Dalam 24 jam, posisi terakhir wajib mengikuti rangkaian rute. Setelah
     * itu sistem menganggap ada waktu untuk perpindahan di luar jadwal.
     */
    private const LOCATION_CONTINUITY_WINDOW_MINUTES = 1440;

    /**
     * Status ini dianggap masih memakai pengemudi dan kendaraan.
     *
     * @return array<int, string>
     */
    public function activeScheduleStatuses(): array
    {
        return ['draft', 'dibuka', 'ditutup', 'berangkat'];
    }

    public function conflictingSchedule(
        int $driverId,
        CarbonInterface $departureTime,
        CarbonInterface $arrivalTime,
        ?Jadwal $currentJadwal = null,
    ): ?Jadwal {
        return $this->conflictingResourceSchedule(
            'pengemudi_id',
            $driverId,
            $departureTime,
            $arrivalTime,
            $currentJadwal,
        );
    }

    public function conflictingVehicleSchedule(
        int $vehicleId,
        CarbonInterface $departureTime,
        CarbonInterface $arrivalTime,
        ?Jadwal $currentJadwal = null,
    ): ?Jadwal {
        return $this->conflictingResourceSchedule(
            'kendaraan_id',
            $vehicleId,
            $departureTime,
            $arrivalTime,
            $currentJadwal,
        );
    }

    /**
     * Data ini dipakai view admin untuk memberi label "terkunci" pada pilihan
     * pengemudi jika interval yang sedang diedit sudah terisi.
     *
     * @param  Collection<int, mixed>  $drivers
     * @return array<int, array{locked: bool, reason: string}>
     */
    public function availabilityForOptions(
        Collection $drivers,
        ?CarbonInterface $departureTime,
        ?CarbonInterface $arrivalTime,
        ?Jadwal $currentJadwal = null,
    ): array {
        if (! $departureTime || ! $arrivalTime) {
            return [];
        }

        $availability = [];

        foreach ($drivers as $driver) {
            $conflict = $this->conflictingSchedule(
                (int) $driver->id,
                $departureTime,
                $arrivalTime,
                $currentJadwal,
            );
            $availability[(int) $driver->id] = [
                'locked' => $conflict !== null,
                'reason' => $conflict ? $this->conflictMessage($conflict) : '',
            ];
        }

        return $availability;
    }

    /**
     * @param  Collection<int, mixed>  $vehicles
     * @return array<int, array{locked: bool, reason: string}>
     */
    public function vehicleAvailabilityForOptions(
        Collection $vehicles,
        ?CarbonInterface $departureTime,
        ?CarbonInterface $arrivalTime,
        ?Jadwal $currentJadwal = null,
    ): array {
        if (! $departureTime || ! $arrivalTime) {
            return [];
        }

        $availability = [];

        foreach ($vehicles as $vehicle) {
            $conflict = $this->conflictingVehicleSchedule(
                (int) $vehicle->id,
                $departureTime,
                $arrivalTime,
                $currentJadwal,
            );
            $availability[(int) $vehicle->id] = [
                'locked' => $conflict !== null,
                'reason' => $conflict ? $this->vehicleConflictMessage($conflict) : '',
            ];
        }

        return $availability;
    }

    public function conflictMessage(Jadwal $schedule): string
    {
        $departure = $schedule->waktu_berangkat?->format('d M Y H:i') ?? 'waktu yang belum tersedia';
        $arrival = $this->scheduleArrival($schedule)?->format('d M Y H:i') ?? '-';
        $route = $this->routeLabel($schedule);
        $status = ucfirst((string) $schedule->status);

        return "Pengemudi sudah bertugas pada interval {$departure}–{$arrival} ({$route}, status {$status}). Pilih jam yang tidak bertabrakan atau pengemudi lain.";
    }

    public function vehicleConflictMessage(Jadwal $schedule): string
    {
        $departure = $schedule->waktu_berangkat?->format('d M Y H:i') ?? 'waktu yang belum tersedia';
        $arrival = $this->scheduleArrival($schedule)?->format('d M Y H:i') ?? '-';
        $route = $this->routeLabel($schedule);
        $status = ucfirst((string) $schedule->status);

        return "Kendaraan sedang digunakan pada interval {$departure}–{$arrival} ({$route}, status {$status}). Pilih jam yang tidak bertabrakan atau kendaraan lain.";
    }

    public function driverContinuityIssue(
        int $driverId,
        Rute $candidateRoute,
        CarbonInterface $departureTime,
        CarbonInterface $arrivalTime,
        ?Jadwal $currentJadwal = null,
    ): ?string {
        return $this->resourceContinuityIssue(
            'pengemudi_id',
            $driverId,
            'Pengemudi',
            $candidateRoute,
            $departureTime,
            $arrivalTime,
            $currentJadwal,
        );
    }

    public function vehicleContinuityIssue(
        int $vehicleId,
        Rute $candidateRoute,
        CarbonInterface $departureTime,
        CarbonInterface $arrivalTime,
        ?Jadwal $currentJadwal = null,
    ): ?string {
        return $this->resourceContinuityIssue(
            'kendaraan_id',
            $vehicleId,
            'Kendaraan',
            $candidateRoute,
            $departureTime,
            $arrivalTime,
            $currentJadwal,
        );
    }

    private function conflictingResourceSchedule(
        string $resourceColumn,
        int $resourceId,
        CarbonInterface $departureTime,
        CarbonInterface $arrivalTime,
        ?Jadwal $currentJadwal,
    ): ?Jadwal {
        if ($arrivalTime->lessThanOrEqualTo($departureTime)) {
            return null;
        }

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
            ->when($currentJadwal, fn ($query) => $query->whereKeyNot($currentJadwal->id))
            ->orderBy('waktu_berangkat')
            ->get()
            ->first(function (Jadwal $schedule) use ($departureTime, $arrivalTime) {
                $scheduleArrival = $this->scheduleArrival($schedule);

                return $schedule->waktu_berangkat !== null
                    && $scheduleArrival !== null
                    && $schedule->waktu_berangkat->lessThan($arrivalTime)
                    && $scheduleArrival->greaterThan($departureTime);
            });
    }

    private function scheduleArrival(Jadwal $schedule): ?CarbonInterface
    {
        if ($schedule->estimasi_tiba) {
            return $schedule->estimasi_tiba;
        }

        if (! $schedule->waktu_berangkat) {
            return null;
        }

        if (! $schedule->relationLoaded('rute')) {
            $schedule->load('rute');
        }

        // Data lama tanpa estimasi tiba tetap aman: durasi rute menjadi
        // fallback, minimal satu menit agar jadwal tidak dianggap berdurasi nol.
        $durationMinutes = max(1, (int) ($schedule->rute?->estimasi_durasi_menit ?? 0));

        return $schedule->waktu_berangkat->copy()->addMinutes($durationMinutes);
    }

    private function resourceContinuityIssue(
        string $resourceColumn,
        int $resourceId,
        string $resourceLabel,
        Rute $candidateRoute,
        CarbonInterface $departureTime,
        CarbonInterface $arrivalTime,
        ?Jadwal $currentJadwal,
    ): ?string {
        $previousSchedule = $this->previousSchedule(
            $resourceColumn,
            $resourceId,
            $departureTime,
            $currentJadwal,
        );

        if ($previousSchedule) {
            $previousArrival = $this->scheduleArrival($previousSchedule);

            // Status berangkat berarti posisi aktual belum dinyatakan tiba,
            // sehingga sumber daya tetap terkunci walau estimasi telah lewat.
            if ($previousSchedule->status === 'berangkat') {
                return "{$resourceLabel} masih menjalankan perjalanan {$this->routeLabel($previousSchedule)}. Selesaikan perjalanan tersebut sebelum membuat tugas baru.";
            }

            if ($previousArrival) {
                $gapAfterPrevious = $previousArrival->diffInMinutes($departureTime, false);

                if (
                    $gapAfterPrevious >= 0
                    && $gapAfterPrevious <= self::LOCATION_CONTINUITY_WINDOW_MINUTES
                ) {
                    $previousDestination = trim((string) $previousSchedule->rute?->kota_tujuan);
                    $candidateOrigin = trim((string) $candidateRoute->kota_asal);

                    if (! $this->sameLocation($previousDestination, $candidateOrigin)) {
                        return "{$resourceLabel} diperkirakan masih berada di {$previousDestination} dari perjalanan sebelumnya. Jadwal baru harus berangkat dari {$previousDestination}, bukan {$candidateOrigin}.";
                    }

                    if ($gapAfterPrevious < self::MINIMUM_TURNAROUND_MINUTES) {
                        return "{$resourceLabel} membutuhkan jeda minimal 2 jam setelah tiba di {$previousDestination} sebelum ditugaskan kembali.";
                    }
                }
            }
        }

        $nextSchedule = $this->nextSchedule(
            $resourceColumn,
            $resourceId,
            $arrivalTime,
            $currentJadwal,
        );

        if (! $nextSchedule || ! $nextSchedule->waktu_berangkat) {
            return null;
        }

        $gapBeforeNext = $arrivalTime->diffInMinutes(
            $nextSchedule->waktu_berangkat,
            false,
        );

        if (
            $gapBeforeNext < 0
            || $gapBeforeNext > self::LOCATION_CONTINUITY_WINDOW_MINUTES
        ) {
            return null;
        }

        $candidateDestination = trim((string) $candidateRoute->kota_tujuan);
        $nextOrigin = trim((string) $nextSchedule->rute?->kota_asal);

        if (! $this->sameLocation($candidateDestination, $nextOrigin)) {
            return "Setelah jadwal baru, {$resourceLabel} akan berada di {$candidateDestination}, sedangkan jadwal berikutnya berangkat dari {$nextOrigin}.";
        }

        if ($gapBeforeNext < self::MINIMUM_TURNAROUND_MINUTES) {
            return "{$resourceLabel} membutuhkan jeda minimal 2 jam sebelum menjalankan jadwal berikutnya dari {$nextOrigin}.";
        }

        return null;
    }

    private function previousSchedule(
        string $resourceColumn,
        int $resourceId,
        CarbonInterface $departureTime,
        ?Jadwal $currentJadwal,
    ): ?Jadwal {
        return $this->resourceTimelineQuery(
            $resourceColumn,
            $resourceId,
            $currentJadwal,
        )
            ->where('waktu_berangkat', '<', $departureTime)
            ->orderByDesc('waktu_berangkat')
            ->get()
            ->first(function (Jadwal $schedule) use ($departureTime) {
                $arrival = $this->scheduleArrival($schedule);

                return $arrival !== null && $arrival->lessThanOrEqualTo($departureTime);
            });
    }

    private function nextSchedule(
        string $resourceColumn,
        int $resourceId,
        CarbonInterface $arrivalTime,
        ?Jadwal $currentJadwal,
    ): ?Jadwal {
        return $this->resourceTimelineQuery(
            $resourceColumn,
            $resourceId,
            $currentJadwal,
        )
            ->where('waktu_berangkat', '>=', $arrivalTime)
            ->orderBy('waktu_berangkat')
            ->first();
    }

    private function resourceTimelineQuery(
        string $resourceColumn,
        int $resourceId,
        ?Jadwal $currentJadwal,
    ) {
        return Jadwal::query()
            ->with('rute')
            ->where($resourceColumn, $resourceId)
            ->where('status', '!=', 'dibatalkan')
            ->when(
                $currentJadwal,
                fn ($query) => $query->whereKeyNot($currentJadwal->id),
            );
    }

    private function sameLocation(string $firstLocation, string $secondLocation): bool
    {
        $normalize = static fn (string $location) => mb_strtolower(
            preg_replace('/\s+/u', ' ', trim($location)) ?? '',
        );

        return $firstLocation !== ''
            && $secondLocation !== ''
            && $normalize($firstLocation) === $normalize($secondLocation);
    }

    private function routeLabel(Jadwal $schedule): string
    {
        if (! $schedule->relationLoaded('rute')) {
            $schedule->load('rute');
        }

        if (! $schedule->rute) {
            return 'rute belum tersedia';
        }

        return $schedule->rute->kota_asal.' - '.$schedule->rute->kota_tujuan;
    }
}
