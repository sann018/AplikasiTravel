<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemesanan', function (Blueprint $table) {
            // Rentang tanggal dipakai oleh monitoring, laporan, dan pembersihan
            // riwayat sehingga indeks ini menjaga query tetap ringan saat data besar.
            $table->index(
                ['created_at', 'status_pemesanan'],
                'pemesanan_created_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('pemesanan', function (Blueprint $table) {
            $table->dropIndex('pemesanan_created_status_index');
        });
    }
};
