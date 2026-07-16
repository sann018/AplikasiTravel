<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScheduleVehicleFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_create_form_offers_departed_status(): void
    {
        $admin = User::factory()->create(['peran' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.jadwal.create'));

        $response->assertOk();
        $response->assertSee('<option value="berangkat"', false);
        $response->assertSee('<option value="dibatalkan"', false);

        $this
            ->actingAs($admin)
            ->get(route('admin.jadwal.index'))
            ->assertOk()
            ->assertSee('<option value="berangkat"', false);
    }

    public function test_departed_status_is_accepted_when_creating_schedule(): void
    {
        $admin = User::factory()->create(['peran' => 'admin']);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.jadwal.create'))
            ->post(route('admin.jadwal.store'), ['status' => 'berangkat']);

        $response->assertRedirect(route('admin.jadwal.create'));
        $response->assertSessionDoesntHaveErrors('status');
    }

    public function test_admin_can_store_a_free_text_vehicle_type(): void
    {
        $admin = User::factory()->create(['peran' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.kendaraan.store'), [
            'nomor_plat' => 'KB 1234 TA',
            'merk_kendaraan' => 'Toyota Hiace',
            'warna' => 'Putih',
            'tahun_kendaraan' => 2025,
            'tipe_kendaraan' => 'Microbus Pariwisata',
            'kapasitas_kursi' => 6,
            'seat_layout' => '1,3,2',
            'total_kilometer' => 0,
            'status_kendaraan' => 'aktif',
        ]);

        $response->assertRedirect(route('admin.kendaraan.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('kendaraan', [
            'nomor_plat' => 'KB 1234 TA',
            'tipe_kendaraan' => 'Microbus Pariwisata',
        ]);
    }
}
