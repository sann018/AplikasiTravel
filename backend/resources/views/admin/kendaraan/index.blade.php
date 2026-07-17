@extends('admin.layouts.app')

@section('title', 'Armada Kendaraan')
@section('page-title', 'Armada Kendaraan')
@section('page-description', 'Kelola data kendaraan travel.')

@section('content')
    <section class="travs-section-card">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Daftar Armada</h3>
                <p>Pakai filter untuk memantau unit aktif, riwayat pemakaian, dan kesiapan armada.</p>
            </div>
            <a href="{{ route('admin.kendaraan.create') }}" class="travs-button">Tambah Armada</a>
        </div>

        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-6 col-xl-4">
                <label for="search" class="form-label">Cari Armada</label>
                <input id="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Plat, merk, atau tipe">
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                    <option value="perawatan" @selected(($filters['status'] ?? '') === 'perawatan')>Perawatan</option>
                    <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                <a href="{{ route('admin.kendaraan.index') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap">
            <table class="travs-table">
                <thead>
                    <tr>
                        <th>Foto</th>
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
                                @if ($item->foto_kendaraan)
                                    <img
                                        class="travs-vehicle-thumb travs-photo-preview-trigger"
                                        src="/storage/{{ ltrim($item->foto_kendaraan, '/') }}"
                                        alt="Foto {{ $item->merk_kendaraan }}"
                                    >
                                @else
                                    <div class="travs-vehicle-thumb travs-vehicle-thumb-fallback" aria-label="Foto kendaraan belum ada">
                                        <i class="bi bi-car-front-fill"></i>
                                    </div>
                                @endif
                            </td>
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
                            <td colspan="6" class="travs-table-empty">Belum ada data armada kendaraan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $kendaraan->links() }}
    </section>
@endsection
