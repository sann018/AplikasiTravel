<?php

namespace App\Services;

use App\Models\Jadwal;
use Illuminate\Validation\ValidationException;

class BaggageCalculatorService
{
    public function __construct(private readonly SettingService $settingService) {}

    /**
     * Mobile hanya memberi estimasi. Validasi foto, batas, dan biaya tetap diulang di sini.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function calculate(Jadwal $schedule, int $seatCount, array $items): array
    {
        $totals = ['weight' => 0.0, 'size' => 0, 'items' => 0];

        foreach ($items as $index => $item) {
            $method = $this->normalizeMethod(data_get($item, 'metode_hitung'));
            $weight = (float) data_get($item, 'berat_kg', 0);
            $size = $this->resolveSize($item);

            if ($method === 'berat') {
                $size = 0;
            } elseif ($method === 'ukuran') {
                $weight = 0.0;
            }

            if ($this->settingService->get('baggage_photo_required', false) && blank(data_get($item, 'foto_url'))) {
                throw ValidationException::withMessages([
                    "baggage_items.$index.foto_url" => 'Foto barang bawaan wajib diunggah sebelum melanjutkan.',
                ]);
            }

            $totals['weight'] += $weight;
            $totals['size'] += $size;
            $totals['items'] += max(1, (int) data_get($item, 'jumlah_item', 1));
        }

        $existingWeight = $this->activeVehicleWeight($schedule);

        return [
            'weight_fee' => $this->weightFee($totals['weight'], $seatCount, $existingWeight),
            'size_fee' => $this->sizeFee($totals['size'], $seatCount),
            'item_fee' => $this->itemFee($totals['items'], $seatCount),
            'totals' => [
                ...$totals,
                'existing_vehicle_weight' => $existingWeight,
                'vehicle_weight_after_booking' => $existingWeight + $totals['weight'],
            ],
        ];
    }

    private function weightFee(float $weight, int $seatCount, float $existingWeight): float
    {
        if (! $this->settingService->get('baggage_weight_enabled', false)) {
            return 0.0;
        }

        $free = (float) $this->settingService->get('baggage_free_weight_kg', 0) * $seatCount;
        $maxPerBooking = (float) $this->settingService->get('baggage_max_weight_per_person_kg', 0) * $seatCount;
        $maxVehicle = (float) $this->settingService->get('baggage_max_total_weight_per_vehicle', 0);

        if ($maxPerBooking > 0 && $weight > $maxPerBooking) {
            throw ValidationException::withMessages([
                'baggage_items' => 'Berat barang bawaan melebihi batas maksimal penumpang.',
            ]);
        }

        if ($maxVehicle > 0 && ($existingWeight + $weight) > $maxVehicle) {
            throw ValidationException::withMessages([
                'baggage_items' => 'Kapasitas bagasi kendaraan tidak mencukupi untuk berat tersebut.',
            ]);
        }

        return max(0, $weight - $free) * (float) $this->settingService->get('baggage_extra_fee_per_kg', 0);
    }

    private function sizeFee(int $size, int $seatCount): float
    {
        if (! $this->settingService->get('baggage_size_enabled', false)) {
            return 0.0;
        }

        $free = (float) $this->settingService->get('baggage_free_size_cm', 0) * $seatCount;
        $max = (float) $this->settingService->get('baggage_max_size_per_person_cm', 0) * $seatCount;
        if ($max > 0 && $size > $max) {
            throw ValidationException::withMessages([
                'baggage_items' => 'Ukuran barang bawaan melebihi batas maksimal penumpang.',
            ]);
        }

        return max(0, $size - $free) * (float) $this->settingService->get('baggage_extra_fee_per_cm', 0);
    }

    private function itemFee(int $itemCount, int $seatCount): float
    {
        if (! $this->settingService->get('baggage_item_enabled', false)) {
            return 0.0;
        }

        $free = (int) $this->settingService->get('baggage_free_item_count', 0) * $seatCount;
        $max = (int) $this->settingService->get('baggage_max_item_per_person', 0) * $seatCount;
        if ($max > 0 && $itemCount > $max) {
            throw ValidationException::withMessages([
                'baggage_items' => 'Jumlah barang bawaan melebihi batas maksimal penumpang.',
            ]);
        }

        return max(0, $itemCount - $free) * (float) $this->settingService->get('baggage_extra_fee_per_item', 0);
    }

    private function activeVehicleWeight(Jadwal $schedule): float
    {
        return (float) $schedule->pemesanan()
            ->whereIn('status_pemesanan', ['menunggu_pembayaran', 'terbayar'])
            ->with('itemBagasi:id,pemesanan_id,berat_kg')
            ->get()
            ->sum(fn ($booking) => $booking->itemBagasi->sum(fn ($item) => (float) $item->berat_kg));
    }

    /** @param array<string, mixed> $item */
    private function resolveSize(array $item): int
    {
        if (filled(data_get($item, 'ukuran_cm'))) {
            return (int) data_get($item, 'ukuran_cm');
        }

        return (int) data_get($item, 'panjang_cm', 0) + (int) data_get($item, 'lebar_cm', 0);
    }

    private function normalizeMethod(mixed $value): ?string
    {
        $method = is_string($value) ? strtolower($value) : null;

        return in_array($method, ['berat', 'ukuran'], true) ? $method : null;
    }
}
