<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeatAvailabilityChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $scheduleId,
        public readonly string $changedAt,
    ) {}

    /**
     * Channel hanya membawa ID jadwal, tanpa data pribadi pemesan.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('jadwal.'.$this->scheduleId);
    }

    public function broadcastAs(): string
    {
        return 'kursi.diperbarui';
    }

    /**
     * Mobile memakai event sebagai pemicu untuk mengambil seat map terbaru.
     *
     * @return array{jadwal_id: int, diperbarui_pada: string}
     */
    public function broadcastWith(): array
    {
        return [
            'jadwal_id' => $this->scheduleId,
            'diperbarui_pada' => $this->changedAt,
        ];
    }
}
