<?php

namespace App\Services;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LogoImageService
{
    private const MAX_SIDE = 512;
    public function store(UploadedFile $file): string
    {
        $binary = file_get_contents($file->getRealPath());
        if ($binary === false) {
            throw new RuntimeException('File logo tidak dapat dibaca.');
        }
        return $this->storeResizedBinary($binary);
    }

    public function storeFromExisting(string $path): string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return $path;
        }

        return $this->storeResizedBinary($disk->get($path));
    }

    private function storeResizedBinary(string $binary): string
    {
        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new RuntimeException('Format gambar logo tidak dapat diproses.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, self::MAX_SIDE / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        // Area transparan dijaga agar logo PNG tetap rapi ketika diubah menjadi WEBP.
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        ob_start();
        $encoded = imagewebp($target, null, 86);
        $optimizedBinary = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        if (! $encoded || ! is_string($optimizedBinary) || $optimizedBinary === '') {
            throw new RuntimeException('Logo belum berhasil diperkecil.');
        }

        $path = 'app-logos/'.Str::uuid().'.webp';
        if (! Storage::disk('public')->put($path, $optimizedBinary)) {
            throw new RuntimeException('Logo belum berhasil disimpan.');
        }

        return $path;
    }
}
