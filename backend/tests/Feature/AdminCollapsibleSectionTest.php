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

    public function test_active_admin_menu_uses_the_main_navy_color(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.travs-nav-link\.is-active\s*\{[^}]*background:\s*var\(--travs-primary\);/s',
            $css,
        );
    }

    public function test_targeted_admin_pages_render_local_collapsible_sections(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $routes = [
            route('admin.dashboard'),
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
            ->assertSee('dashboard-summary-body', false)
            ->assertSee('dashboard-revenue-body', false)
            ->assertSee('dashboard-schedule-body', false);

        $this->actingAs($admin)
            ->get(route('admin.biaya.index'))
            ->assertOk()
            ->assertSee('data-static-cost-summary', false)
            ->assertSee('cost-list-body', false);

        $this->actingAs($admin)
            ->get(route('admin.biaya.index'))
            ->assertOk()
            ->assertSee('cost-recap-route-body', false)
            ->assertSee('cost-list-body', false);

        $this->actingAs($admin)
            ->get(route('admin.notifikasi.index'))
            ->assertOk()
            ->assertSee('notification-summary-body', false)
            ->assertSee('notification-filter-body', false)
            ->assertSee('notification-cleanup-body', false)
            ->assertSee('notification-list-body', false);
    }

    public function test_operational_cost_uses_separate_form_and_integrated_filter_layout(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.biaya.index'))
            ->assertOk()
            ->assertSee('Tambah Biaya')
            ->assertSee('name="rute_id"', false)
            ->assertSee('Tampilkan Semua')
            ->assertSee('travs-cost-table-section', false)
            ->assertDontSee('name="jenis_biaya"', false);

        $this->actingAs($admin)
            ->get(route('admin.biaya.create'))
            ->assertOk()
            ->assertSee('Tambah Biaya Operasional')
            ->assertSee('name="jenis_biaya"', false)
            ->assertSee('name="bukti_nota"', false);
    }

    public function test_old_recap_page_redirects_to_operational_cost_page(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.biaya.rekap'))
            ->assertRedirect(route('admin.biaya.index'));
    }

    public function test_operational_cost_uses_static_summary_and_collapsible_vertical_sections(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.biaya.index'))
            ->assertOk()
            ->assertSee('data-static-cost-summary', false)
            ->assertSee('aria-controls="cost-list-body"', false)
            ->assertSee('aria-controls="cost-recap-route-body"', false)
            ->assertDontSee('data-cost-summary-toggle', false)
            ->assertDontSee('data-cost-filter-toggle', false)
            ->assertSeeInOrder([
                'Ringkasan Biaya',
                'Daftar Biaya',
                'Akumulasi per Rute',
            ]);
    }

    public function test_sidebar_no_longer_shows_separate_recap_menu(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.biaya.index'))
            ->assertOk()
            ->assertDontSee('>Rekap Biaya<', false)
            ->assertDontSee('Lihat Rekap');
    }
}
