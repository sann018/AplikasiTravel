@extends('admin.layouts.app')

@section('title', 'Pengaturan Sistem')
@section('page-title', 'Pengaturan Sistem')
@section('page-description', 'Kelola aturan utama aplikasi.')

@php
    $allSettings = collect($settings)->flatten(1)->keyBy('key');
    $settingValue = fn (string $key, mixed $default = '') => old($key, data_get($allSettings->get($key), 'typed_value', $default));
    $settingEnabled = fn (string $key, bool $default = false) => filter_var($settingValue($key, $default), FILTER_VALIDATE_BOOL);

    $logoPath = (string) $settingValue('app_logo');
    $logoPreviewUrl = null;
    if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
        $logoPreviewUrl = $logoPath;
    } elseif ($logoPath !== '' && file_exists(public_path(ltrim($logoPath, '/')))) {
        $logoPreviewUrl = asset(ltrim($logoPath, '/'));
    } elseif ($logoPath !== '' && file_exists(public_path('storage/'.ltrim($logoPath, '/')))) {
        $logoPreviewUrl = asset('storage/'.ltrim($logoPath, '/'));
    }

    $tabLabels = [
        'account' => 'Akun Admin',
        'general' => 'Umum',
        'booking' => 'Pemesanan',
        'banner' => 'Banner Beranda',
    ];
@endphp

@section('content')
    <div class="travs-settings-page">
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Pengaturan belum dapat disimpan.</strong>
                <div>Periksa kembali kolom yang diberi tanda merah.</div>
            </div>
        @endif

        <section class="travs-settings-tabs collapsible-section mb-3" data-collapsible-section>
            <div class="collapsible-section__header" data-collapse-header>
                <div class="travs-settings-tabs-copy">
                    <span class="travs-settings-label">PUSAT PENGATURAN</span>
                </div>
                @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-control-center-body'])
            </div>

            <div id="settings-control-center-body" class="collapsible-section__body" data-collapse-body>
                {{-- Tombol simpan dipisah per tab supaya admin tidak mengubah semua konfigurasi tanpa sengaja. --}}
                <div class="travs-settings-tab-list" role="tablist" aria-label="Tab pengaturan sistem">
                    @foreach ($tabLabels as $tabKey => $tabLabel)
                        <button type="button" class="travs-settings-tab {{ $activeTab === $tabKey ? 'is-active' : '' }}" data-settings-tab="{{ $tabKey }}" role="tab" aria-controls="settings-{{ $tabKey }}" aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}">
                            {{ $tabLabel }}
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="travs-settings-grid">
            @include('admin.pengaturan.partials.akun')
            @include('admin.pengaturan.partials.umum')
            @include('admin.pengaturan.partials.informasi-penting')
            @include('admin.pengaturan.partials.pemesanan')
            @include('admin.pengaturan.partials.bagasi-lama')
            @include('admin.pengaturan.partials.banner')

        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.pengaturan.partials.skrip')
@endpush
