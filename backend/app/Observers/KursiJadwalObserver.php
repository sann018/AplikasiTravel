<?php

namespace App\Observers;

use App\Models\KursiJadwal;
use App\Services\SeatAvailabilityRealtimeService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class KursiJadwalObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly SeatAvailabilityRealtimeService $realtimeService,
    ) {}

    /**
     * Event dikirim setelah transaksi selesai agar mobile tidak membaca data setengah jadi.
     */
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
