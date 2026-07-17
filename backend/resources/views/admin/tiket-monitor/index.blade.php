@extends('admin.layouts.app')

@section('title', 'Monitoring Tiket QR')
@section('page-title', 'Monitoring Tiket QR')
@section('page-description', 'Pantau status scan tiket QR penumpang.')

@section('content')
    <article class="travs-section-card">
        <div class="travs-panel-title">
            <h3>Filter Tiket</h3>
            <p>Cari tiket berdasarkan kode, penumpang, jadwal, atau status.</p>
        </div>

        <form method="GET" class="travs-filter-grid travs-filter-grid-wide">
            <div class="travs-field">
                <label for="search">Cari</label>
                <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Kode QR, kode pemesanan, nama penumpang">
            </div>
            <div class="travs-field">
                <label for="status_scan">Status Scan</label>
                <select id="status_scan" name="status_scan" class="travs-select">
                    <option value="">Semua status</option>
                    <option value="sudah_dipindai" @selected(($filters['status_scan'] ?? '') === 'sudah_dipindai')>Sudah Dipindai</option>
                    <option value="belum_dipindai" @selected(($filters['status_scan'] ?? '') === 'belum_dipindai')>Belum Dipindai</option>
                </select>
            </div>
            <div class="travs-field">
                <label for="jadwal_id">Jadwal</label>
                <select id="jadwal_id" name="jadwal_id" class="travs-select">
                    <option value="">Semua jadwal</option>
                    @foreach ($jadwalOptions as $jadwal)
                        <option value="{{ $jadwal->id }}" @selected(($filters['jadwal_id'] ?? '') == $jadwal->id)>
                            {{ $jadwal->rute?->kota_asal }} - {{ $jadwal->rute?->kota_tujuan }}
                            ({{ $jadwal->waktu_berangkat?->format('d/m/Y H:i') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="travs-field">
                <label for="tanggal_mulai">Tanggal Mulai</label>
                <input id="tanggal_mulai" name="tanggal_mulai" type="date" class="travs-input" value="{{ $filters['tanggal_mulai'] ?? '' }}">
            </div>
            <div class="travs-field">
                <label for="tanggal_selesai">Tanggal Selesai</label>
                <input id="tanggal_selesai" name="tanggal_selesai" type="date" class="travs-input" value="{{ $filters['tanggal_selesai'] ?? '' }}">
            </div>
            <div class="travs-filter-actions">
                <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                <a href="{{ route('admin.tiket-monitor.index') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap">
            <table class="travs-table">
                <thead>
                    <tr>
                        <th>Kode QR</th>
                        <th>Kode Pemesanan</th>
                        <th>Penumpang</th>
                        <th>Kursi</th>
                        <th>Rute</th>
                        <th>Status Scan</th>
                        <th>Waktu Scan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        @php
                            $detail = $ticket->detailPemesanan;
                            $booking = $detail?->pemesanan;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $ticket->kode_qr }}</strong>
                            </td>
                            <td>
                                {{ $booking?->kode_booking ?: '-' }}
                            </td>
                            <td>
                                {{ $detail?->nama_penumpang ?: '-' }}
                                <div class="travs-table-subcopy">{{ $detail?->no_hp_penumpang ?: '' }}</div>
                            </td>
                            <td>{{ $detail?->nomor_kursi ?: '-' }}</td>
                            <td>
                                {{ $booking?->jadwal?->rute ? $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan : '-' }}
                                <div class="travs-table-subcopy">{{ $booking?->jadwal?->waktu_berangkat?->format('d/m/Y H:i') ?: '' }}</div>
                            </td>
                            <td>
                                <span class="travs-status-pill travs-status-{{ $ticket->sudah_dipindai ? 'terbayar' : 'menunggu' }}">
                                    {{ $ticket->sudah_dipindai ? 'Sudah Dipindai' : 'Belum Dipindai' }}
                                </span>
                            </td>
                            <td>
                                {{ $ticket->dipindai_pada?->format('d/m/Y H:i:s') ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="travs-table-empty">Tidak ada data tiket pada filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $tickets->links() }}
    </article>
@endsection
