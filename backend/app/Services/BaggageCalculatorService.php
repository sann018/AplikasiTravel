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
    public function calculate(Jadwal $schedule, int $passengerCount, array $items): array
    {
        $totals = ['weight' => 0.0, 'size' => 0, 'items' => 0];
        $chargeableItems = [];
        $baggageEnabled = $this->baggageRulesEnabled();

        foreach ($items as $index => $item) {
            $placement = $this->normalizePlacement(data_get($item, 'posisi_barang'));
            $method = 'berat';
            $weight = (float) data_get($item, 'berat_kg', 0);
            $size = 0;

            if ($baggageEnabled && $placement === 'masuk_bagasi' && $this->settingService->get('baggage_photo_required', false) && blank(data_get($item, 'foto_url'))) {
                throw ValidationException::withMessages([
                    "baggage_items.$index.foto_url" => 'Foto barang bawaan wajib diunggah sebelum melanjutkan.',
                ]);
            }

            if ($placement === 'dipegang_sendiri') {
                continue;
            }

            $quantity = max(1, (int) data_get($item, 'jumlah_item', 1));
            $totals['weight'] += $weight;
            $totals['size'] += $size;
            $totals['items'] += $quantity;
            $chargeableItems[] = [
                'index' => $index,
                'quantity' => $quantity,
            ];
        }

        $existingWeight = $this->activeVehicleWeight($schedule);
        $itemFee = $this->itemFee($totals['items'], $passengerCount, $chargeableItems);

        return [
            'weight_fee' => $this->weightFee($totals['weight'], $passengerCount, $existingWeight),
            'size_fee' => $this->sizeFee($totals['size'], $passengerCount),
            'item_fee' => $itemFee['total'],
            'item_breakdowns' => $itemFee['breakdowns'],
            'totals' => [
                ...$totals,
                'existing_vehicle_weight' => $existingWeight,
                'vehicle_weight_after_booking' => $existingWeight + $totals['weight'],
            ],
        ];
    }

    private function weightFee(float $weight, int $passengerCount, float $existingWeight): float
    {
        if (! $this->settingService->get('baggage_weight_enabled', false)) {
            return 0.0;
        }

        // Batas gratis dihitung dari jumlah penumpang terdaftar agar kursi ekstra tidak menambah jatah bagasi.
        $free = (float) $this->settingService->get('baggage_free_weight_kg', 0) * $passengerCount;
        $maxPerBooking = (float) $this->settingService->get('baggage_max_weight_per_person_kg', 0) * $passengerCount;
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

    private function sizeFee(int $size, int $passengerCount): float
    {
        if (! $this->settingService->get('baggage_size_enabled', false)) {
            return 0.0;
        }

        $free = (float) $this->settingService->get('baggage_free_size_cm', 0) * $passengerCount;
        $max = (float) $this->settingService->get('baggage_max_size_per_person_cm', 0) * $passengerCount;
        if ($max > 0 && $size > $max) {
            throw ValidationException::withMessages([
                'baggage_items' => 'Ukuran barang bawaan melebihi batas maksimal penumpang.',
            ]);
        }

        return max(0, $size - $free) * (float) $this->settingService->get('baggage_extra_fee_per_cm', 0);
    }

    /**
     * Kuota gratis hanya dipakai oleh barang yang benar-benar masuk bagasi.
     * Kategori kecil/besar tidak dipakai di versi TA agar alurnya sederhana.
     *
     * @param  array<int, array{index:int, quantity:int}>  $chargeableItems
     * @return array{total:float, breakdowns:array<int, float>}
     */
    private function itemFee(int $itemCount, int $passengerCount, array $chargeableItems): array
    {
        if (! $this->settingService->get('baggage_item_enabled', false)) {
            return ['total' => 0.0, 'breakdowns' => []];
        }

        $free = (int) $this->settingService->get('baggage_free_item_count', 0) * $passengerCount;
        $max = (int) $this->settingService->get('baggage_max_item_per_person', 0) * $passengerCount;
        if ($max > 0 && $itemCount > $max) {
            throw ValidationException::withMessages([
                'baggage_items' => 'Jumlah barang bawaan melebihi batas maksimal penumpang.',
            ]);
        }

        $remainingFree = $free;
        $total = 0.0;
        $breakdowns = [];
        $feePerItem = (float) $this->settingService->get('baggage_extra_fee_per_item', 0);

        foreach ($chargeableItems as $item) {
            $paidQuantity = max(0, $item['quantity'] - $remainingFree);
            $remainingFree = max(0, $remainingFree - $item['quantity']);
            $fee = $paidQuantity * $feePerItem;
            $breakdowns[$item['index']] = $fee;
            $total += $fee;
        }

        return ['total' => $total, 'breakdowns' => $breakdowns];
    }

    private function activeVehicleWeight(Jadwal $schedule): float
    {
        return (float) $schedule->pemesanan()
            ->whereIn('status_pemesanan', ['menunggu_pembayaran', 'terbayar'])
            ->with('itemBagasi:id,pemesanan_id,berat_kg,posisi_barang')
            ->get()
            ->sum(fn ($booking) => $booking->itemBagasi
                ->filter(fn ($item) => ($item->posisi_barang ?? 'masuk_bagasi') === 'masuk_bagasi')
                ->sum(fn ($item) => (float) $item->berat_kg));
    }

    private function normalizePlacement(mixed $value): string
    {
        $placement = is_string($value) ? strtolower($value) : 'masuk_bagasi';

        return in_array($placement, ['dipegang_sendiri', 'masuk_bagasi'], true)
            ? $placement
            : 'masuk_bagasi';
    }

    private function baggageRulesEnabled(): bool
    {
        return (bool) $this->settingService->get('baggage_weight_enabled', false)
            || (bool) $this->settingService->get('baggage_size_enabled', false)
            || (bool) $this->settingService->get('baggage_item_enabled', false);
    }
}
