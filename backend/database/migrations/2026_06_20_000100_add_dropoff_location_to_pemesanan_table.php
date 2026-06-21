<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemesanan', function (Blueprint $table) {
            $table->decimal('lintang_turun', 10, 7)->nullable()->after('alamat_jemput');
            $table->decimal('bujur_turun', 10, 7)->nullable()->after('lintang_turun');
            $table->text('alamat_turun')->nullable()->after('bujur_turun');
        });
    }

    public function down(): void
    {
        Schema::table('pemesanan', function (Blueprint $table) {
            $table->dropColumn(['lintang_turun', 'bujur_turun', 'alamat_turun']);
        });
    }
};
