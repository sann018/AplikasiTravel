        @php
            $contactName = $pemesanan->namaKontakPemesan();
            $contactEmail = $pemesanan->emailKontakPemesan();
            $contactPhone = $pemesanan->nomorKontakPemesan();
            $profilePhoto = $pemesanan->pengguna?->foto_profil;
        @endphp
        <section class="travs-detail-card-grid">
            <article class="travs-section-card card">
                <div class="travs-panel-title">
                    <h3>Informasi Perjalanan</h3>
                    <p>Jadwal, armada, pengemudi, serta lokasi perjalanan.</p>
                </div>

                <dl class="travs-detail-data-list">
                    <div>
                        <dt>Keberangkatan</dt>
                        <dd>
                            {{ $pemesanan->jadwal->waktu_berangkat->locale('id')->translatedFormat('l, d F Y') }}
                            pukul {{ $pemesanan->jadwal->waktu_berangkat->format('H:i') }} {{ $departureTimezoneLabel }}
                        </dd>
                    </div>
                    <div>
                        <dt>Kendaraan</dt>
                        <dd>
                            <strong>{{ $pemesanan->jadwal->kendaraan->merk_kendaraan }}</strong>
                            <span>{{ $pemesanan->jadwal->kendaraan->nomor_plat }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>Pengemudi</dt>
                        <dd>
                            <strong>{{ $pemesanan->jadwal->pengemudi->pengguna->nama }}</strong>
                            <span>{{ $pemesanan->jadwal->pengemudi->pengguna->no_hp }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>Titik Jemput</dt>
                        <dd>
                            <strong>{{ $pemesanan->alamat_jemput ?: '—' }}</strong>
                            <span>{{ $pemesanan->catatan_jemput ?: 'Patokan belum diisi' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>Titik Turun</dt>
                        <dd>
                            <strong>{{ $pemesanan->alamat_turun ?: '—' }}</strong>
                            <span>{{ $pemesanan->catatan_turun ?: 'Patokan belum diisi' }}</span>
                        </dd>
                    </div>
                </dl>
            </article>

            <article class="travs-section-card card">
                <div class="travs-panel-title">
                    <h3>Informasi Pemesan</h3>
                    <p>Identitas pemesan dan kursi yang tercatat.</p>
                </div>

                <dl class="travs-detail-data-list">
                    <div>
                        <dt>Nama Pemesan</dt>
                        <dd>
                            <div class="travs-person-cell d-flex align-items-center">
                                <div class="travs-avatar">
                                    @if ($profilePhoto)
                                        <img class="travs-avatar-image travs-photo-preview-trigger" src="{{ $storageUrl($profilePhoto) }}" alt="Foto {{ $contactName }}">
                                    @else
                                        <span>{{ strtoupper(mb_substr($contactName, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <strong>{{ $contactName }}</strong>
                            </div>
                        </dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>{{ $contactEmail ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Nomor HP</dt>
                        <dd>{{ $contactPhone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Sumber Pemesanan</dt>
                        <dd>{{ $pemesanan->isPemesananLoket() ? 'Loket' : 'Aplikasi Penumpang' }}</dd>
                    </div>
                    @if ($pemesanan->isPemesananLoket())
                    <div>
                        <dt>Staf Pelayanan Pencatat</dt>
                        <dd>{{ $pemesanan->dibuatOleh?->nama ?: '—' }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt>Jumlah Kursi</dt>
                        <dd>{{ number_format($pemesanan->jumlah_kursi) }} kursi</dd>
                    </div>
                    <div>
                        <dt>Nomor Kursi</dt>
                        <dd>{{ $seatNumbers !== '' ? $seatNumbers : '—' }}</dd>
                    </div>
                </dl>
            </article>
        </section>
