<?php

namespace App\Services;

use App\Models\Notifikasi;
use Illuminate\Database\Eloquent\Collection;

class NotificationHistoryService
{
    /**
     * Menandai hanya notifikasi yang benar-benar ditampilkan kepada pemiliknya.
     * Cara ini menjaga proses polling agar tidak menghabiskan badge sebelum daftar dibuka.
     */
    public function markDisplayedAsRead(int $userId, Collection $notifications): int
    {
        $notificationIds = $notifications->pluck('id')->filter()->all();

        if ($notificationIds === []) {
            return 0;
        }

        return Notifikasi::query()
            ->where('pengguna_id', $userId)
            ->whereIn('id', $notificationIds)
            ->where('sudah_dibaca', false)
            ->update(['sudah_dibaca' => true]);
    }

    /**
     * Menghapus riwayat yang sudah dibaca sesuai pilihan umur.
     * Filter status dibaca selalu dipasang agar informasi baru tidak ikut terhapus.
     */
    public function deleteReadHistory(string $period, ?int $userId = null): int
    {
        $query = Notifikasi::query()->where('sudah_dibaca', true);

        if ($userId !== null) {
            $query->where('pengguna_id', $userId);
        }

        $days = $this->periodDays($period);
        if ($days !== null) {
            $query->where('created_at', '<=', now()->subDays($days));
        }

        return $query->delete();
    }

    private function periodDays(string $period): ?int
    {
        return match ($period) {
            '7_days' => 7,
            '30_days' => 30,
            '90_days' => 90,
            'all_read' => null,
        };
    }
}
