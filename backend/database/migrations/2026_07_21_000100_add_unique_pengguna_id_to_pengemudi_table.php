<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Menegakkan satu akun pengguna maksimal mempunyai satu profil pengemudi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengemudi', function (Blueprint $table) {
            $table->unique('pengguna_id', 'pengemudi_pengguna_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pengemudi', function (Blueprint $table) {
            $table->dropUnique('pengemudi_pengguna_id_unique');
        });
    }
};
