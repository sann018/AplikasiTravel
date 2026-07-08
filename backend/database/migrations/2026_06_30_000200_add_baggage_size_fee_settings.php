<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            [
                'key' => 'baggage_small_item_fee',
                'value' => '5000',
                'description' => 'Biaya item kecil yang masuk bagasi setelah kuota gratis habis.',
            ],
            [
                'key' => 'baggage_large_item_fee',
                'value' => '10000',
                'description' => 'Biaya item besar yang masuk bagasi setelah kuota gratis habis.',
            ],
        ] as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'group' => 'baggage',
                    'value' => $setting['value'],
                    'type' => 'float',
                    'description' => $setting['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        Cache::forget('system_settings');
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('key', ['baggage_small_item_fee', 'baggage_large_item_fee'])
            ->delete();

        Cache::forget('system_settings');
    }
};
