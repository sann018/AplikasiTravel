<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDetailPageUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_schedule_detail_uses_the_consistent_detail_layout(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $schedule = Jadwal::query()->whereHas('pemesanan.pembayaran')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.jadwal.show', $schedule))
            ->assertOk()
            ->assertSee('travs-detail-page__header', false)
            ->assertSee('Ringkasan Operasional')
            ->assertSee('Kapasitas')
            ->assertSee('Surplus/Defisit')
            ->assertDontSee('Selisih Tercatat');
    }

    public function test_booking_detail_separates_payment_and_baggage_information(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $booking = Pemesanan::query()->whereHas('pembayaran')->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('admin.pemesanan.show', $booking));

        $response
            ->assertOk()
            ->assertSee('travs-detail-page__header', false)
            ->assertSee('Informasi Perjalanan')
            ->assertSee('Informasi Pemesan')
            ->assertSee('Rincian Pembayaran')
            ->assertSee('Barang Bawaan')
            ->assertDontSee('Pembayaran dan Barang Bawaan');

        // Nominal snapshot cukup ditampilkan sekali agar admin tidak membaca total ganda.
        $this->assertSame(1, substr_count($response->getContent(), '>Nominal Transaksi<'));
    }

    public function test_cash_confirmation_is_only_visible_for_pending_cash_payment(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $digitalPayment = Pembayaran::query()->where('metode_bayar', '!=', 'cash')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.pemesanan.show', $digitalPayment->pemesanan))
            ->assertOk()
            ->assertDontSee('Konfirmasi Pembayaran Tunai');

        $digitalPayment->update([
            'metode_bayar' => 'cash',
            'status_bayar' => 'menunggu',
        ]);
        $digitalPayment->pemesanan()->update([
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pemesanan.show', $digitalPayment->pemesanan()->firstOrFail()))
            ->assertOk()
            ->assertSee('Konfirmasi Pembayaran Tunai');
    }

    public function test_payment_detail_uses_two_responsive_information_cards(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $payment = Pembayaran::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.pembayaran.show', $payment))
            ->assertOk()
            ->assertSee('travs-detail-page__header', false)
            ->assertSee('travs-detail-card-grid', false)
            ->assertSee('Ringkasan Transaksi')
            ->assertSee('Rincian Pembayaran');
    }
}
