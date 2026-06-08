@extends('admin.layouts.app')

@section('title', 'Edit Pengemudi')
@section('page-title', 'Edit Pengemudi')
@section('page-description', 'Perbarui data pengemudi untuk menjaga informasi akun tetap akurat.')

@section('content')
    <div class="travs-page-frame">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Form Edit Pengemudi</h3>
                <p>Perbarui data pengemudi {{ $pengemudi->pengguna->nama }}.</p>
            </div>
            <a href="{{ route('admin.pengemudi.index') }}" class="travs-button-secondary">Kembali</a>
        </div>

        @if ($errors->any())
            <div class="travs-alert travs-alert-danger">
                <strong>Terjadi kesalahan:</strong>
                <ul style="margin: 8px 0 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.pengemudi.update', $pengemudi) }}" class="travs-form-grid" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="travs-form-grid-2">
                <div class="travs-field">
                    <label for="nama">Nama Lengkap</label>
                    <input id="nama" name="nama" class="travs-input" value="{{ old('nama', $pengemudi->pengguna->nama) }}" required>
                </div>
                <div class="travs-field">
                    <label for="nomor_sim">Nomor SIM</label>
                    <input id="nomor_sim" name="nomor_sim" class="travs-input" value="{{ old('nomor_sim', $pengemudi->nomor_sim) }}" required>
                </div>
                <div class="travs-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" class="travs-input" value="{{ old('email', $pengemudi->pengguna->email) }}" required>
                </div>
                <div class="travs-field">
                    <label for="no_hp">Nomor HP</label>
                    <input id="no_hp" name="no_hp" class="travs-input" value="{{ old('no_hp', $pengemudi->pengguna->no_hp) }}" required>
                </div>
                <div class="travs-field">
                    <label for="foto_sim">Foto SIM / URL Dokumen</label>
                    <input id="foto_sim" name="foto_sim" class="travs-input" value="{{ old('foto_sim', $pengemudi->foto_sim) }}">
                </div>
                <div class="travs-field">
                    <label for="foto_profil">Foto Profil Pengemudi</label>
                    @if ($pengemudi->pengguna->foto_profil)
                        <div class="travs-current-photo">
                            <img src="/storage/{{ ltrim($pengemudi->pengguna->foto_profil, '/') }}" alt="Foto {{ $pengemudi->pengguna->nama }}">
                            <span>Foto profil aktif</span>
                        </div>
                    @endif
                    <input id="foto_profil" name="foto_profil" type="file" class="travs-input" accept="image/*">
                    <div class="travs-field-meta">Kosongkan jika tidak ingin mengganti foto. Maksimal 2MB.</div>
                </div>
                <div class="travs-field">
                    <label for="status">Status Akun</label>
                    <select id="status" name="status" class="travs-select" required>
                        <option value="aktif" @selected(old('status', $pengemudi->status) === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status', $pengemudi->status) === 'nonaktif')>Nonaktif</option>
                    </select>
                </div>
                <div class="travs-field">
                    <label for="status_verifikasi">Status Verifikasi</label>
                    <select id="status_verifikasi" name="status_verifikasi" class="travs-select" required>
                        <option value="menunggu" @selected(old('status_verifikasi', $pengemudi->status_verifikasi) === 'menunggu')>Menunggu Verifikasi</option>
                        <option value="terverifikasi" @selected(old('status_verifikasi', $pengemudi->status_verifikasi) === 'terverifikasi')>Terverifikasi</option>
                        <option value="ditolak" @selected(old('status_verifikasi', $pengemudi->status_verifikasi) === 'ditolak')>Ditolak</option>
                    </select>
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="password">Password Baru (opsional)</label>
                    <input id="password" name="password" type="password" class="travs-input" placeholder="Kosongkan jika tidak diubah">
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="catatan_verifikasi">Catatan Verifikasi</label>
                    <textarea id="catatan_verifikasi" name="catatan_verifikasi" class="travs-textarea" placeholder="Catatan admin untuk hasil verifikasi dokumen pengemudi">{{ old('catatan_verifikasi', $pengemudi->catatan_verifikasi) }}</textarea>
                </div>
            </div>

            <div class="travs-form-actions travs-inline">
                <a href="{{ route('admin.pengemudi.index') }}" class="travs-button-secondary">Batal</a>
                <button type="submit" class="travs-button">Simpan Perubahan</button>
            </div>
        </form>
    </div>
@endsection
