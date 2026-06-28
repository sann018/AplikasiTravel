<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom seat_layout (JSON) di tabel kendaraan.
 * Kolom ini menyimpan konfigurasi baris kursi per kendaraan
 * karena setiap kendaraan memiliki jumlah dan tata letak kursi
 * yang berbeda-beda.
 *
 * Format JSON: array of integers, setiap elemen = jumlah kursi per baris.
 * Contoh: [1, 3, 2] artinya baris 1 = 1 kursi, baris 2 = 3 kursi, baris 3 = 2 kursi.
 * Baris pertama biasanya kursi di samping sopir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->json('seat_layout')->nullable()->after('kapasitas_kursi');
        });
    }

    public function down(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->dropColumn('seat_layout');
        });
    }
};
