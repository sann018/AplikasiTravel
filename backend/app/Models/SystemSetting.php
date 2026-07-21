<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $table = 'pengaturan_sistem';

    protected $fillable = [
        'kelompok',
        'kunci',
        'nilai',
        'tipe',
        'deskripsi',
        'diperbarui_oleh',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diperbarui_oleh');
    }

    public function typedValue(): mixed
    {
        return match ($this->tipe) {
            'integer' => (int) $this->nilai,
            'boolean' => filter_var($this->nilai, FILTER_VALIDATE_BOOL),
            'float' => (float) $this->nilai,
            default => $this->nilai,
        };
    }
}
