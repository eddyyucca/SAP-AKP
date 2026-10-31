@extends('layouts.app')

@section('title', 'Laporan & Grafik')
@section('page-title', 'Laporan & Grafik')
@section('breadcrumb')
    <li class="breadcrumb-item active">Laporan</li>
@endsection

@php
    $n = fn ($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.');
    $p = fn ($v) => $v === null ? '-' : $v.'%';
    $tone = fn ($v, $good = 85, $fair = 65) => $v === null ? 'secondary' : ($v >= $good ? 'success' : ($v >= $fair ? 'warning' : 'danger'));
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
        <div class="col-12 col-lg-auto"><button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top align-items-center">
        <span class="small text-muted me-1"><i class="bi bi-download me-1"></i>Unduh CSV:</span>
        @foreach (['hazards' => 'Hazard', 'inspections' => 'Inspeksi', 'findings' => 'Temuan inspeksi', 'actions' => 'Tindakan'] as $ds => $label)
            <a class="btn btn-outline-success btn-sm" href="{{ route('ssap.reports.export', ['dataset' => $ds] + $qs) }}">{{ $label }}</a>
        @endforeach
        <a class="btn btn-outline-secondary btn-sm ms-md-auto" href="{{ route('ssap.reports.monthly', ['month' => \Illuminate\Support\Carbon::parse($filters['to'])->format('Y-m')] + array_filter(['location' => $locationId])) }}"><i class="bi bi-printer me-1"></i>Ringkasan bulanan (cetak)</a>
    </div>
</form>

<div class="small text-muted mb-2">Periode <strong>{{ $from->translatedFormat('d M Y') }}</strong> s/d <strong>{{ $to->translatedFormat('d M Y') }}</strong></div>

{{-- Ringkasan --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Hazard" :value="$kpi['hazards']" icon="bi-cone-striped" tone="info" :sub="'TTA '.$kpi['tta'].' · KTA '.$kpi['kta']" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Penyelesaian" :value="$p($kpi['closure_pct'])" icon="bi-check2-circle" :tone="$tone($kpi['closure_pct'])" :sub="$kpi['closed'].' selesai, '.$kpi['open'].' terbuka'" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Tepat waktu" :value="$p($kpi['sla_pct'])" icon="bi-stopwatch" :tone="$tone($kpi['sla_pct'])" :sub="$kpi['avg_days'] !== null ? 'Rata-rata '.$n($kpi['avg_days'], 1).' hari' : 'Belum ada yang selesai'" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Inspeksi" :value="$ikpi['count']" icon="bi-clipboard2-check" tone="primary" :sub="'Target '.$n($ikpi['target']).' ('.$p($ikpi['compliance']).')'" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Skor inspeksi" :value="$ikpi['avg_score'] === null ? '-' : $n($ikpi['avg_score'], 1).'%'" icon="bi-award" :tone="\App\Models\Inspection::scoreBand($ikpi['avg_score'])['color']" sub="Rata-rata periode" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Temuan inspeksi" :value="$ikpi['findings']" icon="bi-flag" tone="danger" :sub="$ikpi['findings_close_pct'] !== null ? $ikpi['findings_close_pct'].'% sudah selesai' : 'Belum ada temuan'" /></div>
</div>

{{-- Grafik --}}
<div class="section-h">Hazard</div>
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-graph-up"></i>Tren hazard</h3></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvTrend"></canvas></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-pie-chart"></i>TTA vs KTA</h3></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvCategory"></canvas></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Per lokasi</h3></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvLocation"></canvas></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-hourglass-bottom"></i>Umur hazard terbuka</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvAging"></canvas></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-people"></i>Departemen pelapor</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvDept"></canvas></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-person-check"></i>Beban &amp; kinerja PIC</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvPic"></canvas></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-list-check"></i>Status tindakan</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvActions"></canvas></div></div></div></div>
</div>

<div class="section-h">Inspeksi</div>
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clipboard2-data"></i>Inspeksi per bulan vs target &amp; skor</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvInsTrend"></canvas></div></div></div></div>
    <div class="col-12 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Per lokasi</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvInsLocation"></canvas></div></div></div></div>
</div>

{{-- Tabel --}}
<div class="section-h">Rekap tabel</div>
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-table"></i>TTA &amp; KTA menurut status</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Jenis</th><th class="num">Total</th><th class="num">Terbuka</th><th class="num">Terlambat</th><th class="num">Selesai</th></tr></thead>
                <tbody>@foreach ($categoryTable as $r)<tr><td><x-cat :category="$r['key']" :long="true" /></td><td class="num fw-bold">{{ $r['total'] }}</td><td class="num">{{ $r['open'] ?: '-' }}</td><td class="num">{!! $r['overdue'] ? '<span class="pill pill-danger">'.$r['overdue'].'</span>' : '-' !!}</td><td class="num">{{ $r['closed'] }}</td></tr>@endforeach</tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12">
        <div class="s-card"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Rekap per lokasi</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Lokasi</th><th class="num">Hazard</th><th class="num">Terbuka</th><th class="num">Terlambat</th><th class="num">TTA</th><th class="num">KTA</th><th class="num">Tepat waktu</th><th class="num">Inspeksi</th><th class="num">Skor</th><th class="num">Temuan</th></tr></thead>
                <tbody>
                @forelse ($locationRows as $r)<tr><td class="fw-semibold">{{ $r['name'] }}</td><td class="num">{{ $r['hazards'] }}</td><td class="num">{{ $r['open'] ?: '-' }}</td><td class="num">{!! $r['overdue'] ? '<span class="pill pill-danger">'.$r['overdue'].'</span>' : '-' !!}</td><td class="num">{{ $r['tta'] ?: '-' }}</td><td class="num">{{ $r['kta'] ?: '-' }}</td><td class="num">{{ $p($r['sla_pct']) }}</td><td class="num">{{ $r['inspections'] }}</td><td class="num">{{ $r['score'] !== null ? $n($r['score'], 1).'%' : '-' }}</td><td class="num">{{ $r['findings'] ?: '-' }}</td></tr>
                @empty<tr><td colspan="10" class="text-center text-muted py-4">Tidak ada data.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-person-check"></i>Akuntabilitas per PIC</h3></div>@include('ssap._board', ['rows' => $picBoard, 'withDept' => true])</div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-diagram-3"></i>Akuntabilitas per departemen</h3></div>@include('ssap._board', ['rows' => $deptBoard, 'withDept' => false])</div>
    </div>
    <div class="col-12 col-xl-7">
        <div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-bullseye"></i>Pencapaian per template inspeksi</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Template</th><th class="num">Terlaksana</th><th class="num">Target</th><th class="num">Kepatuhan</th><th class="num">Skor rata-rata</th></tr></thead>
                <tbody>
                @forelse ($templateRows as $t)<tr><td>{{ $t['name'] }}</td><td class="num">{{ $t['done'] }}</td><td class="num">{{ $t['target'] }}</td><td class="num"><span class="pill pill-{{ $tone($t['pct'], 90, 60) }}">{{ $p($t['pct']) }}</span></td><td class="num">{{ $t['score'] !== null ? $n($t['score'], 1).'%' : '-' }}</td></tr>
                @empty<tr><td colspan="5" class="text-center text-muted py-4">Belum ada template aktif.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-exclamation-diamond text-danger"></i>Temuan inspeksi paling sering</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0"><tbody>
                @forelse ($topFindings as $f)<tr><td>{{ $f['question'] }}</td><td class="num"><span class="pill pill-danger">{{ $f['total'] }}x</span></td></tr>
                @empty<tr><td class="text-center text-muted py-4">Belum ada temuan.</td></tr>@endforelse
            </tbody></table></div>
        </div>
    </div>
    <div class="col-12">
        <div class="s-card"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-calendar3"></i>Rekap bulanan</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Bulan</th><th class="num">Hazard dilaporkan</th><th class="num">Hazard selesai</th><th class="num">Tepat waktu</th><th class="num">Inspeksi</th><th class="num">Target</th><th class="num">Skor rata-rata</th></tr></thead>
                <tbody>@foreach ($monthRows as $r)<tr><td>{{ $r['label'] }}</td><td class="num">{{ $r['opened'] }}</td><td class="num">{{ $r['closed'] }}</td><td class="num">{{ $p($r['sla']) }}</td><td class="num">{{ $r['inspections'] }}</td><td class="num">{{ $r['target'] }}</td><td class="num">{{ $r['score'] !== null ? $n($r['score'], 1).'%' : '-' }}</td></tr>@endforeach</tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/ssap-charts.js') }}?v=7"></script>
<script>document.addEventListener('DOMContentLoaded', function () { window.SSAPCharts && window.SSAPCharts(@json($charts)); });</script>
@endpush
