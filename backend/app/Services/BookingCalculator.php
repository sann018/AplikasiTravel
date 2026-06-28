<?php

namespace App\Services;

use App\Models\Jadwal;

class BookingCalculator
{
    public function __construct(
        private readonly BaggageCalculatorService $baggageCalculator,
    ) {}

    /**
     * Total selalu dihitung backend agar nominal dari aplikasi tidak bisa dimanipulasi.
     *
     * @param  array<int, array<string, mixed>>  $baggageItems
     * @return array<string, mixed>
     */
    public function calculate(Jadwal $jadwal, int $seatCount, array $baggageItems = []): array
    {
        $subtotalTiket = (float) $jadwal->rute->harga_tiket * $seatCount;
        $baggage = $this->baggageCalculator->calculate($jadwal, $seatCount, $baggageItems);
        $biayaBagasi = $baggage['weight_fee'] + $baggage['size_fee'] + $baggage['item_fee'];

        return [
            'subtotal_tiket' => $subtotalTiket,
            'biaya_bagasi' => $biayaBagasi,
            'diskon' => 0.0,
            'total_harga' => $subtotalTiket + $biayaBagasi,
            'bagasi' => $baggage,
        ];
    }
}
