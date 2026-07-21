@extends('admin.layouts.app')

@section('title', 'Jadwal Perjalanan')
@section('page-title', 'Jadwal Perjalanan')
@section('page-description', 'Susun jadwal keberangkatan, hubungkan rute, armada, pengemudi, dan bangun denah kursi otomatis untuk setiap trip.')

@section('content')
    <section class="travs-section-card card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between">
            <div class="travs-panel-title">
                <h3>Daftar Jadwal</h3>
                <p>Monitoring jadwal mencakup armada, pengemudi, kursi tersedia, dan jumlah pemesanan yang sudah masuk.</p>
            </div>
            <a href="{{ route('admin.jadwal.create') }}" class="travs-button btn btn-primary">Tambah Jadwal</a>
        </div>

        <form method="GET" class="travs-filter-grid d-grid">
            <div class="travs-field">
                <label for="search" class="form-label">Cari Jadwal</label>
                <input id="search" name="search" class="travs-input form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Rute, armada, atau pengemudi">
            </div>
            <div class="travs-field">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="travs-select form-select">
                    <option value="">Semua status</option>
                    @foreach (['draft' => 'Draft', 'dibuka' => 'Dibuka', 'ditutup' => 'Ditutup', 'berangkat' => 'Berangkat', 'selesai' => 'Selesai'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="travs-field">
                <label for="tanggal" class="form-label">Tanggal</label>
                <input id="tanggal" name="tanggal" type="date" class="travs-input form-control" value="{{ $filters['tanggal'] ?? '' }}">
            </div>
            <div class="travs-filter-actions d-flex align-items-end flex-wrap">
                <button type="submit" class="travs-button-secondary btn btn-secondary">Terapkan Filter</button>
                <a href="{{ route('admin.jadwal.index') }}" class="travs-button-secondary btn btn-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap table-responsive">
            <table class="travs-table table mb-0">
                <thead>
                    <tr>
                        <th>Keberangkatan</th>
                        <th>Rute</th>
                        <th>Armada</th>
                        <th>Pengemudi</th>
                        <th>Kursi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jadwal as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->waktu_berangkat->translatedFormat('d M Y') }}</strong>
                                <div class="travs-table-subcopy">{{ $item->waktu_berangkat->format('H:i') }} &middot; {{ ucfirst($item->status) }}</div>
                            </td>
                            <td>{{ $item->rute->kota_asal }} - {{ $item->rute->kota_tujuan }}</td>
                            <td>{{ $item->kendaraan->label_armada }}</td>
                            <td>{{ $item->pengemudi->pengguna->nama }}</td>
                            <td>{{ $item->kursi_tersedia }} tersedia / {{ $item->pemesanan_count }} pemesanan</td>
                            <td>
                                <div class="travs-table-actions d-flex align-items-center flex-wrap">
                                    <a href="{{ route('admin.jadwal.show', $item) }}" class="travs-button-link btn btn-link">Detail</a>
                                    <a href="{{ route('admin.jadwal.edit', $item) }}" class="travs-button-link btn btn-link">Edit</a>
                                    <form method="POST" action="{{ route('admin.jadwal.destroy', $item) }}" onsubmit="return confirm('Hapus jadwal ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="travs-button-link travs-button-link-danger btn btn-link">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="travs-table-empty">Belum ada jadwal perjalanan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $jadwal->links() }}
    </section>
@endsection
