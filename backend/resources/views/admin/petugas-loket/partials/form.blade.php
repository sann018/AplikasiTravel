<div class="travs-form-grid-2 d-grid">
    <div class="travs-field">
        <label for="nama" class="form-label">Nama Lengkap</label>
        <input id="nama" name="nama" class="travs-input @error('nama') is-invalid @enderror form-control" value="{{ old('nama', $petugas->nama ?? '') }}" required maxlength="100" pattern="[A-Za-z ]+" oninput="this.value = this.value.replace(/[^A-Za-z ]/g, '').slice(0, 100)" placeholder="Contoh: Andi Saputra">
        <div class="travs-field-meta">Hanya gunakan huruf dan spasi. Angka tidak diperbolehkan.</div>
        @include('admin.partials.field-error', ['name' => 'nama'])
    </div>
    <div class="travs-field">
        <label for="email" class="form-label">Email Aktif</label>
        <input id="email" name="email" type="email" class="travs-input @error('email') is-invalid @enderror form-control" value="{{ old('email', $petugas->email ?? '') }}" required maxlength="100" placeholder="Contoh: andi@ruslytravel.com">
        <div class="travs-field-meta">Tautan untuk membuat kata sandi dikirim ke email ini.</div>
        @include('admin.partials.field-error', ['name' => 'email'])
    </div>
    <div class="travs-field">
        <label for="no_hp" class="form-label">Nomor HP</label>
        <input id="no_hp" name="no_hp" class="travs-input @error('no_hp') is-invalid @enderror form-control" value="{{ old('no_hp', $petugas->no_hp ?? '') }}" required minlength="11" maxlength="12" inputmode="numeric" pattern="[0-9]{11,12}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12)" placeholder="Contoh: 081234567890">
        <div class="travs-field-meta">Masukkan 11 sampai 12 digit angka tanpa spasi atau tanda baca.</div>
        @include('admin.partials.field-error', ['name' => 'no_hp'])
    </div>
    <div class="travs-field">
        <label for="status" class="form-label">Status Akun</label>
        <select id="status" name="status" class="travs-select @error('status') is-invalid @enderror form-select" required>
            <option value="aktif" @selected(old('status', isset($petugas) && ! $petugas->status_aktif ? 'nonaktif' : 'aktif') === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected(old('status', isset($petugas) && ! $petugas->status_aktif ? 'nonaktif' : 'aktif') === 'nonaktif')>Nonaktif</option>
        </select>
        <div class="travs-field-meta">Akun nonaktif tidak dapat masuk dan semua token perangkatnya dicabut.</div>
        @include('admin.partials.field-error', ['name' => 'status'])
    </div>
</div>
