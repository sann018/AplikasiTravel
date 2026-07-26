            <article class="travs-section-card collapsible-section card" data-collapsible-section data-collapse-id="pemesanan-pembayaran-bagasi">
                <div class="collapsible-section__header" data-collapse-header>
                    <div class="travs-panel-title">
                        <h3>Barang Bawaan</h3>
                        <p>Catatan dan foto barang yang dibawa penumpang.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['target' => 'pemesanan-pembayaran-bagasi-body'])
                </div>

                <div id="pemesanan-pembayaran-bagasi-body" class="collapsible-section__body" data-collapse-body>
                    <div class="travs-bagasi-list">
                        @forelse ($pemesanan->itemBagasi as $item)
                            @php
                                $placementLabel = ($item->posisi_barang ?? 'masuk_bagasi') === 'dipegang_sendiri' ? 'Dipegang sendiri' : 'Masuk bagasi';
                                $photos = collect($item->foto_urls ?? []);
                                if ($item->foto_url) {
                                    $photos = $photos->prepend($item->foto_url);
                                }
                                $photos = $photos->filter()->unique()->values();
                            @endphp
                            <div class="travs-bagasi-item">
                                <div>
                                    <strong>{{ $item->deskripsi_item }}</strong>
                                    <p>{{ $placementLabel }}</p>
                                    <div class="travs-bagasi-photo-row">
                                        @forelse ($photos as $photo)
                                            <img
                                                class="travs-bagasi-thumb travs-photo-preview-trigger"
                                                src="{{ $storageUrl($photo) }}"
                                                alt="Foto barang bawaan {{ $loop->iteration }}"
                                                data-preview-src="{{ $storageUrl($photo) }}"
                                            >
                                        @empty
                                            <span class="travs-empty-inline">Foto barang belum tersedia.</span>
                                        @endforelse
                                    </div>
                                </div>
                                <span>Dicatat</span>
                            </div>
                        @empty
                            <p class="travs-empty-inline">Belum ada barang bawaan yang dicatat pada pemesanan ini.</p>
                        @endforelse
                    </div>
                </div>
            </article>
