@extends('admin.layouts.app')

@section('title', 'Rute Perjalanan')
@section('page-title', 'Rute Perjalanan')
@section('page-description', 'Kelola jalur lintas kota, harga tiket, estimasi durasi, dan status aktif rute.')

@section('content')
    <section class="travs-section-card card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between">
            <div class="travs-panel-title">
                <h3>Daftar Rute</h3>
                <p>Semua rute untuk pengelolaan jadwal aktif.</p>
            </div>
            <a href="{{ route('admin.rute.create') }}" class="travs-button btn btn-primary">Tambah Rute</a>
        </div>

        <form method="GET" class="travs-filter-grid d-grid">
            <div class="travs-field">
                <label for="search" class="form-label">Cari Rute</label>
                <input id="search" name="search" class="travs-input form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Asal, tujuan, atau jalur">
            </div>
            <div class="travs-field">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="travs-select form-select">
                    <option value="">Semua status</option>
                    <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="travs-filter-actions d-flex align-items-end flex-wrap">
                <button type="submit" class="travs-button btn btn-primary">Terapkan Filter</button>
                <a href="{{ route('admin.rute.index') }}" class="travs-button-secondary btn btn-outline-secondary" data-clear-form-draft>Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap table-responsive">
            <table class="travs-table table mb-0">
                <thead>
                    <tr>
                        <th>Rute</th>
                        <th class="text-end">Harga</th>
                        <th class="text-center">Durasi</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rute as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->kota_asal }} - {{ $item->kota_tujuan }}</strong>
                                <div class="travs-table-subcopy">{{ $item->deskripsi_jalur ?: 'Deskripsi jalur belum diisi' }}</div>
                            </td>
                            <td class="text-end text-nowrap">Rp{{ number_format($item->harga_tiket, 0, ',', '.') }}</td>
                            <td class="text-center">{{ $item->durasi_label }}</td>
                            <td class="text-center">
                                <span class="travs-status-pill travs-status-{{ $item->status_aktif ? 'aktif' : 'nonaktif' }} badge rounded-pill">
                                    {{ $item->status_aktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="travs-table-actions d-flex align-items-center justify-content-end flex-wrap">
                                    <a href="{{ route('admin.rute.edit', $item) }}" class="travs-button-link btn btn-link">Edit</a>
                                    <form method="POST" action="{{ route('admin.rute.destroy', $item) }}" data-confirm-title="Hapus rute?" data-confirm-message="Rute yang dihapus tidak dapat dikembalikan." data-confirm-action="Ya, hapus rute">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="travs-button-link travs-button-link-danger btn btn-link">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="travs-table-empty">Tidak ada rute yang sesuai dengan filter yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $rute->links() }}
    </section>
@endsection
