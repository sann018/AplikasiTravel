<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KursiJadwal extends Model
{
    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'kursi_jadwal';

    /**
     * Menentukan apakah Laravel perlu mengatur waktu pembuatan/perubahan otomatis.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'jadwal_id',
        'nomor_kursi',
        'status_kursi',
        'dikunci_oleh',
        'locked_at',
        'lock_expires_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'lock_expires_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_id');
    }

    public function pengunci(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikunci_oleh');
    }

    public function detailPemesanan(): HasOne
    {
        return $this->hasOne(DetailPemesanan::class, 'kursi_jadwal_id');
    }
}
