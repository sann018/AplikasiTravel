<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Menyimpan tabel pengaturan lama sebagai backup tanpa menghapus datanya. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pengaturan_sistem')) {
            throw new RuntimeException('Tabel legacy pengaturan_sistem tidak ditemukan.');
        }

        if (Schema::hasTable('pengaturan_sistem_legacy_backup')) {
            throw new RuntimeException('Tabel pengaturan_sistem_legacy_backup sudah tersedia.');
        }

        Schema::rename('pengaturan_sistem', 'pengaturan_sistem_legacy_backup');
    }

    public function down(): void
    {
        if (! Schema::hasTable('pengaturan_sistem_legacy_backup')) {
            throw new RuntimeException('Tabel pengaturan_sistem_legacy_backup tidak ditemukan.');
        }

        if (Schema::hasTable('pengaturan_sistem')) {
            throw new RuntimeException('Nama pengaturan_sistem sudah digunakan.');
        }

        Schema::rename('pengaturan_sistem_legacy_backup', 'pengaturan_sistem');
    }
};
