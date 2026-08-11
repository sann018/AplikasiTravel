<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminPasswordToggleUiTest extends TestCase
{
    public function test_targeted_admin_password_fields_use_consistent_toggle(): void
    {
        $views = [
            'admin/auth/login.blade.php' => 1,
            'admin/pengemudi/create.blade.php' => 1,
            'admin/pengemudi/edit.blade.php' => 1,
            'admin/pengaturan/partials/akun.blade.php' => 3,
        ];

        foreach ($views as $relativePath => $expectedToggleCount) {
            $source = file_get_contents(resource_path('views/'.$relativePath));

            $this->assertSame(
                $expectedToggleCount,
                substr_count($source, 'data-password-toggle'),
                'Jumlah kontrol password tidak sesuai pada '.$relativePath,
            );
            $this->assertSame(
                $expectedToggleCount,
                substr_count($source, 'data-password-icon'),
                'Setiap field password harus memiliki tepat satu ikon.',
            );
            $this->assertStringNotContainsString('data-password-show-icon', $source);
            $this->assertStringNotContainsString('data-password-hide-icon', $source);
        }

        $script = file_get_contents(resource_path('js/app.js'));
        $style = file_get_contents(resource_path('css/admin/autentikasi-01.css'));

        // Nilai input tidak berubah; tombol hanya mengganti jenis input dan label aksesibel.
        $this->assertStringContainsString(
            "input.type = shouldShow ? 'text' : 'password'",
            $script,
        );
        $this->assertStringContainsString(
            "button.setAttribute('aria-pressed', String(shouldShow))",
            $script,
        );
        $this->assertStringContainsString('feather.icons[iconName].toSvg', $script);
        $this->assertStringContainsString('color: #000;', $style);
        $this->assertStringContainsString('::-ms-reveal', $style);
    }

    public function test_reset_password_uses_one_black_icon_with_twenty_character_limit(): void
    {
        $source = file_get_contents(resource_path('views/auth/reset-password.blade.php'));

        $this->assertSame(
            2,
            substr_count($source, '<button type="button" class="password-toggle" data-password-toggle'),
        );
        $this->assertSame(2, substr_count($source, 'data-password-icon'));
        $this->assertStringContainsString('color: #000;', $source);
        $this->assertStringNotContainsString('maxlength="8"', $source);
        $this->assertStringContainsString('maxlength="20"', $source);
    }
}
