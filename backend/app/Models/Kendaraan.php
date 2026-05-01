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
        'fasilitas',
        'total_kilometer',
        'km_servis_berikutnya',
        'tgl_terakhir_servis',
        'tgl_pajak_tahunan',
        'status_kendaraan',
    ];

    protected function casts(): array
    {
        return [
            'tgl_terakhir_servis' => 'date',
            'tgl_pajak_tahunan' => 'date',
        ];
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'kendaraan_id');
    }
}
