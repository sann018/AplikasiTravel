<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBagasi extends Model
{
    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'item_bagasi';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pemesanan_id',
        'deskripsi_item',
        'metode_hitung',
        'berat_kg',
        'ukuran_cm',
        'panjang_cm',
        'lebar_cm',
        'jumlah_item',
        'foto_url',
        'biaya_item',
    ];

    protected function casts(): array
    {
        return [
            'berat_kg' => 'decimal:2',
            'ukuran_cm' => 'integer',
            'panjang_cm' => 'integer',
            'lebar_cm' => 'integer',
            'biaya_item' => 'decimal:2',
        ];
    }

    public function pemesanan(): BelongsTo
    {
        return $this->belongsTo(Pemesanan::class, 'pemesanan_id');
    }
}
