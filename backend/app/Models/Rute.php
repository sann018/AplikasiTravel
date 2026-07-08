<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rute extends Model
{
    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'rute';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kota_asal',
        'kota_tujuan',
        'deskripsi_jalur',
        'jarak_km',
        'estimasi_durasi_menit',
        'harga_tiket',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'jarak_km' => 'decimal:2',
            'harga_tiket' => 'decimal:2',
            'status_aktif' => 'boolean',
        ];
    }

    public function getDurasiLabelAttribute(): string
    {
        $minutes = (int) $this->estimasi_durasi_menit;

        if ($minutes <= 0) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        // Durasi tetap disimpan menit agar mudah dihitung, lalu diubah
        // menjadi jam-menit supaya lebih gampang dibaca admin dan pengguna.
        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours} jam {$remainingMinutes} menit";
        }

        if ($hours > 0) {
            return "{$hours} jam";
        }

        return "{$remainingMinutes} menit";
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'rute_id');
    }
}
