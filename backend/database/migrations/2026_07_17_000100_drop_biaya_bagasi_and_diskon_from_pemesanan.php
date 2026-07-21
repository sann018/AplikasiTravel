<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus kolom biaya_bagasi dan diskon dari tabel pemesanan.
 *
 * Kedua kolom sudah tidak dipakai lagi: barang bawaan dicatat untuk kebutuhan
 * manifest tanpa memengaruhi tarif, dan fitur diskon tidak lagi digunakan pada
 * sistem versi ini. Total pemesanan kini sama dengan subtotal harga tiket.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pemesanan', function (Blueprint $table) {
            if (Schema::hasColumn('pemesanan', 'biaya_bagasi')) {
                $table->dropColumn('biaya_bagasi');
            }

            if (Schema::hasColumn('pemesanan', 'diskon')) {
                $table->dropColumn('diskon');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pemesanan', function (Blueprint $table) {
            if (! Schema::hasColumn('pemesanan', 'biaya_bagasi')) {
                $table->decimal('biaya_bagasi', 12, 2)->default(0)->after('subtotal_tiket');
            }

            if (! Schema::hasColumn('pemesanan', 'diskon')) {
                $table->decimal('diskon', 12, 2)->default(0)->after('biaya_bagasi');
            }
        });
    }
};
