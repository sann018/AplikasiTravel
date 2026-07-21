<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Menstandardisasi nama kolom path gambar banner tanpa mengubah nilainya. */
return new class extends Migration
{
    public function up(): void
    {
        $this->renameColumn('gambar_path', 'path_gambar');
    }

    public function down(): void
    {
        $this->renameColumn('path_gambar', 'gambar_path');
    }

    private function renameColumn(string $from, string $to): void
    {
        if (! Schema::hasTable('banner_beranda')) {
            throw new RuntimeException('Tabel banner_beranda tidak ditemukan.');
        }

        if (! Schema::hasColumn('banner_beranda', $from)) {
            throw new RuntimeException("Kolom banner_beranda.{$from} tidak ditemukan.");
        }

        if (Schema::hasColumn('banner_beranda', $to)) {
            throw new RuntimeException("Kolom banner_beranda.{$to} sudah tersedia.");
        }

        Schema::table('banner_beranda', function (Blueprint $table) use ($from, $to) {
            $table->renameColumn($from, $to);
        });
    }
};
