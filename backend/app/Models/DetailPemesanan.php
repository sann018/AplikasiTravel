<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DetailPemesanan extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'detail_pemesanan';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pemesanan_id',
        'kursi_jadwal_id',
        'nomor_kursi',
        'nama_penumpang',
        'no_hp_penumpang',
        'status_kehadiran',
        'dijemput_pada',
        'diturunkan_pada',
    ];

    protected function casts(): array
    {
        return [
            'dijemput_pada' => 'datetime',
            'diturunkan_pada' => 'datetime',
        ];
    }

    public function pemesanan(): BelongsTo
    {
        return $this->belongsTo(Pemesanan::class, 'pemesanan_id');
    }

    public function kursiJadwal(): BelongsTo
    {
        return $this->belongsTo(KursiJadwal::class, 'kursi_jadwal_id');
    }

    public function tiketElektronik(): HasOne
    {
        return $this->hasOne(TiketElektronik::class, 'detail_pemesanan_id');
    }
}
