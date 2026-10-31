@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard SAP')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@php
    $n = fn ($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.');
    $p = fn ($v) => $v === null ? '-' : $v.'%';
    $tone = fn ($v, $good = 85, $fair = 65) => $v === null ? 'secondary' : ($v >= $good ? 'success' : ($v >= $fair ? 'warning' : 'danger'));
    $loc = $locationId ? $locations->firstWhere('id', $locationId)?->name : 'Semua lokasi';
    $band = \App\Models\Inspection::scoreBand($ikpi['avg_score']);
@endphp

@section('content')
<div class="ssap-hero p-4 mb-3 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 position-relative" style="z-index:1;">
        <div>
            <div class="small opacity-75">{{ now()->translatedFormat('l, d F Y') }} &middot; {{ $loc }} &middot; Tahun {{ $year }}</div>
            <h2 class="h4 fw-bold mb-1 mt-1">Safety Accountability Program</h2>
            <div class="opacity-75 small">Halo, {{ $portalUser->name }}. Hazard dan inspeksi {{ config('ssap.company') }} dalam satu pandangan.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('ssap.hazards.create') }}" class="btn btn-light btn-sm"><i class="bi bi-cone-striped me-1"></i>Lapor Hazard</a>
            <a href="{{ route('ssap.inspections.create') }}" class="btn btn-light btn-sm"><i class="bi bi-clipboard2-check me-1"></i>Inspeksi Baru</a>
            <a href="{{ route('ssap.reports.monthly', ['month' => now()->format('Y-m')] + ($locationId ? ['location' => $locationId] : [])) }}" class="btn btn-outline-light btn-sm"><i class="bi bi-printer me-1"></i>Ringkasan Bulan Ini</a>
        </div>
    </div>
</div>

@include('ssap._filter')

{{-- Kondisi terkini --}}
@if ($kpi['overdue_now'])
    <div class="alert alert-danger d-flex align-items-start gap-2 py-2 small mb-3">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong>Perlu tindakan hari ini:</strong>
            @if ($kpi['overdue_now'])<a href="{{ route('ssap.hazards.index', ['scope' => 'late']) }}" class="text-danger fw-semibold">{{ $kpi['overdue_now'] }} hazard</a> melewati tenggat.@endif
        </div>
    </div>
@endif

