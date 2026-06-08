@extends('admin.layouts.app')

@section('title', 'Tambah Pengemudi')
@section('page-title', 'Tambah Pengemudi')
@section('page-description', 'Buat akun pengemudi baru untuk menerima tugas jadwal perjalanan.')

@section('content')
    <div class="travs-page-frame">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Form Tambah Pengemudi</h3>
                <p>Isi data pengemudi dengan lengkap untuk memudahkan penugasan jadwal perjalanan.</p>
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

        <form method="POST" action="{{ route('admin.pengemudi.store') }}" class="travs-form-grid" enctype="multipart/form-data">
            @csrf

            <div class="travs-form-grid-2">
                <div class="travs-field">
                    <label for="nama">Nama Lengkap</label>
                    <input id="nama" name="nama" class="travs-input" value="{{ old('nama') }}" required placeholder="Contoh: Budi Santoso">
                </div>
                <div class="travs-field">
                    <label for="nomor_sim">Nomor SIM</label>
                    <input id="nomor_sim" name="nomor_sim" class="travs-input" value="{{ old('nomor_sim') }}" required placeholder="Contoh: 6171234567">
                </div>
                <div class="travs-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" class="travs-input" value="{{ old('email') }}" required placeholder="Contoh: budi@travs.com">
                </div>
                <div class="travs-field">
                    <label for="no_hp">Nomor HP</label>
                    <input id="no_hp" name="no_hp" class="travs-input" value="{{ old('no_hp') }}" required placeholder="Contoh: 08123456789">
                </div>
                <div class="travs-field">
                    <label for="foto_sim">Foto SIM / URL Dokumen</label>
                    <input id="foto_sim" name="foto_sim" class="travs-input" value="{{ old('foto_sim') }}" placeholder="URL foto SIM atau upload">
                </div>
                <div class="travs-field">
                    <label for="foto_profil">Foto Profil Pengemudi</label>
                    <input id="foto_profil" name="foto_profil" type="file" class="travs-input" accept="image/*">
                    <div class="travs-field-meta">Format JPG, PNG, atau WEBP. Maksimal 2MB.</div>
                </div>
                <div class="travs-field">
                    <label for="status">Status Akun</label>
                    <select id="status" name="status" class="travs-select" required>
                        <option value="aktif" @selected(old('status', 'aktif') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status') === 'nonaktif')>Nonaktif</option>
                    </select>
                </div>
                <div class="travs-field">
                    <label for="status_verifikasi">Status Verifikasi</label>
                    <select id="status_verifikasi" name="status_verifikasi" class="travs-select" required>
                        <option value="menunggu" @selected(old('status_verifikasi', 'menunggu') === 'menunggu')>Menunggu Verifikasi</option>
                        <option value="terverifikasi" @selected(old('status_verifikasi') === 'terverifikasi')>Terverifikasi</option>
                        <option value="ditolak" @selected(old('status_verifikasi') === 'ditolak')>Ditolak</option>
                    </select>
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="password">Password Awal</label>
                    <input id="password" name="password" type="password" class="travs-input" placeholder="Minimal 8 karakter">
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="catatan_verifikasi">Catatan Verifikasi</label>
                    <textarea id="catatan_verifikasi" name="catatan_verifikasi" class="travs-textarea" placeholder="Catatan admin untuk hasil verifikasi dokumen pengemudi">{{ old('catatan_verifikasi') }}</textarea>
                </div>
            </div>

            <div class="travs-form-actions">
                <button type="submit" class="travs-button">Simpan Pengemudi</button>
            </div>
        </form>
    </div>
@endsection
