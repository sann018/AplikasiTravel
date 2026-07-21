<?php

namespace App\Services;

class NotificationTemplateService
{
    public function __construct(
        private readonly SettingService $settings,
    ) {}

    public function enabled(string $settingKey, bool $default = true): bool
    {
        return (bool) $this->settings->get($settingKey, $default);
    }

    /**
     * Template membuat isi pesan dapat diubah admin tanpa menanam teks di banyak controller.
     *
     * @param  array<string, scalar|null>  $values
     */
    public function render(string $template, array $values): string
    {
        $replacements = [];
        foreach ($values as $key => $value) {
            $replacements['{'.trim($key, '{}').'}'] = (string) ($value ?? '');
        }

        return trim(strtr($template, $replacements));
    }

    /** @param array<string, scalar|null> $values */
    public function renderFromSetting(string $key, string $fallback, array $values): string
    {
        // Key lama tetap diterima agar pemanggil lama kompatibel, tetapi pesan
        // memakai template standar yang aman dan tidak dapat diubah admin.
        return $this->render($fallback, $values);
    }
}
