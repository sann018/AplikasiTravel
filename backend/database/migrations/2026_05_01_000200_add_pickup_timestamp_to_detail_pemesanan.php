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
        Schema::table('detail_pemesanan', function (Blueprint $table) {
            $table->dateTime('dijemput_pada')->nullable()->after('status_kehadiran');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_pemesanan', function (Blueprint $table) {
            $table->dropColumn('dijemput_pada');
        });
    }
};
