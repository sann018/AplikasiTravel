<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Validation\ValidationException;

class OperationalExpenseCategoryService
{
    public const SETTING_KEY = 'operational_expense_categories';

    /**
     * Kategori awal tetap tersedia saat aplikasi baru dipasang atau data
     * pengaturan lama belum memiliki key kategori pengeluaran.
     *
     * @var array<int, string>
     */
    private const DEFAULT_CATEGORIES = [
        'BBM',
        'Tol',
        'Parkir',
        'Makan Pengemudi',
        'Perawatan Darurat',
        'Lainnya',
    ];

    public function __construct(private readonly SettingService $settingService) {}

    /**
     * @return array<int, string>
     */
    public function categories(): array
    {
        $decoded = json_decode(
            (string) $this->settingService->get(self::SETTING_KEY, '[]'),
            true,
        );
        $categories = is_array($decoded)
            ? $this->normalizeMany($decoded)
            : [];

        return $categories !== [] ? $categories : self::DEFAULT_CATEGORIES;
    }

    /**
     * Kategori lama milik satu catatan tetap boleh ditampilkan saat edit.
     * Catatan tersebut tidak otomatis mengaktifkan kategori lama untuk input baru.
     *
     * @return array<int, string>
     */
    public function categoriesForEdit(?string $currentCategory): array
    {
        return $this->normalizeMany([
            ...$this->categories(),
            $currentCategory,
        ]);
    }

    public function add(string $category, int $adminId): void
    {
        $normalized = $this->normalize($category);
        $categories = $this->categories();

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'nama_kategori' => 'Nama kategori berisi karakter yang tidak valid.',
            ]);
        }

        if ($this->contains($categories, $normalized)) {
            throw ValidationException::withMessages([
                'nama_kategori' => 'Kategori pengeluaran tersebut sudah tersedia.',
            ]);
        }

        $this->persist([...$categories, $normalized], $adminId);
    }

    public function remove(string $category, int $adminId): void
    {
        $normalized = $this->normalize($category);
        $categories = $this->categories();

        if ($normalized === null || ! $this->contains($categories, $normalized)) {
            throw ValidationException::withMessages([
                'nama_kategori' => 'Kategori pengeluaran tidak ditemukan.',
            ]);
        }

        $remaining = array_values(array_filter(
            $categories,
            fn (string $item) => mb_strtolower($item) !== mb_strtolower($normalized),
        ));

        if ($remaining === []) {
            throw ValidationException::withMessages([
                'nama_kategori' => 'Minimal satu kategori pengeluaran harus tetap tersedia.',
            ]);
        }

        $this->persist($remaining, $adminId);
    }

    /**
     * @param  array<int, mixed>  $categories
     * @return array<int, string>
     */
    private function normalizeMany(array $categories): array
    {
        $normalized = [];

        foreach ($categories as $category) {
            if (! is_string($category)) {
                continue;
            }

            $cleanCategory = $this->normalize($category);
            if ($cleanCategory === null || $this->contains($normalized, $cleanCategory)) {
                continue;
            }

            $normalized[] = $cleanCategory;
        }

        return $normalized;
    }

    private function normalize(string $category): ?string
    {
        $category = preg_replace('/\s+/u', ' ', trim($category)) ?? '';

        if (
            $category === ''
            || mb_strlen($category) > 60
            || preg_match('/[\x00-\x1F\x7F]/u', $category) === 1
        ) {
            return null;
        }

        return $category;
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function contains(array $categories, string $needle): bool
    {
        $normalizedNeedle = mb_strtolower($needle);

        return collect($categories)->contains(
            fn (string $category) => mb_strtolower($category) === $normalizedNeedle,
        );
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function persist(array $categories, int $adminId): void
    {
        // Daftar sederhana disimpan sebagai JSON pada pengaturan_sistem agar
        // web dan mobile membaca satu sumber tanpa menambah tabel bisnis baru.
        SystemSetting::query()->updateOrCreate(
            ['kunci' => self::SETTING_KEY],
            [
                'kelompok' => 'operational',
                'nilai' => json_encode($categories, JSON_UNESCAPED_UNICODE),
                'tipe' => 'text',
                'deskripsi' => 'Daftar kategori pengeluaran yang dapat dipilih admin dan pengemudi.',
                'diperbarui_oleh' => $adminId,
            ],
        );

        $this->settingService->clearCache();
    }
}
