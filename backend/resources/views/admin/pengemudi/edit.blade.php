@extends('admin.layouts.app')

@section('title', 'Edit Pengemudi')
@section('page-title', 'Edit Pengemudi')
@section('page-description', 'Perbarui data pengemudi untuk menjaga informasi akun tetap akurat.')

@section('content')
    @include('admin.partials.page-back', [
        'url' => route('admin.pengemudi.index'),
        'label' => 'Kembali ke Daftar Pengemudi',
    ])

    <div class="travs-page-frame card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between">
            <div class="travs-panel-title">
                <h3>Form Edit Pengemudi</h3>
                <p>Perbarui data pengemudi {{ $pengemudi->pengguna->nama }}.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="travs-alert travs-alert-danger alert alert-danger">
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.pengemudi.update', $pengemudi) }}" class="travs-form-grid d-grid" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="travs-form-grid-2 d-grid">
                <div class="travs-field">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <input id="nama" name="nama" class="travs-input @error('nama') is-invalid @enderror form-control" value="{{ old('nama', $pengemudi->pengguna->nama) }}" required maxlength="100" pattern="[A-Za-z ]+" title="Nama hanya boleh berisi huruf dan spasi" oninput="this.value = this.value.replace(/[^A-Za-z ]/g, '').slice(0, 100)">
                    <div class="travs-field-meta">Maksimal 100 karakter. Hanya gunakan huruf dan spasi; angka tidak diperbolehkan.</div>
                    @include('admin.partials.field-error', ['name' => 'nama'])
                </div>
                <div class="travs-field">
                    <label for="nomor_sim" class="form-label">Nomor SIM (NIK KTP)</label>
                    <input id="nomor_sim" name="nomor_sim" class="travs-input @error('nomor_sim') is-invalid @enderror form-control" value="{{ old('nomor_sim', $pengemudi->nomor_sim) }}" required minlength="16" maxlength="16" inputmode="numeric" pattern="[0-9]{16}">
                    <div class="travs-field-meta">Gunakan NIK KTP pengemudi yang terdiri dari tepat 16 digit angka.</div>
                    @include('admin.partials.field-error', ['name' => 'nomor_sim'])
                </div>
                <div class="travs-field">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" name="email" type="email" class="travs-input @error('email') is-invalid @enderror form-control" value="{{ old('email', $pengemudi->pengguna->email) }}" required maxlength="100">
                    <div class="travs-field-meta">Gunakan alamat email aktif dari domain apa pun, misalnya Gmail, Yahoo, atau email kampus.</div>
                    @include('admin.partials.field-error', ['name' => 'email'])
                </div>
                <div class="travs-field">
                    <label for="no_hp" class="form-label">Nomor HP</label>
                    <input id="no_hp" name="no_hp" class="travs-input @error('no_hp') is-invalid @enderror form-control" value="{{ old('no_hp', $pengemudi->pengguna->no_hp) }}" required minlength="11" maxlength="12" inputmode="numeric" pattern="[0-9]{11,12}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12)">
                    <div class="travs-field-meta">Masukkan 11 sampai 12 digit angka tanpa spasi atau tanda baca.</div>
                    @include('admin.partials.field-error', ['name' => 'no_hp'])
                </div>
                <div class="travs-field">
                    <label for="foto_sim" class="form-label">Foto SIM</label>
                    @if ($pengemudi->foto_sim)
                        @php
                            $fotoSimUrl = str_starts_with($pengemudi->foto_sim, 'http')
                                ? $pengemudi->foto_sim
                                : asset('storage/'.ltrim($pengemudi->foto_sim, '/'));
                        @endphp
                        <div class="travs-current-photo travs-current-document">
                            <img class="travs-photo-preview-trigger" src="{{ $fotoSimUrl }}" alt="Foto SIM {{ $pengemudi->pengguna->nama }}">
                            <span>Foto SIM aktif</span>
                        </div>
                    @endif
                    <input id="foto_sim" name="foto_sim" type="file" class="travs-input @error('foto_sim') is-invalid @enderror form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="travs-field-meta">Kosongkan jika tidak ingin mengganti foto SIM. Maksimal 2MB.</div>
                    @include('admin.partials.field-error', ['name' => 'foto_sim'])
                </div>
                <div class="travs-field">
                    <label for="foto_profil" class="form-label">Foto Profil Pengemudi</label>
                    @if ($pengemudi->pengguna->foto_profil)
                        <div class="travs-current-photo">
                            <img class="travs-photo-preview-trigger" src="/storage/{{ ltrim($pengemudi->pengguna->foto_profil, '/') }}" alt="Foto {{ $pengemudi->pengguna->nama }}">
                            <span>Foto profil aktif</span>
                        </div>
                    @endif
                    <input id="foto_profil" name="foto_profil" type="file" class="travs-input @error('foto_profil') is-invalid @enderror form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="travs-field-meta">Kosongkan jika tidak ingin mengganti foto. Maksimal 2MB.</div>
                    @include('admin.partials.field-error', ['name' => 'foto_profil'])
                </div>
                <div class="travs-field">
                    <label for="status" class="form-label">Status Akun</label>
                    <select id="status" name="status" class="travs-select @error('status') is-invalid @enderror form-select" required>
                        <option value="aktif" @selected(old('status', $pengemudi->status) === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status', $pengemudi->status) === 'nonaktif')>Nonaktif</option>
                    </select>
                    @include('admin.partials.field-error', ['name' => 'status'])
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="password" class="form-label">Password Baru (opsional)</label>
                    <div class="travs-password-control">
                        <input id="password" name="password" type="password" class="travs-input @error('password') is-invalid @enderror form-control" minlength="6" maxlength="20" placeholder="Kosongkan jika tidak diubah">
                        <button type="button" class="travs-password-toggle" data-password-toggle data-password-target="password" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi" aria-pressed="false">
                            <i data-feather="eye" data-password-icon aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="travs-field-meta">Jika diisi, gunakan 6–20 karakter berisi huruf dan minimal satu angka atau simbol.</div>
                    @include('admin.partials.field-error', ['name' => 'password'])
                </div>
            </div>

            <div class="travs-form-actions travs-inline d-flex justify-content-end align-items-center flex-wrap">
                <a href="{{ route('admin.pengemudi.index') }}" class="travs-button-secondary btn btn-outline-secondary">Batal</a>
                <button type="submit" class="travs-button btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
@endsection
