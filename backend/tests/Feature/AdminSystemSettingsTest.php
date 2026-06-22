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
            ->assertSee('Simpan Pengaturan Umum');
    }

    public function test_settings_page_contains_separate_forms_and_admin_helpers(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index', ['tab' => 'baggage']))
            ->assertOk()
            ->assertSee('Simpan Pengaturan Bagasi')
            ->assertSee('Simulasi Bagasi')
            ->assertSee('data-baggage-simulator', false)
            ->assertDontSee('data-notification-preview', false)
            ->assertSee('name="max_seat_per_booking"', false)
            ->assertDontSee('name="admin_fee"', false)
            ->assertDontSee('data-settings-tab="notification"', false)
            ->assertDontSee('Simpan Pengaturan Notifikasi')
            ->assertDontSee('Simpan Semua Pengaturan');
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
                'service_whatsapp' => '6281234567890',
                'system_email' => 'sistem@ruslytravel.test',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'general']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('system_settings', [
            'key' => 'app_name',
            'value' => 'RuslyTravel Baru',
            'updated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => 'max_seat_per_booking',
            'value' => '4',
        ]);

        $logoPath = (string) SystemSetting::query()
            ->where('key', 'app_logo')
            ->value('value');
        $this->assertStringStartsWith('app-logos/', $logoPath);
        $this->assertStringEndsWith('.webp', $logoPath);
        Storage::disk('public')->assertExists($logoPath);

        [$logoWidth, $logoHeight] = getimagesize(Storage::disk('public')->path($logoPath));
        $this->assertLessThanOrEqual(512, $logoWidth);
        $this->assertLessThanOrEqual(512, $logoHeight);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('/storage/'.$logoPath, false)
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
                'service_whatsapp' => '6281234567890',
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

    public function test_payment_tab_requires_bank_and_cash_information_when_enabled(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.payment.update'), [
                'payment_transfer_enabled' => '1',
                'payment_qris_enabled' => '1',
                'payment_cash_enabled' => '1',
                'bank_account_number' => '',
                'bank_account_name' => '',
                'bank_name' => '',
                'payment_cash_note' => '',
            ])
            ->assertSessionHasErrors([
                'bank_account_number',
                'payment_cash_note',
            ]);
    }

    public function test_baggage_tab_rejects_free_limits_above_maximum(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.baggage.update'), $this->validBaggagePayload([
                'baggage_free_weight_kg' => 21,
                'baggage_max_weight_per_person_kg' => 20,
                'baggage_free_size_cm' => 170,
                'baggage_max_size_per_person_cm' => 160,
                'baggage_free_item_count' => 11,
                'baggage_max_item_per_person' => 10,
            ]))
            ->assertSessionHasErrors([
                'baggage_free_weight_kg',
                'baggage_free_size_cm',
                'baggage_free_item_count',
            ]);
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
            'baggage_size_enabled' => '1',
            'baggage_item_enabled' => '1',
            'baggage_photo_required' => '1',
            'baggage_free_weight_kg' => 10,
            'baggage_max_weight_per_person_kg' => 20,
            'baggage_extra_fee_per_kg' => 3000,
            'baggage_free_size_cm' => 160,
            'baggage_max_size_per_person_cm' => 160,
            'baggage_extra_fee_per_cm' => 500,
            'baggage_free_item_count' => 2,
            'baggage_max_item_per_person' => 10,
            'baggage_extra_fee_per_item' => 2500,
            'baggage_max_total_weight_per_vehicle' => 50,
        ], $override);
    }
}
