<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Pemesanan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PetugasLoketManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_counter_staff_and_send_activation_link(): void
    {
        Notification::fake();
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.petugas-loket.store'), [
                'nama' => 'Petugas Satu',
                'email' => 'petugas.satu@ruslytravel.com',
                'no_hp' => '081234567899',
                'status' => 'aktif',
            ])
            ->assertRedirect(route('admin.petugas-loket.index'));

        $petugas = User::query()
            ->where('email', 'petugas.satu@ruslytravel.com')
            ->firstOrFail();

        $this->assertSame('petugas_loket', $petugas->peran);
        $this->assertNull($petugas->email_verified_at);
        Notification::assertSentTo($petugas, ResetPassword::class);
    }

    public function test_counter_staff_cannot_open_web_management_page(): void
    {
        $petugas = User::factory()->create(['peran' => 'petugas_loket']);

        $this->actingAs($petugas)
            ->get(route('admin.petugas-loket.index'))
            ->assertForbidden();
    }

    public function test_admin_can_open_counter_staff_detail_with_booking_totals(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $petugas = User::factory()->create(['peran' => 'petugas_loket']);
        $schedule = Jadwal::query()->firstOrFail();

        Pemesanan::query()->create([
            'pengguna_id' => null,
            'dibuat_oleh' => $petugas->id,
            'sumber_pemesanan' => 'loket',
            'nama_pemesan' => 'Pemesan Loket',
            'no_hp_pemesan' => '081234567890',
            'jadwal_id' => $schedule->id,
            'kode_booking' => 'TRAVS-DETAIL-LOKET',
            'jumlah_kursi' => 2,
            'subtotal_tiket' => 500000,
            'total_harga' => 500000,
            'status_pemesanan' => 'terbayar',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.petugas-loket.show', $petugas))
            ->assertOk()
            ->assertSee('Detail Petugas Loket')
            ->assertSee('Kursi Dipesankan')
            ->assertSee('TRAVS-DETAIL-LOKET');
    }

    public function test_disabling_counter_staff_revokes_all_mobile_tokens(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $petugas = User::factory()->create([
            'peran' => 'petugas_loket',
            'status_aktif' => true,
        ]);
        $petugas->createToken('loket-test', ['mobile-counter']);

        $this->actingAs($admin)
            ->patch(route('admin.petugas-loket.status', $petugas))
            ->assertRedirect();

        $this->assertFalse($petugas->fresh()->status_aktif);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $petugas->id,
        ]);
    }
}
