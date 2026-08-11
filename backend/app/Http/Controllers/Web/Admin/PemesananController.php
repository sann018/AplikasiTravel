<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailPemesanan;
use App\Models\ItemBagasi;
use App\Models\KursiJadwal;
use App\Models\Notifikasi;
use App\Models\Pemesanan;
use App\Models\TiketElektronik;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PemesananController extends Controller
{
    public function __construct(private readonly SettingService $settingService) {}

    public function index(Request $request): View
    {
        $tanggalSelesaiRules = ['nullable', 'date'];
        if ($request->filled('tanggal_mulai')) {
            $tanggalSelesaiRules[] = 'after_or_equal:tanggal_mulai';
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['menunggu_pembayaran', 'terbayar', 'dibatalkan', 'selesai'])],
            'payment_status' => ['nullable', Rule::in(['menunggu', 'berhasil', 'gagal', 'kedaluwarsa'])],
            'source' => ['nullable', Rule::in(['aplikasi_penumpang', 'loket'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => $tanggalSelesaiRules,
        ], [
            'tanggal_mulai.date' => 'Tanggal mulai tidak valid.',
            'tanggal_selesai.date' => 'Tanggal selesai tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ]);

        $query = Pemesanan::query()
            ->with(['pengguna', 'dibuatOleh', 'jadwal.rute', 'pembayaran'])
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
                    ->orWhere('nama_pemesan', 'like', "%{$search}%")
                    ->orWhere('no_hp_pemesan', 'like', "%{$search}%")
                    ->orWhereHas('dibuatOleh', fn ($petugasQuery) => $petugasQuery->where('nama', 'like', "%{$search}%"))
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

        if (filled($filters['source'] ?? null)) {
            $query->where('sumber_pemesanan', $filters['source']);
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
            'dibuatOleh',
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

            if ($this->isGatewayPayment($lockedPemesanan)) {
                // Status metode digital mengikuti gateway agar data pembayaran,
                // tiket, dan laporan tidak berubah karena update manual admin.
                throw ValidationException::withMessages([
                    'status_pemesanan' => 'Status pemesanan dengan pembayaran digital tidak dapat diubah manual. Silakan gunakan detail pembayaran atau sinkronisasi sistem.',
                ]);
            }

            if (
                $newStatus === 'terbayar'
                && $lockedPemesanan->pembayaran?->metode_bayar === 'cash'
                && $lockedPemesanan->pembayaran?->status_bayar === 'menunggu'
            ) {
                throw ValidationException::withMessages([
                    'status_pemesanan' => 'Gunakan tombol Konfirmasi Pembayaran Tunai agar pembayaran, tiket, dan notifikasi ikut diperbarui.',
                ]);
            }

            if (
                $newStatus === 'terbayar'
                && $lockedPemesanan->pembayaran
                && $lockedPemesanan->pembayaran->status_bayar !== 'berhasil'
                && $lockedPemesanan->pembayaran->metode_bayar !== 'cash'
            ) {
                // Pembayaran digital harus mengikuti gateway supaya laporan,
                // kursi, dan tiket tidak berbeda dengan status transaksi asli.
                throw ValidationException::withMessages([
                    'status_pemesanan' => 'Status pembayaran digital tidak dapat diubah manual. Silakan sinkronkan status pembayaran.',
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
                        // Pemesanan loket memakai admin pencatat sebagai pemilik kunci.
                        'dikunci_oleh' => $lockedPemesanan->dibuat_oleh ?: $lockedPemesanan->pengguna_id,
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

    public function destroyOld(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hapus_tanggal_mulai' => ['required', 'date', 'before_or_equal:hapus_tanggal_selesai'],
            'hapus_tanggal_selesai' => ['required', 'date', 'after_or_equal:hapus_tanggal_mulai', 'before:today'],
        ], [
            'hapus_tanggal_mulai.required' => 'Tanggal mulai penghapusan wajib diisi.',
            'hapus_tanggal_mulai.date' => 'Tanggal mulai penghapusan tidak valid.',
            'hapus_tanggal_mulai.before_or_equal' => 'Tanggal mulai harus sama atau sebelum tanggal selesai.',
            'hapus_tanggal_selesai.required' => 'Tanggal selesai penghapusan wajib diisi.',
            'hapus_tanggal_selesai.date' => 'Tanggal selesai penghapusan tidak valid.',
            'hapus_tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'hapus_tanggal_selesai.before' => 'Tanggal selesai harus sebelum hari ini.',
        ]);

        $startDate = $request->date('hapus_tanggal_mulai')?->startOfDay();
        $endDate = $request->date('hapus_tanggal_selesai')?->endOfDay();
        $photoPaths = collect();
        $deletedCount = 0;

        DB::transaction(function () use (
            $startDate,
            $endDate,
            &$photoPaths,
            &$deletedCount,
        ): void {
            // Hanya status terminal yang boleh dihapus agar pemesanan aktif,
            // kursi terkunci, dan transaksi berjalan tidak pernah tersentuh.
            $bookingIds = Pemesanan::query()
                ->whereIn('status_pemesanan', ['selesai', 'dibatalkan'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->lockForUpdate()
                ->pluck('id');

            if ($bookingIds->isEmpty()) {
                return;
            }

            $seatIds = DetailPemesanan::query()
                ->whereIn('pemesanan_id', $bookingIds)
                ->pluck('kursi_jadwal_id');

            $photoPaths = ItemBagasi::query()
                ->whereIn('pemesanan_id', $bookingIds)
                ->get(['foto_url', 'foto_urls'])
                ->flatMap(function (ItemBagasi $item) {
                    $paths = is_array($item->foto_urls) ? $item->foto_urls : [];
                    if (is_string($item->foto_url) && trim($item->foto_url) !== '') {
                        $paths[] = $item->foto_url;
                    }

                    // Hanya path unggahan bagasi internal yang boleh dihapus.
                    return collect($paths)
                        ->filter(fn ($path) => is_string($path))
                        ->map(fn (string $path) => ltrim(trim($path), '/'))
                        ->filter(fn (string $path) => str_starts_with($path, 'baggage/'));
                })
                ->unique()
                ->values();

            // referensi_id tidak memiliki foreign key. Notifikasi pemesanan
            // dibersihkan, sedangkan notifikasi yang menunjuk jadwal dijaga.
            Notifikasi::query()
                ->whereIn('referensi_id', $bookingIds)
                ->whereNotIn('judul', Notifikasi::SCHEDULE_REFERENCE_TITLES)
                ->delete();

            $deletedCount = Pemesanan::query()
                ->whereIn('id', $bookingIds)
                ->delete();

            if ($seatIds->isNotEmpty()) {
                KursiJadwal::query()
                    ->whereIn('id', $seatIds)
                    ->update([
                        'status_kursi' => 'tersedia',
                        'dikunci_oleh' => null,
                        'locked_at' => null,
                        'lock_expires_at' => null,
                        'updated_at' => now(),
                    ]);
            }
        });

        if ($photoPaths->isNotEmpty()) {
            Storage::disk('public')->delete($photoPaths->all());
        }

        Log::notice('Admin membersihkan riwayat pemesanan lama.', [
            'admin_id' => $request->user()->id,
            'tanggal_mulai' => $validated['hapus_tanggal_mulai'],
            'tanggal_selesai' => $validated['hapus_tanggal_selesai'],
            'jumlah_dihapus' => $deletedCount,
        ]);

        $message = $deletedCount > 0
            ? "{$deletedCount} riwayat pemesanan lama berhasil dihapus."
            : 'Tidak ada pemesanan selesai atau dibatalkan pada rentang tanggal tersebut.';

        return redirect()
            ->route('admin.pemesanan.index')
            ->with('status', $message);
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
                'status_pemesanan' => 'Perubahan status tidak sesuai dengan alur pemesanan saat ini.',
            ]);
        }
    }

    private function isGatewayPayment(Pemesanan $pemesanan): bool
    {
        $payment = $pemesanan->pembayaran;

        if (! $payment) {
            return false;
        }

        $manualMethods = ['cash', 'transfer_manual', 'manual_transfer', 'bank_manual'];

        return ! in_array($payment->metode_bayar, $manualMethods, true);
    }

    private function escapeLikeValue(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
