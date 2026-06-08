@extends('admin.layouts.app')

@section('title', 'Rekap Biaya')
@section('page-title', 'Rekap Biaya Operasional')
@section('page-description', 'Lihat akumulasi biaya per rute dan detail pengeluaran untuk mendukung evaluasi operasional travel.')

@section('content')
    <article class="travs-section-card travs-rekap-filter-card">
        <div class="travs-panel-title">
            <h3>Filter Rekap</h3>
            <p>Pilih rentang tanggal agar rekap biaya lebih fokus sesuai periode evaluasi.</p>
        </div>

        <form method="GET" class="travs-filter-grid">
            <div class="travs-field">
                <label for="tanggal_mulai">Tanggal Mulai</label>
                <input id="tanggal_mulai" name="tanggal_mulai" type="date" class="travs-input" value="{{ $filters['tanggal_mulai'] ?? '' }}">
            </div>
            <div class="travs-field">
                <label for="tanggal_selesai">Tanggal Selesai</label>
                <input id="tanggal_selesai" name="tanggal_selesai" type="date" class="travs-input" value="{{ $filters['tanggal_selesai'] ?? '' }}">
            </div>
            <div class="travs-filter-actions">
                <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                <a href="{{ route('admin.biaya.rekap') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>
    </article>

    <section class="travs-panel-grid travs-rekap-panel-grid">
        <article class="travs-section-card travs-rekap-filter-card">
            <div class="travs-panel-title">
                <h3>Akumulasi per Rute</h3>
                <p>Ringkasan biaya per rute berdasarkan data biaya operasional yang sudah dicatat.</p>
            </div>

            <div class="travs-table-wrap">
                <table class="travs-table">
                    <thead>
                        <tr>
                            <th>Rute</th>
                            <th>Jumlah Item</th>
                            <th>Total Biaya</th>
                            <th>Catatan Terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rekap as $item)
                            <tr>
                                <td>{{ $item['route'] }}</td>
                                <td>{{ number_format($item['count']) }}</td>
                                <td>Rp{{ number_format($item['total'], 0, ',', '.') }}</td>
                                <td>{{ $item['latest_date'] ? \Illuminate\Support\Carbon::parse($item['latest_date'])->translatedFormat('d M Y H:i') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="travs-table-empty">Belum ada data rekap biaya pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="travs-section-card travs-rekap-filter-card">
            <div class="travs-panel-title">
                <h3>Detail Pencatatan</h3>
                <p>Daftar biaya operasional terbaru yang masuk ke sistem.</p>
            </div>

            <div class="travs-table-wrap">
                <table class="travs-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Rute</th>
                            <th>Jenis Biaya</th>
                            <th>Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $item)
                            <tr>
                                <td>{{ $item->tanggal_biaya->translatedFormat('d M Y H:i') }}</td>
                                <td>{{ $item->jadwal->rute->kota_asal }} - {{ $item->jadwal->rute->kota_tujuan }}</td>
                                <td>{{ $item->jenis_biaya }}</td>
                                <td>Rp{{ number_format($item->nominal, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="travs-table-empty">Belum ada detail biaya pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>
@endsection
