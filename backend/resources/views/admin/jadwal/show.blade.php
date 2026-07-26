@extends('admin.layouts.app')

@section('title', 'Detail Jadwal')
@section('page-title', 'Detail Jadwal Perjalanan')
@section('page-description', 'Ringkasan operasional, penumpang, pendapatan, dan biaya pada satu jadwal perjalanan.')

@section('content')
    @php
        $formatRupiah = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
        $routeLabel = $jadwal->rute->kota_asal.' - '.$jadwal->rute->kota_tujuan;
        $departureTimezone = $jadwal->waktu_berangkat?->timezoneName ?: config('app.timezone');
        $departureTimezoneLabel = match ($departureTimezone) {
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => 'WIB',
        };
        $scheduleStatusLabel = ucwords(str_replace('_', ' ', $jadwal->status));
        $scheduleStatusTone = match ($jadwal->status) {
            'ditutup' => 'menunggu',
            default => $jadwal->status,
        };
        // Nilai tetap berasal dari controller; teks ini hanya memberi konteks surplus atau defisit.
        $balanceContext = match (true) {
            $summary['recorded_balance'] < 0 => 'Defisit',
            $summary['recorded_balance'] > 0 => 'Surplus',
            default => 'Seimbang',
        };
    @endphp

    @include('admin.partials.page-back', [
        'url' => route('admin.jadwal.index'),
        'label' => 'Kembali ke Daftar Jadwal',
    ])

    <div class="travs-admin-detail-simple travs-detail-page">
    <header class="travs-detail-page__header">
        <div>
            <span class="travs-detail-page__eyebrow">Jadwal #{{ $jadwal->id }}</span>
            <h2>{{ $routeLabel }}</h2>
            <p>{{ $jadwal->waktu_berangkat?->translatedFormat('d F Y, H:i') }} {{ $departureTimezoneLabel }}</p>
            <div class="travs-detail-statuses" aria-label="Status jadwal">
                <span class="travs-status-pill travs-status-{{ $scheduleStatusTone }} badge rounded-pill">{{ $scheduleStatusLabel }}</span>
            </div>
        </div>
        <div class="travs-detail-page__actions">
            <a href="{{ route('admin.jadwal.edit', $jadwal) }}" class="travs-button btn btn-primary">Edit Jadwal</a>
        </div>
    </header>

    @include('admin.jadwal.partials.informasi-utama')
    @include('admin.jadwal.partials.ringkasan-metrik')
    @include('admin.jadwal.partials.daftar-pemesanan')
    @include('admin.jadwal.partials.daftar-biaya')
    </div>
@endsection
