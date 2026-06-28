<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notifikasi extends Model
{
    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'notifikasi';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pengguna_id',
        'judul',
        'pesan',
        'jenis',
        'referensi_id',
        'sudah_dibaca',
    ];

    protected function casts(): array
    {
        return [
            'sudah_dibaca' => 'boolean',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    /** Relasi ini dipakai hanya untuk notifikasi pemesanan pada dashboard admin. */
    public function referensiBooking(): BelongsTo
    {
        return $this->belongsTo(Pemesanan::class, 'referensi_id');
    }
}
