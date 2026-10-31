@extends('layouts.app')

@section('title', 'Akuntabilitas PIC')
@section('page-title', 'Akuntabilitas PIC')
@section('breadcrumb')
    <li class="breadcrumb-item active">Akuntabilitas</li>
@endsection

@php
    $n = fn ($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.');
    $p = fn ($v) => $v === null ? '-' : $v.'%';
    $tone = fn ($v, $good = 85, $fair = 65) => $v === null ? 'secondary' : ($v >= $good ? 'success' : ($v >= $fair ? 'warning' : 'danger'));
@endphp

@section('content')
<div class="alert alert-primary small mb-3"><i class="bi bi-info-circle me-1"></i><strong>Akuntabilitas</strong> menjawab: siapa yang bertanggung jawab atas setiap hazard dan tindakan, dan apakah selesai <em>tepat waktu</em>. &ldquo;Tepat waktu&rdquo; = ditutup sebelum atau pada tanggal tenggat; tenggat awal setiap hazard {{ config('ssap.hazard_sla_days') }} hari sejak dilaporkan dan dapat diubah HSE saat menugaskan PIC.</div>

@include('ssap._filter')

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-kpi label="Tepat waktu (SLA)" :value="$p($kpi['sla_pct'])" icon="bi-stopwatch" :tone="$tone($kpi['sla_pct'])" sub="Hazard selesai sebelum tenggat" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Hazard terlambat" :value="$kpi['overdue_now']" icon="bi-alarm" :tone="$kpi['overdue_now'] ? 'danger' : 'success'" :sub="$kpi['unassigned'].' belum ada PIC'" :href="route('ssap.hazards.index', ['scope' => 'late'])" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Tindakan terlambat" :value="$kpi['actions_overdue']" icon="bi-list-check" :tone="$kpi['actions_overdue'] ? 'danger' : 'success'" :sub="$kpi['actions_verify'].' menunggu verifikasi pelapor'" :href="route('ssap.actions.index', ['view' => 'overdue'])" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Kepatuhan inspeksi" :value="$p($ikpi['compliance'])" icon="bi-calendar-check" :tone="$tone($ikpi['compliance'], 90, 70)" :sub="$ikpi['count'].' dari target '.$n($ikpi['target'])" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-person-check"></i>Kinerja per PIC</h3><span class="s-card-sub">Diurutkan dari yang paling banyak terlambat</span></div>
            @include('ssap._board', ['rows' => $picBoard, 'withDept' => true])
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-diagram-3"></i>Kinerja per departemen PIC</h3></div>
            @include('ssap._board', ['rows' => $deptBoard, 'withDept' => false])
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-alarm text-danger"></i>Tindakan terlambat</h3><a href="{{ route('ssap.actions.index', ['view' => 'overdue']) }}" class="small">Lihat semua</a></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Tindakan</th><th>PIC</th><th>Tenggat</th></tr></thead>
                <tbody>
                @forelse ($overdueActions as $a)
                    <tr class="row-link" data-href="{{ $a->sourceUrl() }}#tindakan">
                        <td><div class="small text-muted">{{ $a->source?->number }}</div>{{ \Illuminate\Support\Str::limit($a->description, 70) }}</td>
                        <td class="small">{{ $a->pic_name }}<div class="text-muted">{{ $a->pic_department }}</div></td>
                        <td class="text-danger fw-semibold small text-nowrap">{{ $a->due_date->translatedFormat('d M Y') }}<div class="fw-normal">{{ $a->due_date->diffInDays(today()) }} hari lewat</div></td>
                    </tr>
                @empty
                    <tr><td colspan="3"><div class="empty-state py-4"><i class="bi bi-check2-all"></i>Semua tindakan tepat waktu.</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clipboard2-check"></i>Papan inspektor</h3><span class="s-card-sub">Tahun {{ $year }}</span></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>Inspektor</th><th class="num">Inspeksi</th><th class="num">Temuan</th><th class="num">Skor rata-rata</th></tr></thead>
                <tbody>
                @forelse ($inspectors as $r)
                    <tr><td class="fw-semibold">{{ $r['name'] }}</td><td class="num">{{ $r['count'] }}</td><td class="num">{{ $r['findings'] ?: '-' }}</td><td class="num">{{ $r['score'] !== null ? $n($r['score'], 1).'%' : '-' }}</td></tr>
                @empty
                    <tr><td colspan="4"><div class="empty-state py-4"><i class="bi bi-clipboard2-x"></i>Belum ada inspeksi.</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clipboard2-pulse text-warning"></i>Inspeksi tertinggal bulan ini</h3></div>
            <div class="s-card-body">
                @forelse ($behind as $b)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom small"><span class="fw-semibold">{{ $b['name'] }}</span><span class="pill pill-warning">{{ $b['done'] }} / {{ $b['target'] }}</span></div>
                @empty
                    <div class="empty-state py-4"><i class="bi bi-check2-all"></i>Semua template memenuhi target bulan ini.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-trophy text-warning"></i>Pelapor teraktif</h3></div>
            <div class="table-responsive"><table class="table table-ssap mb-0">
                <thead><tr><th>#</th><th>Nama</th><th class="num">Hazard</th><th class="num">TTA</th><th class="num">KTA</th></tr></thead>
                <tbody>
                @forelse ($reporters as $i => $r)
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
