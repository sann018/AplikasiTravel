<?php

namespace App\Services;

use App\Models\Jadwal;

class BaggageCalculatorService
{
    /**
     * Barang bawaan tetap dinormalisasi untuk kebutuhan manifest, tetapi tidak
     * menambah harga pemesanan pada versi sistem saat ini.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function calculate(Jadwal $schedule, int $passengerCount, array $items): array
    {
        $totals = ['weight' => 0.0, 'size' => 0, 'items' => 0];
        $breakdowns = [];

        foreach ($items as $index => $item) {
            $totals['weight'] += max(0, (float) data_get($item, 'berat_kg', 0));
            $quantity = max(0, (int) data_get($item, 'jumlah_item', 0));
            $totals['items'] += $quantity;
            $breakdowns[$index] = 0.0;
        }

        return [
            'weight_fee' => 0.0,
            'size_fee' => 0.0,
            'item_fee' => 0.0,
            'item_breakdowns' => $breakdowns,
            'totals' => [
                ...$totals,
                'existing_vehicle_weight' => 0.0,
                'vehicle_weight_after_booking' => $totals['weight'],
            ],
        ];
    }
}
