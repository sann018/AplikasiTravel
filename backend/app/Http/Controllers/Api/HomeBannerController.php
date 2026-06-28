<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use Illuminate\Http\JsonResponse;

class HomeBannerController extends Controller
{
    public function index(): JsonResponse
    {
        $items = HomeBanner::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->latest('id')
            ->get()
            ->map(fn (HomeBanner $banner) => [
                'id' => $banner->id,
                'judul' => $banner->judul,
                'subjudul' => $banner->subjudul,
                'gambar_url' => '/storage/'.ltrim($banner->gambar_path, '/'),
            ]);

        return response()->json(['success' => true, 'data' => $items]);
    }
}
