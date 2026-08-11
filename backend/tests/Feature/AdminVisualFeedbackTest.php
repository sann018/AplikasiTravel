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
            ->assertSee('data-flash-close', false);
    }
}
