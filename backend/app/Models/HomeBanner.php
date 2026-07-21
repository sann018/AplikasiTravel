<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeBanner extends Model
{
    protected $table = 'banner_beranda';

    protected $fillable = [
        'judul',
        'subjudul',
        'path_gambar',
        'aktif',
        'urutan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'urutan' => 'integer',
        ];
    }
}
