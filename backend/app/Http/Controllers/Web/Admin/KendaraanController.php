<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kendaraan;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KendaraanController extends Controller
{
    public function index(Request $request): View
    {
        $query = Kendaraan::query()->withCount('jadwal');

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('nomor_plat', 'like', "%{$search}%")
                    ->orWhere('merk_kendaraan', 'like', "%{$search}%")
                    ->orWhere('tipe_kendaraan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status_kendaraan', $request->string('status'));
        }

        $kendaraan = $query->latest()->paginate(10)->withQueryString();

        return view('admin.kendaraan.index', [
            'kendaraan' => $kendaraan,
            'stats' => [
                'total' => Kendaraan::query()->count(),
                'aktif' => Kendaraan::query()->where('status_kendaraan', 'aktif')->count(),
                'perawatan' => Kendaraan::query()->where('status_kendaraan', 'perawatan')->count(),
                'nonaktif' => Kendaraan::query()->where('status_kendaraan', 'nonaktif')->count(),
            ],
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('admin.kendaraan.create');
    }

    public function edit(Kendaraan $kendaraan): View
    {
        return view('admin.kendaraan.edit', [
            'kendaraan' => $kendaraan,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request);

        if ($request->hasFile('foto_kendaraan')) {
            $payload['foto_kendaraan'] = $request->file('foto_kendaraan')->store('vehicles', 'public');
        }

        Kendaraan::query()->create($payload);

        return redirect()
            ->route('admin.kendaraan.index')
            ->with('status', 'Data armada berhasil ditambahkan.');
    }

    public function update(Request $request, Kendaraan $kendaraan): RedirectResponse
    {
        $payload = $this->validatedPayload($request, $kendaraan);

        if ($request->hasFile('foto_kendaraan')) {
            if ($kendaraan->foto_kendaraan) {
                Storage::disk('public')->delete($kendaraan->foto_kendaraan);
            }
            $payload['foto_kendaraan'] = $request->file('foto_kendaraan')->store('vehicles', 'public');
        }

        $kendaraan->update($payload);

        return redirect()
            ->route('admin.kendaraan.index')
            ->with('status', 'Data armada berhasil diperbarui.');
    }

    public function destroy(Kendaraan $kendaraan): RedirectResponse
    {
        try {
            $kendaraan->delete();
        } catch (QueryException) {
            return back()->with('error', 'Armada tidak dapat dihapus karena masih dipakai pada jadwal perjalanan.');
        }

        return back()->with('status', 'Data armada berhasil dihapus.');
    }

    private function validatedPayload(Request $request, ?Kendaraan $kendaraan = null): array
    {
        $validated = $request->validate([
            'nomor_plat' => [
                'required',
                'string',
                'max:20',
                Rule::unique('kendaraan', 'nomor_plat')->ignore($kendaraan?->id),
            ],
            'merk_kendaraan' => ['required', 'string', 'max:100'],
            'warna' => ['required', 'string', 'max:30'],
            'tahun_kendaraan' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'tipe_kendaraan' => ['nullable', 'string', 'max:100'],
            'kapasitas_kursi' => ['required', 'integer', 'min:1', 'max:20'],
            'seat_layout' => ['nullable', 'string', 'max:200'],
            'fasilitas' => ['nullable', 'string'],
            'total_kilometer' => ['nullable', 'integer', 'min:0'],
            'km_servis_berikutnya' => ['nullable', 'integer', 'min:0'],
            'tgl_terakhir_servis' => ['nullable', 'date'],
            'tgl_pajak_tahunan' => ['nullable', 'date'],
            'status_kendaraan' => ['required', Rule::in(['aktif', 'perawatan', 'nonaktif'])],
            'foto_kendaraan' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        // Input seperti "1,3,2" diubah menjadi daftar angka agar mudah disimpan
        // dan dipakai kembali saat aplikasi menyusun denah kursi.
        if (! empty($validated['seat_layout'])) {
            $layoutParts = array_filter(
                array_map('intval', explode(',', $validated['seat_layout'])),
                fn (int $value) => $value > 0,
            );
            $validated['seat_layout'] = ! empty($layoutParts) ? $layoutParts : null;
        } else {
            $validated['seat_layout'] = null;
        }

        return $validated;
    }
}
