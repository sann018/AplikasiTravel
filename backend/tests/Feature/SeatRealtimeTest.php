<?php

namespace Tests\Feature;

use App\Events\SeatAvailabilityChanged;
use App\Services\SeatAvailabilityRealtimeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SeatRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_mobile_receives_safe_reverb_configuration_without_secret(): void
    {
        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb.key', 'public-key-uji');
        config()->set('broadcasting.connections.reverb.secret', 'secret-jangan-dikirim');
        config()->set('realtime.seat_updates_enabled', true);
        config()->set('realtime.public_host', '10.0.2.2');
        config()->set('realtime.public_port', 8080);
        config()->set('realtime.public_scheme', 'ws');

        $response = $this->getJson('/api/settings/mobile');

        $response
            ->assertOk()
            ->assertJsonPath('data.realtime.enabled', true)
            ->assertJsonPath('data.realtime.app_key', 'public-key-uji')
            ->assertJsonPath('data.realtime.host', '10.0.2.2')
            ->assertJsonPath('data.realtime.port', 8080)
            ->assertJsonPath('data.realtime.scheme', 'ws')
            ->assertJsonMissingPath('data.realtime.secret');
    }

    public function test_realtime_service_dispatches_schedule_event_when_enabled(): void
    {
        Event::fake([SeatAvailabilityChanged::class]);
        config()->set('realtime.seat_updates_enabled', true);

        app(SeatAvailabilityRealtimeService::class)->publish(17);

        Event::assertDispatched(
            SeatAvailabilityChanged::class,
            fn (SeatAvailabilityChanged $event) => $event->scheduleId === 17,
        );
    }

    public function test_seat_event_uses_public_schedule_channel_without_passenger_data(): void
    {
        $event = new SeatAvailabilityChanged(9, '2026-07-27T10:00:00+07:00');

        $this->assertSame('jadwal.9', $event->broadcastOn()->name);
        $this->assertSame('kursi.diperbarui', $event->broadcastAs());
        $this->assertSame([
            'jadwal_id' => 9,
            'diperbarui_pada' => '2026-07-27T10:00:00+07:00',
        ], $event->broadcastWith());
    }
}
