@extends('layouts.app')

@section('title', 'Daftar Inspeksi')
@section('page-title', \App\Support\Access::canViewAll() ? 'Daftar Inspeksi Rutin' : 'Inspeksi Saya')
@section('breadcrumb')
    <li class="breadcrumb-item active">Inspeksi</li>
@endsection

@push('topbar-actions')
    <a href="{{ route('ssap.inspections.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Inspeksi Baru</span></a>
@endpush

@section('content')
<form method="GET" class="s-card p-3 mb-3 filter-bar">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-xl-3"><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari nomor, inspektor, area..."></div>
        <div class="col-6 col-md-4 col-xl-3">
            <select name="template" class="form-select form-select-sm"><option value="">Semua template</option>
                @foreach ($templates as $t)<option value="{{ $t->id }}" @selected(($filters['template'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <select name="location" class="form-select form-select-sm"><option value="">Semua lokasi</option>
                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected(($filters['location'] ?? '') == $l->id)>{{ $l->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <select name="result" class="form-select form-select-sm"><option value="">Semua hasil</option>
                <option value="findings" @selected(($filters['result'] ?? '') === 'findings')>Ada temuan</option>
                <option value="clean" @selected(($filters['result'] ?? '') === 'clean')>Tanpa temuan</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm" title="Dari tanggal"></div>
        <div class="col-6 col-md-3 col-xl-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm" title="Sampai tanggal"></div>
        <div class="col-12 col-md-auto d-flex gap-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            <a href="{{ route('ssap.inspections.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </div>
</form>

<div class="s-card">
    <div class="table-responsive">
        <table class="table table-ssap mb-0">
            <thead><tr><th>Nomor</th><th>Template</th><th>Lokasi</th><th>Tanggal</th><th>Inspektor</th><th class="num">Temuan</th><th>Skor</th></tr></thead>
            <tbody>
            @forelse ($inspections as $i)
                @php($band = $i->scoreMeta())
                <tr class="row-link" data-href="{{ route('ssap.inspections.show', $i) }}">
                    <td class="fw-semibold text-nowrap">{{ $i->number }}</td>
                    <td style="min-width:200px;">{{ $i->template_name }}@if ($i->area_detail)<div class="small text-muted">{{ $i->area_detail }}</div>@endif</td>
                    <td>{{ $i->location?->name }}</td>
                    <td class="text-nowrap">{{ $i->inspected_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $i->inspector_name }}</td>
                    <td class="num">@if ($i->nok_count)<span class="pill pill-danger">{{ $i->nok_count }}</span>@else<span class="text-muted">0</span>@endif</td>
                    <td><span class="pill pill-{{ $band['color'] }}">{{ $i->score !== null ? number_format($i->score, 1, ',', '.').'%' : '-' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-clipboard2-x"></i>Belum ada inspeksi yang cocok.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($inspections->hasPages())<div class="p-3 border-top">{{ $inspections->links() }}</div>@endif
</div>
@endsection
