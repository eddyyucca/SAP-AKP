@extends('layouts.app')

@section('title', 'Tindakan Korektif')
@section('page-title', $canViewAll ? 'Tindakan Korektif' : 'Tindakan Saya')
@section('breadcrumb')
    <li class="breadcrumb-item active">Tindakan</li>
@endsection

@section('content')
@if ($canViewAll)
    <ul class="nav nav-pills mb-3 gap-1">
        @php($tabs = ['all' => ['Semua', null], 'mine' => ['Untuk saya', $counts['mine']], 'overdue' => ['Terlambat', $counts['overdue']]] + ['verify' => [\App\Support\Access::isAdmin() ? 'Menunggu verifikasi' : 'Saya verifikasi', $counts['verify']]])
        @foreach ($tabs as $key => [$label, $count])
            <li class="nav-item"><a class="nav-link py-1 px-3 {{ $view === $key ? 'active' : '' }}" href="{{ route('ssap.actions.index', ['view' => $key]) }}">{{ $label }}@if ($count) <span class="badge text-bg-{{ $key === 'overdue' ? 'danger' : 'secondary' }} ms-1">{{ $count }}</span>@endif</a></li>
        @endforeach
    </ul>
@endif

<form method="GET" class="s-card p-3 mb-3 filter-bar">
    <input type="hidden" name="view" value="{{ $view }}">
    <div class="row g-2">
        <div class="col-12 col-md-5"><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari uraian tindakan atau PIC..."></div>
        <div class="col-6 col-md-3">
            <select name="status" class="form-select form-select-sm"><option value="">Semua status</option>
                @foreach (config('ssap.action_statuses') as $k => $s)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $s['label'] }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md-auto d-flex gap-2"><button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Terapkan</button><a href="{{ route('ssap.actions.index', ['view' => $view]) }}" class="btn btn-outline-secondary btn-sm">Reset</a></div>
    </div>
</form>

<div class="s-card">
    <div class="table-responsive">
        <table class="table table-ssap mb-0">
            <thead><tr><th>Sumber</th><th>Tindakan</th><th>PIC</th><th>Tenggat</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($actions as $a)
                <tr class="row-link" data-href="{{ $a->sourceUrl() }}#tindakan">
                    <td class="text-nowrap"><span class="pill pill-warning">Hazard</span><div class="small fw-semibold mt-1">{{ $a->source?->number }}</div></td>
                    <td style="min-width:260px;">{{ \Illuminate\Support\Str::limit($a->description, 110) }}<div class="small text-muted">{{ $a->typeLabel() }}</div></td>
                    <td class="small">{{ $a->pic_name }}<div class="text-muted">{{ $a->pic_department }}</div></td>
                    <td class="text-nowrap {{ $a->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $a->due_date->translatedFormat('d M Y') }}@if ($a->isOverdue())<div class="small fw-normal">{{ $a->due_date->diffInDays(today()) }} hari lewat</div>@endif</td>
                    <td><x-pill :meta="$a->statusMeta()" /></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><i class="bi bi-check2-all"></i>Tidak ada tindakan pada tampilan ini.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($actions->hasPages())<div class="p-3 border-top">{{ $actions->links() }}</div>@endif
</div>
@endsection
