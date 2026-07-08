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
            ->with('pengguna')
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
            ],
            'filters' => $request->only(['search', 'status']),
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

        DB::transaction(function () use ($request, $payload, $normalizedStatus) {
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
                ...$this->adminManagedVerificationAttributes($request->user()->id),
            ]);
        });

        return redirect()
            ->route('admin.pengemudi.index')
            ->with('status', 'Akun pengemudi berhasil ditambahkan.');
    }

    public function update(Request $request, Pengemudi $pengemudi): RedirectResponse
    {
        $payload = $this->validatedPayload($request, $pengemudi);
        $normalizedStatus = $this->normalizeStatus($payload);

        DB::transaction(function () use ($request, $pengemudi, $payload, $normalizedStatus) {
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
                ...$this->adminManagedVerificationAttributes($request->user()->id, $pengemudi),
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
            ->with('status', 'Data pengemudi berhasil diperbarui.');
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
                'size:16',
                'regex:/^[0-9]{16}$/',
                Rule::unique('pengemudi', 'nomor_sim')->ignore($pengemudi?->id),
            ],
            'foto_sim' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'password' => [$pengemudi ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'foto_profil' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'nama.required' => 'Nama pengemudi wajib diisi.',
            'nama.max' => 'Nama pengemudi maksimal 100 karakter.',
            'email.required' => 'Email pengemudi wajib diisi.',
            'email.email' => 'Format email pengemudi belum valid.',
            'email.max' => 'Email pengemudi maksimal 100 karakter.',
            'email.unique' => 'Email ini sudah digunakan akun lain. Gunakan email berbeda.',
            'no_hp.required' => 'Nomor HP pengemudi wajib diisi.',
            'no_hp.min' => 'Nomor HP terlalu pendek.',
            'no_hp.max' => 'Nomor HP maksimal 16 karakter.',
            'no_hp.regex' => 'Nomor HP harus memakai format Indonesia, contoh 081234567890.',
            'no_hp.unique' => 'Nomor HP ini sudah digunakan akun lain.',
            'nomor_sim.required' => 'Nomor SIM/NIK KTP wajib diisi.',
            'nomor_sim.size' => 'Nomor SIM harus menggunakan NIK KTP yang terdiri dari 16 digit.',
            'nomor_sim.regex' => 'Nomor SIM hanya boleh berisi angka.',
            'nomor_sim.unique' => 'NIK KTP tersebut sudah dipakai pengemudi lain.',
            'foto_sim.image' => 'Foto SIM harus berupa file gambar.',
            'foto_sim.mimes' => 'Foto SIM hanya menerima format JPG, JPEG, PNG, atau WEBP.',
            'foto_sim.max' => 'Ukuran foto SIM maksimal 2 MB.',
            'status.required' => 'Status akun wajib dipilih.',
            'status.in' => 'Status akun yang dipilih tidak valid.',
            'password.required' => 'Password awal wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.max' => 'Password maksimal 100 karakter.',
            'foto_profil.image' => 'Foto profil harus berupa file gambar.',
            'foto_profil.mimes' => 'Foto profil hanya menerima format JPG, JPEG, PNG, atau WEBP.',
            'foto_profil.max' => 'Ukuran foto profil maksimal 2 MB.',
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
        $status = $payload['status'];

        return [
            'status' => $status,
            'status_aktif' => $status === 'aktif',
        ];
    }

    private function adminManagedVerificationAttributes(int $adminId, ?Pengemudi $pengemudi = null): array
    {
        return [
            // Akun pengemudi dibuat langsung oleh admin, jadi kolom legacy verifikasi
            // diisi otomatis agar validasi penjadwalan lama tetap kompatibel.
            'status_verifikasi' => 'terverifikasi',
            'catatan_verifikasi' => null,
            'verified_at' => $pengemudi?->verified_at ?? now(),
            'verified_by' => $adminId,
        ];
    }
}
