<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiketElektronik extends Model
{
    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'tiket_elektronik';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
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
