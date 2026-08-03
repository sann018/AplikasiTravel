<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiayaOperasionalPerjalanan extends Model
{
    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'biaya_operasional_perjalanan';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'jadwal_id',
        'jenis_biaya',
        'nominal',
        'keterangan',
        'tanggal_biaya',
        'bukti_url',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'tanggal_biaya' => 'datetime',
        ];
    }

    /**
     * Semua biaya yang tersimpan merupakan pencatatan final. Scope dipertahankan
     * agar query laporan lama tetap kompatibel setelah atribut verifikasi dihapus.
     */
    public function scopeFinalized(Builder $query): Builder
    {
        return $query;
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
