<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScheduleVehicleFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_forms_offer_departed_status_without_cancelled_status(): void
    {
        $admin = User::factory()->create(['peran' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.jadwal.create'));

        $response->assertOk();
        $response->assertSee('<option value="berangkat"', false);
        $response->assertDontSee('<option value="dibatalkan"', false);

        $this
            ->actingAs($admin)
            ->get(route('admin.jadwal.index'))
            ->assertOk()
            ->assertSee('<option value="berangkat"', false)
            ->assertDontSee('<option value="dibatalkan"', false);
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

    public function test_completed_schedule_status_is_locked_in_form_and_backend(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'selesai')->firstOrFail();

        $this
            ->actingAs($admin)
            ->get(route('admin.jadwal.edit', $jadwal))
            ->assertOk()
            ->assertSee('Jadwal telah selesai sehingga statusnya tidak dapat diubah kembali.')
            ->assertSee('disabled', false)
            ->assertDontSee('<option value="dibatalkan"', false);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.jadwal.edit', $jadwal))
            ->put(route('admin.jadwal.update', $jadwal), $this->schedulePayload($jadwal, 'dibuka'));

        $response->assertRedirect(route('admin.jadwal.edit', $jadwal));
        $response->assertSessionHasErrors([
            'status' => 'Jadwal yang sudah selesai tidak dapat diubah ke status lain.',
        ]);
        $this->assertDatabaseHas('jadwal', [
            'id' => $jadwal->id,
            'status' => 'selesai',
        ]);
    }

    public function test_draft_schedule_is_hidden_until_admin_opens_it(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $jadwal = Jadwal::query()
            ->where('status', 'dibuka')
            ->where('waktu_berangkat', '>', now()->addDay())
            ->orderByDesc('waktu_berangkat')
            ->firstOrFail();

        $this
            ->actingAs($admin)
            ->put(route('admin.jadwal.update', $jadwal), $this->schedulePayload($jadwal, 'draft'))
            ->assertRedirect(route('admin.jadwal.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('jadwal', [
            'id' => $jadwal->id,
            'status' => 'draft',
        ]);

        $draftResponse = $this->getJson('/api/guest/jadwal')->assertOk();
        $this->assertFalse(
            collect($draftResponse->json('data'))->contains(fn (array $item) => (int) $item['id'] === $jadwal->id),
            'Jadwal draft tidak boleh tampil pada daftar jadwal aplikasi mobile.',
        );

        $jadwal->refresh();
        $this
            ->actingAs($admin)
            ->put(route('admin.jadwal.update', $jadwal), $this->schedulePayload($jadwal, 'dibuka'))
            ->assertRedirect(route('admin.jadwal.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('jadwal', [
            'id' => $jadwal->id,
            'status' => 'dibuka',
        ]);

        $openedResponse = $this->getJson('/api/guest/jadwal')->assertOk();
        $this->assertTrue(
            collect($openedResponse->json('data'))->contains(fn (array $item) => (int) $item['id'] === $jadwal->id),
            'Jadwal dibuka harus tampil pada daftar jadwal aplikasi mobile.',
        );
    }

    private function schedulePayload(Jadwal $jadwal, string $status): array
    {
        return [
            'rute_id' => $jadwal->rute_id,
            'kendaraan_id' => $jadwal->kendaraan_id,
            'pengemudi_id' => $jadwal->pengemudi_id,
            'waktu_berangkat' => $jadwal->waktu_berangkat?->format('Y-m-d H:i'),
            'status' => $status,
            'kursi_ditutup' => $jadwal->kursi()
                ->where('status_kursi', 'nonaktif')
                ->pluck('nomor_kursi')
                ->all(),
        ];
    }
}
