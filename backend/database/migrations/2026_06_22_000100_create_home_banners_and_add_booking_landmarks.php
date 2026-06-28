<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_banners', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 120);
            $table->string('subjudul', 255)->nullable();
            $table->string('gambar_path');
            $table->boolean('aktif')->default(true)->index();
            $table->unsignedInteger('urutan')->default(0);
            $table->foreignId('dibuat_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('pemesanan', function (Blueprint $table) {
            $table->string('catatan_jemput', 500)->nullable()->after('alamat_jemput');
            $table->string('catatan_turun', 500)->nullable()->after('alamat_turun');
        });
    }

    public function down(): void
    {
        Schema::table('pemesanan', function (Blueprint $table) {
            $table->dropColumn(['catatan_jemput', 'catatan_turun']);
        });

        Schema::dropIfExists('home_banners');
    }
};
