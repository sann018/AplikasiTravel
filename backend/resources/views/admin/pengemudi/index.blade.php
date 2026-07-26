@extends('admin.layouts.app')

@section('title', 'Pengemudi')
@section('page-title', 'Kelola Pengemudi')
@section('page-description', 'Buat, aktifkan, dan perbarui akun pengemudi.')

@section('content')
    <section class="travs-section-card card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between">
            <div class="travs-panel-title">
                <h3>Daftar Pengemudi</h3>
                <p>Gunakan pencarian untuk memantau status aktivasi dan kesiapan pengemudi pada jadwal aktif.</p>
            </div>
            <a href="{{ route('admin.pengemudi.create') }}" class="travs-button btn btn-primary">Tambah Pengemudi</a>
        </div>

        <form method="GET" class="travs-filter-grid d-grid">
            <div class="travs-field">
                <label for="search" class="form-label">Cari Pengemudi</label>
                <input id="search" name="search" class="travs-input form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, email, nomor HP, nomor SIM">
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
                <a href="{{ route('admin.pengemudi.index') }}" class="travs-button-secondary btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap table-responsive">
            <table class="travs-table table mb-0">
                <thead>
                    <tr>
                        <th>Pengemudi</th>
                        <th>Kontak</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Jadwal</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pengemudi as $item)
                        <tr>
                            <td class="text-center">
                                <div class="travs-person-cell d-flex align-items-center">
                                    <div class="travs-avatar">
                                        @if ($item->pengguna->foto_profil)
                                            <img class="travs-avatar-image travs-photo-preview-trigger" src="/storage/{{ ltrim($item->pengguna->foto_profil, '/') }}" alt="Foto {{ $item->pengguna->nama }}">
                                        @else
                                            <span>{{ strtoupper(mb_substr($item->pengguna->nama, 0, 1)) }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <strong>{{ $item->pengguna->nama }}</strong>
                                        <div class="travs-table-subcopy">SIM {{ $item->nomor_sim }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                {{ $item->pengguna->email }}
                                <div class="travs-table-subcopy">{{ $item->pengguna->no_hp }}</div>
                            </td>
                            <td>
                                <div class="travs-status-stack">
                                    <span class="travs-status-pill travs-status-{{ $item->status }} badge rounded-pill">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </div>
                                <div class="travs-table-subcopy">
                                    {{ $item->status === 'aktif' ? 'Siap dipilih pada jadwal.' : 'Tidak dipakai sementara.' }}
                                </div>
                            </td>
                            <td class="text-center">{{ number_format($item->jadwal_count) }} jadwal</td>
                            <td class="text-end">
                                <div class="travs-table-actions d-flex align-items-center justify-content-end flex-wrap">
                                    <a href="{{ route('admin.pengemudi.edit', $item) }}" class="travs-button-link btn btn-link">Edit</a>
                                    <form method="POST" action="{{ route('admin.pengemudi.destroy', $item) }}" onsubmit="return confirm('Hapus akun pengemudi ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="travs-button-link travs-button-link-danger btn btn-link">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="travs-table-empty">Tidak ada pengemudi yang sesuai dengan filter yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $pengemudi->links() }}
    </section>
@endsection
