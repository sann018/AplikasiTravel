<?php

namespace App\Services;

use App\Models\ItemBagasi;
use App\Models\Jadwal;
use App\Models\Notifikasi;
use App\Models\Pemesanan;
use App\Models\User;

class AdminOperationalNotificationService
{
    public function __construct(private readonly SettingService $settingService) {}

    /** Notifikasi ini dibuat setelah draft pemesanan berhasil tersimpan. */
    public function bookingCreated(Pemesanan $booking): void
    {
        $booking->loadMissing(['pengguna', 'jadwal.rute', 'pembayaran']);
        $route = $this->routeLabel($booking->jadwal);
        $departure = $this->departureLabel($booking);

        $this->notifyAdmins(
            'Pemesanan baru masuk',
            "Pemesanan dari {$booking->namaKontakPemesan()} untuk rute {$route} dengan jadwal keberangkatan {$departure} baru dibuat.",
            'sistem',
            $booking->id,
        );

    }

    public function cashPaymentSelected(Pemesanan $booking): void
    {
        $booking->loadMissing(['pengguna', 'jadwal.rute']);
        $departure = $this->departureLabel($booking);

        $this->notifyAdmins(
            'Pembayaran tunai menunggu konfirmasi',
            "Pemesanan untuk jadwal keberangkatan {$departure} memilih pembayaran tunai dan perlu dikonfirmasi setelah uang diterima.",
            'pembayaran',
            $booking->id,
        );
    }

    public function paymentStatusChanged(Pemesanan $booking, string $status): void
    {
        $booking->loadMissing(['pengguna', 'jadwal.rute']);
        $departure = $this->departureLabel($booking);

        [$title, $message] = match ($status) {
            'berhasil' => [
                'Pembayaran berhasil',
                "Pembayaran jadwal keberangkatan {$departure} milik {$booking->namaKontakPemesan()} berhasil dikonfirmasi.",
            ],
            'gagal' => [
                'Pembayaran gagal',
                "Pembayaran jadwal keberangkatan {$departure} gagal. Periksa transaksi jika penumpang membutuhkan bantuan.",
            ],
            'kedaluwarsa' => [
                'Pembayaran kedaluwarsa',
                "Waktu pembayaran jadwal keberangkatan {$departure} telah habis dan kursi dapat dilepas kembali.",
            ],
        };

        $this->notifyAdmins($title, $message, 'pembayaran', $booking->id);
    }

    /**
     * Peringatan saat pembayaran gateway berhasil tetapi pemesanan sudah
     * dibatalkan/kedaluwarsa, sehingga dana perlu dikembalikan secara manual.
     */
    public function paymentReceivedAfterCancellation(Pemesanan $booking): void
    {
        $booking->loadMissing(['pengguna', 'jadwal.rute']);
        $departure = $this->departureLabel($booking);

        $this->notifyAdmins(
            'Pembayaran masuk pada pemesanan yang sudah batal',
            "Pembayaran untuk jadwal keberangkatan {$departure} milik {$booking->namaKontakPemesan()} berhasil, "
                .'tetapi pemesanan sudah dibatalkan/kedaluwarsa sehingga tiket tidak dapat diterbitkan. '
                .'Periksa transaksi dan proses pengembalian dana secara manual.',
            'pembayaran',
            $booking->id,
        );
    }

    public function scheduleNearDeparture(Jadwal $schedule): void
    {
        $schedule->loadMissing(['rute', 'kendaraan']);
        $route = $this->routeLabel($schedule);
        $departure = $schedule->waktu_berangkat?->translatedFormat('d F Y H:i') ?? '-';

        $this->notifyAdmins(
            'Jadwal mendekati keberangkatan',
            "Jadwal {$route} akan berangkat pada {$departure}. Pastikan armada, pengemudi, dan manifes sudah siap.",
            'perjalanan',
            $schedule->id,
        );
    }

    public function scheduleDeparted(Jadwal $schedule): void
    {
        $schedule->loadMissing(['rute', 'kendaraan', 'pengemudi.pengguna']);
        $route = $this->routeLabel($schedule);
        $vehicle = $schedule->kendaraan?->label_armada ?? 'Armada';
        $driver = $schedule->pengemudi?->pengguna?->nama ?? 'pengemudi terjadwal';

        $this->notifyAdmins(
            'Armada telah berangkat',
            "{$vehicle} pada jadwal {$route} telah berangkat pukul ".now()->format('H:i')." bersama {$driver}.",
            'perjalanan',
            $schedule->id,
        );
    }

    /**
     * Peringatan dikirim saat pemakaian kursi atau berat bagasi mencapai 80%.
     * Sisa dua kursi juga dianggap hampir penuh agar admin sempat memantau.
     */
    public function capacityAlmostFull(Jadwal $schedule): void
    {
        $totalSeats = $schedule->kursi()->count();
        $usedSeats = $schedule->kursi()
            ->whereIn('status_kursi', ['terkunci', 'dipesan'])
            ->count();
        $remainingSeats = max(0, $totalSeats - $usedSeats);
        $seatAlmostFull = $totalSeats > 0
            && ($usedSeats / $totalSeats >= 0.8 || $remainingSeats <= 2);

        $maximumBaggageWeight = (float) $this->settingService->get(
            'baggage_max_total_weight_per_vehicle',
            50,
        );
        $currentBaggageWeight = (float) ItemBagasi::query()
            ->whereHas('pemesanan', fn ($query) => $query
                ->where('jadwal_id', $schedule->id)
                ->whereIn('status_pemesanan', ['menunggu_pembayaran', 'terbayar']))
            ->get(['berat_kg', 'jumlah_item'])
            ->sum(fn (ItemBagasi $item) => (float) ($item->berat_kg ?? 0) * max(1, (int) $item->jumlah_item));
        $baggageAlmostFull = $maximumBaggageWeight > 0
            && $currentBaggageWeight / $maximumBaggageWeight >= 0.8;

        if (! $seatAlmostFull && ! $baggageAlmostFull) {
            return;
        }

        $details = [];
        if ($seatAlmostFull) {
            $details[] = "sisa {$remainingSeats} dari {$totalSeats} kursi";
        }
        if ($baggageAlmostFull) {
            $details[] = "bagasi {$currentBaggageWeight} dari {$maximumBaggageWeight} kg";
        }

        $this->notifyAdmins(
            'Kapasitas perjalanan hampir penuh',
            'Jadwal '.$this->routeLabel($schedule).' hampir penuh: '.implode(' dan ', $details).'.',
            'perjalanan',
            $schedule->id,
        );
    }

    private function notifyAdmins(string $title, string $message, string $type, int $referenceId): void
    {
        $adminIds = User::query()
            ->where('peran', 'admin')
            ->where('status_aktif', true)
            ->pluck('id');

        foreach ($adminIds as $adminId) {
            // updateOrCreate mencegah event terjadwal membuat notifikasi yang sama berulang kali.
            Notifikasi::query()->updateOrCreate(
                [
                    'pengguna_id' => $adminId,
                    'judul' => $title,
                    'jenis' => $type,
                    'referensi_id' => $referenceId,
                ],
                ['pesan' => $message],
            );
        }
    }

    private function routeLabel(?Jadwal $schedule): string
    {
        return $schedule?->rute
            ? $schedule->rute->kota_asal.' - '.$schedule->rute->kota_tujuan
            : 'rute yang dipilih';
    }

    private function departureLabel(Pemesanan $booking): string
    {
        return $booking->jadwal?->waktu_berangkat?->translatedFormat('d F Y H:i') ?? '-';
    }
}
