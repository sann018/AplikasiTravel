<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jadwal extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'jadwal';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kendaraan_id',
        'rute_id',
        'pengemudi_id',
        'waktu_berangkat',
        'estimasi_tiba',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'waktu_berangkat' => 'datetime',
            'estimasi_tiba' => 'datetime',
        ];
    }

    public function kendaraan(): BelongsTo
    {
        return $this->belongsTo(Kendaraan::class, 'kendaraan_id');
    }

    public function rute(): BelongsTo
    {
        return $this->belongsTo(Rute::class, 'rute_id');
    }

    public function pengemudi(): BelongsTo
    {
        return $this->belongsTo(Pengemudi::class, 'pengemudi_id');
    }

    public function kursi(): HasMany
    {
        return $this->hasMany(KursiJadwal::class, 'jadwal_id');
    }

    public function pemesanan(): HasMany
    {
        return $this->hasMany(Pemesanan::class, 'jadwal_id');
    }
}
