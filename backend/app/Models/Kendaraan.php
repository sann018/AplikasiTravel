<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kendaraan extends Model
{
    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'kendaraan';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
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

    /// Mengembalikan susunan kursi per baris untuk denah di aplikasi.
    /// Jika admin belum mengatur susunan, sistem membuat susunan awal dari kapasitas.
    /// Contoh [1, 3, 2]: baris pertama 1 kursi, kedua 3 kursi, ketiga 2 kursi.
    public function getSeatLayoutConfig(): array
    {
        if (! empty($this->seat_layout) && is_array($this->seat_layout)) {
            return $this->seat_layout;
        }

        // Kursi pertama ditempatkan di depan, sisanya dibagi ke baris belakang.
        return $this->defaultSeatLayout($this->kapasitas_kursi);
    }

    /// Membuat susunan kursi awal berdasarkan kapasitas kendaraan.
    /// Polanya satu kursi depan, lalu dua atau tiga kursi pada baris berikutnya.
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

        // Kendaraan besar tetap memakai satu kursi depan dan tiga kursi per baris.
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
