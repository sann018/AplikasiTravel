<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('item_bagasi', function (Blueprint $table) {
            $table->string('metode_hitung', 20)->nullable()->after('deskripsi_item');
            $table->unsignedInteger('panjang_cm')->nullable()->after('ukuran_cm');
            $table->unsignedInteger('lebar_cm')->nullable()->after('panjang_cm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_bagasi', function (Blueprint $table) {
            $table->dropColumn(['metode_hitung', 'panjang_cm', 'lebar_cm']);
        });
    }
};
