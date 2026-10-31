@extends('layouts.app')

@section('title', 'Monitoring LPI Kontraktor')
@section('page-title', 'Monitoring LPI Kontraktor')
@section('breadcrumb')
    <li class="breadcrumb-item active">Monitoring LPI</li>
@endsection

@php
    $n = fn ($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.');
    $pctTone = fn ($v) => $v === null ? 'secondary' : ($v >= 90 ? 'success' : ($v >= 70 ? 'warning' : 'danger'));
    $qs = array_filter(['year' => $filters['year'], 'area' => $filters['area'] ?? null, 'contractor' => $filters['contractor'] ?? null]);
@endphp

@section('content')
<div class="alert alert-primary small mb-3"><i class="bi bi-info-circle me-1"></i><strong>LPI</strong> (Laporan Penyelidikan Insiden) wajib disampaikan kontraktor yang bekerja di area <strong>IUP</strong> (Izin Usaha Pertambangan) atau <strong>IUJP</strong> (Izin Usaha Jasa Pertambangan) untuk setiap insiden di areanya, paling lambat pada tenggat investigasi ({{ config('ssap.investigation_days.fatality') }} hari fatal, {{ config('ssap.investigation_days.serious_injury') }} hari cidera berat, {{ config('ssap.investigation_days.default') }} hari lainnya). Near miss tidak mewajibkan LPI.</div>

<form method="GET" class="s-card p-3 mb-3 filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Tahun</label>
            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">@foreach ($years as $y)<option value="{{ $y }}" @selected($y == $filters['year'])>{{ $y }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">Area</label>
            <select name="area" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">IUP &amp; IUJP</option>
                @foreach (config('ssap.contractor_areas') as $k => $a)<option value="{{ $k }}" @selected(($filters['area'] ?? '') === $k)>{{ $a['label'] }} - {{ $a['full'] }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md-4"><label class="form-label small mb-1">Kontraktor</label>
            <select name="contractor" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Semua kontraktor</option>
                @foreach ($contractors as $c)<option value="{{ $c->id }}" @selected(($filters['contractor'] ?? '') == $c->id)>{{ $c->name }} ({{ strtoupper($c->area_type) }})</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md-auto ms-md-auto"><a class="btn btn-outline-success btn-sm" href="{{ route('ssap.lpi.export', $qs) }}"><i class="bi bi-download me-1"></i>Unduh CSV</a></div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-kpi label="Insiden kontraktor" :value="$kpi['incidents']" icon="bi-exclamation-octagon" tone="danger" :sub="$kpi['contractors'].' kontraktor, '.$kpi['required'].' wajib LPI'" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="LPI diterima" :value="$kpi['received_pct'] === null ? '-' : $kpi['received_pct'].'%'" icon="bi-inbox" :tone="$pctTone($kpi['received_pct'])" :sub="$kpi['received'].' dari '.$kpi['required'].' LPI'" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Tepat waktu" :value="$kpi['on_time_pct'] === null ? '-' : $kpi['on_time_pct'].'%'" icon="bi-stopwatch" :tone="$pctTone($kpi['on_time_pct'])" :sub="$kpi['avg_days'] !== null ? 'Rata-rata '.$n($kpi['avg_days'], 1).' hari sejak dilaporkan' : 'Belum ada LPI diterima'" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="LPI terlambat" :value="$kpi['overdue']" icon="bi-alarm" :tone="$kpi['overdue'] ? 'danger' : 'success'" sub="Melewati tenggat, belum diterima" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Menunggu peninjauan" :value="$kpi['review']" icon="bi-hourglass-bottom" tone="info" sub="Sudah diterima, belum diputuskan HSE" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Perlu revisi" :value="$kpi['revision']" icon="bi-arrow-counterclockwise" :tone="$kpi['revision'] ? 'warning' : 'success'" sub="Dikembalikan ke kontraktor" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Belum diterima" :value="$kpi['pending']" icon="bi-hourglass-split" tone="secondary" sub="Termasuk yang terlambat" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Disetujui" :value="$kpi['accepted']" icon="bi-patch-check" tone="success" sub="LPI tuntas" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-bar-chart-steps"></i>Status LPI per kontraktor</h3><span class="s-card-sub">12 kontraktor teratas</span></div><div class="s-card-body"><div class="chart-box tall"><canvas id="cvLpiContractor"></canvas></div></div></div></div>
    <div class="col-12 col-xl-4"><div class="s-card h-100"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-pie-chart"></i>Komposisi status</h3></div><div class="s-card-body"><div class="chart-box"><canvas id="cvLpiStatus"></canvas></div>
        <div class="row g-2 mt-2">
            @foreach ($areas as $a)
                <div class="col-6"><div class="border rounded-3 p-2 small"><span class="pill" style="background:{{ $a['color'] }}1f;color:{{ $a['color'] }};">{{ $a['label'] }}</span>
                    <div class="mt-1"><strong>{{ $a['contractors'] }}</strong> kontraktor &middot; <strong>{{ $a['incidents'] }}</strong> insiden</div>
                    <div class="text-muted">LPI {{ $a['received'] }}/{{ $a['required'] }} &middot; terlambat {{ $a['overdue'] }}</div></div></div>
            @endforeach
        </div>
    </div></div></div>
</div>

<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-building-check"></i>Rekap per kontraktor</h3><span class="s-card-sub">Diurutkan dari LPI terlambat terbanyak</span></div>
    <div class="table-responsive">
        <table class="table table-ssap sticky-first mb-0">
            <thead><tr><th>Kontraktor</th><th>Area</th><th class="num">Insiden</th><th class="num">LTI/Fatal</th><th class="num">Wajib LPI</th><th class="num">Diterima</th><th class="num">Disetujui</th><th class="num">Ditinjau</th><th class="num">Revisi</th><th class="num">Belum</th><th class="num">Terlambat</th><th class="num">Tepat waktu</th><th class="num">Rata-rata hari</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('ssap.lpi.index', ['contractor' => $r['id'], 'year' => $filters['year']]) }}" class="text-decoration-none">{{ $r['name'] }}</a></td>
                    <td><span class="pill" style="background:{{ $r['area_color'] }}1f;color:{{ $r['area_color'] }};">{{ $r['area'] }}</span></td>
                    <td class="num">{{ $r['incidents'] }}</td>
                    <td class="num">{{ $r['lti'] ?: '-' }}</td>
                    <td class="num">{{ $r['required'] }}</td>
                    <td class="num">{{ $r['received'] }} <span class="text-muted small">({{ $r['received_pct'] ?? '-' }}%)</span></td>
                    <td class="num">{{ $r['accepted'] }}</td>
                    <td class="num">{{ $r['review'] ?: '-' }}</td>
                    <td class="num">{!! $r['revision'] ? '<span class="pill pill-warning">'.$r['revision'].'</span>' : '-' !!}</td>
                    <td class="num">{{ $r['pending'] ?: '-' }}</td>
                    <td class="num">{!! $r['overdue'] ? '<span class="pill pill-danger">'.$r['overdue'].'</span>' : '-' !!}</td>
                    <td class="num">@if ($r['on_time_pct'] !== null)<span class="pill pill-{{ $pctTone($r['on_time_pct']) }}">{{ $r['on_time_pct'] }}%</span>@else - @endif</td>
                    <td class="num">{{ $n($r['avg_days'], 1) }}</td>
                </tr>
            @empty
                <tr><td colspan="13"><div class="empty-state"><i class="bi bi-buildings"></i>Belum ada insiden yang dikaitkan ke kontraktor pada periode ini.<br>Pilih kontraktor saat melapor insiden.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-list-task"></i>Daftar LPI yang perlu ditindak</h3><span class="s-card-sub">Terlambat &rarr; revisi &rarr; belum diterima &rarr; menunggu peninjauan</span></div>
    <div class="table-responsive">
        <table class="table table-ssap mb-0">
            <thead><tr><th>Insiden</th><th>Kontraktor</th><th>Tenggat</th><th>Status LPI</th><th>Dokumen</th>@if ($canManage)<th></th>@endif</tr></thead>
            <tbody>
            @forelse ($work as $i)
                @php($m = $i->lpiMeta())
                <tr>
                    <td style="min-width:220px;"><a href="{{ route('ssap.incidents.show', $i) }}#lpi" class="fw-semibold text-decoration-none">{{ $i->number }}</a><div class="small text-muted">{{ \Illuminate\Support\Str::limit($i->title, 54) }} &middot; {{ config('ssap.incident_types.'.$i->type.'.short') }}</div></td>
                    <td>{{ $i->contractor?->name }}<div class="small text-muted">{{ $i->contractor?->areaMeta()['label'] }}</div></td>
                    <td class="text-nowrap {{ $i->lpiOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $i->lpi_due?->translatedFormat('d M Y') }}@if ($i->lpiOverdue())<div class="small fw-normal">{{ $i->lpi_due->diffInDays(today()) }} hari lewat</div>@endif</td>
                    <td><x-pill :meta="$m" />@if ($i->lpiOverdue())<span class="pill pill-danger ms-1">Terlambat</span>@endif</td>
                    <td class="small">{{ $i->lpi_number ?: '-' }}@if ($i->lpi_submitted_at)<div class="text-muted">{{ $i->lpi_submitted_at->translatedFormat('d M Y') }}</div>@endif</td>
                    @if ($canManage)<td class="text-end"><a href="{{ route('ssap.incidents.show', $i) }}#lpi" class="btn btn-sm btn-outline-primary">{{ $i->lpi_status === 'submitted' ? 'Tinjau' : 'Catat LPI' }}</a></td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 6 : 5 }}"><div class="empty-state py-4"><i class="bi bi-check2-all"></i>Tidak ada LPI yang perlu ditindak.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/ssap-charts.js') }}?v=3"></script>
<script>document.addEventListener('DOMContentLoaded', function () { window.SSAPLpiCharts && window.SSAPLpiCharts(@json($charts)); });</script>
@endpush
