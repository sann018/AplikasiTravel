@extends('admin.layouts.app')

@section('title', 'Petugas Loket')
@section('page-title', 'Petugas Loket')
@section('page-description', 'Kelola akun internal untuk pelayanan pemesanan melalui aplikasi mobile.')

@section('content')
    <section class="travs-section-card card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div class="travs-panel-title">
                <h3>Daftar Petugas Loket</h3>
                <p>Setiap petugas menggunakan akun sendiri agar pembuat pemesanan dapat ditelusuri.</p>
            </div>
            <a href="{{ route('admin.petugas-loket.create') }}" class="travs-button btn btn-primary">Tambah Petugas</a>
        </div>

        <form method="GET" class="travs-filter-grid d-grid">
            <div class="travs-field">
                <label for="search" class="form-label">Cari Petugas</label>
                <input id="search" name="search" class="travs-input form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, email, atau nomor HP">
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
                <a href="{{ route('admin.petugas-loket.index') }}" class="travs-button-secondary btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap table-responsive">
            <table class="travs-table table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Petugas</th>
                        <th>Kontak</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Pemesanan Dibuat</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($petugas as $item)
                        <tr>
                            <td><strong>{{ $item->nama }}</strong><div class="travs-table-subcopy">Akun Petugas Loket</div></td>
                            <td>{{ $item->email }}<div class="travs-table-subcopy">{{ $item->no_hp }}</div></td>
                            <td class="text-center">
                                <span class="travs-status-pill travs-status-{{ $item->status_aktif ? 'aktif' : 'nonaktif' }} badge rounded-pill">
                                    {{ $item->status_aktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-center">{{ number_format($item->pemesanan_dibuat_count) }}</td>
                            <td class="text-end">
                                <div class="travs-table-actions d-flex align-items-center justify-content-end flex-wrap">
                                    <a href="{{ route('admin.petugas-loket.show', $item) }}" class="travs-button-link btn btn-link">Detail</a>
                                    <a href="{{ route('admin.petugas-loket.edit', $item) }}" class="travs-button-link btn btn-link">Edit</a>
                                    <form method="POST" action="{{ route('admin.petugas-loket.aktivasi', $item) }}">
                                        @csrf
                                        <button type="submit" class="travs-button-link btn btn-link">Kirim Aktivasi</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.petugas-loket.status', $item) }}" onsubmit="return confirm('{{ $item->status_aktif ? 'Nonaktifkan' : 'Aktifkan' }} akun Petugas Loket ini?')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="travs-button-link {{ $item->status_aktif ? 'travs-button-link-danger' : '' }} btn btn-link">
                                            {{ $item->status_aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="travs-table-empty">Tidak ada Petugas Loket yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $petugas->links() }}
    </section>
@endsection
