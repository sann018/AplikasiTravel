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
    public function calculate(Jadwal $jadwal, int $seatCount, array $baggageItems = [], ?int $passengerCount = null): array
    {
        $subtotalTiket = (float) $jadwal->rute->harga_tiket * $seatCount;
        // Tiket tetap dihitung dari kursi, sedangkan jatah gratis bagasi mengikuti data penumpang valid.
        $baggageAllowanceCount = max(1, $passengerCount ?? $seatCount);
        $baggage = $this->baggageCalculator->calculate($jadwal, $baggageAllowanceCount, $baggageItems);
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
