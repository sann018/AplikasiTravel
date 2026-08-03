<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminTicketMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ticket_monitor_route_is_disabled(): void
    {
        $admin = User::factory()->create(['peran' => 'admin']);

        $this->assertFalse(Route::has('admin.tiket-monitor.index'));

        $this->actingAs($admin)
            ->get('/admin/tiket-monitor')
            ->assertNotFound();
    }

    public function test_admin_sidebar_does_not_show_ticket_monitor_menu(): void
    {
        $admin = User::factory()->create(['peran' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Monitor Tiket');
    }
}
