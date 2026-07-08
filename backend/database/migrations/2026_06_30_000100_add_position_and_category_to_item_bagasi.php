<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_bagasi', function (Blueprint $table) {
            if (! Schema::hasColumn('item_bagasi', 'posisi_barang')) {
                $table->string('posisi_barang', 30)->default('masuk_bagasi')->after('metode_hitung');
            }

            if (! Schema::hasColumn('item_bagasi', 'kategori_ukuran')) {
                $table->string('kategori_ukuran', 30)->nullable()->after('posisi_barang');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_bagasi', function (Blueprint $table) {
            foreach (['kategori_ukuran', 'posisi_barang'] as $column) {
                if (Schema::hasColumn('item_bagasi', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
