<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemSettingSeeder::class);
    }

    public function test_admin_can_open_settings_center_and_guest_cannot(): void
    {
        $this->get('/admin/settings')->assertRedirect(route('admin.login'));

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Pengaturan Sistem')
            ->assertSee('PUSAT PENGATURAN')
            ->assertSee('Simpan Pengaturan Umum');
    }

    public function test_settings_page_uses_clear_indonesian_labels_and_form_guidance(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Durasi Pengisian Form')
            ->assertSee('Batas Waktu Pembayaran')
            ->assertSee('Pratinjau Logo')
            ->assertSee('Perubahan hanya berlaku untuk pemesanan baru')
            ->assertSee('Tambah Banner')
            ->assertDontSee('Control Center')
            ->assertDontSee('Seat Lock Form')
            ->assertDontSee('Seat Lock Payment')
            ->assertDontSee('data-settings-tab="payment"', false)
            ->assertDontSee('Instruksi Pembayaran Tunai')
            ->assertDontSee('Simpan Banner');
    }

    public function test_settings_page_contains_separate_forms_and_admin_helpers(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index', ['tab' => 'baggage']))
            ->assertOk()
            ->assertSee('Informasi Penting')
            ->assertSee('Tambah Informasi')
            ->assertDontSee('Simpan Pengaturan Bagasi')
            ->assertDontSee('Simulasi Bagasi')
            ->assertDontSee('data-baggage-simulator', false)
            ->assertDontSee('data-notification-preview', false)
            ->assertSee('name="max_seat_per_booking"', false)
            ->assertDontSee('name="admin_fee"', false)
            ->assertDontSee('data-settings-tab="notification"', false)
            ->assertDontSee('Simpan Pengaturan Notifikasi')
            ->assertDontSee('Tampilkan di aplikasi')
            ->assertDontSee('Simpan Semua Pengaturan');
    }

    public function test_settings_main_sections_are_visible_without_collapse_buttons(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('settings-admin-account-body', false)
            ->assertSee('settings-control-center-body', false)
            ->assertSee('settings-general-body', false)
            ->assertSee('data-collapsible-section', false)
            ->assertSee('data-collapse-body', false)
            ->assertDontSee('class="collapsible-section__toggle"', false);
    }

    public function test_general_tab_only_updates_general_settings(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $logo = UploadedFile::fake()->image('logo-ruslytravel.png', 1200, 800);

        $this->actingAs($admin)
            ->post(route('admin.settings.general.update'), [
                'app_name' => 'RuslyTravel Baru',
                'app_logo' => $logo,
                'admin_contact' => '081234567890',
                'service_whatsapp' => '081234567891',
                'system_email' => 'sistem@ruslytravel.test',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'general']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pengaturan_sistem', [
            'kunci' => 'app_name',
            'nilai' => 'RuslyTravel Baru',
            'diperbarui_oleh' => $admin->id,
        ]);
        $this->assertDatabaseHas('pengaturan_sistem', [
            'kunci' => 'max_seat_per_booking',
            'nilai' => '4',
        ]);

        $logoPath = (string) SystemSetting::query()
            ->where('kunci', 'app_logo')
            ->value('nilai');
        $this->assertStringStartsWith('app-logos/', $logoPath);
        $this->assertStringEndsWith('.webp', $logoPath);
        $this->assertTrue(
            Storage::disk('public')->exists($logoPath),
            "Logo pengaturan sistem tidak ditemukan pada disk public: {$logoPath}",
        );

        [$logoWidth, $logoHeight] = getimagesize(Storage::disk('public')->path($logoPath));
        $this->assertLessThanOrEqual(512, $logoWidth);
        $this->assertLessThanOrEqual(512, $logoHeight);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('/storage/'.$logoPath, false)
            ->assertSee('travs-settings-logo-preview travs-photo-preview-trigger', false)
            ->assertSee('data-preview-src=', false)
            ->assertSee('travs-brand-logo', false);
    }

    public function test_general_tab_rejects_non_image_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.settings.general.update'), [
                'app_name' => 'RuslyTravel',
                'app_logo' => UploadedFile::fake()->create('logo.txt', 20, 'text/plain'),
                'admin_contact' => '081234567890',
                'service_whatsapp' => '081234567891',
                'system_email' => 'sistem@ruslytravel.test',
            ])
            ->assertSessionHasErrors('app_logo');
    }

    public function test_booking_tab_rejects_invalid_limits(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.booking.update'), [
                'auto_cancel_booking' => '1',
                'max_seat_per_booking' => 0,
                'seat_lock_form_minutes' => 0,
                'seat_lock_payment_minutes' => 0,
            ])
            ->assertSessionHasErrors([
                'max_seat_per_booking',
                'seat_lock_form_minutes',
                'seat_lock_payment_minutes',
            ]);
    }

    public function test_payment_compatibility_endpoint_preserves_existing_settings(): void
    {
        SystemSetting::query()->where('kunci', 'payment_cash_enabled')->update(['nilai' => 'true']);
        SystemSetting::query()->where('kunci', 'bank_account_number')->update(['nilai' => '1234567890']);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.payment.update'), [
                'payment_transfer_enabled' => '1',
                'payment_qris_enabled' => '1',
                'payment_cash_note' => 'Bayarkan langsung kepada pengemudi sebelum keberangkatan.',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'payment']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pengaturan_sistem', [
            'kunci' => 'payment_cash_note',
            'nilai' => 'Bayarkan langsung kepada pengemudi sebelum keberangkatan.',
        ]);
        $this->assertDatabaseHas('pengaturan_sistem', [
            'kunci' => 'payment_cash_enabled',
            'nilai' => 'true',
        ]);
        $this->assertDatabaseHas('pengaturan_sistem', [
            'kunci' => 'bank_account_number',
            'nilai' => '1234567890',
        ]);
    }

    public function test_payment_tab_is_not_available_from_settings_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index', ['tab' => 'payment']))
            ->assertOk()
            ->assertDontSee('data-settings-tab="payment"', false)
            ->assertDontSee('Instruksi Pembayaran Tunai')
            ->assertDontSee('Metode tunai hanya muncul di mobile saat status ini aktif.')
            ->assertDontSee('Rekening Transfer')
            ->assertDontSee('name="payment_cash_enabled"', false)
            ->assertDontSee('name="bank_account_number"', false)
            ->assertDontSee('name="bank_account_name"', false)
            ->assertDontSee('name="bank_name"', false);
    }

    public function test_legacy_baggage_endpoint_no_longer_changes_tariffs(): void
    {
        $before = SystemSetting::query()->where('kunci', 'baggage_extra_fee_per_kg')->value('nilai');

        $this->actingAs($this->admin())
            ->post(route('admin.settings.baggage.update'), $this->validBaggagePayload([
                'baggage_extra_fee_per_kg' => 999999,
            ]))
            ->assertRedirect(route('admin.settings.index', ['tab' => 'general']))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $before,
            SystemSetting::query()->where('kunci', 'baggage_extra_fee_per_kg')->value('nilai'),
        );
    }

    public function test_admin_can_add_and_remove_important_information(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.settings.important-information.store'), [
                'title' => 'Perubahan titik keberangkatan',
                'description' => 'Periksa kembali titik keberangkatan sebelum berangkat.',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'general']))
            ->assertSessionHasNoErrors();

        $items = json_decode((string) SystemSetting::query()
            ->where('kunci', 'important_information')
            ->value('nilai'), true);
        $created = collect($items)->firstWhere('title', 'Perubahan titik keberangkatan');

        $this->assertNotNull($created);

        $this->actingAs($admin)
            ->delete(route('admin.settings.important-information.destroy', $created['id']))
            ->assertRedirect(route('admin.settings.index', ['tab' => 'general']))
            ->assertSessionHasNoErrors();

        $remaining = json_decode((string) SystemSetting::query()
            ->where('kunci', 'important_information')
            ->value('nilai'), true);

        $this->assertNull(collect($remaining)->firstWhere('id', $created['id']));
    }

    public function test_notification_tab_is_not_available_from_settings_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index', ['tab' => 'notification']))
            ->assertOk()
            ->assertDontSee('Simpan Pengaturan Notifikasi')
            ->assertDontSee('Template Pengingat Keberangkatan');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'peran' => 'admin',
            'status_aktif' => true,
        ]);
    }

    private function validBaggagePayload(array $override = []): array
    {
        return array_merge([
            'baggage_weight_enabled' => '1',
            'baggage_item_enabled' => '1',
            'baggage_photo_required' => '1',
            'baggage_free_weight_kg' => 10,
            'baggage_max_weight_per_person_kg' => 20,
            'baggage_extra_fee_per_kg' => 3000,
            'baggage_free_item_count' => 2,
            'baggage_max_item_per_person' => 10,
            'baggage_extra_fee_per_item' => 2500,
            'baggage_large_vehicle_enabled' => '0',
            'baggage_bicycle_fee' => 0,
            'baggage_motorcycle_fee' => 0,
            'baggage_heavy_item_fee' => 0,
            'baggage_max_total_weight_per_vehicle' => 50,
        ], $override);
    }
}
