<?php

namespace Tests\Feature;

use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\Pengemudi;
use App\Models\TiketElektronik;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\TravSDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login_when_opening_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_open_dashboard_page(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            TravSDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Ringkasan Operasional');
        $response->assertDontSee('Total Armada');
        $response->assertSee('Ringkasan Pendapatan Minggu Ini');
        $response->assertSee('Jadwal Keberangkatan Hari Ini');
        $response->assertDontSee('data-dashboard-tab=', false);
        $response->assertDontSee('Fokus Operasional');
        $response->assertDontSee('Biaya Operasional Terbaru');
        $response->assertDontSee('Pemesanan Terbaru');
    }

    public function test_admin_can_update_own_email_from_settings_page(): void
    {
        $admin = User::factory()->create([
            'email' => 'adminlama@travs.com',
            'peran' => 'admin',
            'status_aktif' => true,
            'password' => Hash::make('admin12345'),
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.settings.index', ['tab' => 'account']))
            ->put(route('admin.pengaturan.account.update'), [
                'email' => 'adminbaru@gmail.com',
                'current_password' => 'admin12345',
            ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'account']));
        $response->assertSessionHasNoErrors();

        $this->assertSame('adminbaru@gmail.com', $admin->fresh()->email);
    }

    public function test_admin_can_open_settings_page_with_account_form(): void
    {
        $this->seed(SystemSettingSeeder::class);

        $admin = User::factory()->create([
            'email' => 'adminpengaturan@travs.com',
            'peran' => 'admin',
            'status_aktif' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => 'account']));

        $response->assertOk();
        $response->assertSee('Akun Admin');
        $response->assertSee('Simpan Perubahan Akun');
        $response->assertSee('PUSAT PENGATURAN');
    }

    public function test_admin_account_update_requires_correct_current_password(): void
    {
        $admin = User::factory()->create([
            'email' => 'adminaman@travs.com',
            'peran' => 'admin',
            'status_aktif' => true,
            'password' => Hash::make('admin12345'),
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.settings.index', ['tab' => 'account']))
            ->put(route('admin.pengaturan.account.update'), [
                'email' => 'adminrusak@gmail.com',
                'current_password' => 'password-salah',
            ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'account']));
        $response->assertSessionHasErrors('current_password');

        $this->assertSame('adminaman@travs.com', $admin->fresh()->email);
    }

    public function test_admin_can_update_own_password_from_settings_page(): void
    {
        $admin = User::factory()->create([
            'email' => 'adminpassword@gmail.com',
            'peran' => 'admin',
            'status_aktif' => true,
            'password' => Hash::make('admin12345'),
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.settings.index', ['tab' => 'account']))
            ->put(route('admin.pengaturan.account.update'), [
                'email' => 'adminpassword@gmail.com',
                'current_password' => 'admin12345',
                'password' => 'PasswordAdminBaru12!',
                'password_confirmation' => 'PasswordAdminBaru12!',
            ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'account']));
        $response->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('PasswordAdminBaru12!', $admin->fresh()->password));
    }

    public function test_admin_can_export_booking_report_as_csv(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            TravSDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.laporan.pemesanan.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Kode Pemesanan', $content);
        $this->assertStringContainsString('TRAVS-DEMO-001', $content);
    }

    public function test_admin_can_export_operational_cost_in_supported_formats(): void
    {
        $admin = User::factory()->create([
            'peran' => 'admin',
            'status_aktif' => true,
        ]);

        foreach (['csv', 'xls'] as $format) {
            $response = $this
                ->actingAs($admin)
                ->get(route('admin.biaya.export', ['format' => $format]));

            $response->assertOk();
            $this->assertStringContainsString(
                'attachment',
                (string) $response->headers->get('content-disposition'),
            );
        }
    }

    public function test_admin_created_driver_is_ready_without_verification_step(): void
    {
        $admin = User::factory()->create([
            'peran' => 'admin',
            'status_aktif' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.pengemudi.store'), [
                'nama' => 'Driver Uji',
                'email' => 'driveruji@gmail.com',
                'no_hp' => '081111111111',
                'nomor_sim' => '6102233435410001',
                'status' => 'aktif',
                'password' => 'PasswordDriver12!',
            ]);

        $response->assertRedirect(route('admin.pengemudi.index'));

        $pengemudi = Pengemudi::query()
            ->with('pengguna')
            ->where('nomor_sim', '6102233435410001')
            ->firstOrFail();

        $this->assertSame('aktif', $pengemudi->status);
        $this->assertTrue($pengemudi->pengguna->status_aktif);
        foreach (['status_verifikasi', 'catatan_verifikasi', 'verified_at', 'verified_by'] as $column) {
            $this->assertFalse(Schema::hasColumn('pengemudi', $column));
        }
    }

    public function test_admin_can_upload_driver_profile_photo_from_driver_form(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'peran' => 'admin',
            'status_aktif' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.pengemudi.store'), [
                'nama' => 'Driver Foto',
                'email' => 'driverfoto@gmail.com',
                'no_hp' => '081111111112',
                'nomor_sim' => '6102233435420002',
                'status' => 'aktif',
                'password' => 'Drv34!',
                'foto_profil' => UploadedFile::fake()->image('driver.jpg', 640, 640),
                'foto_sim' => UploadedFile::fake()->image('sim.jpg', 900, 560),
            ]);

        $response->assertRedirect(route('admin.pengemudi.index'));

        $pengemudi = Pengemudi::query()
            ->with('pengguna')
            ->where('nomor_sim', '6102233435420002')
            ->firstOrFail();

        $this->assertNotNull($pengemudi->pengguna->foto_profil);
        $this->assertStringStartsWith('profiles/', $pengemudi->pengguna->foto_profil);
        Storage::disk('public')->assertExists($pengemudi->pengguna->foto_profil);
        $this->assertNotNull($pengemudi->foto_sim);
        $this->assertStringStartsWith('driver-documents/', $pengemudi->foto_sim);
        Storage::disk('public')->assertExists($pengemudi->foto_sim);
    }

    public function test_admin_driver_form_rejects_invalid_phone_password_sim_and_document_file(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'peran' => 'admin',
            'status_aktif' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.pengemudi.store'), [
                'nama' => 'Driver Validasi',
                'email' => 'drivervalidasi@gmail.com',
                'no_hp' => '12345',
                'nomor_sim' => 'SIM-SALAH',
                'status' => 'aktif',
                'password' => 'abcdef',
                'foto_sim' => UploadedFile::fake()->create('sim.pdf', 120, 'application/pdf'),
            ]);

        $response->assertSessionHasErrors(['no_hp', 'password', 'nomor_sim', 'foto_sim']);
    }

    public function test_admin_cannot_jump_cancelled_booking_back_to_paid(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            TravSDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()
            ->where('kode_booking', 'TRAVS-DEMO-002')
            ->firstOrFail();

        $booking->update(['status_pemesanan' => 'dibatalkan']);
        $booking->pembayaran()->update(['status_bayar' => 'kedaluwarsa']);

        $response = $this
            ->actingAs($admin)
            ->put(route('admin.pemesanan.update', $booking), [
                'status_pemesanan' => 'terbayar',
            ]);

        $response->assertSessionHasErrors('status_pemesanan');

        $this->assertSame('dibatalkan', $booking->fresh()->status_pemesanan);
        $this->assertSame('kedaluwarsa', $booking->pembayaran()->firstOrFail()->status_bayar);
    }

    public function test_admin_cannot_update_gateway_booking_status_manually(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            TravSDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()
            ->where('kode_booking', 'TRAVS-DEMO-001')
            ->firstOrFail();

        $booking->update(['status_pemesanan' => 'terbayar']);
        $booking->pembayaran()->update([
            'metode_bayar' => 'qris',
            'status_bayar' => 'berhasil',
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(route('admin.pemesanan.update', $booking), [
                'status_pemesanan' => 'selesai',
            ]);

        $response->assertSessionHasErrors('status_pemesanan');

        $this->assertSame('terbayar', $booking->fresh()->status_pemesanan);
        $this->assertSame('berhasil', $booking->pembayaran()->firstOrFail()->status_bayar);
    }

    public function test_admin_can_open_payment_dashboard_without_technical_status_filter(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            TravSDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()
            ->where('kode_booking', 'TRAVS-DEMO-002')
            ->firstOrFail();

        $booking->pembayaran()->update([
            'metode_bayar' => 'cash',
            'paid_channel' => 'Bayar Tunai',
            'status_bayar' => 'menunggu',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.pembayaran.index'));

        $response->assertOk();
        $response->assertSee('Pembayaran');
        $response->assertSee('TRAVS-DEMO-002');
        $response->assertSee('Verifikasi Cash');
        $response->assertDontSee('<label for="exception">Catatan Status</label>', false);
    }

    public function test_admin_can_open_read_only_payment_detail(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            TravSDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $payment = Pembayaran::query()
            ->where('metode_bayar', 'qris')
            ->with('pemesanan')
            ->firstOrFail();

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.pembayaran.show', $payment));

        $response->assertOk();
        $response->assertSee('Detail Pembayaran');
        $response->assertSee('Ringkasan Transaksi');
        $response->assertSee($payment->pemesanan->kode_booking);
        $response->assertDontSee('Simpan Status');
        $response->assertDontSee('checkout_url');
        $response->assertDontSee('create_payment');
    }

    public function test_admin_can_confirm_cash_payment_and_issue_ticket_notification(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            TravSDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()
            ->with('detailPemesanan.kursiJadwal')
            ->where('kode_booking', 'TRAVS-DEMO-002')
            ->firstOrFail();
        $detail = $booking->detailPemesanan->first();

        $booking->pembayaran()->update([
            'metode_bayar' => 'cash',
            'paid_channel' => 'Bayar Tunai',
            'status_bayar' => 'menunggu',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.pembayaran.confirm-cash', $booking));

        $response->assertRedirect(route('admin.pembayaran.index'));

        $booking->refresh();
        $payment = $booking->pembayaran()->firstOrFail();
        $gatewayResponse = json_decode((string) $payment->gateway_response, true);

        $this->assertSame('terbayar', $booking->status_pemesanan);
        $this->assertSame('cash', $payment->metode_bayar);
        $this->assertSame('berhasil', $payment->status_bayar);
        $this->assertSame((int) $booking->total_harga, $payment->nominal_transaksi);
        $this->assertSame(0, $payment->biaya_admin_provider);
        $this->assertSame((int) $booking->total_harga, $payment->total_dibayar_penumpang);
        $this->assertNull($payment->payment_number);
        $this->assertNull($payment->webhook_verified_at);
        $this->assertNotNull($payment->dibayar_pada);
        $this->assertSame('admin_cash_confirm', data_get($gatewayResponse, 'cash_confirmation.source'));
        $this->assertSame($admin->id, data_get($gatewayResponse, 'cash_confirmation.confirmed_by_id'));

        $this->assertDatabaseHas('tiket_elektronik', [
            'detail_pemesanan_id' => $detail->id,
            'sudah_dipindai' => false,
        ]);
        $this->assertSame('dipesan', $detail->kursiJadwal()->firstOrFail()->status_kursi);

        $this->assertDatabaseHas('notifikasi', [
            'pengguna_id' => $booking->pengguna_id,
            'jenis' => 'pembayaran',
            'referensi_id' => $booking->id,
            'sudah_dibaca' => false,
        ]);

        $detailResponse = $this
            ->actingAs($admin)
            ->get(route('admin.pemesanan.show', $booking));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Status Pemesanan');
        $detailResponse->assertSee('Terbayar');
        $detailResponse->assertDontSee('<select', false);

        $reverseResponse = $this
            ->actingAs($admin)
            ->put(route('admin.pemesanan.update', $booking), [
                'status_pemesanan' => 'menunggu_pembayaran',
            ]);

        $reverseResponse->assertSessionHasErrors('status_pemesanan');
        $this->assertSame('terbayar', $booking->fresh()->status_pemesanan);
        $this->assertSame('berhasil', $booking->pembayaran()->firstOrFail()->status_bayar);
    }

    public function test_admin_cannot_confirm_cash_payment_twice(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            TravSDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()
            ->where('kode_booking', 'TRAVS-DEMO-002')
            ->firstOrFail();

        $booking->pembayaran()->update([
            'metode_bayar' => 'cash',
            'paid_channel' => 'Bayar Tunai',
            'status_bayar' => 'menunggu',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.pembayaran.confirm-cash', $booking))
            ->assertRedirect(route('admin.pembayaran.index'));

        $ticketCount = TiketElektronik::query()
            ->whereHas('detailPemesanan', fn ($query) => $query->where('pemesanan_id', $booking->id))
            ->count();

        $response = $this
            ->from(route('admin.pembayaran.index'))
            ->actingAs($admin)
            ->post(route('admin.pembayaran.confirm-cash', $booking));

        $response->assertRedirect(route('admin.pembayaran.index'));
        $response->assertSessionHasErrors('payment');

        $this->assertSame($ticketCount, TiketElektronik::query()
            ->whereHas('detailPemesanan', fn ($query) => $query->where('pemesanan_id', $booking->id))
            ->count());
    }

    public function test_admin_cannot_confirm_non_cash_payment_from_cash_route(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            TravSDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()
            ->where('kode_booking', 'TRAVS-DEMO-002')
            ->firstOrFail();

        $response = $this
            ->from(route('admin.pembayaran.index'))
            ->actingAs($admin)
            ->post(route('admin.pembayaran.confirm-cash', $booking));

        $response->assertRedirect(route('admin.pembayaran.index'));
        $response->assertSessionHasErrors('payment');

        $this->assertSame('menunggu_pembayaran', $booking->fresh()->status_pemesanan);
        $this->assertSame('menunggu', $booking->pembayaran()->firstOrFail()->status_bayar);
    }

    public function test_admin_can_cancel_pending_cash_booking_and_release_seat(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            TravSDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()
            ->with('detailPemesanan.kursiJadwal')
            ->where('kode_booking', 'TRAVS-DEMO-002')
            ->firstOrFail();
        $detail = $booking->detailPemesanan->first();

        $booking->pembayaran()->update([
            'metode_bayar' => 'cash',
            'paid_channel' => 'Bayar Tunai',
            'status_bayar' => 'menunggu',
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(route('admin.pemesanan.update', $booking), [
                'status_pemesanan' => 'dibatalkan',
            ]);

        $response->assertRedirect(route('admin.pemesanan.show', $booking));

        $this->assertSame('dibatalkan', $booking->fresh()->status_pemesanan);
        $this->assertSame('kedaluwarsa', $booking->pembayaran()->firstOrFail()->status_bayar);
        $this->assertSame('tersedia', $detail->kursiJadwal()->firstOrFail()->status_kursi);
    }
}
