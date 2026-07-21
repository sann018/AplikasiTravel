@extends('admin.layouts.app')

@section('title', 'Dasbor')
@section('page-title', 'Dasbor')
@section('page-description', 'Ringkasan operasional harian.')

@section('content')
    @php
        $today = now()->toDateString();
    @endphp

    <div class="travs-dashboard-simple">
        <article class="travs-dashboard-panel travs-dashboard-panel-full collapsible-section" data-collapsible-section>
            <div class="travs-panel-header collapsible-section__header d-flex align-items-start justify-content-between" data-collapse-header>
                <div class="travs-panel-title">
                    <h3>Ringkasan Pendapatan Minggu Ini</h3>
                    <p>
                        Periode {{ $pendapatanHarian->first()['date']->translatedFormat('d F Y') }} sampai
                        {{ $pendapatanHarian->last()['date']->translatedFormat('d F Y') }}. Total dihitung sampai hari ini.
                    </p>
                </div>
                <div class="collapsible-section__header-actions">
                    <div class="travs-dashboard-panel-chip travs-dashboard-panel-chip--square">
                        <strong>Rp{{ number_format($pendapatanHarian->sum('total'), 0, ',', '.') }}</strong>
                        <span>Total minggu ini</span>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-revenue-body'])
                </div>
            </div>

            <div id="dashboard-revenue-body" class="travs-dashboard-table-wrap collapsible-section__body table-responsive" data-collapse-body>
                <table class="travs-table table mb-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Hari</th>
                            <th>Tanggal</th>
                            <th>Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendapatanHarian as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item['date']->translatedFormat('l') }}</td>
                                <td>{{ $item['date']->translatedFormat('d F Y') }}</td>
                                <td>
                                    {{ $item['is_future'] ? '-' : 'Rp'.number_format($item['total'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Total Pendapatan Minggu Ini</th>
                            <th>Rp{{ number_format($pendapatanHarian->sum('total'), 0, ',', '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </article>

        <article class="travs-dashboard-panel travs-dashboard-panel-full collapsible-section" data-collapsible-section>
            <div class="travs-panel-header collapsible-section__header d-flex align-items-start justify-content-between" data-collapse-header>
                <div class="travs-panel-title">
                    <h3>Jadwal Keberangkatan Hari Ini</h3>
                    <p>Daftar keberangkatan pada tanggal {{ now()->translatedFormat('d F Y') }}.</p>
                </div>
                <div class="collapsible-section__header-actions">
                    <a href="{{ route('admin.jadwal.index', ['tanggal' => $today]) }}" class="travs-button-link btn btn-link">Kelola jadwal</a>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-schedule-body'])
                </div>
            </div>

            <div id="dashboard-schedule-body" class="travs-dashboard-table-wrap collapsible-section__body table-responsive" data-collapse-body>
                <table class="travs-table table mb-0">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Rute</th>
                            <th>Armada</th>
                            <th>Kursi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jadwalHariIni as $jadwal)
                            <tr>
                                <td>{{ $jadwal->waktu_berangkat->format('H:i') }}</td>
                                <td>{{ $jadwal->rute->kota_asal }} - {{ $jadwal->rute->kota_tujuan }}</td>
                                <td>{{ $jadwal->kendaraan->label_armada }}</td>
                                <td>{{ $jadwal->kursi->where('status_kursi', 'tersedia')->count() }} tersedia</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="travs-table-empty">Belum ada jadwal keberangkatan hari ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>
@endsection

@push('scripts')
    @include('admin.partials.collapsible-section-script')
@endpush
