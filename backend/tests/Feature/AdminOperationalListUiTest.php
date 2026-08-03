<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminOperationalListUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_schedule_list_separates_status_seats_and_booking_columns(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.jadwal.index'));

        $response->assertOk()
            ->assertSee('Status Jadwal')
            ->assertSee('Kursi')
            ->assertSee('Booking')
            ->assertSee('Terisi')
            ->assertDontSee('tersedia /');
    }

    public function test_schedule_list_orders_latest_departure_first(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $schedules = Jadwal::query()->orderBy('id')->limit(2)->get();

        $this->assertCount(2, $schedules);

        $schedules[0]->update([
            'waktu_berangkat' => Carbon::parse('2099-01-10 08:00:00'),
            'estimasi_tiba' => Carbon::parse('2099-01-10 12:00:00'),
        ]);
        $schedules[1]->update([
            'waktu_berangkat' => Carbon::parse('2099-02-20 08:00:00'),
            'estimasi_tiba' => Carbon::parse('2099-02-20 12:00:00'),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.jadwal.index'));

        $response->assertOk()
            ->assertSeeInOrder(['20 Feb 2099', '10 Jan 2099']);
    }

    public function test_booking_monitoring_only_shows_main_statuses_in_the_list(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.pemesanan.index'));

        $response->assertOk()
            ->assertSee('Status Pemesanan')
            ->assertSee('Status Pembayaran')
            ->assertSee('Kursi')
            ->assertDontSee('Hadir 0/')
            ->assertDontSee('Turun 0/')
            ->assertDontSee('Barang 0 catatan');
    }

    public function test_resource_lists_use_consistent_filters_and_filtered_empty_states(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $pages = [
            ['route' => 'admin.kendaraan.index', 'empty' => 'Tidak ada armada yang sesuai dengan filter yang dipilih.'],
            ['route' => 'admin.pengemudi.index', 'empty' => 'Tidak ada pengemudi yang sesuai dengan filter yang dipilih.'],
            ['route' => 'admin.rute.index', 'empty' => 'Tidak ada rute yang sesuai dengan filter yang dipilih.'],
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($admin)->get(route($page['route'], [
                'search' => 'data-yang-tidak-mungkin-ditemukan',
            ]));

            $response->assertOk()
                ->assertSee('travs-filter-grid', false)
                ->assertSee('Terapkan Filter')
                ->assertSee('Reset')
                ->assertSee($page['empty']);
        }
    }
}
