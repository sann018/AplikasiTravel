<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\Notifikasi;

class DepartureReminderService
{
    public function __construct(
        private readonly NotificationTemplateService $notificationTemplate,
        private readonly AdminOperationalNotificationService $adminNotification,
    ) {}

    public function sendUpcomingReminders(): int
    {
        if (! $this->notificationTemplate->enabled('notif_departure_reminder', true)) {
            return 0;
        }

        $sent = 0;
        $schedules = Jadwal::query()
            ->with(['rute', 'pemesanan.pengguna'])
            ->whereIn('status', ['dibuka', 'ditutup', 'berangkat'])
            ->whereBetween('waktu_berangkat', [now(), now()->addHour()])
            ->get();

        foreach ($schedules as $schedule) {
            $this->adminNotification->scheduleNearDeparture($schedule);

            $route = $schedule->rute
                ? $schedule->rute->kota_asal.' - '.$schedule->rute->kota_tujuan
                : 'perjalanan Anda';

            foreach ($schedule->pemesanan->where('status_pemesanan', 'terbayar') as $booking) {
                $message = $this->notificationTemplate->renderFromSetting(
                    'notif_departure_reminder_template',
                    'Perjalanan {rute} berangkat pada {tanggal} pukul {jam}.',
                    [
                        'nama' => $booking->pengguna?->nama ?? 'Penumpang',
                        'rute' => $route,
                        'tanggal' => $schedule->waktu_berangkat?->translatedFormat('d F Y') ?? '-',
                        'jam' => $schedule->waktu_berangkat?->format('H.i') ?? '-',
                        'kode_booking' => $booking->kode_booking,
                    ],
                );

                Notifikasi::query()->updateOrCreate(
                    [
                        'pengguna_id' => $booking->pengguna_id,
                        'jenis' => 'perjalanan',
                        'referensi_id' => $booking->id,
                        'judul' => 'Pengingat keberangkatan',
                    ],
                    ['pesan' => $message, 'sudah_dibaca' => false],
                );
                $sent++;
            }
        }

        return $sent;
    }
}
