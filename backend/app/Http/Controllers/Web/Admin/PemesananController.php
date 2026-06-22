<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pemesanan;
use App\Models\TiketElektronik;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PemesananController extends Controller
{
    public function __construct(private readonly SettingService $settingService) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['menunggu_pembayaran', 'terbayar', 'dibatalkan', 'selesai'])],
            'payment_status' => ['nullable', Rule::in(['menunggu', 'berhasil', 'gagal', 'kedaluwarsa'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
        ]);

        $query = Pemesanan::query()
            ->with(['pengguna', 'jadwal.rute', 'pembayaran'])
            ->withCount([
                'detailPemesanan',
                'detailPemesanan as penumpang_hadir_count' => fn ($detailQuery) => $detailQuery
                    ->where('status_kehadiran', 'hadir'),
                'detailPemesanan as penumpang_turun_count' => fn ($detailQuery) => $detailQuery
                    ->whereNotNull('diturunkan_pada'),
                'itemBagasi',
            ]);

        if (filled($filters['search'] ?? null)) {
            $search = $this->escapeLikeValue((string) $filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('kode_booking', 'like', "%{$search}%")
                    ->orWhereHas('pengguna', fn ($penggunaQuery) => $penggunaQuery->where('nama', 'like', "%{$search}%"))
                    ->orWhereHas('jadwal.rute', function ($ruteQuery) use ($search) {
                        $ruteQuery
                            ->where('kota_asal', 'like', "%{$search}%")
                            ->orWhere('kota_tujuan', 'like', "%{$search}%");
                    });
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status_pemesanan', $filters['status']);
        }

        if (filled($filters['payment_status'] ?? null)) {
            $query->whereHas('pembayaran', fn ($paymentQuery) => $paymentQuery->where('status_bayar', $filters['payment_status']));
        }

        if (filled($filters['tanggal_mulai'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['tanggal_mulai']);
        }

        if (filled($filters['tanggal_selesai'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['tanggal_selesai']);
        }

        $pemesanan = $query->latest()->paginate(12)->withQueryString();

        return view('admin.pemesanan.index', [
            'pemesanan' => $pemesanan,
            'stats' => [
                'total' => Pemesanan::query()->count(),
                'menunggu' => Pemesanan::query()->where('status_pemesanan', 'menunggu_pembayaran')->count(),
                'terbayar' => Pemesanan::query()->where('status_pemesanan', 'terbayar')->count(),
                'selesai' => Pemesanan::query()->where('status_pemesanan', 'selesai')->count(),
            ],
            'filters' => $filters,
        ]);
    }

    public function show(Pemesanan $pemesanan): View
    {
        $pemesanan->load([
            'pengguna',
            'jadwal.rute',
            'jadwal.kendaraan',
            'jadwal.pengemudi.pengguna',
            'detailPemesanan.kursiJadwal',
            'detailPemesanan.tiketElektronik',
            'itemBagasi',
            'pembayaran',
        ]);

        return view('admin.pemesanan.show', [
            'pemesanan' => $pemesanan,
        ]);
    }

    public function update(Request $request, Pemesanan $pemesanan): RedirectResponse
    {
        $payload = $request->validate([
            'status_pemesanan' => ['required', Rule::in(['menunggu_pembayaran', 'terbayar', 'dibatalkan', 'selesai'])],
        ]);

        DB::transaction(function () use ($pemesanan, $payload) {
            $lockedPemesanan = Pemesanan::query()
                ->lockForUpdate()
                ->with(['detailPemesanan.kursiJadwal', 'detailPemesanan.tiketElektronik', 'pembayaran'])
                ->findOrFail($pemesanan->id);

            $newStatus = $payload['status_pemesanan'];
            $this->ensureValidStatusTransition($lockedPemesanan, $newStatus);

            if ($lockedPemesanan->status_pemesanan === $newStatus) {
                return;
            }

            if (
                $newStatus === 'terbayar'
                && $lockedPemesanan->pembayaran?->metode_bayar === 'cash'
                && $lockedPemesanan->pembayaran?->status_bayar === 'menunggu'
            ) {
                throw ValidationException::withMessages([
                    'status_pemesanan' => 'Gunakan tombol Verifikasi Cash agar audit pembayaran, tiket, dan notifikasi ikut tersinkron.',
                ]);
            }

            $lockedPemesanan->update(['status_pemesanan' => $newStatus]);

            if ($newStatus === 'menunggu_pembayaran') {
                $deadline = $lockedPemesanan->batas_pembayaran ?? now()->addMinutes(
                    (int) $this->settingService->get('seat_lock_payment_minutes', 5),
                );

                foreach ($lockedPemesanan->detailPemesanan as $detail) {
                    $detail->kursiJadwal?->update([
                        'status_kursi' => 'terkunci',
                        'dikunci_oleh' => $lockedPemesanan->pengguna_id,
                        'locked_at' => now(),
                        'lock_expires_at' => $deadline,
                        'updated_at' => now(),
                    ]);
                }

                $lockedPemesanan->pembayaran?->update([
                    'status_bayar' => 'menunggu',
                    'expired_at' => $deadline,
                ]);
            }

            if ($newStatus === 'terbayar') {
                foreach ($lockedPemesanan->detailPemesanan as $detail) {
                    $detail->kursiJadwal?->update([
                        'status_kursi' => 'dipesan',
                        'dikunci_oleh' => null,
                        'locked_at' => null,
                        'lock_expires_at' => null,
                        'updated_at' => now(),
                    ]);

                    TiketElektronik::query()->updateOrCreate(
                        ['detail_pemesanan_id' => $detail->id],
                        [
                            'kode_qr' => 'TRAVS-'.$lockedPemesanan->kode_booking.'-'.$detail->nomor_kursi,
                            'token_unik_qr' => $detail->tiketElektronik?->token_unik_qr ?: Str::uuid()->toString(),
                            'diterbitkan_pada' => $detail->tiketElektronik?->diterbitkan_pada ?: now(),
                        ],
                    );
                }

                $lockedPemesanan->pembayaran?->update([
                    'status_bayar' => 'berhasil',
                    'dibayar_pada' => $lockedPemesanan->pembayaran?->dibayar_pada ?: now(),
                    'webhook_verified_at' => $lockedPemesanan->pembayaran?->webhook_verified_at ?: now(),
                ]);
            }

            if ($newStatus === 'dibatalkan') {
                foreach ($lockedPemesanan->detailPemesanan as $detail) {
                    $detail->kursiJadwal?->update([
                        'status_kursi' => 'tersedia',
                        'dikunci_oleh' => null,
                        'locked_at' => null,
                        'lock_expires_at' => null,
                        'updated_at' => now(),
                    ]);
                }

                if ($lockedPemesanan->pembayaran && $lockedPemesanan->pembayaran->status_bayar === 'menunggu') {
                    $lockedPemesanan->pembayaran->update([
                        'status_bayar' => 'kedaluwarsa',
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.pemesanan.show', $pemesanan)
            ->with('status', 'Status pemesanan berhasil diperbarui.');
    }

    private function ensureValidStatusTransition(Pemesanan $pemesanan, string $newStatus): void
    {
        if ($pemesanan->status_pemesanan === $newStatus) {
            return;
        }

        $allowedTransitions = [
            'menunggu_pembayaran' => ['terbayar', 'dibatalkan'],
            'terbayar' => ['selesai'],
        ];

        if (! in_array($newStatus, $allowedTransitions[$pemesanan->status_pemesanan] ?? [], true)) {
            throw ValidationException::withMessages([
                'status_pemesanan' => 'Perubahan status tidak valid untuk lifecycle pemesanan saat ini.',
            ]);
        }
    }

    private function escapeLikeValue(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
