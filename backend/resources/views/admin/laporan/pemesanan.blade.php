@extends('admin.layouts.app')

@section('title', 'Laporan Pemesanan')
@section('page-title', 'Laporan Pemesanan')
@section('page-description', 'Pantau pemesanan berdasarkan periode.')

@section('content')
    <section aria-labelledby="booking-report-summary-title">
        <div class="travs-panel-title mb-3">
            <h3 id="booking-report-summary-title">Ringkasan Laporan</h3>
            <p><strong>Periode Aktif:</strong> {{ $activePeriodLabel }}.</p>
        </div>

        <div class="travs-summary-grid travs-summary-grid-auto">
            <article class="travs-summary-card card">
                <small>Total Pemesanan</small>
                <strong>{{ number_format($summary['total_booking'], 0, ',', '.') }}</strong>
                <span>Seluruh pemesanan sesuai filter aktif.</span>
            </article>
            <article class="travs-summary-card card">
                <small>Terbayar</small>
                <strong>{{ number_format($summary['terbayar'], 0, ',', '.') }}</strong>
                <span>Pemesanan yang sudah memiliki pembayaran berhasil.</span>
            </article>
            <article class="travs-summary-card card">
                <small>Dibatalkan</small>
                <strong>{{ number_format($summary['dibatalkan'], 0, ',', '.') }}</strong>
                <span>Pemesanan berstatus dibatalkan.</span>
            </article>
            <article class="travs-summary-card card">
                <small>Selesai</small>
                <strong>{{ number_format($summary['selesai'], 0, ',', '.') }}</strong>
                <span>Perjalanan pemesanan yang telah selesai.</span>
            </article>
            <article class="travs-summary-card card">
                <small>Total Nilai Pemesanan</small>
                <strong>Rp{{ number_format($summary['total_nilai'], 0, ',', '.') }}</strong>
                <span>Nilai historis pemesanan sesuai filter aktif.</span>
            </article>
        </div>
    </section>

    <article class="travs-section-card card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between">
            <div class="travs-panel-title">
                <h3>Filter Laporan</h3>
                <p>Pilih status dan rentang tanggal untuk menampilkan laporan pemesanan yang dibutuhkan.</p>
            </div>

            <details class="travs-export-menu">
                <summary class="travs-button btn btn-primary">Export Data</summary>
                <div class="travs-export-menu__list">
                    <button type="submit" form="booking-report-filter" formaction="{{ route('admin.laporan.pemesanan.export') }}" name="format" value="xls">Export Excel</button>
                    <button type="submit" form="booking-report-filter" formaction="{{ route('admin.laporan.pemesanan.export') }}" name="format" value="csv">Export CSV</button>
                </div>
            </details>
        </div>

        <form id="booking-report-filter" method="GET" class="travs-filter-grid d-grid">
            <div class="travs-field">
                <label for="status" class="form-label">Status Pemesanan</label>
                <select id="status" name="status" class="travs-select @error('status') is-invalid @enderror form-select">
                    <option value="">Semua status</option>
                    @foreach (['menunggu_pembayaran' => 'Menunggu Pembayaran', 'terbayar' => 'Terbayar', 'dibatalkan' => 'Dibatalkan', 'selesai' => 'Selesai'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @include('admin.partials.field-error', ['name' => 'status'])
            </div>
            <div class="travs-field">
                <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                <input id="tanggal_mulai" name="tanggal_mulai" type="date" class="travs-input @error('tanggal_mulai') is-invalid @enderror form-control" value="{{ $filters['tanggal_mulai'] ?? '' }}">
                @include('admin.partials.field-error', ['name' => 'tanggal_mulai'])
            </div>
            <div class="travs-field">
                <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                <input id="tanggal_selesai" name="tanggal_selesai" type="date" class="travs-input @error('tanggal_selesai') is-invalid @enderror form-control" value="{{ $filters['tanggal_selesai'] ?? '' }}">
                @include('admin.partials.field-error', ['name' => 'tanggal_selesai'])
            </div>
            <div class="travs-filter-actions d-flex align-items-end flex-wrap">
                <button type="submit" class="travs-button btn btn-primary">Terapkan Filter</button>
                <a href="{{ route('admin.laporan.pemesanan') }}" class="travs-button-secondary btn btn-outline-secondary" data-clear-form-draft>Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap table-responsive">
            <table class="travs-table table mb-0">
                <thead>
                    <tr>
                        <th>Kode Pemesanan</th>
                        <th>Penumpang</th>
                        <th>Rute</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Kursi</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        <tr>
                            <td>{{ $booking->kode_booking }}</td>
                            <td>{{ $booking->pengguna->nama }}</td>
                            <td>{{ $booking->jadwal->rute->kota_asal }} - {{ $booking->jadwal->rute->kota_tujuan }}</td>
                            <td class="text-center">
                                <span class="travs-status-pill travs-status-{{ $booking->status_pemesanan }} badge rounded-pill">
                                    {{ ucwords(str_replace('_', ' ', $booking->status_pemesanan)) }}
                                </span>
                            </td>
                            <td class="text-center">{{ number_format($booking->detail_pemesanan_count) }}</td>
                            <td class="text-end text-nowrap">Rp{{ number_format($booking->total_harga, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="travs-table-empty">Tidak ada pemesanan yang sesuai dengan filter yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $bookings->links() }}
    </article>
@endsection
