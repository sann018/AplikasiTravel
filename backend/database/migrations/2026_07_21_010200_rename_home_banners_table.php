<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Menstandardisasi nama tabel banner tanpa mengubah data atau kontrak API. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('home_banners')) {
            throw new RuntimeException('Tabel home_banners tidak ditemukan.');
        }

        if (Schema::hasTable('banner_beranda')) {
            throw new RuntimeException('Tabel banner_beranda sudah tersedia.');
        }

        Schema::rename('home_banners', 'banner_beranda');
    }

    public function down(): void
    {
        if (! Schema::hasTable('banner_beranda')) {
            throw new RuntimeException('Tabel banner_beranda tidak ditemukan.');
        }

        if (Schema::hasTable('home_banners')) {
            throw new RuntimeException('Nama home_banners sudah digunakan.');
        }

        Schema::rename('banner_beranda', 'home_banners');
    }
};
