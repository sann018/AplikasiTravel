<?php

namespace App\Models;

use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanSistem extends Model
{
    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'pengaturan_sistem';

    /**
     * Menentukan apakah Laravel perlu mengatur waktu pembuatan/perubahan otomatis.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Kolom yang menjadi kunci utama tabel pengaturan.
     *
     * @var string
     */
    protected $primaryKey = 'kunci';

    /**
     * Menentukan apakah nilai kunci utama bertambah otomatis.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tipe data kunci utama karena tabel ini memakai teks, bukan angka otomatis.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
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
        // Jembatan ini menjaga kode lama tetap bekerja sambil memakai pusat pengaturan baru.
        return app(SettingService::class)->get($kunci, $default);
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
