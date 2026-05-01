@extends('admin.layouts.app')

@section('title', 'Pengemudi')
@section('page-title', 'Kelola Pengemudi')
@section('page-description', 'Buat, aktifkan, dan perbarui akun pengemudi yang akan dipakai pada jadwal perjalanan TravS.')

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

    <section class="travs-split-grid">
        <article class="travs-section-card travs-form-card">
            <div class="travs-panel-title">
                <h3>{{ $editingPengemudi ? 'Edit Pengemudi' : 'Tambah Pengemudi' }}</h3>
                <p>Pengelolaan akun pengemudi dipisahkan dari pengaturan sistem agar administrasinya lebih jelas.</p>
            </div>

            <form method="POST" action="{{ $editingPengemudi ? route('admin.pengemudi.update', $editingPengemudi) : route('admin.pengemudi.store') }}" class="travs-form-grid">
                @csrf
                @if ($editingPengemudi)
                    @method('PUT')
                @endif

                <div class="travs-form-grid-2">
                    <div class="travs-field">
                        <label for="nama">Nama Lengkap</label>
                        <input id="nama" name="nama" class="travs-input" value="{{ old('nama', $editingPengemudi?->pengguna?->nama) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="nomor_sim">Nomor SIM</label>
                        <input id="nomor_sim" name="nomor_sim" class="travs-input" value="{{ old('nomor_sim', $editingPengemudi?->nomor_sim) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" class="travs-input" value="{{ old('email', $editingPengemudi?->pengguna?->email) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="no_hp">Nomor HP</label>
                        <input id="no_hp" name="no_hp" class="travs-input" value="{{ old('no_hp', $editingPengemudi?->pengguna?->no_hp) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="foto_sim">Foto SIM / URL Dokumen</label>
                        <input id="foto_sim" name="foto_sim" class="travs-input" value="{{ old('foto_sim', $editingPengemudi?->foto_sim) }}">
                    </div>
                    <div class="travs-field">
                        <label for="status">Status Akun</label>
                        <select id="status" name="status" class="travs-select" required>
                            <option value="aktif" @selected(old('status', $editingPengemudi?->status ?? 'aktif') === 'aktif')>Aktif</option>
                            <option value="nonaktif" @selected(old('status', $editingPengemudi?->status) === 'nonaktif')>Nonaktif</option>
                        </select>
                    </div>
                    <div class="travs-field">
                        <label for="status_verifikasi">Status Verifikasi</label>
                        <select id="status_verifikasi" name="status_verifikasi" class="travs-select" required>
                            <option value="menunggu" @selected(old('status_verifikasi', $editingPengemudi?->status_verifikasi ?? 'menunggu') === 'menunggu')>Menunggu Verifikasi</option>
                            <option value="terverifikasi" @selected(old('status_verifikasi', $editingPengemudi?->status_verifikasi) === 'terverifikasi')>Terverifikasi</option>
                            <option value="ditolak" @selected(old('status_verifikasi', $editingPengemudi?->status_verifikasi) === 'ditolak')>Ditolak</option>
                        </select>
                    </div>
                    <div class="travs-field travs-field-span-2">
                        <label for="password">{{ $editingPengemudi ? 'Password Baru (opsional)' : 'Password Awal' }}</label>
                        <input id="password" name="password" type="password" class="travs-input" placeholder="{{ $editingPengemudi ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter' }}">
                    </div>
                    <div class="travs-field travs-field-span-2">
                        <label for="catatan_verifikasi">Catatan Verifikasi</label>
                        <textarea id="catatan_verifikasi" name="catatan_verifikasi" class="travs-textarea" placeholder="Catatan admin untuk hasil verifikasi dokumen pengemudi">{{ old('catatan_verifikasi', $editingPengemudi?->catatan_verifikasi) }}</textarea>
                    </div>
                </div>

                <div class="travs-form-actions travs-inline">
                    @if ($editingPengemudi)
                        <a href="{{ route('admin.pengemudi.index') }}" class="travs-button-secondary">Batal Edit</a>
                    @endif
                    <button type="submit" class="travs-button">{{ $editingPengemudi ? 'Simpan Perubahan' : 'Tambah Pengemudi' }}</button>
                </div>
            </form>
        </article>

        <article class="travs-section-card">
            <div class="travs-panel-header">
                <div class="travs-panel-title">
                    <h3>Daftar Pengemudi</h3>
                    <p>Gunakan pencarian untuk memantau status aktivasi dan kesiapan pengemudi pada jadwal aktif.</p>
                </div>
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
                                    <strong>{{ $item->pengguna->nama }}</strong>
                                    <div class="travs-table-subcopy">SIM {{ $item->nomor_sim }}</div>
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
                                        <a href="{{ route('admin.pengemudi.index', ['edit' => $item->id] + request()->query()) }}" class="travs-button-link">Edit</a>
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
        </article>
    </section>
@endsection
