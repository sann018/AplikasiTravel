<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'pengguna';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama',
        'email',
        'no_hp',
        'foto_profil',
        'peran',
        'token_push',
        'status_aktif',
        'password',
        'otp_code',
        'otp_expires_at',
        'email_verified_at',
    ];

    /**
     * Kolom sensitif yang tidak boleh ikut tampil pada JSON atau balasan API.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    /**
     * Mengatur perubahan tipe data otomatis saat nilai dibaca dari model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'status_aktif' => 'boolean',
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function pengemudiProfile(): HasOne
    {
        return $this->hasOne(Pengemudi::class, 'pengguna_id');
    }

    public function pemesanan(): HasMany
    {
        return $this->hasMany(Pemesanan::class, 'pengguna_id');
    }

    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'pengguna_id');
    }

    public function isAdmin(): bool
    {
        return $this->peran === 'admin';
    }

    public function isPengemudi(): bool
    {
        return $this->peran === 'pengemudi';
    }

    public function isPenumpang(): bool
    {
        return $this->peran === 'penumpang';
    }
}
