<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminFilterValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[DataProvider('filterRouteProvider')]
    public function test_admin_filter_rejects_invalid_date(string $routeName): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $origin = route($routeName);

        $this->actingAs($admin)
            ->from($origin)
            ->get(route($routeName, ['tanggal_mulai' => 'bukan-tanggal']))
            ->assertRedirect($origin)
            ->assertSessionHasErrors('tanggal_mulai');
    }

    #[DataProvider('filterRouteProvider')]
    public function test_admin_filter_rejects_reversed_date_range(string $routeName): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $origin = route($routeName);

        $this->actingAs($admin)
            ->from($origin)
            ->get(route($routeName, [
                'tanggal_mulai' => '2026-07-26',
                'tanggal_selesai' => '2026-07-25',
            ]))
            ->assertRedirect($origin)
            ->assertSessionHasErrors('tanggal_selesai');
    }

    public function test_report_filters_reject_unknown_status_and_payment_method(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.laporan.pemesanan'))
            ->get(route('admin.laporan.pemesanan', ['status' => 'status-tidak-dikenal']))
            ->assertRedirect(route('admin.laporan.pemesanan'))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->from(route('admin.laporan.transaksi'))
            ->get(route('admin.laporan.transaksi', ['metode_bayar' => 'metode-tidak-dikenal']))
            ->assertRedirect(route('admin.laporan.transaksi'))
            ->assertSessionHasErrors('metode_bayar');
    }

    public function test_schedule_filter_rejects_invalid_date_and_status(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $origin = route('admin.jadwal.index');

        $this->actingAs($admin)
            ->from($origin)
            ->get(route('admin.jadwal.index', ['tanggal' => 'bukan-tanggal']))
            ->assertRedirect($origin)
            ->assertSessionHasErrors('tanggal');

        $this->actingAs($admin)
            ->from($origin)
            ->get(route('admin.jadwal.index', ['status' => 'status-tidak-dikenal']))
            ->assertRedirect($origin)
            ->assertSessionHasErrors('status');
    }

    public function test_filter_validation_message_is_visible_after_redirect(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $origin = route('admin.jadwal.index');

        $this->actingAs($admin)
            ->followingRedirects()
            ->from($origin)
            ->get(route('admin.jadwal.index', ['tanggal' => 'bukan-tanggal']))
            ->assertOk()
            ->assertSee('Tanggal jadwal tidak valid.');
    }

    public static function filterRouteProvider(): array
    {
        return [
            'monitoring pemesanan' => ['admin.pemesanan.index'],
            'daftar pembayaran' => ['admin.pembayaran.index'],
            'biaya operasional' => ['admin.biaya.index'],
            'laporan pemesanan' => ['admin.laporan.pemesanan'],
            'laporan transaksi' => ['admin.laporan.transaksi'],
        ];
    }
}
