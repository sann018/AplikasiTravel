<?php

namespace App\Services;

use App\Models\Jadwal;

class ScheduleLifecycleService
{
    private const BOOKING_CLOSE_HOURS_BEFORE_DEPARTURE = 1;

    /**
     * Jadwal yang sudah masuk hari H dan berada di H-1 jam tidak boleh
     * dipesan lagi. Statusnya ditutup agar admin tetap punya arsip jadwalnya.
     */
    public function closeOpenSchedulesNearDeparture(?int $jadwalId = null): int
    {
        $now = now();
        $today = $now->toDateString();
        $latestBookableDeparture = $now->copy()->addHours(self::BOOKING_CLOSE_HOURS_BEFORE_DEPARTURE);

        return Jadwal::query()
            ->where('status', 'dibuka')
            ->when($jadwalId !== null, fn ($query) => $query->whereKey($jadwalId))
            ->where(function ($query) use ($today, $latestBookableDeparture) {
                $query
                    ->whereDate('waktu_berangkat', '<', $today)
                    ->orWhere(function ($todayQuery) use ($today, $latestBookableDeparture) {
                        $todayQuery
                            ->whereDate('waktu_berangkat', $today)
                            ->where('waktu_berangkat', '<=', $latestBookableDeparture);
                    });
            })
            ->update([
                'status' => 'ditutup',
                'updated_at' => $now,
            ]);
    }

    public function closeSingleIfPastBookingWindow(Jadwal $jadwal): Jadwal
    {
        if ($jadwal->status === 'dibuka') {
            $this->closeOpenSchedulesNearDeparture((int) $jadwal->id);
            $jadwal->refresh();
        }

        return $jadwal;
    }
}
