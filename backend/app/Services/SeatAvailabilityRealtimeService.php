<?php

namespace App\Services;

use App\Events\SeatAvailabilityChanged;
use Illuminate\Support\Facades\Log;
use Throwable;

class SeatAvailabilityRealtimeService
{
    /**
     * Mengirim tanda perubahan kursi tanpa mengganggu transaksi utama.
     */
    public function publish(int $scheduleId): void
    {
        if (! config('realtime.seat_updates_enabled', false) || $scheduleId < 1) {
            return;
        }

        try {
            SeatAvailabilityChanged::dispatch($scheduleId, now()->toIso8601String());
        } catch (Throwable $error) {
            // Booking tetap berhasil ketika server Reverb sedang tidak aktif.
            Log::warning('Pembaruan kursi real-time gagal dikirim.', [
                'jadwal_id' => $scheduleId,
                'error' => $error->getMessage(),
            ]);
        }
    }
}
