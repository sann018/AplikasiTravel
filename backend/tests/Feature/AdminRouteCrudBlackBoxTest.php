<?php

namespace Tests\Feature;

use App\Models\Rute;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRouteCrudBlackBoxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_read_update_and_delete_route(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.rute.store'), [
                'kota_asal' => 'Kota Uji A',
                'kota_tujuan' => 'Kota Uji B',
                'deskripsi_jalur' => 'Jalur khusus pengujian CRUD rute.',
                'jarak_km' => 125,
                'estimasi_durasi_menit' => 180,
                'harga_tiket' => 175000,
                'status' => 'aktif',
            ])
            ->assertRedirect(route('admin.rute.index'))
            ->assertSessionHasNoErrors();

        $route = Rute::query()
            ->where('kota_asal', 'Kota Uji A')
            ->where('kota_tujuan', 'Kota Uji B')
            ->firstOrFail();

        $this
            ->actingAs($admin)
            ->get(route('admin.rute.index', ['search' => 'Kota Uji A']))
            ->assertOk()
            ->assertSee('Kota Uji A')
            ->assertSee('Kota Uji B');

        $this
            ->actingAs($admin)
            ->put(route('admin.rute.update', $route), [
                'kota_asal' => 'Kota Uji A',
                'kota_tujuan' => 'Kota Uji C',
                'deskripsi_jalur' => 'Jalur pengujian yang sudah diperbarui.',
                'jarak_km' => 130,
                'estimasi_durasi_menit' => 200,
                'harga_tiket' => 185000,
                'status' => 'nonaktif',
            ])
            ->assertRedirect(route('admin.rute.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rute', [
            'id' => $route->id,
            'kota_tujuan' => 'Kota Uji C',
            'estimasi_durasi_menit' => 200,
            'harga_tiket' => 185000,
            'status_aktif' => false,
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.rute.destroy', $route))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('rute', ['id' => $route->id]);
    }

    public function test_route_validation_rejects_incomplete_or_invalid_payload(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.rute.store'), [
                'kota_asal' => '',
                'kota_tujuan' => '',
                'estimasi_durasi_menit' => 0,
                'harga_tiket' => -1,
                'status' => 'tidak-valid',
            ])
            ->assertSessionHasErrors([
                'kota_asal',
                'kota_tujuan',
                'estimasi_durasi_menit',
                'harga_tiket',
                'status',
            ]);
    }
}