{{-- Angka kunci (ringkas) --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><x-kpi label="Hazard dilaporkan" :value="$kpi['hazards']" icon="bi-cone-striped" tone="info" :sub="'TTA '.$kpi['tta'].' · KTA '.$kpi['kta']" :href="route('ssap.hazards.index', ['from' => $year.'-01-01', 'to' => $year.'-12-31'])" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Terbuka saat ini" :value="$kpi['open_now']" icon="bi-folder2-open" tone="warning" :sub="'TTA '.$kpi['tta_open_now'].' · KTA '.$kpi['kta_open_now']" :href="route('ssap.hazards.index', ['scope' => 'open'])" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Melewati tenggat" :value="$kpi['overdue_now']" icon="bi-alarm" :tone="$kpi['overdue_now'] ? 'danger' : 'success'" :sub="$kpi['in_progress'].' sedang diperbaiki'" :href="route('ssap.hazards.index', ['scope' => 'late'])" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Penyelesaian" :value="$p($kpi['closure_pct'])" icon="bi-check2-circle" :tone="$tone($kpi['closure_pct'], 85, 65)" :sub="$kpi['closed'].' dari '.$kpi['hazards'].' selesai'" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Tepat waktu (SLA)" :value="$p($kpi['sla_pct'])" icon="bi-stopwatch" :tone="$tone($kpi['sla_pct'], 85, 65)" sub="Selesai sebelum tenggat" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Inspeksi" :value="$ikpi['count']" icon="bi-clipboard2-check" tone="primary" :sub="$p($ikpi['compliance']).' dari target'" :href="route('ssap.inspections.index')" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Skor inspeksi" :value="$ikpi['avg_score'] === null ? '-' : $n($ikpi['avg_score'], 1).'%'" icon="bi-award" :tone="$band['color']" :sub="$ikpi['findings'].' temuan'" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Rata-rata selesai" :value="$kpi['avg_days'] !== null ? $n($kpi['avg_days'], 1).' hr' : '-'" icon="bi-hourglass-split" tone="primary" sub="Sejak dilaporkan sampai ditutup" /></div>
</div>

{{-- Grafik hazard --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-graph-up"></i>Hazard per bulan</h3><span class="s-card-sub">Batang: TTA &amp; KTA &middot; garis: diselesaikan &amp; % tepat waktu</span></div>
            <div class="s-card-body"><div class="chart-box tall"><canvas id="cvTrend"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-pie-chart"></i>TTA vs KTA</h3></div>
            <div class="s-card-body"><div class="chart-box tall"><canvas id="cvCategory"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Hazard per lokasi</h3><span class="s-card-sub">TTA &amp; KTA</span></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvLocation"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-hourglass-bottom"></i>Umur hazard terbuka</h3><span class="s-card-sub">Sejak dilaporkan</span></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvAging"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-activity"></i>Status hazard</h3><span class="s-card-sub">Periode terpilih</span></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvStatus"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-person-check"></i>Kinerja pelapor (PIC)</h3><a href="{{ route('ssap.accountability', ['year' => $year]) }}" class="small">Papan lengkap</a></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvPic"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-people"></i>Departemen pelapor</h3><span class="s-card-sub">Partisipasi pelaporan</span></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvDept"></canvas></div></div>
        </div>
    </div>
</div>

{{-- Target pelaporan per departemen (grade HRIS, periode berjalan) --}}
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-bullseye"></i>Target pelaporan per departemen</h3><a href="{{ route('ssap.targets') }}" class="small">Rincian per anggota</a></div>
            @if ($targetDepts)
                <div class="s-card-body"><div class="chart-box tall"><canvas id="cvTarget"></canvas></div></div>
                <div class="table-responsive border-top"><table class="table table-ssap table-fit mb-0">
                    <thead><tr><th>Departemen</th><th class="num">Orang</th><th class="num">Hazard<br><small class="fw-normal">real / target</small></th><th class="num d-none d-md-table-cell">Inspeksi<br><small class="fw-normal">real / target</small></th><th class="num">Belum tercapai</th><th class="num">Capaian</th></tr></thead>
                    <tbody>@foreach (array_slice($targetDepts, 0, 8) as $d)
                        <tr class="row-link" data-href="{{ route('ssap.targets', ['department' => $d['name'] === 'Tanpa departemen' ? '' : $d['name']]) }}">
                            <td class="fw-semibold">{{ $d['name'] }}</td><td class="num">{{ $d['members'] }}</td>
                            <td class="num">{{ $d['hazard'] }} / {{ $d['target_hazard'] }}</td>
                            <td class="num d-none d-md-table-cell">{{ $d['target_inspection'] ? $d['inspection'].' / '.$d['target_inspection'] : '-' }}</td>
                            <td class="num">{!! $d['behind'] ? '<span class="pill pill-danger">'.$d['behind'].'</span>' : '-' !!}</td>
                            <td class="num">@if ($d['volume_pct'] !== null)<span class="pill pill-{{ $tone($d['volume_pct'], 100, 50) }}">{{ $d['volume_pct'] }}%</span>@else - @endif</td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
            @else
                <div class="s-card-body"><div class="empty-state py-4"><i class="bi bi-people"></i>Belum ada data target. Pastikan HRIS tersambung dan target grade diatur di Pengaturan.</div></div>
            @endif
        </div>
    </div>
</div>

{{-- Grafik inspeksi --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clipboard2-data"></i>Inspeksi per bulan vs target &amp; skor</h3></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvInsTrend"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Inspeksi per lokasi</h3></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvInsLocation"></canvas></div></div>
        </div>
    </div>
</div>

{{-- Daftar perlu ditindak --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-flag text-danger"></i>Hazard perlu perhatian</h3><a href="{{ route('ssap.hazards.index', ['scope' => 'late']) }}" class="small">Lihat semua</a></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <thead><tr><th>Hazard</th><th>Jenis</th><th>Pelapor (PIC) / tenggat</th></tr></thead>
                    <tbody>
                    @forelse ($attention as $h)
                        <tr class="row-link" data-href="{{ route('ssap.hazards.show', $h) }}">
                            <td><div class="fw-semibold">{{ $h->number }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($h->description, 60) }}</div></td>
                            <td><x-cat :category="$h->category" /></td>
                            <td class="small">{{ $h->pic_name ?? $h->reporter_name }}<div class="{{ $h->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $h->due_date?->translatedFormat('d M Y') ?? '-' }}@if ($h->isOverdue()) &middot; {{ $h->due_date->diffInDays(today()) }} hari lewat @endif</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state py-4"><i class="bi bi-shield-check"></i>Tidak ada hazard yang melewati tenggat.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-person-check"></i>Papan pelapor (PIC)</h3><a href="{{ route('ssap.accountability', ['year' => $year]) }}" class="small">Papan lengkap</a></div>
            @include('ssap._board', ['rows' => $board, 'withDept' => true])
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clipboard2-pulse text-warning"></i>Inspeksi tertinggal bulan ini</h3><span class="s-card-sub">Template di bawah target {{ now()->translatedFormat('F') }}</span></div>
            <div class="s-card-body">
                @forelse ($behind as $b)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom small">
                        <span class="fw-semibold">{{ $b['name'] }}</span>
                        <span><span class="pill pill-warning">{{ $b['done'] }} / {{ $b['target'] }}</span></span>
                    </div>
                @empty
                    <div class="empty-state py-4"><i class="bi bi-check2-all"></i>Semua template sudah memenuhi target bulan ini.</div>
                @endforelse
                @if ($behind)<div class="mt-3"><a href="{{ route('ssap.inspections.create') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Mulai inspeksi</a></div>@endif
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clock-history"></i>Inspeksi terbaru</h3><a href="{{ route('ssap.inspections.index') }}" class="small">Lihat semua</a></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <tbody>
                    @forelse ($recentInspections as $i)
                        @php($b = $i->scoreMeta())
                        <tr class="row-link" data-href="{{ route('ssap.inspections.show', $i) }}">
                            <td><div class="fw-semibold">{{ $i->number }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($i->template_name, 40) }}</div></td>
                            <td class="small">{{ $i->location?->name }}<div class="text-muted">{{ $i->inspected_at->translatedFormat('d M Y') }}</div></td>
                            <td class="num">{{ $i->nok_count ? $i->nok_count.' temuan' : 'bersih' }}</td>
                            <td><span class="pill pill-{{ $b['color'] }}">{{ $i->score !== null ? number_format($i->score, 1, ',', '.').'%' : '-' }}</span></td>
                        </tr>
                    @empty
                        <tr><td><div class="empty-state py-4"><i class="bi bi-clipboard2-x"></i>Belum ada inspeksi.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/ssap-charts.js') }}?v=9"></script>
<script>document.addEventListener('DOMContentLoaded', function () { window.SSAPCharts && window.SSAPCharts(@json($charts)); });</script>
@endpush
