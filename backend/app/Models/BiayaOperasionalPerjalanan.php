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
        'status_verifikasi',
        'diverifikasi_oleh',
        'diverifikasi_pada',
        'catatan_verifikasi',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'tanggal_biaya' => 'datetime',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    /**
     * Kolom verifikasi dipertahankan untuk data lama. Pencatatan versi saat ini
     * langsung berstatus disetujui agar dapat masuk ke rekap tanpa alur approval.
     */
    public function scopeFinalized(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder
                ->where('status_verifikasi', 'disetujui')
                ->orWhereNull('status_verifikasi');
        });
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
