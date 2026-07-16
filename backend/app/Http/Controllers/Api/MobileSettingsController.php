<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

class MobileSettingsController extends Controller
{
    private const SAFE_KEYS = [
        'app_name',
        'service_whatsapp',
        'max_seat_per_booking',
        'seat_lock_form_minutes',
        'seat_lock_payment_minutes',
        'payment_cash_enabled',
        'payment_cash_note',
        'baggage_weight_enabled',
        'baggage_size_enabled',
        'baggage_item_enabled',
        'baggage_photo_required',
        'baggage_free_weight_kg',
        'baggage_max_weight_per_person_kg',
        'baggage_extra_fee_per_kg',
        'baggage_free_size_cm',
        'baggage_max_size_per_person_cm',
        'baggage_extra_fee_per_cm',
        'baggage_free_item_count',
        'baggage_max_item_per_person',
        'baggage_extra_fee_per_item',
        'baggage_small_item_fee',
        'baggage_large_item_fee',
        'baggage_large_vehicle_enabled',
        'baggage_bicycle_fee',
        'baggage_motorcycle_fee',
        'baggage_heavy_item_fee',
        'baggage_other_large_item_fee',
        'baggage_max_total_weight_per_vehicle',
    ];

    public function __construct(private readonly SettingService $settingService) {}

    public function __invoke(): JsonResponse
    {
        $allSettings = $this->settingService->all();

        // Daftar putih mencegah secret atau konfigurasi admin ikut terkirim ke mobile.
        $safeSettings = collect(self::SAFE_KEYS)
            ->mapWithKeys(fn (string $key) => [$key => $allSettings[$key] ?? null])
            ->all();
        // Seluruh key bagasi lama tetap tersedia untuk kompatibilitas parsing,
        // tetapi tidak lagi mengaktifkan form maupun perhitungan tarif di mobile.
        foreach (self::SAFE_KEYS as $key) {
            if (str_starts_with($key, 'baggage_')) {
                $safeSettings[$key] = str_ends_with($key, '_enabled') || $key === 'baggage_photo_required'
                    ? false
                    : 0;
            }
        }
        $safeSettings['app_logo_url'] = $this->settingService->publicAssetPath(
            'app_logo',
            'travs-logo.svg',
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan aplikasi berhasil dimuat.',
            'data' => $safeSettings,
        ]);
    }
}
