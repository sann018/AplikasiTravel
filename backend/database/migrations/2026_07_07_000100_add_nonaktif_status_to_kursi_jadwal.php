<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE kursi_jadwal MODIFY status_kursi ENUM('tersedia', 'terkunci', 'dipesan', 'nonaktif') DEFAULT 'tersedia'");
        }
    }

    public function down(): void
    {
        DB::table('kursi_jadwal')
            ->where('status_kursi', 'nonaktif')
            ->update(['status_kursi' => 'tersedia']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE kursi_jadwal MODIFY status_kursi ENUM('tersedia', 'terkunci', 'dipesan') DEFAULT 'tersedia'");
        }
    }
};
