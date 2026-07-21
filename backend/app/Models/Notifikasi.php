<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notifikasi extends Model
{
    /**
     * Judul ini menjadi penanda aman bahwa referensi_id menunjuk jadwal.
     * Judul perjalanan lain tetap diperlakukan sebagai referensi pemesanan.
     */
    public const SCHEDULE_REFERENCE_TITLES = [
        'Tugas perjalanan baru',
        'Jadwal mendekati keberangkatan',
        'Kapasitas perjalanan hampir penuh',
        'Armada telah berangkat',
    ];

    /**
     * Nama tabel database yang digunakan model ini.
     *
     * @var string
     */
    protected $table = 'notifikasi';

    /**
     * Kolom yang boleh diisi sekaligus saat membuat atau memperbarui data.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pengguna_id',
        'judul',
        'pesan',
        'jenis',
        'referensi_id',
        'sudah_dibaca',
    ];

    protected function casts(): array
    {
        return [
            'sudah_dibaca' => 'boolean',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    /** Relasi ini dipakai hanya untuk notifikasi pemesanan pada dashboard admin. */
    public function referensiBooking(): BelongsTo
    {
        return $this->belongsTo(Pemesanan::class, 'referensi_id');
    }

    /** Relasi jadwal hanya dipakai setelah jenis referensi diperiksa lewat judul. */
    public function referensiJadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'referensi_id');
    }

    public function hasScheduleReference(): bool
    {
        return $this->referensi_id !== null
            && in_array($this->judul, self::SCHEDULE_REFERENCE_TITLES, true);
    }
}
