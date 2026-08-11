<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pemesanan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PetugasLoketController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->where('peran', 'petugas_loket');

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('nama', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status_aktif', $request->string('status')->toString() === 'aktif');
        }

        return view('admin.petugas-loket.index', [
            'petugas' => $query->withCount('pemesananDibuat')->latest()->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('admin.petugas-loket.create');
    }

    public function show(User $petugas): View
    {
        $this->ensureCounterStaff($petugas);

        $pemesananQuery = Pemesanan::query()
            ->where('dibuat_oleh', $petugas->id);

        return view('admin.petugas-loket.show', [
            'petugas' => $petugas,
            'statistik' => [
                'total_pemesanan' => (clone $pemesananQuery)->count(),
                'total_kursi' => (int) (clone $pemesananQuery)->sum('jumlah_kursi'),
                'menunggu_pembayaran' => (clone $pemesananQuery)
                    ->where('status_pemesanan', 'menunggu_pembayaran')
                    ->count(),
                'berhasil' => (clone $pemesananQuery)
                    ->whereIn('status_pemesanan', ['terbayar', 'selesai'])
                    ->count(),
            ],
            'pemesananTerbaru' => (clone $pemesananQuery)
                ->with(['jadwal.rute', 'pembayaran'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request);

        $petugas = User::query()->create([
            'nama' => $payload['nama'],
            'email' => $payload['email'],
            'no_hp' => $payload['no_hp'],
            'peran' => 'petugas_loket',
            'status_aktif' => $payload['status'] === 'aktif',
            'email_verified_at' => null,
            // Kata sandi acak mencegah akun dipakai sebelum petugas membuka tautan aktivasi.
            'password' => Hash::make(Str::random(64)),
        ]);

        return redirect()
            ->route('admin.petugas-loket.index')
            ->with($this->sendActivationLink($petugas));
    }

    public function edit(User $petugas): View
    {
        $this->ensureCounterStaff($petugas);

        return view('admin.petugas-loket.edit', ['petugas' => $petugas]);
    }

    public function update(Request $request, User $petugas): RedirectResponse
    {
        $this->ensureCounterStaff($petugas);
        $oldEmail = $petugas->email;
        $payload = $this->validatedPayload($request, $petugas);

        $petugas->update([
            'nama' => $payload['nama'],
            'email' => $payload['email'],
            'no_hp' => $payload['no_hp'],
            'status_aktif' => $payload['status'] === 'aktif',
            ...($oldEmail !== $payload['email'] ? ['email_verified_at' => null] : []),
        ]);

        if (! $petugas->status_aktif || $oldEmail !== $payload['email']) {
            $petugas->tokens()->delete();
        }

        return redirect()
            ->route('admin.petugas-loket.index')
            ->with('status', 'Data Petugas Loket berhasil diperbarui.');
    }

    public function toggleStatus(User $petugas): RedirectResponse
    {
        $this->ensureCounterStaff($petugas);
        $petugas->update(['status_aktif' => ! $petugas->status_aktif]);

        if (! $petugas->status_aktif) {
            // Semua sesi perangkat dicabut saat akun dinonaktifkan.
            $petugas->tokens()->delete();
        }

        return back()->with(
            'status',
            $petugas->status_aktif
                ? 'Akun Petugas Loket berhasil diaktifkan.'
                : 'Akun Petugas Loket berhasil dinonaktifkan.',
        );
    }

    public function sendActivation(User $petugas): RedirectResponse
    {
        $this->ensureCounterStaff($petugas);

        return back()->with($this->sendActivationLink($petugas));
    }

    /** @return array<string, mixed> */
    private function validatedPayload(Request $request, ?User $petugas = null): array
    {
        $request->merge([
            'nama' => trim((string) $request->input('nama')),
            'email' => Str::lower(trim((string) $request->input('email'))),
            'no_hp' => trim((string) $request->input('no_hp')),
        ]);

        return $request->validate([
            'nama' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z ]+$/'],
            'email' => [
                'required',
                'email:rfc',
                'max:100',
                Rule::unique('pengguna', 'email')->ignore($petugas?->id),
            ],
            'no_hp' => [
                'required',
                'string',
                'regex:/^[0-9]{11,12}$/',
                Rule::unique('pengguna', 'no_hp')->ignore($petugas?->id),
            ],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [
            'nama.required' => 'Nama Petugas Loket wajib diisi.',
            'nama.max' => 'Nama Petugas Loket maksimal 100 karakter.',
            'nama.regex' => 'Nama Petugas Loket hanya boleh berisi huruf dan spasi.',
            'email.required' => 'Email Petugas Loket wajib diisi.',
            'email.email' => 'Format email Petugas Loket belum valid.',
            'email.unique' => 'Email tersebut sudah digunakan akun lain.',
            'no_hp.required' => 'Nomor HP Petugas Loket wajib diisi.',
            'no_hp.regex' => 'Nomor HP harus terdiri dari 11 sampai 12 digit angka.',
            'no_hp.unique' => 'Nomor HP tersebut sudah digunakan akun lain.',
            'status.required' => 'Status akun wajib dipilih.',
            'status.in' => 'Status akun yang dipilih tidak valid.',
        ]);
    }

    private function ensureCounterStaff(User $petugas): void
    {
        abort_unless($petugas->isPetugasLoket(), 404);
    }

    /** @return array<string, string> */
    private function sendActivationLink(User $petugas): array
    {
        $result = Password::sendResetLink(['email' => $petugas->email]);

        if ($result === Password::RESET_LINK_SENT) {
            return ['status' => 'Akun Petugas Loket berhasil disimpan dan tautan aktivasi telah dikirim.'];
        }

        return ['error' => 'Akun berhasil disimpan, tetapi tautan aktivasi belum terkirim. Gunakan aksi Kirim Aktivasi untuk mencoba kembali.'];
    }
}
