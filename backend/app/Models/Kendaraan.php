<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kendaraan extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kendaraan';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nomor_plat',
        'merk_kendaraan',
        'warna',
        'tahun_kendaraan',
        'tipe_kendaraan',
        'kapasitas_kursi',
        'seat_layout',
        'fasilitas',
        'total_kilometer',
        'km_servis_berikutnya',
        'tgl_terakhir_servis',
        'tgl_pajak_tahunan',
        'status_kendaraan',
        'foto_kendaraan',
    ];

    protected function casts(): array
    {
        return [
            'tgl_terakhir_servis' => 'date',
            'tgl_pajak_tahunan' => 'date',
            'seat_layout' => 'array',
        ];
    }

    /// Mengembalikan konfigurasi baris kursi.
    /// Jika seat_layout belum diisi, generate default berdasarkan kapasitas.
    /// Format: [1, 3, 2] = baris 1 ada 1 kursi, baris 2 ada 3 kursi, baris 3 ada 2 kursi.
    public function getSeatLayoutConfig(): array
    {
        if (! empty($this->seat_layout) && is_array($this->seat_layout)) {
            return $this->seat_layout;
        }

        // Default layout: baris depan 1 kursi (samping sopir), sisanya dibagi rata
        return $this->defaultSeatLayout($this->kapasitas_kursi);
    }

    /// Generate layout kursi default berdasarkan kapasitas.
    /// Pola umum travel: 1 kursi depan (samping sopir), lalu baris 2-3 kursi.
    private function defaultSeatLayout(int $capacity): array
    {
        if ($capacity <= 0) return [];
        if ($capacity == 1) return [1];
        if ($capacity == 2) return [1, 1];
        if ($capacity == 3) return [1, 2];
        if ($capacity == 4) return [1, 3];
        if ($capacity == 5) return [1, 2, 2];
        if ($capacity == 6) return [1, 3, 2];
        if ($capacity == 7) return [1, 3, 3];
        if ($capacity == 8) return [1, 3, 2, 2];

        // Untuk kapasitas lebih besar, baris pertama 1, sisanya baris 3
        $rows = [1];
        $remaining = $capacity - 1;
        while ($remaining > 0) {
            $rowSize = min(3, $remaining);
            $rows[] = $rowSize;
            $remaining -= $rowSize;
        }
        return $rows;
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'kendaraan_id');
    }
}
