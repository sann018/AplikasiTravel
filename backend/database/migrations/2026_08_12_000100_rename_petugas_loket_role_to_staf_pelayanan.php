<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            DB::statement(
                "ALTER TABLE pengguna MODIFY peran ENUM('penumpang','pengemudi','petugas_loket','staf_pelayanan','admin') NOT NULL DEFAULT 'penumpang'"
            );
        } else {
            $this->changeRoleValues([
                'penumpang',
                'pengemudi',
                'petugas_loket',
                'staf_pelayanan',
                'admin',
            ]);
        }

        DB::table('pengguna')
            ->where('peran', 'petugas_loket')
            ->update(['peran' => 'staf_pelayanan']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE pengguna MODIFY peran ENUM('penumpang','pengemudi','staf_pelayanan','admin') NOT NULL DEFAULT 'penumpang'"
            );
        } else {
            $this->changeRoleValues(['penumpang', 'pengemudi', 'staf_pelayanan', 'admin']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pengguna', 'peran')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE pengguna MODIFY peran ENUM('penumpang','pengemudi','petugas_loket','staf_pelayanan','admin') NOT NULL DEFAULT 'penumpang'"
            );
        } else {
            $this->changeRoleValues([
                'penumpang',
                'pengemudi',
                'petugas_loket',
                'staf_pelayanan',
                'admin',
            ]);
        }

        DB::table('pengguna')
            ->where('peran', 'staf_pelayanan')
            ->update(['peran' => 'petugas_loket']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE pengguna MODIFY peran ENUM('penumpang','pengemudi','petugas_loket','admin') NOT NULL DEFAULT 'penumpang'"
            );
        } else {
            $this->changeRoleValues(['penumpang', 'pengemudi', 'petugas_loket', 'admin']);
        }
    }

    /** @param array<int, string> $roles */
    private function changeRoleValues(array $roles): void
    {
        Schema::table('pengguna', function (Blueprint $table) use ($roles) {
            $table->enum('peran', $roles)->default('penumpang')->change();
        });
    }
};
