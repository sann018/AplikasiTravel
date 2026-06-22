<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCollapsibleSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_targeted_admin_pages_render_local_collapsible_sections(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $routes = [
            route('admin.dashboard'),
            route('admin.biaya.index'),
            route('admin.biaya.rekap'),
            route('admin.notifikasi.index'),
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)
                ->get($route)
                ->assertOk()
                ->assertSee('data-collapsible-section', false)
                ->assertSee('data-collapse-toggle', false)
                ->assertSee('data-collapse-body', false);
        }
    }

    public function test_filter_pages_keep_explicit_apply_filter_button(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $routes = [
            route('admin.biaya.index'),
            route('admin.biaya.rekap'),
            route('admin.notifikasi.index'),
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)
                ->get($route)
                ->assertOk()
                ->assertSee('Terapkan Filter');
        }
    }

    public function test_each_page_renders_the_requested_main_section_bodies(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('dashboard-tabs-body', false)
            ->assertSee('dashboard-revenue-body', false)
            ->assertSee('dashboard-latest-cost-body', false)
            ->assertSee('dashboard-latest-booking-body', false)
            ->assertSee('dashboard-schedule-body', false);

        $this->actingAs($admin)
            ->get(route('admin.biaya.index'))
            ->assertOk()
            ->assertSee('cost-summary-body', false)
            ->assertSee('cost-form-body', false)
            ->assertSee('cost-filter-body', false)
            ->assertSee('cost-list-body', false);

        $this->actingAs($admin)
            ->get(route('admin.biaya.rekap'))
            ->assertOk()
            ->assertSee('cost-recap-filter-body', false)
            ->assertSee('cost-recap-route-body', false)
            ->assertSee('cost-recap-detail-body', false);

        $this->actingAs($admin)
            ->get(route('admin.notifikasi.index'))
            ->assertOk()
            ->assertSee('notification-summary-body', false)
            ->assertSee('notification-filter-body', false)
            ->assertSee('notification-cleanup-body', false)
            ->assertSee('notification-list-body', false);
    }
}
