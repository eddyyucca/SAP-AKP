@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard SAP')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="ssap-hero p-4 mb-3 shadow-sm">
    <div class="position-relative" style="z-index:1;">
        <div class="small opacity-75">{{ now()->translatedFormat('l, d F Y') }}</div>
        <h2 class="h4 fw-bold mb-1 mt-1">Halo, {{ $portalUser->name }}</h2>
        <div class="opacity-75 small">Lihat bahaya? Laporkan sekarang, sekecil apa pun. Keselamatan dimulai dari kita.</div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <a href="{{ route('ssap.hazards.create') }}" class="quick-btn">
            <span class="qi tone-warning"><i class="bi bi-cone-striped"></i></span>
            <span><span class="fw-bold d-block">Lapor Hazard</span><span class="small text-muted">Kondisi atau tindakan tidak aman yang Anda temui</span></span>
        </a>
    </div>
    <div class="col-12 col-md-6">
        <a href="{{ route('ssap.inspections.create') }}" class="quick-btn">
            <span class="qi tone-primary"><i class="bi bi-clipboard2-check"></i></span>
            <span><span class="fw-bold d-block">Isi Inspeksi Rutin</span><span class="small text-muted">Checklist area kerja; temuan otomatis menjadi hazard</span></span>
        </a>
    </div>
</div>

@if ($counts['overdue'])
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 small mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><strong>{{ $counts['overdue'] }} hazard</strong> yang Anda laporkan melewati tenggat. Bila perbaikan sudah dikerjakan, catat tindakan perbaikannya agar laporan ditutup. <a href="{{ route('ssap.hazards.index', ['scope' => 'late']) }}" class="fw-semibold">Lihat</a></div>
    </div>
@endif

@if ($mine)
    <a href="{{ route('ssap.targets') }}" class="s-card d-block p-3 mb-3 text-decoration-none text-reset">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong><i class="bi bi-bullseye me-1"></i>Target {{ $mine['period'] === 'week' ? 'minggu ini' : 'bulan ini' }}</strong> <span class="small text-muted">{{ $mine['period_label'] }}</span>
            <span class="pill pill-{{ $mine['achieved'] ? 'success' : 'warning' }}">{{ $mine['achieved'] ? 'Tercapai' : 'Belum tercapai' }}</span>
        </div>
        <div class="progress mb-2" style="height:8px;"><div class="progress-bar bg-{{ $mine['pct'] >= 100 ? 'success' : ($mine['pct'] >= 50 ? 'warning' : 'danger') }}" style="width:{{ $mine['pct'] }}%"></div></div>
        <div class="small text-muted">Hazard {{ $mine['hazard'] }}/{{ $mine['target_hazard'] }} &middot; Inspeksi {{ $mine['inspection'] }}/{{ $mine['target_inspection'] }}</div>
    </a>
@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-kpi label="Hazard saya" :value="$counts['hazards']" icon="bi-cone-striped" tone="warning" :sub="$counts['hazards_open'].' masih diproses'" :href="route('ssap.hazards.index')" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Inspeksi bulan ini" :value="$counts['inspections']" icon="bi-clipboard2-check" tone="primary" :href="route('ssap.inspections.index')" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Perlu saya selesaikan" :value="$counts['hazards_open']" icon="bi-tools" :tone="$counts['hazards_open'] ? 'warning' : 'success'" sub="Catat tindakan perbaikan bila sudah selesai" :href="route('ssap.hazards.index', ['scope' => 'open'])" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Terlambat" :value="$counts['overdue']" icon="bi-alarm" :tone="$counts['overdue'] ? 'danger' : 'success'" sub="Hazard melewati tenggat" :href="route('ssap.hazards.index', ['scope' => 'late'])" /></div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-tools"></i>Hazard saya yang belum selesai</h3></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <thead><tr><th>Hazard</th><th>Tenggat</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($openHazards as $h)
                        <tr class="row-link" data-href="{{ route('ssap.hazards.show', $h) }}">
                            <td><div class="small text-muted">{{ $h->number }}</div>{{ \Illuminate\Support\Str::limit($h->description, 60) }}</td>
                            <td class="text-nowrap {{ $h->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $h->due_date?->translatedFormat('d M Y') }}</td>
                            <td><x-pill :meta="$h->statusMeta()" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state py-4"><i class="bi bi-check2-all"></i>Tidak ada hazard yang menunggu penyelesaian.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clock-history"></i>Laporan terakhir saya</h3></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <tbody>
                    @foreach ($recentHazards as $h)
                        <tr class="row-link" data-href="{{ route('ssap.hazards.show', $h) }}">
                            <td><span class="pill pill-warning">Hazard</span></td>
                            <td><div class="fw-semibold">{{ $h->number }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($h->description, 40) }}</div></td>
                            <td><x-cat :category="$h->category" /></td>
                            <td><x-pill :meta="$h->statusMeta()" /></td>
                        </tr>
                    @endforeach
                    @foreach ($recentInspections as $i)
                        @php($b = $i->scoreMeta())
                        <tr class="row-link" data-href="{{ route('ssap.inspections.show', $i) }}">
                            <td><span class="pill pill-primary">Inspeksi</span></td>
                            <td><div class="fw-semibold">{{ $i->number }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($i->template_name, 40) }}</div></td>
                            <td class="small">{{ $i->nok_count ? $i->nok_count.' temuan' : 'bersih' }}</td>
                            <td><span class="pill pill-{{ $b['color'] }}">{{ $i->score !== null ? number_format($i->score, 1, ',', '.').'%' : '-' }}</span></td>
                        </tr>
                    @endforeach
                    @if ($recentHazards->isEmpty() && $recentInspections->isEmpty())
                        <tr><td><div class="empty-state py-4"><i class="bi bi-journal-plus"></i>Anda belum membuat laporan.</div></td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
