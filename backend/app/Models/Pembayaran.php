<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'pembayaran';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pemesanan_id',
        'order_id_gateway',
        'gateway_reference',
        'metode_bayar',
        'paid_channel',
        'jumlah_bayar',
        'status_bayar',
        'gateway_response',
        'raw_webhook_payload',
        'expired_at',
        'webhook_verified_at',
        'dibayar_pada',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_bayar' => 'decimal:2',
            'raw_webhook_payload' => 'array',
            'expired_at' => 'datetime',
            'webhook_verified_at' => 'datetime',
            'dibayar_pada' => 'datetime',
        ];
    }

    public function pemesanan(): BelongsTo
    {
        return $this->belongsTo(Pemesanan::class, 'pemesanan_id');
    }
}
