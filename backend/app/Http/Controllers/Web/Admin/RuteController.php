<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rute;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RuteController extends Controller
{
    public function index(Request $request): View
    {
        $query = Rute::query()->withCount('jadwal');

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('kota_asal', 'like', "%{$search}%")
                    ->orWhere('kota_tujuan', 'like', "%{$search}%")
                    ->orWhere('deskripsi_jalur', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status_aktif', $request->string('status') === 'aktif');
        }

        $rute = $query->latest()->paginate(10)->withQueryString();
        $editingRute = $request->filled('edit')
            ? Rute::query()->findOrFail($request->integer('edit'))
            : null;

        return view('admin.rute.index', [
            'rute' => $rute,
            'editingRute' => $editingRute,
            'stats' => [
                'total' => Rute::query()->count(),
                'aktif' => Rute::query()->where('status_aktif', true)->count(),
                'nonaktif' => Rute::query()->where('status_aktif', false)->count(),
            ],
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Rute::query()->create($this->validatedPayload($request));

        return redirect()
            ->route('admin.rute.index')
            ->with('status', 'Rute perjalanan berhasil ditambahkan.');
    }

    public function update(Request $request, Rute $rute): RedirectResponse
    {
        $rute->update($this->validatedPayload($request));

        return redirect()
            ->route('admin.rute.index')
            ->with('status', 'Rute perjalanan berhasil diperbarui.');
    }

    public function destroy(Rute $rute): RedirectResponse
    {
        try {
            $rute->delete();
        } catch (QueryException) {
            return back()->with('error', 'Rute tidak dapat dihapus karena masih dipakai oleh jadwal perjalanan.');
        }

        return back()->with('status', 'Rute perjalanan berhasil dihapus.');
    }

    private function validatedPayload(Request $request): array
    {
        $payload = $request->validate([
            'kota_asal' => ['required', 'string', 'max:100'],
            'kota_tujuan' => ['required', 'string', 'max:100'],
            'deskripsi_jalur' => ['nullable', 'string', 'max:255'],
            'jarak_km' => ['nullable', 'numeric', 'min:0'],
            'estimasi_durasi_menit' => ['nullable', 'integer', 'min:1'],
            'harga_tiket' => ['required', 'numeric', 'min:0'],
            'status_aktif' => ['nullable', 'boolean'],
        ]);

        $payload['status_aktif'] = (bool) ($payload['status_aktif'] ?? false);

        return $payload;
    }
}
