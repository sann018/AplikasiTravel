<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    private const CACHE_KEY = 'system_settings';

    public function all(): array
    {
        return $this->records()
            ->mapWithKeys(fn (array $setting) => [
                $setting['key'] => $this->castValue($setting['value'], $setting['type']),
            ])
            ->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function group(string $group): array
    {
        return $this->records()
            ->where('group', $group)
            ->mapWithKeys(fn (array $setting) => [
                $setting['key'] => $this->castValue($setting['value'], $setting['type']),
            ])
            ->all();
    }

    public function publicAssetPath(string $key, ?string $default = null): ?string
    {
        $path = trim((string) $this->get($key, $default));
        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, '/')) {
            return $path;
        }

        return str_starts_with($path, 'app-logos/')
            ? '/storage/'.$path
            : '/'.$path;
    }

    /**
     * Informasi penting disimpan sebagai JSON agar admin dapat menambah atau
     * menghapus pengumuman tanpa menambah tabel baru untuk data yang sederhana.
     *
     * @return array<int, array{id:string, title:string, description:string}>
     */
    public function importantInformation(): array
    {
        $decoded = json_decode((string) $this->get('important_information', '[]'), true);
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => [
                'id' => (string) ($item['id'] ?? ''),
                'title' => trim((string) ($item['title'] ?? '')),
                'description' => trim((string) ($item['description'] ?? '')),
            ])
            ->filter(fn (array $item) => $item['id'] !== '' && $item['title'] !== '' && $item['description'] !== '')
            ->values()
            ->all();
    }

    public function definitionsByGroup(): Collection
    {
        return $this->records()
            ->map(function (array $setting) {
                $setting['typed_value'] = $this->castValue($setting['value'], $setting['type']);

                return $setting;
            })
            ->groupBy('group');
    }

    public function updateMany(array $data, ?int $adminId = null): void
    {
        // Controller cukup mengirim nilai; detail tabel dan tipe data ditangani di satu tempat.
        $settings = SystemSetting::query()
            ->whereIn('kunci', array_keys($data))
            ->get()
            ->keyBy('kunci');

        foreach ($data as $key => $value) {
            $setting = $settings->get($key);
            if (! $setting) {
                continue;
            }

            $setting->update([
                'nilai' => $this->prepareStoredValue($value, $setting->tipe),
                // Audit sederhana ini menunjukkan admin terakhir yang mengubah nilai.
                'diperbarui_oleh' => $adminId,
            ]);
        }

        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function records(): Collection
    {
        // Cache hanya menyimpan array sederhana agar aman dibaca web, scheduler, dan perintah Artisan.
        $records = Cache::rememberForever(self::CACHE_KEY, fn () => SystemSetting::query()
            ->orderBy('kelompok')
            ->orderBy('kunci')
            ->get()
            ->map(fn (SystemSetting $setting) => [
                'id' => $setting->id,
                // Alias internal lama dipertahankan agar controller, Blade,
                // dan kontrak API tidak ikut berubah karena rename database.
                'group' => $setting->kelompok,
                'key' => $setting->kunci,
                'value' => $setting->nilai,
                'type' => $setting->tipe,
                'description' => $setting->deskripsi,
                'updated_by' => $setting->diperbarui_oleh,
                'updated_at' => $setting->updated_at?->toISOString(),
            ])
            ->all());

        return collect($records);
    }

    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'float' => (float) $value,
            default => $value,
        };
    }

    private function prepareStoredValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL) ? 'true' : 'false',
            'integer' => (string) ((int) $value),
            'float' => (string) ((float) $value),
            default => (string) $value,
        };
    }
}
