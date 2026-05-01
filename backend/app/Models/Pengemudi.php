<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengemudi extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pengemudi';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pengguna_id',
        'nomor_sim',
        'foto_sim',
        'status',
        'status_verifikasi',
        'catatan_verifikasi',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'pengemudi_id');
    }
}
