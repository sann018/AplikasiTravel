<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pengguna', 'peran')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            // Menambah peran petugas tanpa mengubah nilai akun lama.
            DB::statement(
                "ALTER TABLE pengguna MODIFY peran ENUM('penumpang','pengemudi','petugas_loket','admin') NOT NULL DEFAULT 'penumpang'"
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pengguna', 'peran') || DB::getDriverName() !== 'mysql') {
            return;
        }

        if (DB::table('pengguna')->where('peran', 'petugas_loket')->exists()) {
            throw new RuntimeException(
                'Rollback dibatalkan karena masih ada akun Petugas Loket. Nonaktifkan dan pindahkan perannya secara sadar terlebih dahulu.'
            );
        }

        DB::statement(
            "ALTER TABLE pengguna MODIFY peran ENUM('penumpang','pengemudi','admin') NOT NULL DEFAULT 'penumpang'"
        );
    }
};
