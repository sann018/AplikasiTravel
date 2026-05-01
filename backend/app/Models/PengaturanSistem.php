<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanSistem extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pengaturan_sistem';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'kunci';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kunci',
        'nilai',
        'tipe_data',
        'grup',
        'deskripsi',
        'updated_at',
        'updated_by',
    ];

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function nilai(string $kunci, mixed $default = null): mixed
    {
        $setting = static::query()->find($kunci);

        if (! $setting) {
            return $default;
        }

        return $setting->castNilai();
    }

    public function castNilai(): mixed
    {
        return match ($this->tipe_data) {
            'integer' => (int) $this->nilai,
            'decimal' => (float) $this->nilai,
            'boolean' => filter_var($this->nilai, FILTER_VALIDATE_BOOL),
            'json' => json_decode((string) $this->nilai, true),
            default => $this->nilai,
        };
    }
}
