<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiketElektronik extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tiket_elektronik';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'detail_pemesanan_id',
        'kode_qr',
        'token_unik_qr',
        'sudah_dipindai',
        'dipindai_pada',
        'diterbitkan_pada',
    ];

    protected function casts(): array
    {
        return [
            'sudah_dipindai' => 'boolean',
            'dipindai_pada' => 'datetime',
            'diterbitkan_pada' => 'datetime',
        ];
    }

    public function detailPemesanan(): BelongsTo
    {
        return $this->belongsTo(DetailPemesanan::class, 'detail_pemesanan_id');
    }
}
