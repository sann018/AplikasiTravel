<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Pemesanan;
use App\Models\User;
use App\Notifications\EmployeeAccountActivation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StafPelayananManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_staf_pelayanan_and_send_activation_link(): void
    {
        Notification::fake();
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.staf-pelayanan.store'), [
                'nama' => 'Staf Pelayanan Satu',
                'email' => 'staf.pelayanan.satu@ruslytravel.com',
                'no_hp' => '081234567899',
                'status' => 'aktif',
            ])
            ->assertRedirect(route('admin.staf-pelayanan.index'));

        $stafPelayanan = User::query()
            ->where('email', 'staf.pelayanan.satu@ruslytravel.com')
            ->firstOrFail();

        $this->assertSame('staf_pelayanan', $stafPelayanan->peran);
        $this->assertNull($stafPelayanan->email_verified_at);
        Notification::assertSentTo($stafPelayanan, EmployeeAccountActivation::class);
    }

    public function test_staf_pelayanan_cannot_open_web_management_page(): void
    {
        $stafPelayanan = User::factory()->create(['peran' => 'staf_pelayanan']);

        $this->actingAs($stafPelayanan)
            ->get(route('admin.staf-pelayanan.index'))
            ->assertForbidden();
    }

    public function test_staf_pelayanan_cannot_login_before_finishing_email_activation(): void
    {
        $stafPelayanan = User::factory()->create([
            'email' => 'belum.aktif@ruslytravel.com',
            'peran' => 'staf_pelayanan',
            'email_verified_at' => null,
            'password' => 'Password123',
        ]);

        $this->postJson('/api/auth/login', [
            'login' => $stafPelayanan->email,
            'password' => 'Password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.login.0', 'Akun Staf Pelayanan belum diaktifkan. Buka tautan aktivasi yang dikirim ke email akun.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $stafPelayanan->id,
        ]);
    }

    public function test_staf_pelayanan_cannot_change_managed_email_from_mobile_profile(): void
    {
        $stafPelayanan = User::factory()->create([
            'email' => 'staf.aktif@ruslytravel.com',
            'peran' => 'staf_pelayanan',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($stafPelayanan, ['mobile-staf-pelayanan']);

        $this->putJson('/api/auth/profile', [
            'nama' => $stafPelayanan->nama,
            'email' => 'email.baru@ruslytravel.com',
            'no_hp' => $stafPelayanan->no_hp,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email Staf Pelayanan hanya dapat diubah oleh Admin melalui web agar aktivasi akun tetap terkontrol.');

        $this->assertDatabaseHas('pengguna', [
            'id' => $stafPelayanan->id,
            'email' => 'staf.aktif@ruslytravel.com',
        ]);
    }

    public function test_admin_can_open_staf_pelayanan_detail_with_booking_totals(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $stafPelayanan = User::factory()->create(['peran' => 'staf_pelayanan']);
        $schedule = Jadwal::query()->firstOrFail();

        Pemesanan::query()->create([
            'pengguna_id' => null,
            'dibuat_oleh' => $stafPelayanan->id,
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
            ->get(route('admin.staf-pelayanan.show', $stafPelayanan))
            ->assertOk()
            ->assertSee('Detail Staf Pelayanan')
            ->assertSee('Kursi Dipesankan')
            ->assertSee('TRAVS-DETAIL-LOKET');
    }

    public function test_disabling_staf_pelayanan_revokes_all_mobile_tokens(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $stafPelayanan = User::factory()->create([
            'peran' => 'staf_pelayanan',
            'status_aktif' => true,
        ]);
        $stafPelayanan->createToken('staf-pelayanan-test', ['mobile-staf-pelayanan']);

        $this->actingAs($admin)
            ->patch(route('admin.staf-pelayanan.status', $stafPelayanan))
            ->assertRedirect();

        $this->assertFalse($stafPelayanan->fresh()->status_aktif);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $stafPelayanan->id,
        ]);
    }

    public function test_changing_staf_pelayanan_email_revokes_sessions_and_sends_new_activation_link(): void
    {
        Notification::fake();
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $stafPelayanan = User::factory()->create([
            'nama' => 'Staf Pelayanan Lama',
            'peran' => 'staf_pelayanan',
            'email_verified_at' => now(),
        ]);
        $stafPelayanan->createToken('staf-pelayanan-test', ['mobile-staf-pelayanan']);

        $this->actingAs($admin)
            ->put(route('admin.staf-pelayanan.update', $stafPelayanan), [
                'nama' => $stafPelayanan->nama,
                'email' => 'email.baru.staf@ruslytravel.com',
                'no_hp' => $stafPelayanan->no_hp,
                'status' => 'aktif',
            ])
            ->assertRedirect(route('admin.staf-pelayanan.index'));

        $updated = $stafPelayanan->fresh();
        $this->assertSame('email.baru.staf@ruslytravel.com', $updated->email);
        $this->assertNull($updated->email_verified_at);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $stafPelayanan->id,
        ]);
        Notification::assertSentTo($updated, EmployeeAccountActivation::class);
    }
}
