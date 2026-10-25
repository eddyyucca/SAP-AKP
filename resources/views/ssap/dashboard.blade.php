@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard SAP Safety')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@php
    $n = fn ($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.');
    $loc = $locationId ? $locations->firstWhere('id', $locationId)?->name : 'Semua lokasi';
    $q = fn (array $extra = []) => array_filter(['year' => $year, 'location' => $locationId] + $extra);
@endphp

@push('topbar-actions')
    <a href="{{ route('ssap.incidents.create') }}" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"><i class="bi bi-exclamation-octagon"></i><span class="d-none d-md-inline">Lapor Insiden</span></a>
    <a href="{{ route('ssap.hazards.create') }}" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2"><i class="bi bi-cone-striped"></i><span class="d-none d-md-inline">Lapor Hazard</span></a>
    <a href="{{ route('ssap.reports.monthly', ['month' => now()->format('Y-m')] + ($locationId ? ['location' => $locationId] : [])) }}" class="btn btn-outline-secondary btn-sm d-none d-lg-inline-flex align-items-center gap-2"><i class="bi bi-printer"></i>Ringkasan Bulan Ini</a>
@endpush

@section('content')
{{-- Filter --}}
<form method="GET" class="s-card p-3 mb-3 filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small mb-1">Tahun</label>
            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <label class="form-label small mb-1">Lokasi</label>
            <select name="location" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Semua lokasi</option>
                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected($l->id == $locationId)>{{ $l->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md-auto ms-md-auto small text-muted">
            <i class="bi bi-info-circle me-1"></i>Angka mengikuti tahun &amp; lokasi terpilih. Hari tanpa LTI dan peta risiko selalu kondisi terkini.
        </div>
    </div>
</form>

{{-- Spanduk hari tanpa LTI + indikator utama --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-4">
        @php($d = $kpi['days_without_lti'])
        <div class="lti-banner {{ $d !== null && $d < 30 ? 'warn' : '' }} p-4 h-100 shadow-sm">
            <div class="small opacity-75 text-uppercase fw-semibold">Hari tanpa LTI / fatal</div>
            <div class="lti-num mt-2">{{ $d === null ? '∞' : $n($d) }}</div>
            <div class="small opacity-75 mt-2">{{ $d === null ? 'Belum pernah tercatat LTI atau fatal.' : 'Sejak LTI/fatal terakhir ('.now()->subDays($d)->translatedFormat('d M Y').')' }}</div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <x-kpi label="LTIFR" :value="$n($kpi['ltifr'], 2)" icon="bi-heart-pulse" tone="danger" :sub="$kpi['man_hours'] ? 'dari '.$n($kpi['man_hours']).' jam kerja' : 'isi jam kerja dulu'" :href="route('ssap.manhours.index', ['year' => $year])" />
            </div>
            <div class="col-6 col-md-3">
                <x-kpi label="TRIR" :value="$n($kpi['trir'], 2)" icon="bi-bandaid" tone="warning" :sub="$kpi['recordable'].' kasus tercatat'" />
            </div>
            <div class="col-6 col-md-3">
                <x-kpi label="Severity Rate" :value="$n($kpi['sr'], 1)" icon="bi-calendar-x" tone="secondary" :sub="$kpi['lost_days'].' hari kerja hilang'" />
            </div>
            <div class="col-6 col-md-3">
                <x-kpi label="Jam kerja" :value="$n($kpi['man_hours'])" icon="bi-clock-history" tone="info" sub="Jam kerja aman terkumpul" :href="route('ssap.manhours.index', ['year' => $year])" />
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi label="Insiden" :value="$kpi['incidents']" icon="bi-exclamation-octagon" tone="danger" :sub="$kpi['incidents_open'].' masih berjalan'" :href="route('ssap.incidents.index', ['scope' => 'open'])" />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi label="Nyaris celaka" :value="$kpi['near_miss']" icon="bi-eye" tone="teal" :sub="$kpi['near_miss_ratio'] !== null ? $kpi['near_miss_ratio'].' near miss per kasus tercatat' : 'Pelaporan dini bagus'" :href="route('ssap.incidents.index', ['type' => 'near_miss'])" />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi label="Investigasi telat" :value="$kpi['investigation_overdue']" icon="bi-search" :tone="$kpi['investigation_overdue'] ? 'danger' : 'success'" sub="Lewat batas waktu" :href="route('ssap.incidents.index', ['scope' => 'late'])" />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi label="Hazard dilaporkan" :value="$kpi['hazards']" icon="bi-cone-striped" tone="info" :sub="$kpi['hazards_open'].' masih terbuka'" :href="route('ssap.hazards.index', ['scope' => 'open'])" />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi label="Hazard risiko tinggi" :value="$kpi['hazards_high']" icon="bi-lightning-charge" :tone="$kpi['hazards_high'] ? 'danger' : 'success'" sub="Belum ditutup" :href="route('ssap.hazards.index', ['scope' => 'open', 'risk' => 'severe'])" />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi label="Tindakan terlambat" :value="$kpi['actions_overdue']" icon="bi-alarm" :tone="$kpi['actions_overdue'] ? 'danger' : 'success'" :sub="$kpi['actions_pending'].' menunggu selesai'" :href="route('ssap.actions.index', ['view' => 'overdue'])" />
    </div>
</div>

{{-- Tren --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-graph-up"></i>Tren kejadian per bulan</h3><span class="s-card-sub">Per jenis kejadian</span></div>
            <div class="s-card-body"><div class="chart-box tall"><canvas id="cvTrend"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-pie-chart"></i>Jenis kejadian</h3></div>
            <div class="s-card-body"><div class="chart-box tall"><canvas id="cvType"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-speedometer2"></i>LTIFR &amp; TRIR per bulan</h3><span class="s-card-sub">Per 1.000.000 jam kerja</span></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvRate"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Per lokasi</h3><span class="s-card-sub">Insiden &amp; hazard</span></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvLocation"></canvas></div></div>
        </div>
    </div>
</div>

{{-- Hazard & risiko --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-5 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-grid-3x3-gap"></i>Peta risiko hazard terbuka</h3><span class="s-card-sub">{{ $heatmap['total'] }} hazard</span></div>
            <div class="s-card-body">
                <div class="heat">
                    <div class="axis"></div>
                    @for ($c = 1; $c <= 5; $c++)<div class="axis">{{ $c }}</div>@endfor
                    @foreach (collect($heatmap['cells'])->groupBy('l') as $l => $row)
                        <div class="axis">{{ $l }}</div>
                        @foreach ($row as $cell)
                            <a href="{{ $cell['count'] ? route('ssap.hazards.index', ['scope' => 'open', 'risk' => $cell['level']]) : '#' }}" class="cell lv-{{ $cell['level'] }} {{ $cell['count'] ? '' : 'zero' }} text-decoration-none" title="Kemungkinan {{ $cell['l'] }} x Konsekuensi {{ $cell['c'] }} = skor {{ $cell['score'] }}">
                                <small>{{ $cell['score'] }}</small>{{ $cell['count'] ?: '' }}
                            </a>
                        @endforeach
                    @endforeach
                </div>
                <div class="d-flex justify-content-between small text-muted mt-2"><span>Baris: kemungkinan (5 = hampir pasti)</span><span>Kolom: konsekuensi</span></div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach (config('ssap.risk_levels') as $lv)
                        <span class="small"><span class="legend-dot" style="background:{{ $lv['color'] }}"></span>{{ $lv['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7 col-xl-5">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-arrow-left-right"></i>Hazard dilaporkan vs selesai</h3>
                <span class="s-card-sub">Penyelesaian {{ $kpi['hazard_close_pct'] !== null ? $kpi['hazard_close_pct'].'%' : '-' }}@if ($kpi['hazard_avg_days'] !== null) &middot; rata-rata {{ $n($kpi['hazard_avg_days'], 1) }} hari @endif</span></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvHazTrend"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-3">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-bullseye"></i>Tingkat risiko</h3></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvHazLevel"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-tags"></i>Kategori hazard</h3></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvHazCat"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-people"></i>Departemen pelapor</h3></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvDept"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-list-check"></i>Status tindakan korektif</h3><span class="s-card-sub">Selesai &amp; terverifikasi {{ $kpi['actions_done_pct'] !== null ? $kpi['actions_done_pct'].'%' : '-' }}</span></div>
            <div class="s-card-body"><div class="chart-box"><canvas id="cvActions"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-diagram-3"></i>Akar masalah insiden</h3><span class="s-card-sub">Dari investigasi</span></div>
            <div class="s-card-body"><div class="chart-box short"><canvas id="cvRoot"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-bar-chart-steps"></i>Keparahan insiden</h3></div>
            <div class="s-card-body"><div class="chart-box short"><canvas id="cvSeverity"></canvas></div></div>
        </div>
    </div>
</div>

{{-- Daftar perlu perhatian --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-flag text-danger"></i>Insiden perlu perhatian</h3><a href="{{ route('ssap.incidents.index', ['scope' => 'open']) }}" class="small">Lihat semua</a></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <thead><tr><th>Insiden</th><th>Status</th><th>Batas investigasi</th></tr></thead>
                    <tbody>
                    @forelse ($attention as $i)
                        <tr class="row-link" data-href="{{ route('ssap.incidents.show', $i) }}">
                            <td><div class="fw-semibold">{{ $i->number }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($i->title, 46) }}</div></td>
                            <td><x-pill :meta="$i->statusMeta()" /></td>
                            <td class="{{ $i->investigationOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $i->investigation_due?->translatedFormat('d M Y') ?? '-' }}@if ($i->investigationOverdue()) <i class="bi bi-exclamation-circle"></i>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state py-4"><i class="bi bi-emoji-smile"></i>Tidak ada insiden terbuka.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-lightning-charge text-warning"></i>Hazard risiko tertinggi (terbuka)</h3><a href="{{ route('ssap.hazards.index', ['scope' => 'open']) }}" class="small">Lihat semua</a></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <thead><tr><th>Hazard</th><th>Risiko</th><th>PIC / tenggat</th></tr></thead>
                    <tbody>
                    @forelse ($topHazards as $h)
                        <tr class="row-link" data-href="{{ route('ssap.hazards.show', $h) }}">
                            <td><div class="fw-semibold">{{ $h->number }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($h->title, 46) }}</div></td>
                            <td><x-risk :level="$h->risk_level" :score="$h->risk_score" /></td>
                            <td class="small">{{ $h->pic_name ?? 'Belum ada PIC' }}<div class="{{ $h->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $h->due_date?->translatedFormat('d M Y') ?? '-' }}</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state py-4"><i class="bi bi-shield-check"></i>Tidak ada hazard terbuka.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-alarm text-danger"></i>Tindakan terlambat</h3><a href="{{ route('ssap.actions.index', ['view' => 'overdue']) }}" class="small">Lihat semua</a></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <thead><tr><th>Tindakan</th><th>PIC</th><th>Tenggat</th></tr></thead>
                    <tbody>
                    @forelse ($overdueActions as $a)
                        <tr class="row-link" data-href="{{ $a->sourceUrl() }}">
                            <td><div class="small text-muted">{{ $a->source?->number }}</div>{{ \Illuminate\Support\Str::limit($a->description, 56) }}</td>
                            <td class="small">{{ $a->pic_name }}</td>
                            <td class="text-danger fw-semibold small">{{ $a->due_date->translatedFormat('d M Y') }}<div class="fw-normal">{{ $a->due_date->diffInDays(today()) }} hari lewat</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state py-4"><i class="bi bi-check2-all"></i>Semua tindakan tepat waktu.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-trophy text-warning"></i>Pelapor teraktif</h3><span class="s-card-sub">Budaya pelaporan, tahun {{ $year }}</span></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <thead><tr><th>#</th><th>Nama</th><th class="num">Insiden</th><th class="num">Hazard</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    @forelse ($topReporters as $i => $r)
                        <tr><td>{{ $i + 1 }}</td><td><div class="fw-semibold">{{ $r['name'] }}</div><div class="small text-muted">{{ $r['dept'] ?? '-' }}</div></td><td class="num">{{ $r['incidents'] }}</td><td class="num">{{ $r['hazards'] }}</td><td class="num fw-bold">{{ $r['total'] }}</td></tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state py-4"><i class="bi bi-people"></i>Belum ada laporan.</div></td></tr>
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
<script src="{{ asset('assets/js/ssap-charts.js') }}?v=1"></script>
<script>document.addEventListener('DOMContentLoaded', function () { window.SSAPCharts && window.SSAPCharts(@json($charts)); });</script>
@endpush
