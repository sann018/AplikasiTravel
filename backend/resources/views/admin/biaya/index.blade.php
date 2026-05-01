@extends('admin.layouts.app')

@section('title', 'Biaya Operasional')
@section('page-title', 'Biaya Operasional')
@section('page-description', 'Catat pengeluaran operasional per perjalanan agar rekap biaya dan laporan lebih akurat.')

@section('content')
    <section class="travs-summary-grid">
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
    </section>

    <section class="travs-split-grid">
        <article class="travs-section-card travs-form-card">
            <div class="travs-panel-title">
                <h3>{{ $editingBiaya ? 'Edit Biaya Operasional' : 'Catat Biaya Operasional' }}</h3>
                <p>Gunakan modul ini untuk bahan bakar, tol, makan driver, parkir, dan biaya perjalanan lainnya.</p>
            </div>

            <form method="POST" action="{{ $editingBiaya ? route('admin.biaya.update', $editingBiaya) : route('admin.biaya.store') }}" class="travs-form-grid">
                @csrf
                @if ($editingBiaya)
                    @method('PUT')
                @endif

                <div class="travs-form-grid-2">
                    <div class="travs-field travs-field-span-2">
                        <label for="jadwal_id">Jadwal Terkait</label>
                        <select id="jadwal_id" name="jadwal_id" class="travs-select" required>
                            <option value="">Pilih jadwal</option>
                            @foreach ($jadwalOptions as $jadwal)
                                <option value="{{ $jadwal->id }}" @selected((string) old('jadwal_id', $editingBiaya?->jadwal_id) === (string) $jadwal->id)>
                                    {{ $jadwal->waktu_berangkat->translatedFormat('d M Y H:i') }} &middot; {{ $jadwal->rute->kota_asal }} - {{ $jadwal->rute->kota_tujuan }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="travs-field">
                        <label for="jenis_biaya">Jenis Biaya</label>
                        <input id="jenis_biaya" name="jenis_biaya" class="travs-input" value="{{ old('jenis_biaya', $editingBiaya?->jenis_biaya) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="nominal">Nominal</label>
                        <input id="nominal" name="nominal" type="number" step="0.01" class="travs-input" value="{{ old('nominal', $editingBiaya?->nominal) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="tanggal_biaya">Tanggal Biaya</label>
                        <input id="tanggal_biaya" name="tanggal_biaya" type="datetime-local" class="travs-input" value="{{ old('tanggal_biaya', optional($editingBiaya?->tanggal_biaya)->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="travs-field travs-field-span-2">
                        <label for="keterangan">Keterangan</label>
                        <textarea id="keterangan" name="keterangan" class="travs-textarea">{{ old('keterangan', $editingBiaya?->keterangan) }}</textarea>
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

        <article class="travs-section-card">
            <div class="travs-panel-header">
                <div class="travs-panel-title">
                    <h3>Daftar Biaya</h3>
                    <p>Semua pencatatan biaya operasional yang sudah masuk akan muncul di sini.</p>
                </div>
                <a href="{{ route('admin.biaya.rekap') }}" class="travs-button-secondary">Lihat Rekap</a>
            </div>

            <form method="GET" class="travs-filter-grid">
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

            <div class="travs-table-wrap">
                <table class="travs-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Biaya</th>
                            <th>Rute</th>
                            <th>Nominal</th>
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
                                    <div class="travs-table-actions">
                                        <a href="{{ route('admin.biaya.index', ['edit' => $item->id] + request()->query()) }}" class="travs-button-link">Edit</a>
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
                                <td colspan="5" class="travs-table-empty">Belum ada biaya operasional tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $biaya->links() }}
        </article>
    </section>
@endsection
