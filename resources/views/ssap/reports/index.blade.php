@extends('layouts.app')

@section('title', 'Laporan & Grafik')
@section('page-title', 'Laporan & Grafik Safety')
@section('breadcrumb')
    <li class="breadcrumb-item active">Laporan</li>
@endsection

@php
    $n = fn ($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.');
    $qs = array_filter(['from' => $filters['from'], 'to' => $filters['to'], 'location' => $locationId]);
@endphp

@section('content')
<form method="GET" class="s-card p-3 mb-3 filter-bar">
    <div class="d-flex flex-wrap gap-1 mb-2">
        @foreach (['month' => 'Bulan ini', 'last_month' => 'Bulan lalu', 'quarter' => 'Kuartal ini', 'year' => 'Tahun ini', 'last_year' => 'Tahun lalu'] as $k => $label)
            <a class="btn btn-sm btn-outline-primary" href="{{ route('ssap.reports.index', ['preset' => $k] + array_filter(['location' => $locationId])) }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3 col-lg-2"><label class="form-label small mb-1">Dari</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-3 col-lg-2"><label class="form-label small mb-1">Sampai</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm"></div>
        <div class="col-12 col-md-4 col-lg-3"><label class="form-label small mb-1">Lokasi</label>
            <select name="location" class="form-select form-select-sm"><option value="">Semua lokasi</option>@foreach ($locations as $l)<option value="{{ $l->id }}" @selected($l->id == $locationId)>{{ $l->name }}</option>@endforeach</select>
        </div>
        <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
            <a class="btn btn-outline-success btn-sm" href="{{ route('ssap.reports.export', ['dataset' => 'incidents'] + $qs) }}"><i class="bi bi-download me-1"></i>Insiden CSV</a>
            <a class="btn btn-outline-success btn-sm" href="{{ route('ssap.reports.export', ['dataset' => 'hazards'] + $qs) }}"><i class="bi bi-download me-1"></i>Hazard CSV</a>
            <a class="btn btn-outline-success btn-sm" href="{{ route('ssap.reports.export', ['dataset' => 'actions'] + $qs) }}"><i class="bi bi-download me-1"></i>Tindakan CSV</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('ssap.reports.monthly', ['month' => \Illuminate\Support\Carbon::parse($filters['to'])->format('Y-m')] + array_filter(['location' => $locationId])) }}"><i class="bi bi-printer me-1"></i>Ringkasan bulanan</a>
        </div>
    </div>
</form>

