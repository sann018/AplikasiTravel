@extends('admin.layouts.app')

@section('title', 'Armada Kendaraan')
@section('page-title', 'Armada Kendaraan')
@section('page-description', 'Kelola data kendaraan travel yang aktif, sedang perawatan, atau dinonaktifkan dari satu halaman operasional.')

@section('content')
    <section class="travs-summary-grid">
        <article class="travs-summary-card">
            <small>Total Armada</small>
            <strong>{{ number_format($stats['total']) }}</strong>
            <span>Seluruh unit tercatat</span>
        </article>
        <article class="travs-summary-card">
            <small>Armada Aktif</small>
            <strong>{{ number_format($stats['aktif']) }}</strong>
            <span>Siap dipakai operasional</span>
        </article>
        <article class="travs-summary-card">
            <small>Perawatan</small>
            <strong>{{ number_format($stats['perawatan']) }}</strong>
            <span>Unit sedang servis</span>
        </article>
        <article class="travs-summary-card">
            <small>Nonaktif</small>
            <strong>{{ number_format($stats['nonaktif']) }}</strong>
            <span>Belum dipakai saat ini</span>
        </article>
    </section>

    <section class="travs-section-card">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Daftar Armada</h3>
                <p>Pakai filter untuk memantau unit aktif, riwayat pemakaian, dan kesiapan armada.</p>
            </div>
            <a href="{{ route('admin.kendaraan.create') }}" class="travs-button">Tambah Armada</a>
        </div>

        <form method="GET" class="travs-filter-grid">
            <div class="travs-field">
                <label for="search">Cari Armada</label>
                <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Plat, merk, atau tipe">
            </div>
            <div class="travs-field">
                <label for="status">Status</label>
                <select id="status" name="status" class="travs-select">
                    <option value="">Semua status</option>
                    <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                    <option value="perawatan" @selected(($filters['status'] ?? '') === 'perawatan')>Perawatan</option>
                    <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="travs-filter-actions">
                <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                <a href="{{ route('admin.kendaraan.index') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap">
            <table class="travs-table">
                <thead>
                    <tr>
                        <th>Armada</th>
                        <th>Kapasitas</th>
                        <th>Status</th>
                        <th>Jadwal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($kendaraan as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->merk_kendaraan }}</strong>
                                <div class="travs-table-subcopy">{{ $item->nomor_plat }} &middot; {{ $item->tipe_kendaraan ?: 'Tipe belum diisi' }}</div>
                            </td>
                            <td>{{ $item->kapasitas_kursi }} kursi</td>
                            <td>
                                <span class="travs-status-pill travs-status-{{ $item->status_kendaraan }}">
                                    {{ ucfirst($item->status_kendaraan) }}
                                </span>
                            </td>
                            <td>{{ number_format($item->jadwal_count) }} jadwal</td>
                            <td>
                                <div class="travs-table-actions">
                                    <a href="{{ route('admin.kendaraan.edit', $item) }}" class="travs-button-link">Edit</a>
                                    <form method="POST" action="{{ route('admin.kendaraan.destroy', $item) }}" onsubmit="return confirm('Hapus armada ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="travs-button-link travs-button-link-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="travs-table-empty">Belum ada data armada kendaraan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $kendaraan->links() }}
    </section>
@endsection
