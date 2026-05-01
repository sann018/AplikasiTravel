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

    <section class="travs-split-grid">
        <article class="travs-section-card travs-form-card">
            <div class="travs-panel-title">
                <h3>{{ $editingKendaraan ? 'Edit Armada' : 'Tambah Armada' }}</h3>
                <p>Lengkapi data kendaraan agar jadwal dan kapasitas kursi bisa disusun otomatis.</p>
            </div>

            <form method="POST" action="{{ $editingKendaraan ? route('admin.kendaraan.update', $editingKendaraan) : route('admin.kendaraan.store') }}" class="travs-form-grid">
                @csrf
                @if ($editingKendaraan)
                    @method('PUT')
                @endif

                <div class="travs-form-grid-2">
                    <div class="travs-field">
                        <label for="nomor_plat">Nomor Plat</label>
                        <input id="nomor_plat" name="nomor_plat" class="travs-input" value="{{ old('nomor_plat', $editingKendaraan?->nomor_plat) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="merk_kendaraan">Merk Kendaraan</label>
                        <input id="merk_kendaraan" name="merk_kendaraan" class="travs-input" value="{{ old('merk_kendaraan', $editingKendaraan?->merk_kendaraan) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="warna">Warna</label>
                        <input id="warna" name="warna" class="travs-input" value="{{ old('warna', $editingKendaraan?->warna) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="tahun_kendaraan">Tahun</label>
                        <input id="tahun_kendaraan" name="tahun_kendaraan" type="number" class="travs-input" value="{{ old('tahun_kendaraan', $editingKendaraan?->tahun_kendaraan) }}">
                    </div>
                    <div class="travs-field">
                        <label for="tipe_kendaraan">Tipe Kendaraan</label>
                        <input id="tipe_kendaraan" name="tipe_kendaraan" class="travs-input" value="{{ old('tipe_kendaraan', $editingKendaraan?->tipe_kendaraan) }}">
                    </div>
                    <div class="travs-field">
                        <label for="kapasitas_kursi">Kapasitas Kursi</label>
                        <input id="kapasitas_kursi" name="kapasitas_kursi" type="number" class="travs-input" value="{{ old('kapasitas_kursi', $editingKendaraan?->kapasitas_kursi) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="total_kilometer">Total Kilometer</label>
                        <input id="total_kilometer" name="total_kilometer" type="number" class="travs-input" value="{{ old('total_kilometer', $editingKendaraan?->total_kilometer ?? 0) }}">
                    </div>
                    <div class="travs-field">
                        <label for="km_servis_berikutnya">KM Servis Berikutnya</label>
                        <input id="km_servis_berikutnya" name="km_servis_berikutnya" type="number" class="travs-input" value="{{ old('km_servis_berikutnya', $editingKendaraan?->km_servis_berikutnya) }}">
                    </div>
                    <div class="travs-field">
                        <label for="tgl_terakhir_servis">Tanggal Servis Terakhir</label>
                        <input id="tgl_terakhir_servis" name="tgl_terakhir_servis" type="date" class="travs-input" value="{{ old('tgl_terakhir_servis', optional($editingKendaraan?->tgl_terakhir_servis)->format('Y-m-d')) }}">
                    </div>
                    <div class="travs-field">
                        <label for="tgl_pajak_tahunan">Tanggal Pajak Tahunan</label>
                        <input id="tgl_pajak_tahunan" name="tgl_pajak_tahunan" type="date" class="travs-input" value="{{ old('tgl_pajak_tahunan', optional($editingKendaraan?->tgl_pajak_tahunan)->format('Y-m-d')) }}">
                    </div>
                    <div class="travs-field travs-field-span-2">
                        <label for="fasilitas">Fasilitas</label>
                        <textarea id="fasilitas" name="fasilitas" class="travs-textarea">{{ old('fasilitas', $editingKendaraan?->fasilitas) }}</textarea>
                    </div>
                    <div class="travs-field travs-field-span-2">
                        <label for="status_kendaraan">Status Kendaraan</label>
                        <select id="status_kendaraan" name="status_kendaraan" class="travs-select" required>
                            @foreach (['aktif' => 'Aktif', 'perawatan' => 'Perawatan', 'nonaktif' => 'Nonaktif'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status_kendaraan', $editingKendaraan?->status_kendaraan ?? 'aktif') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="travs-form-actions travs-inline">
                    @if ($editingKendaraan)
                        <a href="{{ route('admin.kendaraan.index') }}" class="travs-button-secondary">Batal Edit</a>
                    @endif
                    <button type="submit" class="travs-button">{{ $editingKendaraan ? 'Simpan Perubahan' : 'Tambah Armada' }}</button>
                </div>
            </form>
        </article>

        <article class="travs-section-card">
            <div class="travs-panel-header">
                <div class="travs-panel-title">
                    <h3>Daftar Armada</h3>
                    <p>Pakai filter untuk memantau unit aktif, riwayat pemakaian, dan kesiapan armada.</p>
                </div>
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
                                        <a href="{{ route('admin.kendaraan.index', ['edit' => $item->id] + request()->query()) }}" class="travs-button-link">Edit</a>
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
        </article>
    </section>
@endsection
