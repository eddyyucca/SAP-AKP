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

@if ($mine)
    <a href="{{ route('ssap.targets') }}" class="s-card d-block p-3 mb-3 text-decoration-none text-reset">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong><i class="bi bi-bullseye me-1"></i>Target {{ now()->translatedFormat('F') }}</strong>
            <span class="pill pill-{{ $mine['achieved'] ? 'success' : 'warning' }}">{{ $mine['achieved'] ? 'Tercapai' : 'Belum tercapai' }}</span>
        </div>
        <div class="progress mb-2" style="height:8px;"><div class="progress-bar bg-{{ $mine['pct'] >= 100 ? 'success' : ($mine['pct'] >= 50 ? 'warning' : 'danger') }}" style="width:{{ $mine['pct'] }}%"></div></div>
        <div class="small text-muted">Hazard {{ $mine['hazard'] }}/{{ $mine['target_hazard'] }} &middot; Inspeksi {{ $mine['inspection'] }}/{{ $mine['target_inspection'] }}</div>
    </a>
@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-kpi label="Hazard saya" :value="$counts['hazards']" icon="bi-cone-striped" tone="warning" :sub="$counts['hazards_open'].' masih diproses'" :href="route('ssap.hazards.index')" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Inspeksi bulan ini" :value="$counts['inspections']" icon="bi-clipboard2-check" tone="primary" :href="route('ssap.inspections.index')" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Tindakan untuk saya" :value="$counts['actions']" icon="bi-list-check" :tone="$counts['overdue'] ? 'danger' : 'info'" :sub="$counts['overdue'] ? $counts['overdue'].' terlambat' : 'Tepat waktu'" :href="route('ssap.actions.index')" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Terlambat" :value="$counts['overdue']" icon="bi-alarm" :tone="$counts['overdue'] ? 'danger' : 'success'" sub="Tindakan melewati tenggat" :href="route('ssap.actions.index')" /></div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-list-check"></i>Tindakan yang harus saya kerjakan</h3></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <thead><tr><th>Tindakan</th><th>Tenggat</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($actions as $a)
                        <tr class="row-link" data-href="{{ $a->sourceUrl() }}#tindakan">
                            <td><div class="small text-muted">{{ $a->source?->number }}</div>{{ \Illuminate\Support\Str::limit($a->description, 60) }}</td>
                            <td class="text-nowrap {{ $a->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $a->due_date->translatedFormat('d M Y') }}</td>
                            <td><x-pill :meta="$a->statusMeta()" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state py-4"><i class="bi bi-check2-all"></i>Tidak ada tindakan untuk Anda.</div></td></tr>
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
