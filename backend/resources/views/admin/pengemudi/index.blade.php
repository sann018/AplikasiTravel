@extends('admin.layouts.app')

@section('title', 'Pengemudi')
@section('page-title', 'Kelola Pengemudi')
@section('page-description', 'Buat, aktifkan, dan perbarui akun pengemudi yang akan dipakai pada jadwal perjalanan RuslyTravel.')

@section('content')
    <section class="travs-summary-grid">
        <article class="travs-summary-card">
            <small>Total Pengemudi</small>
            <strong>{{ number_format($stats['total']) }}</strong>
            <span>Seluruh akun driver</span>
        </article>
        <article class="travs-summary-card">
            <small>Aktif</small>
            <strong>{{ number_format($stats['aktif']) }}</strong>
            <span>Siap menerima tugas</span>
        </article>
        <article class="travs-summary-card">
            <small>Nonaktif</small>
            <strong>{{ number_format($stats['nonaktif']) }}</strong>
            <span>Belum dijadwalkan</span>
        </article>
        <article class="travs-summary-card">
            <small>Terverifikasi</small>
            <strong>{{ number_format($stats['terverifikasi']) }}</strong>
            <span>Sudah lolos validasi admin</span>
        </article>
    </section>

    <section class="travs-section-card">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Daftar Pengemudi</h3>
                <p>Gunakan pencarian untuk memantau status aktivasi dan kesiapan pengemudi pada jadwal aktif.</p>
            </div>
            <a href="{{ route('admin.pengemudi.create') }}" class="travs-button">Tambah Pengemudi</a>
        </div>

        <form method="GET" class="travs-filter-grid">
            <div class="travs-field">
                <label for="search">Cari Pengemudi</label>
                <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, email, nomor HP, nomor SIM">
            </div>
            <div class="travs-field">
                <label for="status">Status</label>
                <select id="status" name="status" class="travs-select">
                    <option value="">Semua status</option>
                    <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="travs-field">
                <label for="status_verifikasi_filter">Verifikasi</label>
                <select id="status_verifikasi_filter" name="status_verifikasi" class="travs-select">
                    <option value="">Semua verifikasi</option>
                    <option value="menunggu" @selected(($filters['status_verifikasi'] ?? '') === 'menunggu')>Menunggu</option>
                    <option value="terverifikasi" @selected(($filters['status_verifikasi'] ?? '') === 'terverifikasi')>Terverifikasi</option>
                    <option value="ditolak" @selected(($filters['status_verifikasi'] ?? '') === 'ditolak')>Ditolak</option>
                </select>
            </div>
            <div class="travs-filter-actions">
                <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                <a href="{{ route('admin.pengemudi.index') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap">
            <table class="travs-table">
                <thead>
                    <tr>
                        <th>Pengemudi</th>
                        <th>Kontak</th>
                        <th>Status</th>
                        <th>Jadwal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pengemudi as $item)
                        <tr>
                            <td>
                                <div class="travs-person-cell">
                                    <div class="travs-avatar">
                                        @if ($item->pengguna->foto_profil)
                                            <img src="/storage/{{ ltrim($item->pengguna->foto_profil, '/') }}" alt="Foto {{ $item->pengguna->nama }}">
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
                                    <span class="travs-status-pill travs-status-{{ $item->status }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                    <span class="travs-status-pill travs-status-{{ $item->status_verifikasi }}">
                                        {{ ucfirst($item->status_verifikasi) }}
                                    </span>
                                </div>
                                <div class="travs-table-subcopy">
                                    @if ($item->verified_at)
                                        Diverifikasi {{ $item->verified_at->translatedFormat('d M Y H:i') }}
                                    @elseif ($item->catatan_verifikasi)
                                        {{ $item->catatan_verifikasi }}
                                    @else
                                        Menunggu review admin
                                    @endif
                                </div>
                            </td>
                            <td>{{ number_format($item->jadwal_count) }} jadwal</td>
                            <td>
                                <div class="travs-table-actions">
                                    <a href="{{ route('admin.pengemudi.edit', $item) }}" class="travs-button-link">Edit</a>
                                    <form method="POST" action="{{ route('admin.pengemudi.destroy', $item) }}" onsubmit="return confirm('Hapus akun pengemudi ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="travs-button-link travs-button-link-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="travs-table-empty">Belum ada akun pengemudi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $pengemudi->links() }}
    </section>
@endsection
