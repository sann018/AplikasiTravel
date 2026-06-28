<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BiayaOperasionalPerjalanan;
use App\Models\Jadwal;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BiayaOperasionalController extends Controller
{
    public function index(Request $request): View
    {
        $query = BiayaOperasionalPerjalanan::query()->with(['jadwal.rute', 'pencatat']);

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('jenis_biaya', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%")
                    ->orWhereHas('jadwal.rute', function ($ruteQuery) use ($search) {
                        $ruteQuery
                            ->where('kota_asal', 'like', "%{$search}%")
                            ->orWhere('kota_tujuan', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('jadwal_id')) {
            $query->where('jadwal_id', $request->integer('jadwal_id'));
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal_biaya', '>=', $request->date('tanggal_mulai'));
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('tanggal_biaya', '<=', $request->date('tanggal_selesai'));
        }

        // Rekap final hanya memakai biaya yang sudah disetujui admin.
        $rekapRecords = (clone $query)
            ->where('status_verifikasi', 'disetujui')
            ->orderByDesc('tanggal_biaya')
            ->get();
        $rekap = $rekapRecords
            ->groupBy(fn (BiayaOperasionalPerjalanan $item) => optional($item->jadwal?->rute)->kota_asal.' - '.optional($item->jadwal?->rute)->kota_tujuan)
            ->map(function (Collection $items, string $routeLabel) {
                return [
                    'route' => $routeLabel !== ' - ' ? $routeLabel : 'Tanpa rute',
                    'total' => (float) $items->sum('nominal'),
                    'count' => $items->count(),
                    'latest_date' => optional($items->max('tanggal_biaya'))->format('Y-m-d H:i:s'),
                ];
            })
            ->values();

        $biaya = $query->latest('tanggal_biaya')->paginate(12)->withQueryString();
        $editingBiaya = $request->filled('edit')
            ? BiayaOperasionalPerjalanan::query()->findOrFail($request->integer('edit'))
            : null;

        return view('admin.biaya.index', [
            'biaya' => $biaya,
            'editingBiaya' => $editingBiaya,
            'jadwalOptions' => Jadwal::query()
                ->with('rute')
                ->orderByDesc('waktu_berangkat')
                ->limit(100)
                ->get(),
            'stats' => [
                'hari_ini' => (float) BiayaOperasionalPerjalanan::query()
                    ->where('status_verifikasi', 'disetujui')
                    ->whereDate('tanggal_biaya', today())
                    ->sum('nominal'),
                'bulan_ini' => (float) BiayaOperasionalPerjalanan::query()
                    ->where('status_verifikasi', 'disetujui')
                    ->whereMonth('tanggal_biaya', today()->month)
                    ->whereYear('tanggal_biaya', today()->year)
                    ->sum('nominal'),
                'total_item' => BiayaOperasionalPerjalanan::query()
                    ->where('status_verifikasi', 'disetujui')
                    ->count(),
            ],
            'filters' => $request->only(['search', 'jadwal_id', 'tanggal_mulai', 'tanggal_selesai']),
            'rekap' => $rekap,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request);
        $payload['created_by'] = $request->user()->id;
        $payload['status_verifikasi'] = 'disetujui';
        $payload['diverifikasi_oleh'] = $request->user()->id;
        $payload['diverifikasi_pada'] = now();

        BiayaOperasionalPerjalanan::query()->create($payload);

        return redirect()
            ->route('admin.biaya.index')
            ->with('status', 'Biaya operasional berhasil dicatat.');
    }

    public function update(Request $request, BiayaOperasionalPerjalanan $biayaOperasional): RedirectResponse
    {
        $biayaOperasional->update($this->validatedPayload($request));

        return redirect()
            ->route('admin.biaya.index')
            ->with('status', 'Biaya operasional berhasil diperbarui.');
    }

    public function destroy(BiayaOperasionalPerjalanan $biayaOperasional): RedirectResponse
    {
        try {
            $biayaOperasional->delete();
        } catch (QueryException) {
            return back()->with('error', 'Biaya operasional tidak dapat dihapus.');
        }

        return back()->with('status', 'Biaya operasional berhasil dihapus.');
    }

    public function verify(Request $request, BiayaOperasionalPerjalanan $biayaOperasional): RedirectResponse
    {
        $validated = $request->validate([
            'status_verifikasi' => ['required', 'in:disetujui,ditolak'],
            'catatan_verifikasi' => ['nullable', 'string', 'max:1000'],
        ], [
            'status_verifikasi.required' => 'Status verifikasi wajib dipilih.',
            'status_verifikasi.in' => 'Status verifikasi tidak valid.',
        ]);

        $biayaOperasional->update([
            'status_verifikasi' => $validated['status_verifikasi'],
            'catatan_verifikasi' => $validated['catatan_verifikasi'] ?? null,
            'diverifikasi_oleh' => $request->user()->id,
            'diverifikasi_pada' => now(),
        ]);

        $message = $validated['status_verifikasi'] === 'disetujui'
            ? 'Pengajuan biaya sudah disetujui dan masuk rekap.'
            : 'Pengajuan biaya sudah ditolak.';

        return back()->with('status', $message);
    }

    public function rekap(): RedirectResponse
    {
        // Alamat lama tetap diarahkan agar bookmark admin tidak berakhir di halaman 404.
        return redirect()->route('admin.biaya.index');
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'jadwal_id' => ['required', 'exists:jadwal,id'],
            'jenis_biaya' => ['required', 'string', 'max:100'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'tanggal_biaya' => ['required', 'date'],
        ], [
            'jadwal_id.required' => 'Jadwal terkait wajib dipilih.',
            'jenis_biaya.required' => 'Jenis biaya wajib diisi.',
            'nominal.required' => 'Nominal biaya wajib diisi.',
            'nominal.min' => 'Nominal biaya tidak boleh negatif.',
            'tanggal_biaya.required' => 'Tanggal biaya wajib diisi.',
        ]);
    }
}
