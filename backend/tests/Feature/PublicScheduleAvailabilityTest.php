<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicScheduleAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_full_schedule_is_not_sent_to_passenger_or_guest_home(): void
    {
        $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();

        // Semua kursi dibuat terisi untuk memastikan jadwal penuh tidak ditawarkan.
        $schedule->kursi()->update(['status_kursi' => 'dipesan']);

        $guestResponse = $this->getJson('/api/guest/jadwal')->assertOk();
        $bootstrapResponse = $this->getJson('/api/mobile/bootstrap')->assertOk();

        $this->assertFalse(
            collect($guestResponse->json('data'))->contains(
                fn (array $item) => (int) $item['id'] === $schedule->id,
            ),
        );
        $this->assertFalse(
            collect($bootstrapResponse->json('data.featured_schedules'))->contains(
                fn (array $item) => (int) $item['id'] === $schedule->id,
            ),
        );
    }
}
