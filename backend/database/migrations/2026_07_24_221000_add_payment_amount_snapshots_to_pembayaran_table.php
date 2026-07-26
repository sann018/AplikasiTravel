<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->unsignedBigInteger('nominal_transaksi')->nullable()->after('paid_channel');
            $table->unsignedBigInteger('biaya_admin_provider')->nullable()->after('nominal_transaksi');
            $table->unsignedBigInteger('total_dibayar_penumpang')->nullable()->after('biaya_admin_provider');
            $table->text('payment_number')->nullable()->after('total_dibayar_penumpang');
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropColumn([
                'nominal_transaksi',
                'biaya_admin_provider',
                'total_dibayar_penumpang',
                'payment_number',
            ]);
        });
    }
};
