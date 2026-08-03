<?php

namespace Tests\Feature;

use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRevenueReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_transaction_report_separates_revenue_provider_fee_and_customer_total(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()->where('kode_booking', 'TRAVS-DEMO-001')->firstOrFail();
        $booking->update([
            'status_pemesanan' => 'terbayar',
            'total_harga' => 400000,
        ]);
        $booking->pembayaran()->update([
            'metode_bayar' => 'qris',
            'status_bayar' => 'berhasil',
            'nominal_transaksi' => 400000,
            'biaya_admin_provider' => 4000,
            'total_dibayar_penumpang' => 404000,
            'jumlah_bayar' => 404000,
            'dibayar_pada' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.laporan.transaksi', [
            'status' => 'berhasil',
            'metode_bayar' => 'qris',
        ]));

        $response->assertOk();
        $response->assertSee('Pendapatan RuslyTravel');
        $response->assertSee('Biaya Admin Pakasir');
        $response->assertSee('Dibayar Penumpang');
        $response->assertSee('Rp400.000');
        $response->assertSee('Rp4.000');
        $response->assertSee('Rp404.000');
    }

    public function test_dashboard_revenue_uses_transaction_amount_and_excludes_cancelled_payment(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()->where('kode_booking', 'TRAVS-DEMO-001')->firstOrFail();
        Pembayaran::query()->whereKeyNot($booking->pembayaran()->firstOrFail()->id)->update([
            'dibayar_pada' => now()->subMonth(),
        ]);
        $booking->update(['status_pemesanan' => 'terbayar']);
        $booking->pembayaran()->update([
            'status_bayar' => 'berhasil',
            'nominal_transaksi' => 400000,
            'biaya_admin_provider' => 4000,
            'total_dibayar_penumpang' => 404000,
            'jumlah_bayar' => 404000,
            'dibayar_pada' => now(),
        ]);

        $cancelled = Pemesanan::query()->where('kode_booking', 'TRAVS-DEMO-002')->firstOrFail();
        $cancelled->update(['status_pemesanan' => 'dibatalkan']);
        $cancelled->pembayaran()->update([
            'status_bayar' => 'berhasil',
            'nominal_transaksi' => 175000,
            'biaya_admin_provider' => 3500,
            'total_dibayar_penumpang' => 178500,
            'jumlah_bayar' => 178500,
            'dibayar_pada' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertSame(
            400000,
            (int) collect($response->viewData('pendapatanHarian'))->sum('total'),
        );
    }

    public function test_dashboard_shows_critical_information_cards(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()->where('kode_booking', 'TRAVS-DEMO-001')->firstOrFail();

        Pembayaran::query()->update([
            'status_bayar' => 'menunggu',
            'dibayar_pada' => now()->subYears(2),
        ]);
        $booking->update(['status_pemesanan' => 'selesai']);
        $booking->pembayaran()->update([
            'status_bayar' => 'berhasil',
            'nominal_transaksi' => 400000,
            'dibayar_pada' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Pendapatan Minggu Ini')
            ->assertSee('Rute Paling Banyak Dikunjungi')
            ->assertDontSee('Pemesanan Berhasil Bulan Ini')
            ->assertSee('Menunggu Pembayaran')
            ->assertSee('Keberangkatan Hari Ini')
            ->assertSee('Ringkasan Biaya Operasional')
            ->assertSee('Biaya Operasional Hari Ini')
            ->assertDontSee('Armada Aktif')
            ->assertDontSee('Pengemudi Aktif')
            ->assertDontSee('Rute Aktif');

        $this->assertSame(400000, $response->viewData('stats')['pendapatan_minggu_ini']);
        $this->assertSame(
            $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan,
            $response->viewData('ruteTerpopuler')['label'],
        );
        $this->assertSame(1, $response->viewData('ruteTerpopuler')['jumlah_pemesanan']);
        $this->assertSame(2, $response->viewData('ruteTerpopuler')['jumlah_penumpang']);
        $response->assertSee('1 pemesanan selesai');
        $response->assertSee('2 penumpang');
    }

    public function test_transaction_report_shows_current_month_and_year_revenue_cards(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $booking = Pemesanan::query()->where('kode_booking', 'TRAVS-DEMO-001')->firstOrFail();
        $cancelled = Pemesanan::query()->where('kode_booking', 'TRAVS-DEMO-002')->firstOrFail();

        Pembayaran::query()->update([
            'status_bayar' => 'menunggu',
            'dibayar_pada' => now()->subYears(2),
        ]);
        $booking->update(['status_pemesanan' => 'terbayar']);
        $booking->pembayaran()->update([
            'status_bayar' => 'berhasil',
            'nominal_transaksi' => 400000,
            'biaya_admin_provider' => 4000,
            'total_dibayar_penumpang' => 404000,
            'dibayar_pada' => now(),
        ]);
        $cancelled->update(['status_pemesanan' => 'dibatalkan']);
        $cancelled->pembayaran()->update([
            'status_bayar' => 'berhasil',
            'nominal_transaksi' => 175000,
            'biaya_admin_provider' => 3500,
            'total_dibayar_penumpang' => 178500,
            'dibayar_pada' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.laporan.transaksi'));

        $response->assertOk()
            ->assertSee('Pendapatan Tahun Ini')
            ->assertSee('Pendapatan Bulan Ini')
            ->assertSee('Transaksi Berhasil')
            ->assertSee('Pendapatan Filter Aktif');

        $this->assertSame(400000, $response->viewData('periodSummary')['pendapatan_tahun_ini']);
        $this->assertSame(400000, $response->viewData('periodSummary')['pendapatan_bulan_ini']);
        $this->assertSame(1, $response->viewData('summary')['berhasil']);
    }

    public function test_cash_report_has_zero_provider_fee_and_equal_total(): void
    {
        $payment = Pembayaran::query()->where('metode_bayar', 'cash')->firstOrFail();
        $payment->update([
            'nominal_transaksi' => 240000,
            'biaya_admin_provider' => 0,
            'total_dibayar_penumpang' => 240000,
            'jumlah_bayar' => 240000,
        ]);

        $payment->refresh();

        $this->assertSame(240000, $payment->nominal_transaksi);
        $this->assertSame(0, $payment->biaya_admin_provider);
        $this->assertSame(240000, $payment->total_dibayar_penumpang);
    }
}
