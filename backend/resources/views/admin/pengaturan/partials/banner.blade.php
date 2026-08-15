            <section id="settings-banner" class="travs-card travs-settings-group travs-settings-pane collapsible-section card" data-settings-pane="banner" data-collapsible-section @if ($activeTab !== 'banner') hidden @endif>
                <div class="travs-settings-group-header collapsible-section__header" data-collapse-header>
                    <div>
                        <span class="travs-settings-label">Banner Beranda</span>
                        <h3>Informasi Beranda Aplikasi</h3>
                        <p>Kelola gambar promo atau informasi yang tampil di beranda tamu dan penumpang.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-banner-body'])
                </div>

                <div id="settings-banner-body" class="collapsible-section__body" data-collapse-body>
                    <div class="row g-4 travs-banner-manager-row">
                        <div class="col-xl-4">
                            <form method="POST" action="{{ route('admin.banner.store') }}" enctype="multipart/form-data" class="travs-settings-subsection mt-0">
                                @csrf
                                <div class="travs-settings-subsection-head">
                                    <h4>Tambah Banner</h4>
                                    <p>Banner aktif akan dibaca API yang sama oleh aplikasi mobile.</p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="banner_judul">Judul</label>
                                    <input id="banner_judul" name="judul" class="form-control @error('judul') is-invalid @enderror" value="{{ old('judul') }}" maxlength="120" required>
                                    @include('admin.partials.field-error', ['name' => 'judul'])
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="banner_subjudul">Keterangan Singkat</label>
                                    <textarea id="banner_subjudul" name="subjudul" class="form-control @error('subjudul') is-invalid @enderror" rows="3" maxlength="255">{{ old('subjudul') }}</textarea>
                                    @include('admin.partials.field-error', ['name' => 'subjudul'])
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="banner_gambar">Gambar Banner</label>
                                    <input id="banner_gambar" name="gambar" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('gambar') is-invalid @enderror" data-banner-image-input required>
                                    <div class="form-text">JPG, JPEG, PNG, atau WEBP. Maksimal 3 MB.</div>
                                    @include('admin.partials.field-error', ['name' => 'gambar'])
                                    <img class="travs-banner-preview d-none" alt="Pratinjau banner" data-banner-image-preview>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="banner_urutan">Urutan Tampil</label>
                                    <input id="banner_urutan" name="urutan" type="number" min="0" max="999" class="form-control @error('urutan') is-invalid @enderror" value="{{ old('urutan', 0) }}" required>
                                    @include('admin.partials.field-error', ['name' => 'urutan'])
                                </div>
                                <button class="travs-button w-100 btn btn-primary" type="submit">Tambah Banner</button>
                            </form>
                        </div>

                        <div class="col-xl-8">
                            <section class="travs-settings-subsection mt-0">
                                <div class="travs-settings-subsection-head">
                                    <h4>Daftar Banner</h4>
                                    <p>Urutan terkecil tampil lebih awal pada carousel mobile.</p>
                                </div>
                                <div class="row g-3 travs-banner-list-grid">
                                    @forelse ($banners as $banner)
                                        <div class="col-auto" style="width: 260px; max-width: 100%;">
                                            <article class="travs-banner-card" style="width: 260px; max-width: 100%; height: auto;">
                                                <img src="{{ asset('storage/'.$banner->path_gambar) }}" class="travs-banner-card-image travs-photo-preview-trigger" alt="{{ $banner->judul }}" data-preview-src="{{ asset('storage/'.$banner->path_gambar) }}" style="display: block; width: 232px; max-width: 100%; height: 131px; object-fit: cover; border-radius: 12px;">
                                                <div class="travs-banner-card-body">
                                                    <div>
                                                        <strong>{{ $banner->judul }}</strong>
                                                        <p>{{ $banner->subjudul ?: 'Tanpa keterangan' }}</p>
                                                    </div>
                                                    <span class="travs-status-pill travs-status-{{ $banner->aktif ? 'aktif' : 'nonaktif' }} badge rounded-pill">{{ $banner->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                                                </div>
                                                <form method="POST" action="{{ route('admin.banner.destroy', $banner) }}" data-confirm-title="Hapus banner?" data-confirm-message="Banner yang dipilih akan dihapus dari aplikasi." data-confirm-action="Ya, hapus banner">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="travs-button-link travs-button-link-danger btn btn-link" type="submit">Hapus</button>
                                                </form>
                                            </article>
                                        </div>
                                    @empty
                                        <div class="col-12">
                                            <p class="travs-empty-inline">Belum ada banner beranda.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>
