<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengemudi;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PengemudiController extends Controller
{
    public function index(Request $request): View
    {
        $query = Pengemudi::query()
            ->with(['pengguna', 'verifikator'])
            ->withCount('jadwal');

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('nomor_sim', 'like', "%{$search}%")
                    ->orWhereHas('pengguna', function ($penggunaQuery) use ($search) {
                        $penggunaQuery
                            ->where('nama', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('no_hp', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('status_verifikasi')) {
            $query->where('status_verifikasi', $request->string('status_verifikasi'));
        }

        $pengemudi = $query->latest()->paginate(10)->withQueryString();
        $editingPengemudi = $request->filled('edit')
            ? Pengemudi::query()->with('pengguna')->findOrFail($request->integer('edit'))
            : null;

        return view('admin.pengemudi.index', [
            'pengemudi' => $pengemudi,
            'stats' => [
                'total' => Pengemudi::query()->count(),
                'aktif' => Pengemudi::query()->where('status', 'aktif')->count(),
                'nonaktif' => Pengemudi::query()->where('status', 'nonaktif')->count(),
                'terverifikasi' => Pengemudi::query()->where('status_verifikasi', 'terverifikasi')->count(),
                'menunggu' => Pengemudi::query()->where('status_verifikasi', 'menunggu')->count(),
            ],
            'filters' => $request->only(['search', 'status', 'status_verifikasi']),
        ]);
    }

    public function create(): View
    {
        return view('admin.pengemudi.create');
    }

    public function edit(Pengemudi $pengemudi): View
    {
        $pengemudi->load('pengguna');

        return view('admin.pengemudi.edit', [
            'pengemudi' => $pengemudi,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request);
        $normalizedStatus = $this->normalizeStatus($payload);
        $verificationAttributes = $this->verificationAttributes($payload, null, $request->user()->id);

        DB::transaction(function () use ($request, $payload, $normalizedStatus, $verificationAttributes) {
            $profilePhotoPath = $this->storeUploadedImage($request, 'foto_profil', 'profiles');
            $simPhotoPath = $this->storeUploadedImage($request, 'foto_sim', 'driver-documents');

            $user = User::query()->create([
                'nama' => $payload['nama'],
                'email' => $payload['email'],
                'no_hp' => $payload['no_hp'],
                'peran' => 'pengemudi',
                'status_aktif' => $normalizedStatus['status_aktif'],
                'foto_profil' => $profilePhotoPath,
                'email_verified_at' => now(),
                'password' => Hash::make($payload['password']),
            ]);

            Pengemudi::query()->create([
                'pengguna_id' => $user->id,
                'nomor_sim' => $payload['nomor_sim'],
                'foto_sim' => $simPhotoPath,
                'status' => $normalizedStatus['status'],
                ...$verificationAttributes,
            ]);
        });

        return redirect()
            ->route('admin.pengemudi.index')
            ->with('status', $normalizedStatus['forced_nonactive']
                ? 'Akun pengemudi berhasil ditambahkan, tetapi tetap nonaktif sampai dokumen diverifikasi.'
                : 'Akun pengemudi berhasil ditambahkan.');
    }

    public function update(Request $request, Pengemudi $pengemudi): RedirectResponse
    {
        $payload = $this->validatedPayload($request, $pengemudi);
        $normalizedStatus = $this->normalizeStatus($payload);
        $verificationAttributes = $this->verificationAttributes($payload, $pengemudi, $request->user()->id);

        DB::transaction(function () use ($request, $pengemudi, $payload, $normalizedStatus, $verificationAttributes) {
            $oldProfilePhotoPath = $pengemudi->pengguna->foto_profil;
            $oldSimPhotoPath = $pengemudi->foto_sim;
            $profilePhotoPath = $this->storeUploadedImage($request, 'foto_profil', 'profiles');
            $simPhotoPath = $this->storeUploadedImage($request, 'foto_sim', 'driver-documents');

            $pengemudi->pengguna->update([
                'nama' => $payload['nama'],
                'email' => $payload['email'],
                'no_hp' => $payload['no_hp'],
                'status_aktif' => $normalizedStatus['status_aktif'],
                ...($profilePhotoPath ? ['foto_profil' => $profilePhotoPath] : []),
                ...($payload['password']
                    ? ['password' => Hash::make($payload['password'])]
                    : []),
            ]);

            $pengemudi->update([
                'nomor_sim' => $payload['nomor_sim'],
                ...($simPhotoPath ? ['foto_sim' => $simPhotoPath] : []),
                'status' => $normalizedStatus['status'],
                ...$verificationAttributes,
            ]);

            if ($profilePhotoPath) {
                $this->deletePublicFile($oldProfilePhotoPath);
            }

            if ($simPhotoPath) {
                $this->deletePublicFile($oldSimPhotoPath);
            }
        });

        return redirect()
            ->route('admin.pengemudi.index')
            ->with('status', $normalizedStatus['forced_nonactive']
                ? 'Data pengemudi berhasil diperbarui. Akun tetap nonaktif sampai status verifikasi terkonfirmasi.'
                : 'Data pengemudi berhasil diperbarui.');
    }

    public function destroy(Pengemudi $pengemudi): RedirectResponse
    {
        try {
            $pengemudi->pengguna()->delete();
        } catch (QueryException) {
            return back()->with('error', 'Pengemudi tidak dapat dihapus karena masih terhubung dengan jadwal perjalanan.');
        }

        return back()->with('status', 'Data pengemudi berhasil dihapus.');
    }

    private function validatedPayload(Request $request, ?Pengemudi $pengemudi = null): array
    {
        $user = $pengemudi?->pengguna;

        return $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:100',
                Rule::unique('pengguna', 'email')->ignore($user?->id),
            ],
            'no_hp' => [
                'required',
                'string',
                'min:10',
                'max:16',
                'regex:/^(?:\+62|62|0)8[1-9][0-9]{7,10}$/',
                Rule::unique('pengguna', 'no_hp')->ignore($user?->id),
            ],
            'nomor_sim' => [
                'required',
                'string',
                'min:12',
                'max:16',
                'regex:/^[0-9]{12,16}$/',
                Rule::unique('pengemudi', 'nomor_sim')->ignore($pengemudi?->id),
            ],
            'foto_sim' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'status_verifikasi' => ['required', Rule::in(['menunggu', 'terverifikasi', 'ditolak'])],
            'catatan_verifikasi' => ['nullable', 'string', 'max:1000'],
            'password' => [$pengemudi ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'foto_profil' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
    }

    private function storeUploadedImage(Request $request, string $fieldName, string $folder): ?string
    {
        if (! $request->hasFile($fieldName)) {
            return null;
        }

        return $request->file($fieldName)->store($folder, 'public');
    }

    private function deletePublicFile(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function normalizeStatus(array $payload): array
    {
        $isVerified = $payload['status_verifikasi'] === 'terverifikasi';
        $status = $isVerified ? $payload['status'] : 'nonaktif';

        return [
            'status' => $status,
            'status_aktif' => $isVerified && $status === 'aktif',
            'forced_nonactive' => ! $isVerified && $payload['status'] === 'aktif',
        ];
    }

    private function verificationAttributes(array $payload, ?Pengemudi $pengemudi, int $adminId): array
    {
        $statusVerifikasi = $payload['status_verifikasi'];
        $catatan = filled($payload['catatan_verifikasi'] ?? null)
            ? $payload['catatan_verifikasi']
            : null;

        return match ($statusVerifikasi) {
            'terverifikasi' => [
                'status_verifikasi' => 'terverifikasi',
                'catatan_verifikasi' => $catatan,
                'verified_at' => $pengemudi?->verified_at ?? now(),
                'verified_by' => $adminId,
            ],
            'ditolak' => [
                'status_verifikasi' => 'ditolak',
                'catatan_verifikasi' => $catatan ?: 'Dokumen pengemudi perlu diperbarui sebelum bisa diaktifkan.',
                'verified_at' => null,
                'verified_by' => $adminId,
            ],
            default => [
                'status_verifikasi' => 'menunggu',
                'catatan_verifikasi' => $catatan,
                'verified_at' => null,
                'verified_by' => null,
            ],
        };
    }
}
