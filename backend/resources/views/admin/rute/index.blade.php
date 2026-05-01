@extends('admin.layouts.app')

@section('title', 'Rute Perjalanan')
@section('page-title', 'Rute Perjalanan')
@section('page-description', 'Kelola jalur lintas kota, harga tiket, estimasi durasi, dan status aktif rute untuk kebutuhan jadwal.')

@section('content')
    <section class="travs-summary-grid">
        <article class="travs-summary-card">
            <small>Total Rute</small>
            <strong>{{ number_format($stats['total']) }}</strong>
            <span>Seluruh jalur terdaftar</span>
        </article>
        <article class="travs-summary-card">
            <small>Rute Aktif</small>
            <strong>{{ number_format($stats['aktif']) }}</strong>
            <span>Siap digunakan booking</span>
        </article>
        <article class="travs-summary-card">
            <small>Rute Nonaktif</small>
            <strong>{{ number_format($stats['nonaktif']) }}</strong>
            <span>Tidak tampil ke pengguna</span>
        </article>
    </section>

    <section class="travs-split-grid">
        <article class="travs-section-card travs-form-card">
            <div class="travs-panel-title">
                <h3>{{ $editingRute ? 'Edit Rute' : 'Tambah Rute' }}</h3>
                <p>Rute aktif akan menjadi dasar daftar jadwal, harga tiket, dan estimasi perjalanan.</p>
            </div>

            <form method="POST" action="{{ $editingRute ? route('admin.rute.update', $editingRute) : route('admin.rute.store') }}" class="travs-form-grid">
                @csrf
                @if ($editingRute)
                    @method('PUT')
                @endif

                <div class="travs-form-grid-2">
                    <div class="travs-field">
                        <label for="kota_asal">Kota Asal</label>
                        <input id="kota_asal" name="kota_asal" class="travs-input" value="{{ old('kota_asal', $editingRute?->kota_asal) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="kota_tujuan">Kota Tujuan</label>
                        <input id="kota_tujuan" name="kota_tujuan" class="travs-input" value="{{ old('kota_tujuan', $editingRute?->kota_tujuan) }}" required>
                    </div>
                    <div class="travs-field travs-field-span-2">
                        <label for="deskripsi_jalur">Deskripsi Jalur</label>
                        <input id="deskripsi_jalur" name="deskripsi_jalur" class="travs-input" value="{{ old('deskripsi_jalur', $editingRute?->deskripsi_jalur) }}" placeholder="Contoh: Pontianak - Tayan - Sanggau">
                    </div>
                    <div class="travs-field">
                        <label for="jarak_km">Jarak (KM)</label>
                        <input id="jarak_km" name="jarak_km" type="number" step="0.01" class="travs-input" value="{{ old('jarak_km', $editingRute?->jarak_km) }}">
                    </div>
                    <div class="travs-field">
                        <label for="estimasi_durasi_menit">Estimasi Durasi (menit)</label>
                        <input id="estimasi_durasi_menit" name="estimasi_durasi_menit" type="number" class="travs-input" value="{{ old('estimasi_durasi_menit', $editingRute?->estimasi_durasi_menit) }}">
                    </div>
                    <div class="travs-field">
                        <label for="harga_tiket">Harga Tiket</label>
                        <input id="harga_tiket" name="harga_tiket" type="number" step="0.01" class="travs-input" value="{{ old('harga_tiket', $editingRute?->harga_tiket) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="status_aktif">Status Rute</label>
                        <select id="status_aktif" name="status_aktif" class="travs-select">
                            <option value="1" @selected((string) old('status_aktif', (int) ($editingRute?->status_aktif ?? 1)) === '1')>Aktif</option>
                            <option value="0" @selected((string) old('status_aktif', (int) ($editingRute?->status_aktif ?? 1)) === '0')>Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="travs-form-actions travs-inline">
                    @if ($editingRute)
                        <a href="{{ route('admin.rute.index') }}" class="travs-button-secondary">Batal Edit</a>
                    @endif
                    <button type="submit" class="travs-button">{{ $editingRute ? 'Simpan Perubahan' : 'Tambah Rute' }}</button>
                </div>
            </form>
        </article>

        <article class="travs-section-card">
            <div class="travs-panel-header">
                <div class="travs-panel-title">
                    <h3>Daftar Rute</h3>
                    <p>Semua jadwal aktif akan mengambil referensi dari rute dan harga tiket yang ada di sini.</p>
                </div>
            </div>

            <form method="GET" class="travs-filter-grid">
                <div class="travs-field">
                    <label for="search">Cari Rute</label>
                    <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Asal, tujuan, atau jalur">
                </div>
                <div class="travs-field">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="travs-select">
                        <option value="">Semua status</option>
                        <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
                    </select>
                </div>
                <div class="travs-filter-actions">
                    <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                    <a href="{{ route('admin.rute.index') }}" class="travs-button-secondary">Reset</a>
                </div>
            </form>

            <div class="travs-table-wrap">
                <table class="travs-table">
                    <thead>
                        <tr>
                            <th>Rute</th>
                            <th>Harga</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rute as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->kota_asal }} - {{ $item->kota_tujuan }}</strong>
                                    <div class="travs-table-subcopy">{{ $item->deskripsi_jalur ?: 'Deskripsi jalur belum diisi' }}</div>
                                </td>
                                <td>Rp{{ number_format($item->harga_tiket, 0, ',', '.') }}</td>
                                <td>{{ $item->estimasi_durasi_menit ? $item->estimasi_durasi_menit.' menit' : '-' }}</td>
                                <td>
                                    <span class="travs-status-pill travs-status-{{ $item->status_aktif ? 'aktif' : 'nonaktif' }}">
                                        {{ $item->status_aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="travs-table-actions">
                                        <a href="{{ route('admin.rute.index', ['edit' => $item->id] + request()->query()) }}" class="travs-button-link">Edit</a>
                                        <form method="POST" action="{{ route('admin.rute.destroy', $item) }}" onsubmit="return confirm('Hapus rute ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="travs-button-link travs-button-link-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="travs-table-empty">Belum ada data rute perjalanan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $rute->links() }}
        </article>
    </section>
@endsection
