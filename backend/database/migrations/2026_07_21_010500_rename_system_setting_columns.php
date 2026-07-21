<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Menstandardisasi kolom tabel pengaturan aktif tanpa mengubah isi konfigurasi. */
return new class extends Migration
{
    private const UP_COLUMNS = [
        'group' => 'kelompok',
        'key' => 'kunci',
        'value' => 'nilai',
        'type' => 'tipe',
        'description' => 'deskripsi',
        'updated_by' => 'diperbarui_oleh',
    ];

    public function up(): void
    {
        $this->renameColumns(self::UP_COLUMNS);
        Cache::forget('system_settings');
    }

    public function down(): void
    {
        $this->renameColumns(array_flip(self::UP_COLUMNS));
        Cache::forget('system_settings');
    }

    /** @param array<string, string> $columns */
    private function renameColumns(array $columns): void
    {
        if (! Schema::hasTable('pengaturan_sistem')) {
            throw new RuntimeException('Tabel pengaturan_sistem aktif tidak ditemukan.');
        }

        foreach ($columns as $from => $to) {
            if (! Schema::hasColumn('pengaturan_sistem', $from)) {
                throw new RuntimeException("Kolom pengaturan_sistem.{$from} tidak ditemukan.");
            }

            if (Schema::hasColumn('pengaturan_sistem', $to)) {
                throw new RuntimeException("Kolom pengaturan_sistem.{$to} sudah tersedia.");
            }

            Schema::table('pengaturan_sistem', function (Blueprint $table) use ($from, $to) {
                $table->renameColumn($from, $to);
            });
        }
    }
};
