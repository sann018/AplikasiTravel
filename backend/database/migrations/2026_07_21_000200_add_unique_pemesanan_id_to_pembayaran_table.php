<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Menegakkan satu pemesanan maksimal mempunyai satu record pembayaran. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->unique('pemesanan_id', 'pembayaran_pemesanan_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropUnique('pembayaran_pemesanan_id_unique');
        });
    }
};
