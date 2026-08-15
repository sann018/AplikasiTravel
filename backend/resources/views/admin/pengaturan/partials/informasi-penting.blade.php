            <section id="settings-important-information" class="travs-card travs-settings-group travs-settings-pane collapsible-section card" data-settings-pane="general" data-collapsible-section @if ($activeTab !== 'general') hidden @endif>
                <div class="travs-settings-group-header collapsible-section__header" data-collapse-header>
                    <div>
                        <span class="travs-settings-label">Informasi Penting</span>
                        <h3>Informasi Beranda Aplikasi</h3>
                        <p>Tambahkan pengumuman singkat yang perlu dibaca tamu dan penumpang.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-important-information-body'])
                </div>

                <div id="settings-important-information-body" class="collapsible-section__body" data-collapse-body>
                    <form method="POST" action="{{ route('admin.settings.important-information.store') }}" class="travs-settings-subsection mt-0">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="important_title">Judul Informasi</label>
                                <input id="important_title" name="title" type="text" maxlength="100" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                                @include('admin.partials.field-error', ['name' => 'title'])
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="important_description">Isi Informasi</label>
                                <textarea id="important_description" name="description" rows="3" maxlength="1000" class="form-control @error('description') is-invalid @enderror" required>{{ old('description') }}</textarea>
                                @include('admin.partials.field-error', ['name' => 'description'])
                            </div>
                        </div>
                        <div class="travs-form-actions mt-3 d-flex justify-content-end">
                            <button type="submit" class="travs-button btn btn-primary">Tambah Informasi</button>
                        </div>
                    </form>

                    <div class="travs-settings-subsection mt-3">
                        <div class="travs-settings-subsection-head">
                            <h4>Informasi yang Sedang Tampil</h4>
                            <p>Informasi ini dibaca aplikasi dari API saat beranda dimuat ulang.</p>
                        </div>
                        @forelse ($importantInformation as $information)
                            <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 border rounded-3 p-3 mb-2">
                                <div>
                                    <strong>{{ $information['title'] }}</strong>
                                    <p class="mb-0 mt-1 text-muted">{{ $information['description'] }}</p>
                                </div>
                                <form method="POST" action="{{ route('admin.settings.important-information.destroy', $information['id']) }}" data-confirm-title="Hapus informasi penting?" data-confirm-message="Informasi yang dipilih tidak akan ditampilkan lagi pada aplikasi." data-confirm-action="Ya, hapus informasi">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Belum ada informasi penting yang ditampilkan.</p>
                        @endforelse
                    </div>
                </div>
            </section>
