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
                <ul style="margin: 0; padding-left: 18px;">
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
                    <input id="nama" name="nama" class="travs-input @error('nama') is-invalid @enderror" value="{{ old('nama') }}" required placeholder="Contoh: Subianto">
                    @include('admin.partials.field-error', ['name' => 'nama'])
                </div>
                <div class="travs-field">
                    <label for="nomor_sim">Nomor SIM (NIK KTP)</label>
                    <input id="nomor_sim" name="nomor_sim" class="travs-input @error('nomor_sim') is-invalid @enderror" value="{{ old('nomor_sim') }}" required minlength="16" maxlength="16" inputmode="numeric" pattern="[0-9]{16}" placeholder="Contoh: 6102233435450001">
                    <div class="travs-field-meta">Gunakan NIK KTP pengemudi yang terdiri dari tepat 16 digit angka.</div>
                    @include('admin.partials.field-error', ['name' => 'nomor_sim'])
                </div>
                <div class="travs-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" class="travs-input @error('email') is-invalid @enderror" value="{{ old('email') }}" required placeholder="Contoh: budi@travs.com">
                    @include('admin.partials.field-error', ['name' => 'email'])
                </div>
                <div class="travs-field">
                    <label for="no_hp">Nomor HP</label>
                    <input id="no_hp" name="no_hp" class="travs-input @error('no_hp') is-invalid @enderror" value="{{ old('no_hp') }}" required minlength="10" maxlength="16" inputmode="tel" pattern="(\+62|62|0)8[1-9][0-9]{7,10}" placeholder="Contoh: 081234567890">
                    <div class="travs-field-meta">Nomor Indonesia, contoh 081234567890 atau +6281234567890.</div>
                    @include('admin.partials.field-error', ['name' => 'no_hp'])
                </div>
                <div class="travs-field">
                    <label for="foto_sim">Foto SIM</label>
                    <input id="foto_sim" name="foto_sim" type="file" class="travs-input @error('foto_sim') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="travs-field-meta">Upload gambar JPG, PNG, atau WEBP. Maksimal 2MB.</div>
                    @include('admin.partials.field-error', ['name' => 'foto_sim'])
                </div>
                <div class="travs-field">
                    <label for="foto_profil">Foto Profil Pengemudi</label>
                    <input id="foto_profil" name="foto_profil" type="file" class="travs-input @error('foto_profil') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="travs-field-meta">Format JPG, PNG, atau WEBP. Maksimal 2MB.</div>
                    @include('admin.partials.field-error', ['name' => 'foto_profil'])
                </div>
                <div class="travs-field">
                    <label for="status">Status Akun</label>
                    <select id="status" name="status" class="travs-select @error('status') is-invalid @enderror" required>
                        <option value="aktif" @selected(old('status', 'aktif') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status') === 'nonaktif')>Nonaktif</option>
                    </select>
                    @include('admin.partials.field-error', ['name' => 'status'])
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="password">Password Awal</label>
                    <input id="password" name="password" type="password" class="travs-input @error('password') is-invalid @enderror" placeholder="Minimal 8 karakter">
                    @include('admin.partials.field-error', ['name' => 'password'])
                </div>
            </div>

            <div class="travs-form-actions">
                <button type="submit" class="travs-button">Simpan Pengemudi</button>
            </div>
        </form>
    </div>
@endsection
