<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HomeBannerController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.index', ['tab' => 'banner']);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:120'],
            'subjudul' => ['nullable', 'string', 'max:255'],
            'gambar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'urutan' => ['required', 'integer', 'min:0', 'max:999'],
        ], [
            'gambar.image' => 'File banner harus berupa gambar.',
            'gambar.mimes' => 'Format banner harus JPG, JPEG, PNG, atau WEBP.',
            'gambar.max' => 'Ukuran banner maksimal 3 MB.',
        ]);

        $path = $request->file('gambar')?->store('home-banners', 'public');
        if (! $path) {
            return back()->withInput()->withErrors(['gambar' => 'Banner belum berhasil diunggah.']);
        }

        HomeBanner::query()->create([
            'judul' => $validated['judul'],
            'subjudul' => $validated['subjudul'] ?? null,
            'path_gambar' => $path,
            'urutan' => $validated['urutan'],
            // Kontrol aktif/nonaktif sudah dihapus; banner baru langsung tampil.
            'aktif' => true,
            'dibuat_oleh' => $request->user()?->id,
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'banner'])->with('status', 'Banner beranda berhasil ditambahkan.');
    }

    public function destroy(HomeBanner $banner): RedirectResponse
    {
        $path = $banner->path_gambar;
        $banner->delete();
        Storage::disk('public')->delete($path);

        return redirect()->route('admin.settings.index', ['tab' => 'banner'])->with('status', 'Banner beranda berhasil dihapus.');
    }
}
