<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rute extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rute';

    /**
     * The attributes that are mass assignable.
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

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'rute_id');
    }
}
