<?php

namespace App\Services;

use App\Models\Jadwal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DriverAvailabilityService
{
    /**
     * Status ini dianggap masih menjadi tugas aktif pengemudi.
     * Jika salah satu status ini ada di tanggal yang sama, pengemudi dikunci
     * supaya admin tidak memberi dua perjalanan dalam satu hari kerja.
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
        ?Jadwal $currentJadwal = null,
    ): ?Jadwal {
        return Jadwal::query()
            ->with('rute')
            ->where('pengemudi_id', $driverId)
            ->whereIn('status', $this->activeScheduleStatuses())
            ->whereDate('waktu_berangkat', $departureTime->toDateString())
            ->when($currentJadwal, fn ($query) => $query->whereKeyNot($currentJadwal->id))
            ->orderBy('waktu_berangkat')
            ->first();
    }

    /**
     * Data ini dipakai view admin untuk memberi label "terkunci" pada pilihan
     * pengemudi setelah admin memilih tanggal keberangkatan.
     *
     * @param  Collection<int, mixed>  $drivers
     * @return array<int, array{locked: bool, reason: string}>
     */
    public function availabilityForOptions(
        Collection $drivers,
        ?CarbonInterface $departureTime,
        ?Jadwal $currentJadwal = null,
    ): array {
        if (! $departureTime) {
            return [];
        }

        $availability = [];

        foreach ($drivers as $driver) {
            $conflict = $this->conflictingSchedule((int) $driver->id, $departureTime, $currentJadwal);
            $availability[(int) $driver->id] = [
                'locked' => $conflict !== null,
                'reason' => $conflict ? $this->conflictMessage($conflict) : '',
            ];
        }

        return $availability;
    }

    public function conflictMessage(Jadwal $schedule): string
    {
        $departure = $schedule->waktu_berangkat?->format('d M Y H:i') ?? 'tanggal yang sama';
        $route = $this->routeLabel($schedule);
        $status = ucfirst((string) $schedule->status);

        return "Pengemudi masih memiliki jadwal aktif pada {$departure} ({$route}, status {$status}). Tutup atau selesaikan jadwal itu dulu sebelum menambah jadwal baru di tanggal yang sama.";
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
