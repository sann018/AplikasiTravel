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

class StafPelayananController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->where('peran', 'staf_pelayanan');

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

        return view('admin.staf-pelayanan.index', [
            'stafPelayanan' => $query->withCount('pemesananDibuat')->latest()->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('admin.staf-pelayanan.create');
    }

    public function show(User $stafPelayanan): View
    {
        $this->ensureStafPelayanan($stafPelayanan);

        $pemesananQuery = Pemesanan::query()
            ->where('dibuat_oleh', $stafPelayanan->id);

        return view('admin.staf-pelayanan.show', [
            'stafPelayanan' => $stafPelayanan,
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

        $stafPelayanan = User::query()->create([
            'nama' => $payload['nama'],
            'email' => $payload['email'],
            'no_hp' => $payload['no_hp'],
            'peran' => 'staf_pelayanan',
            'status_aktif' => $payload['status'] === 'aktif',
            'email_verified_at' => null,
            // Kata sandi acak mencegah akun dipakai sebelum Staf Pelayanan membuka tautan aktivasi.
            'password' => Hash::make(Str::random(64)),
        ]);

        return $this->redirectWithActivationResult($stafPelayanan);
    }

    public function edit(User $stafPelayanan): View
    {
        $this->ensureStafPelayanan($stafPelayanan);

        return view('admin.staf-pelayanan.edit', ['stafPelayanan' => $stafPelayanan]);
    }

    public function update(Request $request, User $stafPelayanan): RedirectResponse
    {
        $this->ensureStafPelayanan($stafPelayanan);
        $oldEmail = $stafPelayanan->email;
        $payload = $this->validatedPayload($request, $stafPelayanan);
        $emailChanged = $oldEmail !== $payload['email'];

        $stafPelayanan->update([
            'nama' => $payload['nama'],
            'email' => $payload['email'],
            'no_hp' => $payload['no_hp'],
            'status_aktif' => $payload['status'] === 'aktif',
            ...($emailChanged ? ['email_verified_at' => null] : []),
        ]);

        if (! $stafPelayanan->status_aktif || $emailChanged) {
            $stafPelayanan->tokens()->delete();
        }

        if ($emailChanged) {
            // Email baru harus diaktifkan sebelum akun dapat digunakan kembali.
            return $this->redirectWithActivationResult($stafPelayanan);
        }

        return redirect()
            ->route('admin.staf-pelayanan.index')
            ->with('status', 'Data Staf Pelayanan berhasil diperbarui.');
    }

    public function toggleStatus(User $stafPelayanan): RedirectResponse
    {
        $this->ensureStafPelayanan($stafPelayanan);
        $stafPelayanan->update(['status_aktif' => ! $stafPelayanan->status_aktif]);

        if (! $stafPelayanan->status_aktif) {
            // Semua sesi perangkat dicabut saat akun dinonaktifkan.
            $stafPelayanan->tokens()->delete();
        }

        return back()->with(
            'status',
            $stafPelayanan->status_aktif
                ? 'Akun Staf Pelayanan berhasil diaktifkan.'
                : 'Akun Staf Pelayanan berhasil dinonaktifkan.',
        );
    }

    public function sendActivation(User $stafPelayanan): RedirectResponse
    {
        $this->ensureStafPelayanan($stafPelayanan);

        return back()->with($this->sendActivationLink($stafPelayanan));
    }

    /** @return array<string, mixed> */
    private function validatedPayload(Request $request, ?User $stafPelayanan = null): array
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
                Rule::unique('pengguna', 'email')->ignore($stafPelayanan?->id),
            ],
            'no_hp' => [
                'required',
                'string',
                'regex:/^[0-9]{11,12}$/',
                Rule::unique('pengguna', 'no_hp')->ignore($stafPelayanan?->id),
            ],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [
            'nama.required' => 'Nama Staf Pelayanan wajib diisi.',
            'nama.max' => 'Nama Staf Pelayanan maksimal 100 karakter.',
            'nama.regex' => 'Nama Staf Pelayanan hanya boleh berisi huruf dan spasi.',
            'email.required' => 'Email Staf Pelayanan wajib diisi.',
            'email.email' => 'Format email Staf Pelayanan belum valid.',
            'email.unique' => 'Email tersebut sudah digunakan akun lain.',
            'no_hp.required' => 'Nomor HP Staf Pelayanan wajib diisi.',
            'no_hp.regex' => 'Nomor HP harus terdiri dari 11 sampai 12 digit angka.',
            'no_hp.unique' => 'Nomor HP tersebut sudah digunakan akun lain.',
            'status.required' => 'Status akun wajib dipilih.',
            'status.in' => 'Status akun yang dipilih tidak valid.',
        ]);
    }

    private function ensureStafPelayanan(User $stafPelayanan): void
    {
        abort_unless($stafPelayanan->isStafPelayanan(), 404);
    }

    /** @return array<string, string> */
    private function sendActivationLink(User $stafPelayanan): array
    {
        $result = Password::sendResetLink(['email' => $stafPelayanan->email]);

        if ($result === Password::RESET_LINK_SENT) {
            return ['status' => 'Akun Staf Pelayanan berhasil disimpan dan tautan aktivasi telah dikirim.'];
        }

        return ['error' => 'Akun berhasil disimpan, tetapi tautan aktivasi belum terkirim. Gunakan aksi Kirim Aktivasi untuk mencoba kembali.'];
    }

    private function redirectWithActivationResult(User $stafPelayanan): RedirectResponse
    {
        $feedback = $this->sendActivationLink($stafPelayanan);
        $messageType = array_key_first($feedback) ?? 'status';

        return redirect()
            ->route('admin.staf-pelayanan.index')
            ->with($messageType, $feedback[$messageType] ?? 'Data Staf Pelayanan berhasil disimpan.');
    }
}
