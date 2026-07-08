@extends('admin.layouts.app')

@section('title', 'Biaya Operasional')
@section('page-title', 'Biaya Operasional')
@section('page-description', 'Catat pengeluaran operasional per perjalanan agar rekap biaya dan laporan lebih akurat.')

@section('content')
    <section data-static-cost-summary>
        <div class="travs-panel-title mb-3">
            <h3>Ringkasan Biaya</h3>
            <p>Ikhtisar pengeluaran yang sudah tercatat.</p>
        </div>

        <div id="cost-summary-body" class="travs-summary-grid">
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

    <section class="travs-cost-workspace">
        <article class="travs-section-card travs-form-card travs-cost-form-panel collapsible-section" data-collapsible-section>
            <div class="collapsible-section__header" data-collapse-header>
                <div class="travs-panel-title">
                <h3>{{ $editingBiaya ? 'Edit Biaya Operasional' : 'Catat Biaya Operasional' }}</h3>
                <p>Gunakan modul ini untuk bahan bakar, tol, makan driver, parkir, dan biaya perjalanan lainnya.</p>
                </div>
                @include('admin.partials.collapsible-toggle', ['targetId' => 'cost-form-body'])
            </div>

            <form id="cost-form-body" method="POST" action="{{ $editingBiaya ? route('admin.biaya.update', $editingBiaya) : route('admin.biaya.store') }}" class="travs-form-grid collapsible-section__body" data-collapse-body>
                @csrf
                @if ($editingBiaya)
                    @method('PUT')
                @endif

                <div class="travs-form-grid-2">
                    <div class="travs-field travs-field-span-2">
                        <label for="jadwal_id">Jadwal Terkait</label>
                        <select id="jadwal_id" name="jadwal_id" class="travs-select @error('jadwal_id') is-invalid @enderror" required>
                            <option value="">Pilih jadwal</option>
                            @foreach ($jadwalOptions as $jadwal)
                                <option value="{{ $jadwal->id }}" @selected((string) old('jadwal_id', $editingBiaya?->jadwal_id) === (string) $jadwal->id)>
                                    {{ $jadwal->waktu_berangkat->translatedFormat('d M Y H:i') }} &middot; {{ $jadwal->rute->kota_asal }} - {{ $jadwal->rute->kota_tujuan }}
                                </option>
                            @endforeach
                        </select>
                        @include('admin.partials.field-error', ['name' => 'jadwal_id'])
                    </div>
                    <div class="travs-field">
                        <label for="jenis_biaya">Jenis Biaya</label>
                        <input id="jenis_biaya" name="jenis_biaya" class="travs-input @error('jenis_biaya') is-invalid @enderror" value="{{ old('jenis_biaya', $editingBiaya?->jenis_biaya) }}" required>
                        @include('admin.partials.field-error', ['name' => 'jenis_biaya'])
                    </div>
                    <div class="travs-field">
                        <label for="nominal">Nominal</label>
                        <input id="nominal" name="nominal" type="number" step="0.01" class="travs-input @error('nominal') is-invalid @enderror" value="{{ old('nominal', $editingBiaya?->nominal) }}" required>
                        @include('admin.partials.field-error', ['name' => 'nominal'])
                    </div>
                    <div class="travs-field">
                        <label for="tanggal_biaya">Tanggal Biaya</label>
                        <input id="tanggal_biaya" name="tanggal_biaya" type="datetime-local" class="travs-input @error('tanggal_biaya') is-invalid @enderror" value="{{ old('tanggal_biaya', optional($editingBiaya?->tanggal_biaya)->format('Y-m-d\TH:i')) }}" required>
                        @include('admin.partials.field-error', ['name' => 'tanggal_biaya'])
                    </div>
                    <div class="travs-field travs-field-span-2">
                        <label for="keterangan">Keterangan</label>
                        <textarea id="keterangan" name="keterangan" class="travs-textarea @error('keterangan') is-invalid @enderror">{{ old('keterangan', $editingBiaya?->keterangan) }}</textarea>
                        @include('admin.partials.field-error', ['name' => 'keterangan'])
                    </div>
                </div>

                <div class="travs-form-actions travs-inline">
                    @if ($editingBiaya)
                        <a href="{{ route('admin.biaya.index') }}" class="travs-button-secondary">Batal Edit</a>
                    @endif
                    <button type="submit" class="travs-button">{{ $editingBiaya ? 'Simpan Perubahan' : 'Catat Biaya' }}</button>
                </div>
            </form>
        </article>

        <article class="travs-section-card travs-cost-list-panel collapsible-section" data-collapsible-section>
            <div class="travs-panel-header collapsible-section__header" data-collapse-header>
                <div class="travs-panel-title">
                    <h3>Daftar Biaya</h3>
                    <p>Semua pencatatan biaya operasional yang sudah masuk akan muncul di sini.</p>
                </div>
                <div class="collapsible-section__header-actions">
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
                <section class="travs-cost-filter-section">
                    <div class="travs-panel-title mb-3">
                        <h3>Filter Daftar Biaya</h3>
                        <p>Pilih kriteria terlebih dahulu, lalu tekan Terapkan Filter.</p>
                    </div>

                    <form id="cost-filter-body" method="GET" class="travs-filter-grid">
                        <div class="travs-field">
                            <label for="search">Cari</label>
                            <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Jenis biaya, keterangan, atau rute">
                        </div>
                        <div class="travs-field">
                            <label for="jadwal_filter">Jadwal</label>
                            <select id="jadwal_filter" name="jadwal_id" class="travs-select">
                                <option value="">Semua jadwal</option>
                                @foreach ($jadwalOptions as $jadwal)
                                    <option value="{{ $jadwal->id }}" @selected((string) ($filters['jadwal_id'] ?? '') === (string) $jadwal->id)>
                                        {{ $jadwal->rute->kota_asal }} - {{ $jadwal->rute->kota_tujuan }}
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
                            <a href="{{ route('admin.biaya.index') }}" class="travs-button-secondary">Reset</a>
                        </div>
                    </form>
                </section>

            <div class="travs-table-wrap travs-cost-table-section">
                <table class="travs-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Biaya</th>
                            <th>Rute</th>
                            <th>Nominal</th>
                            <th>Status</th>
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
                                    @php
                                        $statusBiaya = $item->status_verifikasi ?: 'disetujui';
                                        $statusLabel = [
                                            'menunggu_verifikasi' => 'Menunggu Verifikasi',
                                            'disetujui' => 'Disetujui',
                                            'ditolak' => 'Ditolak',
                                        ][$statusBiaya] ?? ucfirst(str_replace('_', ' ', $statusBiaya));
                                    @endphp
                                    <span class="travs-badge travs-badge-{{ $statusBiaya === 'ditolak' ? 'danger' : ($statusBiaya === 'menunggu_verifikasi' ? 'warning' : 'success') }}">
                                        {{ $statusLabel }}
                                    </span>
                                    @if ($item->bukti_url)
                                        <a href="{{ asset('storage/'.ltrim($item->bukti_url, '/')) }}" target="_blank" rel="noopener" class="travs-button-link d-block mt-2">Lihat Bukti</a>
                                    @endif
                                </td>
                                <td>
                                    <div class="travs-table-actions">
                                        <a href="{{ route('admin.biaya.index', ['edit' => $item->id] + request()->query()) }}" class="travs-button-link">Edit</a>
                                        @if ($statusBiaya === 'menunggu_verifikasi')
                                            <form method="POST" action="{{ route('admin.biaya.verify', $item) }}" onsubmit="return confirm('Setujui pengajuan biaya ini?')">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status_verifikasi" value="disetujui">
                                                <button type="submit" class="travs-button-link">Setujui</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.biaya.verify', $item) }}" onsubmit="return confirm('Tolak pengajuan biaya ini?')">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status_verifikasi" value="ditolak">
                                                <button type="submit" class="travs-button-link travs-button-link-danger">Tolak</button>
                                            </form>
                                        @endif
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
                                <td colspan="6" class="travs-table-empty">Belum ada biaya operasional tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $biaya->links() }}
            </div>
        </article>

        <article class="travs-section-card travs-cost-accumulation-panel collapsible-section" data-collapsible-section>
            <div class="collapsible-section__header" data-collapse-header>
                <div class="travs-panel-title">
                    <h3>Akumulasi per Rute</h3>
                    <p>Ringkasan biaya per rute mengikuti filter Daftar Biaya yang sedang diterapkan.</p>
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
    </section>
@endsection

@push('scripts')
    @include('admin.partials.collapsible-section-script')
@endpush