<div class="small text-muted mb-2">Periode <strong>{{ $from->translatedFormat('d M Y') }}</strong> s/d <strong>{{ $to->translatedFormat('d M Y') }}</strong></div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><x-kpi label="Insiden" :value="$kpi['incidents']" icon="bi-exclamation-octagon" tone="danger" :sub="$kpi['near_miss'].' nyaris celaka'" /></div>
    <div class="col-6 col-md-3"><x-kpi label="LTIFR" :value="$n($kpi['ltifr'], 2)" icon="bi-heart-pulse" tone="danger" :sub="$kpi['lti'].' kasus LTI/fatal'" /></div>
    <div class="col-6 col-md-3"><x-kpi label="TRIR" :value="$n($kpi['trir'], 2)" icon="bi-bandaid" tone="warning" :sub="$kpi['recordable'].' kasus tercatat'" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Hari kerja hilang" :value="$kpi['lost_days']" icon="bi-calendar-x" tone="secondary" :sub="'SR '.$n($kpi['sr'], 1)" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Jam kerja" :value="$n($kpi['man_hours'])" icon="bi-clock-history" tone="info" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Hazard" :value="$kpi['hazards']" icon="bi-cone-striped" tone="info" :sub="$kpi['hazard_close_pct'] !== null ? $kpi['hazard_close_pct'].'% selesai' : null" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Tindakan" :value="$kpi['actions']" icon="bi-list-check" tone="primary" :sub="$kpi['actions_overdue'].' terlambat'" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Lapor terlambat" :value="$kpi['reported_late_pct'] === null ? '-' : $kpi['reported_late_pct'].'%'" icon="bi-hourglass" tone="warning" :sub="$kpi['reported_late'].' insiden melewati batas'" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-graph-up"></i>Tren kejadian</h3></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvTrend"></canvas></div></div></div></div>
    <div class="col-12 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-pie-chart"></i>Jenis kejadian</h3></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvType"></canvas></div></div></div></div>
    <div class="col-12 col-xl-6"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-speedometer2"></i>LTIFR &amp; TRIR</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvRate"></canvas></div></div></div></div>
    <div class="col-12 col-xl-6"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-arrow-left-right"></i>Hazard dilaporkan vs selesai</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvHazTrend"></canvas></div></div></div></div>
    <div class="col-12 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-tags"></i>Kategori hazard</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvHazCat"></canvas></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-diagram-3"></i>Akar masalah</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvRoot"></canvas></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-bar-chart-steps"></i>Keparahan</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvSeverity"></canvas></div></div></div></div>
    <div class="col-12 col-xl-6"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Per lokasi</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvLocation"></canvas></div></div></div></div>
    <div class="col-12 col-xl-6"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-people"></i>Per departemen</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvDept"></canvas></div></div></div></div>
    <div class="col-12 col-xl-6"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-bullseye"></i>Tingkat risiko hazard</h3></div><div class="s-card-body"><div class="chart-box short"><canvas id="cvHazLevel"></canvas></div></div></div></div>
    <div class="col-12 col-xl-6"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-list-check"></i>Status tindakan</h3></div><div class="s-card-body"><div class="chart-box short"><canvas id="cvActions"></canvas></div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clipboard-data"></i>Rekap kecelakaan (format A-E)</h3></div>
            <div class="s-card-body">@include('ssap._recap', ['recap' => $recap])</div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-table"></i>Hari kerja hilang per jenis</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Jenis</th><th class="num">Kasus</th><th class="num">Hari hilang</th></tr></thead>
                <tbody>
                @forelse ($typeRows as $r)<tr><td><span class="legend-dot" style="background:{{ $r['color'] }}"></span>{{ $r['label'] }}</td><td class="num">{{ $r['count'] }}</td><td class="num">{{ $r['lost_days'] }}</td></tr>
                @empty<tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data.</td></tr>@endforelse
                </tbody>
                @if ($typeRows->isNotEmpty())<tfoot><tr class="fw-bold"><td>Total</td><td class="num">{{ $typeRows->sum('count') }}</td><td class="num">{{ $typeRows->sum('lost_days') }}</td></tr></tfoot>@endif
            </table></div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-table"></i>Rekap per lokasi</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Lokasi</th><th class="num">Insiden</th><th class="num">LTI</th><th class="num">Jam kerja</th><th class="num">LTIFR</th><th class="num">TRIR</th><th class="num">Hazard</th></tr></thead>
                <tbody>
                @forelse ($locationRows as $r)<tr><td>{{ $r['name'] }}</td><td class="num">{{ $r['incidents'] }}</td><td class="num">{{ $r['lti'] }}</td><td class="num">{{ $n($r['hours']) }}</td><td class="num">{{ $n($r['ltifr'], 2) }}</td><td class="num">{{ $n($r['trir'], 2) }}</td><td class="num">{{ $r['hazards'] }} <span class="text-muted small">({{ $r['hazards_open'] }} buka)</span></td></tr>
                @empty<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12">
        <div class="s-card">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-calendar3"></i>Rekap bulanan</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Bulan</th><th class="num">Insiden</th><th class="num">Jam kerja</th><th class="num">LTIFR</th><th class="num">TRIR</th><th class="num">Hazard dilaporkan</th><th class="num">Hazard selesai</th></tr></thead>
                <tbody>
                @foreach ($monthRows as $r)<tr><td>{{ $r['label'] }}</td><td class="num">{{ $r['incidents'] }}</td><td class="num">{{ $n($r['hours']) }}</td><td class="num">{{ $n($r['ltifr'], 2) }}</td><td class="num">{{ $n($r['trir'], 2) }}</td><td class="num">{{ $r['hazard_opened'] }}</td><td class="num">{{ $r['hazard_closed'] }}</td></tr>@endforeach
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/ssap-charts.js') }}?v=2"></script>
<script>document.addEventListener('DOMContentLoaded', function () { window.SSAPCharts && window.SSAPCharts(@json($charts)); });</script>
@endpush
