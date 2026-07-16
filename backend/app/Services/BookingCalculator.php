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
        // Barang bawaan biasa dicatat tanpa tarif. Sepeda, motor, dan barang
        // besar dikonfirmasi langsung kepada admin di luar kalkulasi otomatis.
        $baggage = $this->baggageCalculator->calculate(
            $jadwal,
            max(1, $passengerCount ?? $seatCount),
            $baggageItems,
        );
        $biayaBagasi = 0.0;

        return [
            'subtotal_tiket' => $subtotalTiket,
            'biaya_bagasi' => $biayaBagasi,
            'diskon' => 0.0,
            'total_harga' => $subtotalTiket + $biayaBagasi,
            'bagasi' => $baggage,
        ];
    }
}
