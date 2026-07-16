@extends('admin.layouts.app')

@section('title', 'Biaya Operasional')
@section('page-title', 'Biaya Operasional')
@section('page-description', 'Catat pengeluaran setiap perjalanan tanpa proses persetujuan terpisah.')

@section('content')
    <section data-static-cost-summary>
        <div class="travs-panel-title mb-3">
            <h3>Ringkasan Biaya</h3>
            <p>Ikhtisar pengeluaran yang sudah tercatat.</p>
        </div>

        <div class="travs-summary-grid">
            <article class="travs-summary-card">
                <small>Biaya Hari Ini</small>
                <strong>Rp{{ number_format($stats['hari_ini'], 0, ',', '.') }}</strong>
                <span>Pengeluaran tercatat hari ini</span>
            </article>
            <article class="travs-summary-card">
                <small>Biaya Bulan Ini</small>
                <strong>Rp{{ number_format($stats['bulan_ini'], 0, ',', '.') }}</strong>
                <span>Akumulasi bulan berjalan</span>
            </article>
            <article class="travs-summary-card">
                <small>Total Item Biaya</small>
                <strong>{{ number_format($stats['total_item']) }}</strong>
                <span>Jumlah pencatatan biaya</span>
            </article>
        </div>
    </section>

    <article class="travs-section-card collapsible-section" data-collapsible-section>
        <div class="travs-panel-header collapsible-section__header" data-collapse-header>
            <div class="travs-panel-title">
                <h3>Daftar Biaya</h3>
                <p>Filter dan pencatatan biaya ditampilkan dalam satu bagian agar lebih mudah diperiksa.</p>
            </div>
            <div class="collapsible-section__header-actions">
                <a href="{{ route('admin.biaya.create') }}" class="travs-button">Tambah Biaya</a>
                <details class="travs-export-menu">
                    <summary>Export Data</summary>
                    <div class="travs-export-menu__list">
                        <a href="{{ route('admin.biaya.export', array_merge(request()->query(), ['format' => 'csv'])) }}">CSV</a>
                        <a href="{{ route('admin.biaya.export', array_merge(request()->query(), ['format' => 'xls'])) }}">Excel (.xls)</a>
                        <a href="{{ route('admin.biaya.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}">XLSX</a>
                    </div>
                </details>
                @include('admin.partials.collapsible-toggle', ['targetId' => 'cost-list-body'])
            </div>
        </div>

        <div id="cost-list-body" class="collapsible-section__body" data-collapse-body>
            <form method="GET" class="travs-filter-grid">
                <div class="travs-field">
                    <label for="search">Cari</label>
                    <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Jenis biaya, keterangan, atau rute">
                </div>
                <div class="travs-field">
                    <label for="rute_id">Rute</label>
                    <select id="rute_id" name="rute_id" class="travs-select">
                        <option value="">Semua rute</option>
                        @foreach ($routeOptions as $rute)
                            <option value="{{ $rute->id }}" @selected((string) ($filters['rute_id'] ?? '') === (string) $rute->id)>
                                {{ $rute->kota_asal }} - {{ $rute->kota_tujuan }}
                            </option>
                        @endforeach
                    </select>
                </div>
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
                    <a href="{{ route('admin.biaya.index') }}" class="travs-button-secondary">Tampilkan Semua</a>
                </div>
            </form>

            <div class="travs-table-wrap travs-cost-table-section">
                <table class="travs-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Biaya</th>
                            <th>Rute</th>
                            <th>Nominal</th>
                            <th>Sumber Pencatatan</th>
                            <th>Bukti</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($biaya as $item)
                            <tr>
                                <td>{{ $item->tanggal_biaya->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    <strong>{{ $item->jenis_biaya }}</strong>
                                    <div class="travs-table-subcopy">{{ $item->keterangan ?: 'Tanpa keterangan' }}</div>
                                </td>
                                <td>{{ $item->jadwal->rute->kota_asal }} - {{ $item->jadwal->rute->kota_tujuan }}</td>
                                <td>Rp{{ number_format($item->nominal, 0, ',', '.') }}</td>
                                <td>
                                    <strong>{{ $item->pencatat?->nama ?? 'Pencatat tidak tersedia' }}</strong>
                                    <div class="travs-table-subcopy">{{ $item->pencatat?->peran === 'pengemudi' ? 'Dicatat pengemudi' : 'Dicatat admin' }}</div>
                                </td>
                                <td>
                                    @if ($item->bukti_url)
                                        <button type="button" class="travs-button-link travs-photo-preview-trigger" data-preview-src="{{ asset('storage/'.ltrim($item->bukti_url, '/')) }}" aria-label="Lihat bukti {{ $item->jenis_biaya }}">Lihat Bukti</button>
                                    @else
                                        <span class="travs-table-subcopy">Bukti lama tidak tersedia</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="travs-table-actions">
                                        <a href="{{ route('admin.biaya.edit', $item) }}" class="travs-button-link">Edit</a>
                                        <form method="POST" action="{{ route('admin.biaya.destroy', $item) }}" onsubmit="return confirm('Hapus biaya ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="travs-button-link travs-button-link-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="travs-table-empty">Belum ada biaya operasional pada filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $biaya->links() }}
        </div>
    </article>

    <article class="travs-section-card collapsible-section" data-collapsible-section>
        <div class="collapsible-section__header" data-collapse-header>
            <div class="travs-panel-title">
                <h3>Akumulasi per Rute</h3>
                <p>Ringkasan mengikuti rute dan rentang tanggal yang dipilih pada Daftar Biaya.</p>
            </div>
            @include('admin.partials.collapsible-toggle', ['targetId' => 'cost-recap-route-body'])
        </div>

        <div id="cost-recap-route-body" class="travs-table-wrap collapsible-section__body" data-collapse-body>
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
                            <td colspan="4" class="travs-table-empty">Belum ada akumulasi biaya pada filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
@endsection

@push('scripts')
    @include('admin.partials.collapsible-section-script')
@endpush
