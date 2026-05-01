<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\PengaturanSistem;
use Illuminate\Validation\ValidationException;

class BookingCalculator
{
    /**
     * Calculate booking totals based on route price and baggage settings.
     *
     * @param  array<int, array<string, mixed>>  $baggageItems
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function calculate(Jadwal $jadwal, int $seatCount, array $baggageItems = []): array
    {
        $subtotalTiket = (float) $jadwal->rute->harga_tiket * $seatCount;
        $adminFee = (float) PengaturanSistem::nilai('admin_fee', 0);

        $totals = [
            'weight' => 0.0,
            'size' => 0,
            'items' => 0,
        ];

        foreach ($baggageItems as $index => $item) {
            $method = $this->normalizeCalculationMethod(data_get($item, 'metode_hitung'));
            $weight = (float) ($item['berat_kg'] ?? 0);
            $size = $this->resolveBaggageSize($item);

            if ($method === 'berat') {
                $size = 0;
            } elseif ($method === 'ukuran') {
                $weight = 0.0;
            }

            $totals['weight'] += $weight;
            $totals['size'] += $size;
            $totals['items'] += max(1, (int) ($item['jumlah_item'] ?? 1));

            if (
                PengaturanSistem::nilai('baggage_photo_required', false)
                && empty($item['foto_url'])
            ) {
                throw ValidationException::withMessages([
                    "baggage_items.$index.foto_url" => 'Foto bagasi wajib diunggah sesuai pengaturan sistem.',
                ]);
            }
        }

        $baggageBreakdown = [
            'weight_fee' => $this->calculateWeightFee($totals['weight'], $seatCount),
            'size_fee' => $this->calculateSizeFee($totals['size'], $seatCount),
            'item_fee' => $this->calculateItemFee($totals['items'], $seatCount),
            'totals' => $totals,
        ];

        $biayaBagasi = $baggageBreakdown['weight_fee'] + $baggageBreakdown['size_fee'] + $baggageBreakdown['item_fee'];
        $total = $subtotalTiket + $adminFee + $biayaBagasi;

        return [
            'subtotal_tiket' => $subtotalTiket,
            'biaya_admin' => $adminFee,
            'biaya_bagasi' => $biayaBagasi,
            'diskon' => 0.0,
            'total_harga' => $total,
            'bagasi' => $baggageBreakdown,
        ];
    }

    /**
     * @throws ValidationException
     */
    private function calculateWeightFee(float $totalWeight, int $seatCount): float
    {
        if (! PengaturanSistem::nilai('baggage_weight_enabled', false)) {
            return 0.0;
        }

        $freeWeight = (float) PengaturanSistem::nilai('baggage_free_weight_kg', 0) * $seatCount;
        $maxWeightPerPassenger = (float) PengaturanSistem::nilai('baggage_max_weight_per_person_kg', 0) * $seatCount;
        $maxWeightPerVehicle = (float) PengaturanSistem::nilai('baggage_max_total_weight_per_vehicle', 0);

        if ($maxWeightPerPassenger > 0 && $totalWeight > $maxWeightPerPassenger) {
            throw ValidationException::withMessages([
                'baggage_items' => 'Total berat bagasi melebihi batas maksimal per penumpang.',
            ]);
        }

        if ($maxWeightPerVehicle > 0 && $totalWeight > $maxWeightPerVehicle) {
            throw ValidationException::withMessages([
                'baggage_items' => 'Total berat bagasi melebihi batas maksimal kendaraan.',
            ]);
        }

        $overFreeWeight = max(0, $totalWeight - $freeWeight);
        $rate = (float) PengaturanSistem::nilai('baggage_extra_fee_per_kg', 0);

        return $overFreeWeight * $rate;
    }

    /**
     * @throws ValidationException
     */
    private function calculateSizeFee(int $totalSize, int $seatCount): float
    {
        if (! PengaturanSistem::nilai('baggage_size_enabled', false)) {
            return 0.0;
        }

        $freeSize = (int) PengaturanSistem::nilai('baggage_free_size_cm', 0) * $seatCount;
        $maxSize = (int) PengaturanSistem::nilai('baggage_max_size_per_person_cm', 0) * $seatCount;

        if ($maxSize > 0 && $totalSize > $maxSize) {
            throw ValidationException::withMessages([
                'baggage_items' => 'Total ukuran bagasi melebihi batas maksimal per penumpang.',
            ]);
        }

        $overFreeSize = max(0, $totalSize - $freeSize);
        $rate = (float) PengaturanSistem::nilai('baggage_extra_fee_per_cm', 0);

        return $overFreeSize * $rate;
    }

    /**
     * @throws ValidationException
     */
    private function calculateItemFee(int $totalItems, int $seatCount): float
    {
        if (! PengaturanSistem::nilai('baggage_item_enabled', false)) {
            return 0.0;
        }

        $freeItems = (int) PengaturanSistem::nilai('baggage_free_item_count', 0) * $seatCount;
        $maxItems = (int) PengaturanSistem::nilai('baggage_max_item_per_person', 0) * $seatCount;

        if ($maxItems > 0 && $totalItems > $maxItems) {
            throw ValidationException::withMessages([
                'baggage_items' => 'Jumlah item bagasi melebihi batas maksimal per penumpang.',
            ]);
        }

        $overFreeItems = max(0, $totalItems - $freeItems);
        $rate = (float) PengaturanSistem::nilai('baggage_extra_fee_per_item', 0);

        return $overFreeItems * $rate;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveBaggageSize(array $item): int
    {
        if (isset($item['ukuran_cm']) && $item['ukuran_cm'] !== null && $item['ukuran_cm'] !== '') {
            return (int) $item['ukuran_cm'];
        }

        $length = (int) ($item['panjang_cm'] ?? 0);
        $width = (int) ($item['lebar_cm'] ?? 0);

        return $length + $width;
    }

    private function normalizeCalculationMethod(mixed $value): ?string
    {
        $method = is_string($value) ? strtolower($value) : null;

        return in_array($method, ['berat', 'ukuran'], true) ? $method : null;
    }
}
