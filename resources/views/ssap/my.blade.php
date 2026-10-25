@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard SSAP Safety')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<p class="text-muted small mb-3">Keselamatan dimulai dari kita. Lihat bahaya? Laporkan sekarang, sekecil apa pun.</p>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6 col-lg-4">
        <a href="{{ route('ssap.incidents.create') }}" class="quick-btn">
            <span class="qi tone-danger"><i class="bi bi-exclamation-octagon"></i></span>
            <span><span class="fw-bold d-block">Lapor Insiden</span><span class="small text-muted">Kecelakaan, cedera, nyaris celaka, kerusakan</span></span>
        </a>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
        <a href="{{ route('ssap.hazards.create') }}" class="quick-btn">
            <span class="qi tone-warning"><i class="bi bi-cone-striped"></i></span>
            <span><span class="fw-bold d-block">Lapor Hazard</span><span class="small text-muted">Kondisi atau tindakan tidak aman yang Anda temui</span></span>
        </a>
    </div>
    <div class="col-12 col-lg-4">
        <div class="lti-banner {{ $daysWithoutLti !== null && $daysWithoutLti < 30 ? 'warn' : '' }} p-3 h-100 d-flex align-items-center gap-3">
            <div class="lti-num">{{ $daysWithoutLti === null ? '∞' : number_format($daysWithoutLti, 0, ',', '.') }}</div>
            <div class="small">hari tanpa kecelakaan kerja berat (LTI/fatal) di perusahaan</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-kpi label="Insiden saya" :value="$counts['incidents']" icon="bi-exclamation-octagon" tone="danger" :href="route('ssap.incidents.index')" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Hazard saya" :value="$counts['hazards']" icon="bi-cone-striped" tone="warning" :href="route('ssap.hazards.index')" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Masih diproses" :value="$counts['open']" icon="bi-hourglass-split" tone="info" sub="Laporan saya yang belum selesai" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Tindakan untuk saya" :value="$counts['actions']" icon="bi-list-check" :tone="$counts['overdue'] ? 'danger' : 'primary'" :sub="$counts['overdue'] ? $counts['overdue'].' terlambat' : 'Tepat waktu'" :href="route('ssap.actions.index')" /></div>
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
                        <tr class="row-link" data-href="{{ route('ssap.actions.index') }}">
                            <td><div class="small text-muted">{{ $a->source?->number }}</div>{{ \Illuminate\Support\Str::limit($a->description, 60) }}</td>
                            <td class="{{ $a->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $a->due_date->translatedFormat('d M Y') }}</td>
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
                    @foreach ($recentIncidents as $i)
                        <tr class="row-link" data-href="{{ route('ssap.incidents.show', $i) }}">
                            <td><span class="pill pill-danger">Insiden</span></td>
                            <td><div class="fw-semibold">{{ $i->number }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($i->title, 40) }}</div></td>
                            <td><x-pill :meta="$i->statusMeta()" /></td>
                        </tr>
                    @endforeach
                    @foreach ($recentHazards as $h)
                        <tr class="row-link" data-href="{{ route('ssap.hazards.show', $h) }}">
                            <td><span class="pill pill-warning">Hazard</span></td>
                            <td><div class="fw-semibold">{{ $h->number }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($h->title, 40) }}</div></td>
                            <td><x-pill :meta="$h->statusMeta()" /></td>
                        </tr>
                    @endforeach
                    @if ($recentIncidents->isEmpty() && $recentHazards->isEmpty())
                        <tr><td><div class="empty-state py-4"><i class="bi bi-journal-plus"></i>Anda belum membuat laporan.</div></td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
