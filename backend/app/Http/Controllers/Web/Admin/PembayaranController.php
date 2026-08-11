<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Services\BookingService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PembayaranController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function index(Request $request): View
    {
        $tanggalSelesaiRules = ['nullable', 'date'];
        if ($request->filled('tanggal_mulai')) {
            $tanggalSelesaiRules[] = 'after_or_equal:tanggal_mulai';
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'metode_bayar' => ['nullable', Rule::in(array_keys(PaymentMethodCatalog::adminOptions()))],
            'status_bayar' => ['nullable', Rule::in(['menunggu', 'berhasil', 'gagal', 'kedaluwarsa'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => $tanggalSelesaiRules,
        ], [
            'tanggal_mulai.date' => 'Tanggal mulai tidak valid.',
            'tanggal_selesai.date' => 'Tanggal selesai tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ]);

        $query = Pembayaran::query()
            ->with(['pemesanan.pengguna', 'pemesanan.dibuatOleh', 'pemesanan.jadwal.rute']);

        if (filled($filters['search'] ?? null)) {
            $search = $this->escapeLikeValue((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('order_id_gateway', 'like', "%{$search}%")
                    ->orWhere('gateway_reference', 'like', "%{$search}%")
                    ->orWhereHas('pemesanan', function (Builder $bookingQuery) use ($search) {
                        $bookingQuery
                            ->where('kode_booking', 'like', "%{$search}%")
                            ->orWhere('nama_pemesan', 'like', "%{$search}%")
                            ->orWhere('no_hp_pemesan', 'like', "%{$search}%")
                            ->orWhereHas('pengguna', fn (Builder $userQuery) => $userQuery->where('nama', 'like', "%{$search}%"))
                            ->orWhereHas('jadwal.rute', function (Builder $routeQuery) use ($search) {
                                $routeQuery
                                    ->where('kota_asal', 'like', "%{$search}%")
                                    ->orWhere('kota_tujuan', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if (filled($filters['metode_bayar'] ?? null)) {
            $query->where('metode_bayar', $filters['metode_bayar']);
        }

        if (filled($filters['status_bayar'] ?? null)) {
            $query->where('status_bayar', $filters['status_bayar']);
        }

        if (filled($filters['tanggal_mulai'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['tanggal_mulai']);
        }

        if (filled($filters['tanggal_selesai'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['tanggal_selesai']);
        }

        $payments = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $payments->getCollection()->transform(function (Pembayaran $payment) {
            $payment->issue_label = $this->paymentIssueLabel($payment);
            $payment->can_confirm_cash = $this->canConfirmCash($payment);

            return $payment;
        });

        return view('admin.pembayaran.index', [
            'payments' => $payments,
            'summary' => $this->summary(),
            'filters' => $filters,
        ]);
    }

    public function show(Pembayaran $pembayaran): View
    {
        $pembayaran->load([
            'pemesanan.pengguna',
            'pemesanan.dibuatOleh',
            'pemesanan.jadwal.rute',
        ]);

        $pembayaran->issue_label = $this->paymentIssueLabel($pembayaran);
        $pembayaran->can_confirm_cash = $this->canConfirmCash($pembayaran);

        return view('admin.pembayaran.show', [
            'payment' => $pembayaran,
        ]);
    }

    public function confirmCash(Request $request, Pemesanan $pemesanan): RedirectResponse
    {
        $booking = $this->bookingService->confirmCashPayment($pemesanan, $request->user());

        return redirect()
            ->route('admin.pembayaran.index')
            ->with('status', 'Pembayaran tunai '.$booking->kode_booking.' berhasil diverifikasi dan tiket sudah diterbitkan.');
    }

    private function summary(): array
    {
        return [
            'total' => Pembayaran::query()->count(),
            'menunggu' => Pembayaran::query()->where('status_bayar', 'menunggu')->count(),
            'cash_pending' => Pembayaran::query()
                ->where('metode_bayar', 'cash')
                ->where('status_bayar', 'menunggu')
                ->whereHas('pemesanan', fn (Builder $query) => $query->where('status_pemesanan', 'menunggu_pembayaran'))
                ->count(),
            'exceptions' => $this->applyExceptionFilter(
                Pembayaran::query(),
                'perlu_tindak_lanjut',
            )->count(),
        ];
    }

    private function applyExceptionFilter(Builder $query, string $exception): Builder
    {
        $now = now();

        return match ($exception) {
            'perlu_verifikasi_cash' => $query
                ->where('metode_bayar', 'cash')
                ->where('status_bayar', 'menunggu')
                ->whereHas('pemesanan', fn (Builder $bookingQuery) => $bookingQuery->where('status_pemesanan', 'menunggu_pembayaran')),
            'gateway_gagal' => $query->where('status_bayar', 'gagal'),
            'kedaluwarsa' => $query->where(function (Builder $builder) use ($now) {
                $builder
                    ->where('status_bayar', 'kedaluwarsa')
                    ->orWhere(function (Builder $expiredPendingQuery) use ($now) {
                        $expiredPendingQuery
                            ->where('status_bayar', 'menunggu')
                            ->whereNotNull('expired_at')
                            ->where('expired_at', '<=', $now);
                    });
            }),
            'status_tidak_sinkron' => $this->applyMismatchFilter($query),
            default => $query->where(function (Builder $builder) use ($now) {
                $builder
                    ->where(function (Builder $cashQuery) {
                        $cashQuery
                            ->where('metode_bayar', 'cash')
                            ->where('status_bayar', 'menunggu')
                            ->whereHas('pemesanan', fn (Builder $bookingQuery) => $bookingQuery->where('status_pemesanan', 'menunggu_pembayaran'));
                    })
                    ->orWhereIn('status_bayar', ['gagal', 'kedaluwarsa'])
                    ->orWhere(function (Builder $expiredPendingQuery) use ($now) {
                        $expiredPendingQuery
                            ->where('status_bayar', 'menunggu')
                            ->whereNotNull('expired_at')
                            ->where('expired_at', '<=', $now);
                    })
                    ->orWhere(function (Builder $mismatchQuery) {
                        $this->applyMismatchFilter($mismatchQuery);
                    });
            }),
        };
    }

    private function applyMismatchFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder
                ->where(function (Builder $successButBookingPending) {
                    $successButBookingPending
                        ->where('status_bayar', 'berhasil')
                        ->whereHas('pemesanan', fn (Builder $bookingQuery) => $bookingQuery->whereNotIn('status_pemesanan', ['terbayar', 'selesai']));
                })
                ->orWhere(function (Builder $bookingPaidButPaymentPending) {
                    $bookingPaidButPaymentPending
                        ->where('status_bayar', '!=', 'berhasil')
                        ->whereHas('pemesanan', fn (Builder $bookingQuery) => $bookingQuery->whereIn('status_pemesanan', ['terbayar', 'selesai']));
                });
        });
    }

    private function paymentIssueLabel(Pembayaran $payment): string
    {
        $bookingStatus = $payment->pemesanan?->status_pemesanan;

        if ($this->canConfirmCash($payment)) {
            return 'Perlu verifikasi cash';
        }

        if ($payment->status_bayar === 'gagal') {
            return 'Gateway gagal';
        }

        if (
            $payment->status_bayar === 'kedaluwarsa'
            || (
                $payment->status_bayar === 'menunggu'
                && $payment->expired_at !== null
                && $payment->expired_at->isPast()
            )
        ) {
            return 'Kedaluwarsa';
        }

        if (
            ($payment->status_bayar === 'berhasil' && ! in_array($bookingStatus, ['terbayar', 'selesai'], true))
            || ($payment->status_bayar !== 'berhasil' && in_array($bookingStatus, ['terbayar', 'selesai'], true))
        ) {
            return 'Status tidak sinkron';
        }

        return 'Normal';
    }

    private function canConfirmCash(Pembayaran $payment): bool
    {
        return $payment->metode_bayar === 'cash'
            && $payment->status_bayar === 'menunggu'
            && $payment->pemesanan?->status_pemesanan === 'menunggu_pembayaran';
    }

    private function escapeLikeValue(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
