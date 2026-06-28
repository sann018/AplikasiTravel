<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RuslyTravel tidak menambah biaya admin karena biaya kanal ditentukan Pakasir.
        DB::table('system_settings')->where('key', 'admin_fee')->delete();

        // Database lama mungkin masih memiliki kolom ini, jadi migration membersihkannya dengan aman.
        if (Schema::hasColumn('pemesanan', 'biaya_admin')) {
            Schema::table('pemesanan', function (Blueprint $table) {
                $table->dropColumn('biaya_admin');
            });
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'admin_fee'],
            [
                'group' => 'payment',
                'value' => '0',
                'type' => 'integer',
                'description' => 'Biaya admin internal RuslyTravel.',
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
