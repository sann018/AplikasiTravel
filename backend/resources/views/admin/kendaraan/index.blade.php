@extends('admin.layouts.app')

@section('title', 'Armada Kendaraan')
@section('page-title', 'Armada Kendaraan')
@section('page-description', 'Kelola data kendaraan travel.')

@section('content')
    <section class="travs-section-card card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between">
            <div class="travs-panel-title">
                <h3>Daftar Armada</h3>
                <p>Pakai filter untuk memantau unit aktif, riwayat pemakaian, dan kesiapan armada.</p>
            </div>
            <a href="{{ route('admin.kendaraan.create') }}" class="travs-button btn btn-primary">Tambah Armada</a>
        </div>

        <form method="GET" class="travs-filter-grid d-grid">
            <div class="travs-field">
                <label for="search" class="form-label">Cari Armada</label>
                <input id="search" name="search" class="travs-input form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Plat, merk, atau tipe">
            </div>
            <div class="travs-field">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="travs-select form-select">
                    <option value="">Semua status</option>
                    <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                    <option value="perawatan" @selected(($filters['status'] ?? '') === 'perawatan')>Perawatan</option>
                    <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="travs-filter-actions d-flex align-items-end flex-wrap">
                <button type="submit" class="travs-button btn btn-primary">Terapkan Filter</button>
                <a href="{{ route('admin.kendaraan.index') }}" class="travs-button-secondary btn btn-outline-secondary" data-clear-form-draft>Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap table-responsive">
            <table class="travs-table table mb-0">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Armada</th>
                        <th class="text-center">Kapasitas</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Jadwal</th>
                        <th class="text-end">Aksi</th>
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
                            <td class="text-center">{{ $item->kapasitas_kursi }} kursi</td>
                            <td class="text-center">
                                <span class="travs-status-pill travs-status-{{ $item->status_kendaraan }} badge rounded-pill">
                                    {{ ucfirst($item->status_kendaraan) }}
                                </span>
                            </td>
                            <td class="text-center">{{ number_format($item->jadwal_count) }} jadwal</td>
                            <td class="text-end">
                                <div class="travs-table-actions d-flex align-items-center justify-content-end flex-wrap">
                                    <a href="{{ route('admin.kendaraan.edit', $item) }}" class="travs-button-link btn btn-link">Edit</a>
                                    <form method="POST" action="{{ route('admin.kendaraan.destroy', $item) }}" onsubmit="return confirm('Hapus armada ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="travs-button-link travs-button-link-danger btn btn-link">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="travs-table-empty">Tidak ada armada yang sesuai dengan filter yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $kendaraan->links() }}
    </section>
@endsection
