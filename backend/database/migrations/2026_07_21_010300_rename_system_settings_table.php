<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Menjadikan tabel pengaturan aktif menggunakan nama bahasa Indonesia. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            throw new RuntimeException('Tabel system_settings tidak ditemukan.');
        }

        if (Schema::hasTable('pengaturan_sistem')) {
            throw new RuntimeException('Nama pengaturan_sistem masih digunakan.');
        }

        Schema::rename('system_settings', 'pengaturan_sistem');
    }

    public function down(): void
    {
        if (! Schema::hasTable('pengaturan_sistem')) {
            throw new RuntimeException('Tabel pengaturan_sistem aktif tidak ditemukan.');
        }

        if (Schema::hasTable('system_settings')) {
            throw new RuntimeException('Nama system_settings sudah digunakan.');
        }

        Schema::rename('pengaturan_sistem', 'system_settings');
    }
};
