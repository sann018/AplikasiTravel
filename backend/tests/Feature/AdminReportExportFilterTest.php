<?php

namespace Tests\Feature;

use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportExportFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_export_buttons_use_current_report_filter_form(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.laporan.pemesanan'))
            ->assertOk()
            ->assertSee('id="booking-report-filter"', false)
            ->assertSee('form="booking-report-filter"', false)
            ->assertSee('name="format" value="xls"', false);

        $this->actingAs($admin)
            ->get(route('admin.laporan.transaksi'))
            ->assertOk()
            ->assertSee('id="transaction-report-filter"', false)
            ->assertSee('form="transaction-report-filter"', false)
            ->assertSee('name="format" value="xls"', false);
    }

    public function test_booking_excel_export_uses_date_range_and_has_excel_filter(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        Pemesanan::query()->where('kode_booking', 'TRAVS-DEMO-001')->update([
            'created_at' => '2026-07-10 10:00:00',
        ]);
        Pemesanan::query()->where('kode_booking', 'TRAVS-DEMO-002')->update([
            'created_at' => '2026-07-20 10:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.laporan.pemesanan.export', [
            'format' => 'xls',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-07-15',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('TRAVS-DEMO-001', $content);
        $this->assertStringNotContainsString('TRAVS-DEMO-002', $content);
        $this->assertStringContainsString('01 Juli 2026 - 15 Juli 2026', $content);
        $this->assertStringContainsString('<AutoFilter x:Range=', $content);
        $this->assertStringContainsString('Status Pemesanan', $content);
        $this->assertStringContainsString('Status Pembayaran', $content);
        $this->assertTrue((new \DOMDocument)->loadXML($content));
    }

    public function test_transaction_excel_export_uses_date_range_and_has_excel_filter(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $first = Pembayaran::query()->where('gateway_reference', 'TRX-TRAVS-DEMO-001')->firstOrFail();
        $second = Pembayaran::query()->where('gateway_reference', 'TRX-TRAVS-DEMO-002')->firstOrFail();
        $first->timestamps = false;
        $first->forceFill(['created_at' => '2026-07-10 10:00:00'])->save();
        $second->timestamps = false;
        $second->forceFill(['created_at' => '2026-07-20 10:00:00'])->save();

        $response = $this->actingAs($admin)->get(route('admin.laporan.transaksi.export', [
            'format' => 'xls',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-07-15',
        ]));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString((string) $first->order_id_gateway, $content);
        $this->assertStringNotContainsString((string) $second->order_id_gateway, $content);
        $this->assertStringContainsString('<AutoFilter x:Range=', $content);
        $this->assertStringContainsString('Metode Bayar', $content);
        $this->assertStringContainsString('Status Bayar', $content);
    }

    public function test_reset_filter_action_has_orange_background(): void
    {
        $styles = file_get_contents(resource_path('css/admin/fase-satu.css'));

        $this->assertIsString($styles);
        $this->assertStringContainsString('[data-clear-form-draft]', $styles);
        $this->assertStringContainsString('background: #f58a2a', $styles);
        $this->assertStringContainsString('background: #d96f12', $styles);
    }
}
