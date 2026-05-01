@extends('admin.layouts.app')

@section('title', 'Log Notifikasi')
@section('page-title', 'Log Notifikasi')
@section('page-description', 'Pantau notifikasi sistem yang tersimpan ke database untuk penumpang dan pengemudi.')

@section('content')
    <section class="travs-summary-grid">
        <article class="travs-summary-card">
            <small>Total Notifikasi</small>
            <strong>{{ number_format($stats['total']) }}</strong>
            <span>Semua log notifikasi</span>
        </article>
        <article class="travs-summary-card">
            <small>Belum Dibaca</small>
            <strong>{{ number_format($stats['belum_dibaca']) }}</strong>
            <span>Menunggu dibuka user</span>
        </article>
        <article class="travs-summary-card">
            <small>Jenis Pembayaran</small>
            <strong>{{ number_format($stats['pembayaran']) }}</strong>
            <span>Notifikasi terkait transaksi</span>
        </article>
    </section>

    <article class="travs-section-card">
        <div class="travs-panel-title">
            <h3>Filter Notifikasi</h3>
            <p>Cari log notifikasi berdasarkan judul, isi pesan, jenis, dan status baca.</p>
        </div>

        <form method="GET" class="travs-filter-grid">
            <div class="travs-field">
                <label for="search">Cari</label>
                <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Judul, pesan, atau nama pengguna">
            </div>
            <div class="travs-field">
                <label for="jenis">Jenis</label>
                <select id="jenis" name="jenis" class="travs-select">
                    <option value="">Semua jenis</option>
                    @foreach (['pembayaran' => 'Pembayaran', 'perjalanan' => 'Perjalanan', 'sistem' => 'Sistem'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['jenis'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="travs-field">
                <label for="dibaca">Status Baca</label>
                <select id="dibaca" name="dibaca" class="travs-select">
                    <option value="">Semua status</option>
                    <option value="1" @selected(($filters['dibaca'] ?? '') === '1')>Sudah dibaca</option>
                    <option value="0" @selected(($filters['dibaca'] ?? '') === '0')>Belum dibaca</option>
                </select>
            </div>
            <div class="travs-filter-actions">
                <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                <a href="{{ route('admin.notifikasi.index') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap">
            <table class="travs-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Penerima</th>
                        <th>Jenis</th>
                        <th>Judul</th>
                        <th>Status Baca</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notifikasi as $item)
                        <tr>
                            <td>{{ $item->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $item->pengguna->nama }}</td>
                            <td>
                                <span class="travs-status-pill travs-status-{{ $item->jenis }}">
                                    {{ ucfirst($item->jenis) }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ $item->judul }}</strong>
                                <div class="travs-table-subcopy">{{ $item->pesan }}</div>
                            </td>
                            <td>
                                <span class="travs-status-pill travs-status-{{ $item->sudah_dibaca ? 'selesai' : 'menunggu' }}">
                                    {{ $item->sudah_dibaca ? 'Sudah dibaca' : 'Belum dibaca' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="travs-table-empty">Belum ada log notifikasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $notifikasi->links() }}
    </article>
@endsection
