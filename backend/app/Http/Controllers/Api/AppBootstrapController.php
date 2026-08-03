<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Services\BookingService;
use App\Services\ScheduleLifecycleService;
use App\Services\SettingService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Http\JsonResponse;

class AppBootstrapController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly ScheduleLifecycleService $scheduleLifecycleService,
        private readonly SettingService $settingService,
    ) {}

    public function index(): JsonResponse
    {
        $this->bookingService->releaseExpiredResources();
        $this->scheduleLifecycleService->closeOpenSchedulesNearDeparture();

        $jadwal = Jadwal::query()
            ->with(['rute', 'kendaraan', 'pengemudi.pengguna', 'kursi'])
            ->where('status', 'dibuka')
            // Filter dilakukan sebelum limit agar jadwal penuh tidak menutup jadwal tersedia.
            ->whereHas('kursi', fn ($builder) => $builder->where('status_kursi', 'tersedia'))
            ->orderBy('waktu_berangkat')
            ->limit(4)
            ->get();
        $qrisEnabled = (bool) $this->settingService->get('payment_qris_enabled', true);
        $virtualAccountEnabled = (bool) $this->settingService->get('payment_transfer_enabled', true);
        $cashEnabled = (bool) $this->settingService->get('payment_cash_enabled', false);

        return response()->json([
            'success' => true,
            'data' => [
                'branding' => [
                    'app_name' => $this->settingService->get('app_name', 'RuslyTravel'),
                    'logo' => $this->settingService->get('app_logo', 'travs-logo.svg'),
                    'logo_url' => $this->settingService->publicAssetPath('app_logo', 'travs-logo.svg'),
                    'contact' => $this->settingService->get('admin_contact'),
                    'service_whatsapp' => $this->settingService->get('service_whatsapp'),
                ],
                'theme' => [
                    'surface' => '#FFFFFF',
                    'primary' => '#10233F',
                    'accent' => '#F58A2A',
                    'muted' => '#EEF2F7',
                ],
                'payment_methods' => [
                    'transfer' => $virtualAccountEnabled,
                    'qris' => $qrisEnabled,
                    'cash' => $cashEnabled,
                    'options' => PaymentMethodCatalog::selectableOptions(
                        $qrisEnabled,
                        $virtualAccountEnabled,
                        $cashEnabled,
                    ),
                ],
                'important_information' => $this->settingService->importantInformation(),
                'featured_schedules' => $jadwal->map(fn (Jadwal $item) => $this->formatSchedule($item))->all(),
            ],
        ]);
    }

    private function formatSchedule(Jadwal $jadwal): array
    {
        $kursiTersedia = $jadwal->kursi->where('status_kursi', 'tersedia')->count();

        return [
            'id' => $jadwal->id,
            'rute' => [
                'asal' => $jadwal->rute->kota_asal,
                'tujuan' => $jadwal->rute->kota_tujuan,
                'jalur' => $jadwal->rute->deskripsi_jalur,
            ],
            'waktu_berangkat' => $jadwal->waktu_berangkat?->toIso8601String(),
            'estimasi_tiba' => $jadwal->estimasi_tiba?->toIso8601String(),
            'harga_tiket' => (float) $jadwal->rute->harga_tiket,
            'sisa_kursi' => $kursiTersedia,
            'kapasitas' => $jadwal->kendaraan->kapasitas_kursi,
            'estimasi_durasi_menit' => (int) $jadwal->rute->estimasi_durasi_menit,
            'durasi_label' => $jadwal->rute->durasi_label,
            'pengemudi' => $jadwal->pengemudi->pengguna->nama,
            'kendaraan' => $jadwal->kendaraan->label_armada,
        ];
    }
}
