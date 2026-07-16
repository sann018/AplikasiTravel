@extends('admin.layouts.app')

@section('title', 'Edit Biaya Operasional')
@section('page-title', 'Edit Biaya Operasional')
@section('page-description', 'Perbarui data biaya operasional perjalanan.')

@section('content')
    <section class="travs-section-card">
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.biaya.update', $biaya) }}" class="travs-form-grid">
            @csrf
            @method('PUT')

            <div class="travs-form-grid-2">
                <div class="travs-field">
                    <label for="jadwal_id">Jadwal Perjalanan <span class="text-danger">*</span></label>
                    <select id="jadwal_id" name="jadwal_id" class="travs-select @error('jadwal_id') is-invalid @enderror" required>
                        <option value="">Pilih Jadwal</option>
                        @foreach($jadwalOptions as $jadwal)
                            <option value="{{ $jadwal->id }}" @selected(old('jadwal_id', $biaya->jadwal_id) == $jadwal->id)>
                                {{ $jadwal->rute->kota_asal }} - {{ $jadwal->rute->kota_tujuan }} | {{ $jadwal->waktu_berangkat->format('d M Y H:i') }}
                            </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'jadwal_id'])
                </div>

                <div class="travs-field">
                    <label for="jenis_biaya">Jenis Biaya <span class="text-danger">*</span></label>
                    <input
                        id="jenis_biaya"
                        name="jenis_biaya"
                        class="travs-input @error('jenis_biaya') is-invalid @enderror"
                        value="{{ old('jenis_biaya', $biaya->jenis_biaya) }}"
                        required
                        placeholder="Contoh: BBM, Tol, Parkir"
                    >
                    @include('admin.partials.field-error', ['name' => 'jenis_biaya'])
                </div>

                <div class="travs-field">
                    <label for="nominal">Nominal <span class="text-danger">*</span></label>
                    <input
                        id="nominal"
                        name="nominal"
                        type="number"
                        class="travs-input @error('nominal') is-invalid @enderror"
                        value="{{ old('nominal', $biaya->nominal) }}"
                        required
                        placeholder="Contoh: 500000"
                        min="0"
                    >
                    @include('admin.partials.field-error', ['name' => 'nominal'])
                </div>

                <div class="travs-field">
                    <label for="tanggal_biaya">Tanggal Biaya <span class="text-danger">*</span></label>
                    <input
                        id="tanggal_biaya"
                        name="tanggal_biaya"
                        type="datetime-local"
                        class="travs-input @error('tanggal_biaya') is-invalid @enderror"
                        value="{{ old('tanggal_biaya', $biaya->tanggal_biaya->format('Y-m-d\TH:i')) }}"
                        required
                    >
                    @include('admin.partials.field-error', ['name' => 'tanggal_biaya'])
                </div>

                <div class="travs-field travs-field-span-2">
                    <label for="keterangan">Keterangan <span class="text-danger">*</span></label>
                    <textarea
                        id="keterangan"
                        name="keterangan"
                        class="travs-textarea @error('keterangan') is-invalid @enderror"
                        rows="3"
                        required
                        placeholder="Detail tambahan tentang biaya ini"
                    >{{ old('keterangan', $biaya->keterangan) }}</textarea>
                    @include('admin.partials.field-error', ['name' => 'keterangan'])
                </div>

                <div class="travs-field travs-field-span-2">
                    <label for="bukti_nota">Foto Bukti Nota <span class="text-danger">*</span></label>
                    <input
                        id="bukti_nota"
                        name="bukti_nota"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="travs-input @error('bukti_nota') is-invalid @enderror"
                        @required(! $biaya->bukti_url)
                    >
                    <div class="travs-field-meta">
                        @if ($biaya->bukti_url)
                            Bukti saat ini tetap digunakan jika tidak memilih file baru.
                        @else
                            Bukti wajib berupa JPG, JPEG, PNG, atau WEBP dengan ukuran maksimal 3 MB.
                        @endif
                    </div>
                    @include('admin.partials.field-error', ['name' => 'bukti_nota'])
                    @if ($biaya->bukti_url)
                        <button
                            type="button"
                            class="travs-photo-preview-trigger mt-2"
                            data-preview-src="{{ asset('storage/'.ltrim($biaya->bukti_url, '/')) }}"
                            aria-label="Lihat bukti nota yang tersimpan"
                        >
                            <img src="{{ asset('storage/'.ltrim($biaya->bukti_url, '/')) }}" alt="Bukti nota {{ $biaya->jenis_biaya }}" width="100" height="100" style="width:100px;height:100px;object-fit:cover;border-radius:8px;">
                        </button>
                    @endif
                </div>
            </div>

            <div class="travs-form-actions travs-inline">
                <button type="submit" class="travs-button">
                    <i data-feather="save"></i>
                    Simpan Perubahan
                </button>
                <a href="{{ route('admin.biaya.index') }}" class="travs-button-secondary">
                    <i data-feather="x"></i>
                    Batal
                </a>
            </div>
        </form>
    </section>
@endsection
