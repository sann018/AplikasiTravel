<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVisualFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_use_rusly_travel_favicon(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('rusly-travel-icon.svg');
    }

    public function test_admin_layout_renders_custom_success_and_error_messages(): void
    {
        $admin = User::factory()->create([
            'peran' => 'admin',
            'status_aktif' => true,
        ]);

        $this->actingAs($admin)
            ->withSession([
                'status' => 'Data berhasil diperbarui.',
                'error' => 'Data belum dapat dihapus.',
            ])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('travs-feedback-success', false)
            ->assertSee('travs-feedback-danger', false)
            ->assertSee('Data berhasil diperbarui.')
            ->assertSee('Data belum dapat dihapus.')
            ->assertSee('data-flash-close', false)
            ->assertDontSee('travs-feedback-icon', false)
            ->assertSee('data-confirm-dialog', false)
            ->assertSee('data-confirm-submit', false);
    }

    public function test_admin_feedback_styles_remain_compact_and_responsive(): void
    {
        $css = file_get_contents(resource_path('css/admin/umpan-balik.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('left: calc(250px + (100vw - 250px) / 2);', $css);
        $this->assertStringContainsString('width: min(380px, calc(100vw - 282px));', $css);
        $this->assertStringContainsString('min-height: 78px;', $css);
        $this->assertStringContainsString('width: min(100%, 620px);', $css);
        $this->assertStringContainsString('width: min(100%, 390px);', $css);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $css);
    }

    public function test_schedule_delete_uses_custom_confirmation_instead_of_browser_confirm(): void
    {
        $view = file_get_contents(resource_path('views/admin/jadwal/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('data-confirm-title="Hapus jadwal?"', $view);
        $this->assertStringNotContainsString("confirm('Hapus jadwal ini?')", $view);
    }
}
