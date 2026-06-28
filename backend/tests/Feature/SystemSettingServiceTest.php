<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_reads_settings_using_their_declared_types(): void
    {
        $this->seed(SystemSettingSeeder::class);

        $settings = app(SettingService::class);

        $this->assertSame(4, $settings->get('max_seat_per_booking'));
        $this->assertTrue($settings->get('auto_cancel_booking'));
        $this->assertSame(3000.0, $settings->get('baggage_extra_fee_per_kg'));
        $this->assertSame('nilai-cadangan', $settings->get('tidak_ada', 'nilai-cadangan'));
        $this->assertArrayHasKey('app_name', $settings->group('general'));
        $this->assertArrayNotHasKey('app_name', $settings->group('booking'));
    }

    public function test_update_many_refreshes_cache_and_records_last_admin(): void
    {
        $this->seed(SystemSettingSeeder::class);
        $admin = User::factory()->create([
            'peran' => 'admin',
            'status_aktif' => true,
        ]);
        $settings = app(SettingService::class);

        $this->assertSame(4, $settings->get('max_seat_per_booking'));

        $settings->updateMany([
            'max_seat_per_booking' => 3,
            'auto_cancel_booking' => false,
        ], $admin->id);

        $this->assertSame(3, $settings->get('max_seat_per_booking'));
        $this->assertFalse($settings->get('auto_cancel_booking'));
        $this->assertDatabaseHas('system_settings', [
            'key' => 'max_seat_per_booking',
            'value' => '3',
            'updated_by' => $admin->id,
        ]);
    }
}
