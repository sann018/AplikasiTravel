@extends('admin.layouts.app')

@section('title', 'Tambah Pengemudi')
@section('page-title', 'Tambah Pengemudi')
@section('page-description', 'Buat akun pengemudi baru untuk menerima tugas jadwal perjalanan.')

@section('content')
    @include('admin.partials.page-back', [
        'url' => route('admin.pengemudi.index'),
        'label' => 'Kembali ke Daftar Pengemudi',
    ])

    <div class="travs-page-frame card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between">
            <div class="travs-panel-title">
                <h3>Form Tambah Pengemudi</h3>
                <p>Isi data pengemudi dengan lengkap untuk memudahkan penugasan jadwal perjalanan.</p>
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

        <form method="POST" action="{{ route('admin.pengemudi.store') }}" class="travs-form-grid d-grid" enctype="multipart/form-data">
            @csrf

            <div class="travs-form-grid-2 d-grid">
                <div class="travs-field">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <input id="nama" name="nama" class="travs-input @error('nama') is-invalid @enderror form-control" value="{{ old('nama') }}" required placeholder="Contoh: Subianto">
                    @include('admin.partials.field-error', ['name' => 'nama'])
                </div>
                <div class="travs-field">
                    <label for="nomor_sim" class="form-label">Nomor SIM (NIK KTP)</label>
                    <input id="nomor_sim" name="nomor_sim" class="travs-input @error('nomor_sim') is-invalid @enderror form-control" value="{{ old('nomor_sim') }}" required minlength="16" maxlength="16" inputmode="numeric" pattern="[0-9]{16}" placeholder="Contoh: 6102233435450001">
                    <div class="travs-field-meta">Gunakan NIK KTP pengemudi yang terdiri dari tepat 16 digit angka.</div>
                    @include('admin.partials.field-error', ['name' => 'nomor_sim'])
                </div>
                <div class="travs-field">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" name="email" type="email" class="travs-input @error('email') is-invalid @enderror form-control" value="{{ old('email') }}" required pattern="[A-Za-z0-9._%+-]+@gmail\.com" placeholder="Contoh: budi@gmail.com">
                    <div class="travs-field-meta">Gunakan alamat email @gmail.com.</div>
                    @include('admin.partials.field-error', ['name' => 'email'])
                </div>
                <div class="travs-field">
                    <label for="no_hp" class="form-label">Nomor HP</label>
                    <input id="no_hp" name="no_hp" class="travs-input @error('no_hp') is-invalid @enderror form-control" value="{{ old('no_hp') }}" required minlength="11" maxlength="12" inputmode="numeric" pattern="[0-9]{11,12}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12)" placeholder="Contoh: 081234567890">
                    <div class="travs-field-meta">Masukkan 11 sampai 12 digit angka tanpa spasi atau tanda baca.</div>
                    @include('admin.partials.field-error', ['name' => 'no_hp'])
                </div>
                <div class="travs-field">
                    <label for="foto_sim" class="form-label">Foto SIM</label>
                    <input id="foto_sim" name="foto_sim" type="file" class="travs-input @error('foto_sim') is-invalid @enderror form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="travs-field-meta">Upload gambar JPG, PNG, atau WEBP. Maksimal 2MB.</div>
                    @include('admin.partials.field-error', ['name' => 'foto_sim'])
                </div>
                <div class="travs-field">
                    <label for="foto_profil" class="form-label">Foto Profil Pengemudi</label>
                    <input id="foto_profil" name="foto_profil" type="file" class="travs-input @error('foto_profil') is-invalid @enderror form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="travs-field-meta">Format JPG, PNG, atau WEBP. Maksimal 2MB.</div>
                    @include('admin.partials.field-error', ['name' => 'foto_profil'])
                </div>
                <div class="travs-field">
                    <label for="status" class="form-label">Status Akun</label>
                    <select id="status" name="status" class="travs-select @error('status') is-invalid @enderror form-select" required>
                        <option value="aktif" @selected(old('status', 'aktif') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status') === 'nonaktif')>Nonaktif</option>
                    </select>
                    @include('admin.partials.field-error', ['name' => 'status'])
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="password" class="form-label">Password Awal</label>
                    <input id="password" name="password" type="password" class="travs-input @error('password') is-invalid @enderror form-control" required minlength="6" maxlength="8" placeholder="6-8 karakter">
                    <div class="travs-field-meta">Wajib mengandung huruf dan minimal satu angka atau simbol.</div>
                    @include('admin.partials.field-error', ['name' => 'password'])
                </div>
            </div>

            <div class="travs-form-actions d-flex justify-content-end">
                <button type="submit" class="travs-button btn btn-primary">Simpan Pengemudi</button>
            </div>
        </form>
    </div>
@endsection
