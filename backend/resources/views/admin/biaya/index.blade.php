@extends('admin.layouts.app')

@section('title', 'Biaya Operasional')
@section('page-title', 'Biaya Operasional')
@section('page-description', 'Catat pengeluaran setiap perjalanan tanpa proses persetujuan terpisah.')

@section('content')
    <section aria-labelledby="cost-summary-title">
        <div class="travs-panel-title mb-3">
            <h3 id="cost-summary-title">Ringkasan Biaya</h3>
            <p>Ringkasan mengikuti rute dan rentang tanggal yang sedang diterapkan.</p>
        </div>

        <div class="travs-summary-grid travs-summary-grid-auto">
            <article class="travs-summary-card card">
                <small>Total Biaya Periode</small>
                <strong>Rp{{ number_format($periodSummary['total_biaya'], 0, ',', '.') }}</strong>
                <span>{{ $periodSummary['periode_label'] }}.</span>
            </article>
            <article class="travs-summary-card card">
                <small>Jumlah Transaksi</small>
                <strong>{{ number_format($periodSummary['jumlah_transaksi'], 0, ',', '.') }}</strong>
                <span>Catatan biaya yang sesuai dengan filter aktif.</span>
            </article>
            <article class="travs-summary-card card">
                <small>Rute dengan Biaya Tertinggi</small>
                <strong>{{ $periodSummary['rute_tertinggi'] }}</strong>
                <span>Rp{{ number_format($periodSummary['rute_tertinggi_total'], 0, ',', '.') }} pada periode aktif.</span>
            </article>
        </div>
    </section>

    <article class="travs-section-card card">
        <div class="travs-panel-title">
            <h3>Kelola Kategori Pengeluaran</h3>
            <p>Daftar ini menjadi pilihan dropdown yang sama pada form admin dan aplikasi pengemudi.</p>
        </div>

        <form method="POST" action="{{ route('admin.biaya.categories.store') }}" class="travs-filter-grid d-grid">
            @csrf
            <div class="travs-field">
                <label for="nama_kategori" class="form-label">Nama Kategori Baru</label>
                <input
                    id="nama_kategori"
                    name="nama_kategori"
                    class="travs-input @error('nama_kategori') is-invalid @enderror form-control"
                    value="{{ old('nama_kategori') }}"
                    maxlength="60"
                    required
                    placeholder="Contoh: Cuci Kendaraan"
                >
                @include('admin.partials.field-error', ['name' => 'nama_kategori'])
            </div>
            <div class="travs-filter-actions d-flex align-items-end">
                <button type="submit" class="travs-button btn btn-primary">Tambah Kategori</button>
            </div>
        </form>

        <div class="d-flex flex-wrap gap-2 mt-3" aria-label="Kategori pengeluaran aktif">
            @foreach ($expenseCategories as $category)
                <form method="POST" action="{{ route('admin.biaya.categories.destroy') }}" class="d-inline-flex">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="nama_kategori" value="{{ $category }}">
                    <button type="submit" class="travs-button-secondary btn btn-outline-secondary">
                        {{ $category }} <span aria-hidden="true">&times;</span>
                    </button>
                </form>
            @endforeach
        </div>
    </article>

    <article class="travs-section-card collapsible-section card" data-collapsible-section>
        <div class="travs-panel-header collapsible-section__header d-flex align-items-start justify-content-between" data-collapse-header>
            <div class="travs-panel-title">
                <h3>Daftar Biaya</h3>
                <p>Filter dan pencatatan biaya ditampilkan dalam satu bagian agar lebih mudah diperiksa.</p>
            </div>
            <div class="collapsible-section__header-actions">
                <a href="{{ route('admin.biaya.create') }}" class="travs-button btn btn-primary">Tambah Biaya</a>
                <details class="travs-export-menu">
                    <summary class="travs-button btn btn-primary">Export Data</summary>
                    <div class="travs-export-menu__list">
                        <a href="{{ route('admin.biaya.export', array_merge(request()->query(), ['format' => 'csv'])) }}">CSV</a>
                        <a href="{{ route('admin.biaya.export', array_merge(request()->query(), ['format' => 'xls'])) }}">Excel (.xls)</a>
                    </div>
                </details>
                @include('admin.partials.collapsible-toggle', ['targetId' => 'cost-list-body'])
            </div>
        </div>

        <div id="cost-list-body" class="collapsible-section__body" data-collapse-body>
            <form method="GET" class="travs-filter-grid d-grid">
                <div class="travs-field">
                    <label for="search" class="form-label">Cari</label>
                    <input id="search" name="search" class="travs-input form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Jenis biaya, keterangan, atau rute">
                </div>
                <div class="travs-field">
                    <label for="rute_id" class="form-label">Rute</label>
                    <select id="rute_id" name="rute_id" class="travs-select @error('rute_id') is-invalid @enderror form-select">
                        <option value="">Semua rute</option>
                        @foreach ($routeOptions as $rute)
                            <option value="{{ $rute->id }}" @selected((string) ($filters['rute_id'] ?? '') === (string) $rute->id)>
                                {{ $rute->kota_asal }} - {{ $rute->kota_tujuan }}
                            </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'rute_id'])
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
                    <a href="{{ route('admin.biaya.index') }}" class="travs-button-secondary btn btn-outline-secondary" data-clear-form-draft>Tampilkan Semua</a>
                </div>
            </form>

            <div class="travs-table-wrap travs-cost-table-section table-responsive">
                <table class="travs-table table mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Biaya</th>
                            <th>Rute</th>
                            <th class="travs-cost-schedule-column">Jadwal</th>
                            <th class="travs-cost-amount-column text-end">Nominal</th>
                            <th class="travs-cost-source-column">Sumber Pencatatan</th>
                            <th>Bukti</th>
                            <th class="text-end">Aksi</th>
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
                                <td class="travs-cost-schedule-column">
                                    <strong>{{ $item->jadwal?->waktu_berangkat?->translatedFormat('l') ?? '—' }}</strong>
                                    <div class="travs-table-subcopy">
                                        {{ $item->jadwal?->waktu_berangkat?->translatedFormat('d F Y') ?? 'Jadwal tidak tersedia' }}
                                    </div>
                                </td>
                                <td class="travs-cost-amount-column text-end text-nowrap">Rp{{ number_format($item->nominal, 0, ',', '.') }}</td>
                                <td class="travs-cost-source-column">
                                    <strong>{{ $item->pencatat?->nama ?? 'Pencatat tidak tersedia' }}</strong>
                                    <div class="travs-table-subcopy">{{ $item->pencatat?->peran === 'pengemudi' ? 'Dicatat pengemudi' : 'Dicatat admin' }}</div>
                                </td>
                                <td>
                                    @if ($item->bukti_url)
                                        <a
                                            href="{{ asset('storage/'.ltrim($item->bukti_url, '/')) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="travs-button-link btn btn-link"
                                        >Lihat Bukti</a>
                                    @else
                                        <span class="text-muted">Data lama tanpa bukti</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="travs-table-actions d-flex align-items-center justify-content-end flex-wrap">
                                        <a href="{{ route('admin.biaya.edit', $item) }}" class="travs-button-link btn btn-link">Edit</a>
                                        <form method="POST" action="{{ route('admin.biaya.destroy', $item) }}" data-confirm-title="Hapus biaya?" data-confirm-message="Catatan biaya yang dipilih akan dihapus permanen." data-confirm-action="Ya, hapus biaya">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="travs-button-link travs-button-link-danger btn btn-link">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="travs-table-empty">Tidak ada biaya operasional yang sesuai dengan filter yang dipilih.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $biaya->links() }}
        </div>
    </article>

    <article class="travs-section-card collapsible-section card" data-collapsible-section>
        <div class="collapsible-section__header" data-collapse-header>
            <div class="travs-panel-title">
                <h3>Akumulasi per Rute</h3>
                <p>Ringkasan mengikuti rute dan rentang tanggal yang dipilih pada Daftar Biaya.</p>
            </div>
            @include('admin.partials.collapsible-toggle', ['targetId' => 'cost-recap-route-body'])
        </div>

        <div id="cost-recap-route-body" class="travs-table-wrap collapsible-section__body table-responsive" data-collapse-body>
            <table class="travs-table table mb-0">
                <thead>
                    <tr>
                        <th>Rute</th>
                        <th class="text-center">Jumlah Item</th>
                        <th class="travs-cost-total-column text-end">Total Biaya</th>
                        <th class="travs-cost-latest-column">Catatan Terakhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rekap as $item)
                        <tr>
                            <td>{{ $item['route'] }}</td>
                            <td class="text-center">{{ number_format($item['count']) }}</td>
                            <td class="travs-cost-total-column text-end text-nowrap">Rp{{ number_format($item['total'], 0, ',', '.') }}</td>
                            <td class="travs-cost-latest-column text-nowrap">{{ $item['latest_date'] ? \Illuminate\Support\Carbon::parse($item['latest_date'])->translatedFormat('d M Y H:i') : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="travs-table-empty">Tidak ada akumulasi biaya yang sesuai dengan filter yang dipilih.</td>
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
