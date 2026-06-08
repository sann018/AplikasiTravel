<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pengaturan_sistem')
            ->where('kunci', 'baggage_max_size_per_person_cm')
            ->where('nilai', '160')
            ->update([
                'nilai' => '300',
                'deskripsi' => 'Batas maksimal total panjang dan lebar per penumpang.',
                'updated_at' => now(),
            ]);

        DB::table('pengaturan_sistem')
            ->where('kunci', 'baggage_extra_fee_per_item')
            ->where('nilai', '2500')
            ->update([
                'nilai' => '3000',
                'deskripsi' => 'Tarif tambahan per item bagasi (default Rp 3.000).',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('pengaturan_sistem')
            ->where('kunci', 'baggage_max_size_per_person_cm')
            ->where('nilai', '300')
            ->update([
                'nilai' => '160',
                'deskripsi' => 'Batas maksimal total panjang dan lebar per penumpang.',
                'updated_at' => now(),
            ]);

        DB::table('pengaturan_sistem')
            ->where('kunci', 'baggage_extra_fee_per_item')
            ->where('nilai', '3000')
            ->update([
                'nilai' => '2500',
                'deskripsi' => 'Tarif tambahan per item bagasi (default Rp 2.500).',
                'updated_at' => now(),
            ]);
    }
};
