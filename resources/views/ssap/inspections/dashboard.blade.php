@extends('layouts.app')

@section('title', 'Dashboard Inspeksi')
@section('page-title', 'Dashboard Inspeksi Rutin')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ssap.inspections.index') }}">Inspeksi</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@php
    $n = fn ($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.');
    $band = \App\Models\Inspection::scoreBand($kpi['avg_score']);
@endphp

@push('topbar-actions')
    <a href="{{ route('ssap.inspections.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Inspeksi Baru</span></a>
@endpush

@section('content')
<form method="GET" class="s-card p-3 mb-3 filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3 col-lg-2"><label class="form-label small mb-1">Tahun</label>
            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">@foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-4 col-lg-3"><label class="form-label small mb-1">Lokasi</label>
            <select name="location" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Semua lokasi</option>
                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected($l->id == $locationId)>{{ $l->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md ms-md-auto small text-muted">Target = jumlah target bulanan seluruh template aktif x bulan berjalan (diatur di Template Inspeksi).</div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-kpi label="Inspeksi terlaksana" :value="$kpi['count']" icon="bi-clipboard2-check" tone="primary" :sub="'Target '.$n($kpi['target'])" :href="route('ssap.inspections.index')" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Kepatuhan jadwal" :value="$kpi['compliance'] === null ? '-' : $kpi['compliance'].'%'" icon="bi-calendar-check" :tone="$kpi['compliance'] === null ? 'secondary' : ($kpi['compliance'] >= 90 ? 'success' : ($kpi['compliance'] >= 70 ? 'warning' : 'danger'))" sub="Terlaksana dibanding target" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Skor rata-rata" :value="$kpi['avg_score'] === null ? '-' : $n($kpi['avg_score'], 1).'%'" icon="bi-award" :tone="$band['color']" :sub="'Kondisi: '.$band['label']" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Temuan" :value="$kpi['findings']" icon="bi-flag" tone="danger" :sub="$kpi['findings_open'].' hazard masih terbuka'" :href="route('ssap.hazards.index', ['scope' => 'open'])" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-graph-up"></i>Inspeksi per bulan vs target &amp; skor</h3></div>
            <div class="s-card-body"><div class="chart-box tall"><canvas id="cvInsTrend"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Per lokasi</h3><span class="s-card-sub">Jumlah &amp; skor</span></div>
            <div class="s-card-body"><div class="chart-box tall"><canvas id="cvInsLocation"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-bullseye"></i>Pencapaian per template</h3><span class="s-card-sub">{{ $kpi['findings_close_pct'] !== null ? $kpi['findings_close_pct'].'% temuan sudah selesai' : 'Belum ada temuan' }}</span></div>
            <div class="s-card-body">
                @forelse ($templates as $t)
                    @php($pct = $t['pct'] ?? 0)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">{{ $t['name'] }}</span>
                            <span class="text-muted">{{ $t['done'] }}/{{ $t['target'] }} @if ($t['score'] !== null)&middot; skor {{ $n($t['score'], 1) }}%@endif</span>
                        </div>
                        <div class="progress" style="height:10px;" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar {{ $pct >= 90 ? 'bg-success' : ($pct >= 60 ? 'bg-warning' : 'bg-danger') }}" style="width:{{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state py-4"><i class="bi bi-clipboard2"></i>Belum ada template aktif.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-exclamation-diamond text-danger"></i>Temuan yang paling sering berulang</h3></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <tbody>
                    @forelse ($topFindings as $f)
                        <tr><td>{{ $f['question'] }}</td><td class="num"><span class="pill pill-danger">{{ $f['total'] }}x</span></td></tr>
                    @empty
                        <tr><td><div class="empty-state py-4"><i class="bi bi-emoji-smile"></i>Belum ada temuan.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clock-history"></i>Inspeksi terbaru</h3><a href="{{ route('ssap.inspections.index') }}" class="small">Lihat semua</a></div>
    <div class="table-responsive">
        <table class="table table-ssap mb-0">
            <thead><tr><th>Nomor</th><th>Template</th><th>Lokasi</th><th>Tanggal</th><th>Inspektor</th><th class="num">Temuan</th><th>Skor</th></tr></thead>
            <tbody>
            @forelse ($recent as $i)
                @php($b = $i->scoreMeta())
                <tr class="row-link" data-href="{{ route('ssap.inspections.show', $i) }}">
                    <td class="fw-semibold">{{ $i->number }}</td><td>{{ $i->template_name }}</td><td>{{ $i->location?->name }}</td>
                    <td class="text-nowrap">{{ $i->inspected_at->translatedFormat('d M Y') }}</td><td>{{ $i->inspector_name }}</td>
                    <td class="num">{{ $i->nok_count }}</td>
                    <td><span class="pill pill-{{ $b['color'] }}">{{ $i->score !== null ? $n($i->score, 1).'%' : '-' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-clipboard2-x"></i>Belum ada inspeksi.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/ssap-charts.js') }}?v=2"></script>
<script>document.addEventListener('DOMContentLoaded', function () { window.SSAPInspectionCharts && window.SSAPInspectionCharts(@json($charts)); });</script>
@endpush
