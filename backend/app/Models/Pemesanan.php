<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pemesanan extends Model
{
    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'pemesanan';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pengguna_id',
        'dibuat_oleh',
        'sumber_pemesanan',
        'nama_pemesan',
        'no_hp_pemesan',
        'email_pemesan',
        'jadwal_id',
        'kode_booking',
        'jumlah_kursi',
        'batas_kunci_kursi',
        'batas_pembayaran',
        'lintang_jemput',
        'bujur_jemput',
        'alamat_jemput',
        'catatan_jemput',
        'lintang_turun',
        'bujur_turun',
        'alamat_turun',
        'catatan_turun',
        'subtotal_tiket',
        'total_harga',
        'status_pemesanan',
    ];

    protected function casts(): array
    {
        return [
            'batas_kunci_kursi' => 'datetime',
            'batas_pembayaran' => 'datetime',
            'subtotal_tiket' => 'decimal:2',
            'total_harga' => 'decimal:2',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function isPemesananLoket(): bool
    {
        return $this->sumber_pemesanan === 'loket';
    }

    public function namaKontakPemesan(): string
    {
        return $this->nama_pemesan ?: ($this->pengguna?->nama ?: 'Pemesan loket');
    }

    public function nomorKontakPemesan(): ?string
    {
        return $this->no_hp_pemesan ?: $this->pengguna?->no_hp;
    }

    public function emailKontakPemesan(): ?string
    {
        return $this->email_pemesan ?: $this->pengguna?->email;
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_id');
    }

    public function detailPemesanan(): HasMany
    {
        return $this->hasMany(DetailPemesanan::class, 'pemesanan_id');
    }

    public function pembayaran(): HasOne
    {
        return $this->hasOne(Pembayaran::class, 'pemesanan_id');
    }

    public function itemBagasi(): HasMany
    {
        return $this->hasMany(ItemBagasi::class, 'pemesanan_id');
    }
}
