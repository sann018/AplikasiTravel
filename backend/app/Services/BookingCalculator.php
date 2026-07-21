<?php

namespace App\Services;

use App\Models\Jadwal;

class BookingCalculator
{
    /**
     * Total selalu dihitung backend agar nominal dari aplikasi tidak bisa dimanipulasi.
     *
     * Barang bawaan dicatat untuk kebutuhan manifes tanpa memengaruhi tarif,
     * sehingga total pemesanan sama dengan subtotal harga tiket.
     *
     * @return array<string, mixed>
     */
    public function calculate(Jadwal $jadwal, int $seatCount): array
    {
        $subtotalTiket = (float) $jadwal->rute->harga_tiket * $seatCount;

        return [
            'subtotal_tiket' => $subtotalTiket,
            'total_harga' => $subtotalTiket,
        ];
    }
}
