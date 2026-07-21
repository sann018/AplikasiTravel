@extends('admin.layouts.app')
@section('title', 'Banner Beranda')
@section('page-title', 'Banner Beranda')
@section('page-description', 'Kelola informasi promo yang tampil di beranda aplikasi.')
@section('content')
<div class="row g-4">
    <div class="col-xl-4">
        <section class="travs-panel p-4 card">
            <h4 class="mb-1">Tambah Banner</h4>
            <p class="text-muted mb-4">Gambar aktif akan tampil pada carousel beranda penumpang dan tamu.</p>

            <form method="POST" action="{{ route('admin.banner.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="judul">Judul</label>
                    <input class="form-control @error('judul') is-invalid @enderror" id="judul" name="judul" value="{{ old('judul') }}" maxlength="120" required>
                    @error('judul')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="subjudul">Keterangan singkat</label>
                    <textarea class="form-control @error('subjudul') is-invalid @enderror" id="subjudul" name="subjudul" rows="3" maxlength="255">{{ old('subjudul') }}</textarea>
                    @error('subjudul')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="gambar">Gambar banner</label>
                    <input class="form-control @error('gambar') is-invalid @enderror" type="file" id="gambar" name="gambar" accept="image/jpeg,image/png,image/webp" data-banner-image-input required>
                    <div class="form-text">JPG, JPEG, PNG, atau WEBP. Maksimal 3 MB.</div>
                    @error('gambar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <img class="img-fluid mt-3 d-none" style="max-height:220px;object-fit:cover" alt="Preview banner" data-banner-image-preview>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="urutan">Urutan tampil</label>
                    <input class="form-control" type="number" id="urutan" name="urutan" value="{{ old('urutan', 0) }}" min="0" max="999" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Simpan Banner</button>
            </form>
        </section>
    </div>

    <div class="col-xl-8">
        <section class="travs-panel p-4 card">
            <h4 class="mb-1">Daftar Banner</h4>
            <p class="text-muted mb-4">Banner disusun berdasarkan nomor urutan terkecil.</p>
            <div class="row g-3">
                @forelse ($banners as $banner)
                    <div class="col-md-6">
                        <article class="border rounded-3 p-3 h-100">
                            <img src="{{ asset('storage/'.$banner->path_gambar) }}" class="img-fluid rounded-3 mb-3 travs-photo-preview-trigger" style="width:100%;height:160px;object-fit:cover" alt="{{ $banner->judul }}" data-preview-src="{{ asset('storage/'.$banner->path_gambar) }}">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <strong>{{ $banner->judul }}</strong>
                                    <p class="text-muted small mb-0">{{ $banner->subjudul ?: 'Tanpa keterangan' }}</p>
                                </div>
                                <span class="badge {{ $banner->aktif ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $banner->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <form method="POST" action="{{ route('admin.banner.destroy', $banner) }}" class="mt-3" onsubmit="return confirm('Hapus banner ini?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit">Hapus</button>
                            </form>
                        </article>
                    </div>
                @empty
                    <p class="text-muted mb-0">Belum ada banner beranda.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const bannerInput = document.querySelector('[data-banner-image-input]');
    const bannerPreview = document.querySelector('[data-banner-image-preview]');
    bannerInput?.addEventListener('change', function () {
        const file = this.files?.[0];
        if (!file || !bannerPreview) return;
        bannerPreview.src = URL.createObjectURL(file);
        bannerPreview.classList.remove('d-none');
    });
</script>
@endpush
