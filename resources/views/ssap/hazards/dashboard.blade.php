@extends('layouts.app')

@section('title', 'Dashboard Hazard')
@section('page-title', 'Dashboard Hazard')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ssap.hazards.index') }}">Hazard</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@php
    $n = fn ($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.');
    $p = fn ($v) => $v === null ? '-' : $v.'%';
    $tone = fn ($v, $good = 85, $fair = 65) => $v === null ? 'secondary' : ($v >= $good ? 'success' : ($v >= $fair ? 'warning' : 'danger'));
@endphp

@push('topbar-actions')
    <a href="{{ route('ssap.hazards.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Lapor Hazard</span></a>
@endpush

@section('content')
@include('ssap._filter')

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3 col-xl"><x-kpi label="Hazard dilaporkan" :value="$kpi['hazards']" icon="bi-cone-striped" tone="info" :sub="'TTA '.$kpi['tta'].' · KTA '.$kpi['kta']" /></div>
    <div class="col-6 col-md-3 col-xl"><x-kpi label="Terbuka saat ini" :value="$kpi['open_now']" icon="bi-folder2-open" tone="warning" :sub="'TTA '.$kpi['tta_open_now'].' · KTA '.$kpi['kta_open_now']" :href="route('ssap.hazards.index', ['scope' => 'open'])" /></div>
    <div class="col-6 col-md-3 col-xl"><x-kpi label="Melewati tenggat" :value="$kpi['overdue_now']" icon="bi-alarm" :tone="$kpi['overdue_now'] ? 'danger' : 'success'" :sub="$kpi['in_progress'].' sedang diperbaiki'" :href="route('ssap.hazards.index', ['scope' => 'late'])" /></div>
    <div class="col-6 col-md-3 col-xl"><x-kpi label="Penyelesaian" :value="$p($kpi['closure_pct'])" icon="bi-check2-circle" :tone="$tone($kpi['closure_pct'])" :sub="$kpi['closed'].' dari '.$kpi['hazards'].' selesai'" /></div>
    <div class="col-6 col-md-3 col-xl"><x-kpi label="Tepat waktu (SLA)" :value="$p($kpi['sla_pct'])" icon="bi-stopwatch" :tone="$tone($kpi['sla_pct'])" sub="Selesai sebelum tenggat" /></div>
    <div class="col-6 col-md-3 col-xl"><x-kpi label="Rata-rata selesai" :value="$kpi['avg_days'] !== null ? $n($kpi['avg_days'], 1).' hr' : '-'" icon="bi-hourglass-split" tone="primary" :sub="$kpi['oldest_open_days'] !== null ? 'Tertua '.$kpi['oldest_open_days'].' hr terbuka' : 'Sejak dilaporkan'" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-graph-up"></i>Tren hazard per bulan</h3><span class="s-card-sub">Batang: TTA &amp; KTA &middot; garis: diselesaikan &amp; % tepat waktu</span></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvTrend"></canvas></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-pie-chart"></i>TTA vs KTA</h3></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvCategory"></canvas></div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Per lokasi</h3><span class="s-card-sub">TTA &amp; KTA</span></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvLocation"></canvas></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-hourglass-bottom"></i>Umur hazard terbuka</h3></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvAging"></canvas></div></div></div></div>
    <div class="col-12 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-activity"></i>Status hazard</h3></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvStatus"></canvas></div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-person-check"></i>Kinerja pelapor (PIC)</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvPic"></canvas></div></div></div></div>
    <div class="col-12 col-xl-6"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-people"></i>Departemen pelapor</h3><span class="s-card-sub">Partisipasi pelaporan</span></div><div class="s-card-body"><div class="chart-box"><canvas id="cvDept"></canvas></div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-table"></i>TTA &amp; KTA menurut status</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Jenis</th><th class="num">Total</th><th class="num">Terbuka</th><th class="num">Terlambat</th><th class="num">Selesai</th></tr></thead>
                <tbody>@foreach ($categoryTable as $r)<tr><td><x-cat :category="$r['key']" :long="true" /></td><td class="num">{{ $r['total'] }}</td><td class="num">{{ $r['open'] ?: '-' }}</td><td class="num">{!! $r['overdue'] ? '<span class="pill pill-danger">'.$r['overdue'].'</span>' : '-' !!}</td><td class="num">{{ $r['closed'] }}</td></tr>@endforeach</tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-lightning-charge text-warning"></i>Hazard terbuka dengan tenggat terdekat</h3><a href="{{ route('ssap.hazards.index', ['scope' => 'open']) }}" class="small">Lihat semua</a></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Hazard</th><th>Jenis</th><th>Lokasi</th><th>Pelapor (PIC) / tenggat</th></tr></thead>
                <tbody>
                @forelse ($topHazards as $h)
                    <tr class="row-link" data-href="{{ route('ssap.hazards.show', $h) }}">
                        <td><div class="fw-semibold">{{ $h->number }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($h->description, 60) }}</div></td>
                        <td><x-cat :category="$h->category" /></td>
                        <td class="small">{{ $h->location?->name }}</td>
                        <td class="small">{{ $h->pic_name ?? $h->reporter_name }}<div class="{{ $h->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $h->due_date?->translatedFormat('d M Y') ?? '-' }}</div></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="empty-state py-4"><i class="bi bi-shield-check"></i>Tidak ada hazard terbuka.</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12 col-xl-7">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Rekap per lokasi</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Lokasi</th><th class="num">Total</th><th class="num">TTA</th><th class="num">KTA</th><th class="num">Terbuka</th><th class="num">Terlambat</th><th class="num">Selesai</th><th class="num">Tepat waktu</th></tr></thead>
                <tbody>
                @forelse ($locationRows as $r)
                    <tr><td class="fw-semibold">{{ $r['name'] }}</td><td class="num">{{ $r['total'] }}</td><td class="num">{{ $r['tta'] ?: '-' }}</td><td class="num">{{ $r['kta'] ?: '-' }}</td><td class="num">{{ $r['open'] ?: '-' }}</td><td class="num">{!! $r['overdue'] ? '<span class="pill pill-danger">'.$r['overdue'].'</span>' : '-' !!}</td><td class="num">{{ $r['closed'] }}</td><td class="num">{{ $p($r['sla_pct']) }}</td></tr>
                @empty
                    <tr><td colspan="8"><div class="empty-state py-4"><i class="bi bi-geo-alt"></i>Belum ada data.</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-trophy text-warning"></i>Pelapor teraktif</h3><span class="s-card-sub">Budaya pelaporan</span></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>#</th><th>Nama</th><th class="num">Hazard</th><th class="num">TTA</th><th class="num">KTA</th></tr></thead>
                <tbody>
                @forelse ($topReporters as $i => $r)
                    <tr><td>{{ $i + 1 }}</td><td><div class="fw-semibold">{{ $r['name'] }}</div><div class="small text-muted">{{ $r['dept'] ?? '-' }}</div></td><td class="num fw-bold">{{ $r['total'] }}</td><td class="num">{{ $r['tta'] ?: '-' }}</td><td class="num">{{ $r['kta'] ?: '-' }}</td></tr>
                @empty
                    <tr><td colspan="5"><div class="empty-state py-4"><i class="bi bi-people"></i>Belum ada laporan.</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/ssap-charts.js') }}?v=8"></script>
<script>document.addEventListener('DOMContentLoaded', function () { window.SSAPCharts && window.SSAPCharts(@json($charts)); });</script>
@endpush
