@extends('layouts.app')

@section('title', 'Daftar Insiden')
@section('page-title', \App\Support\Access::canViewAll() ? 'Daftar Insiden' : 'Insiden Saya')
@section('breadcrumb')
    <li class="breadcrumb-item active">Insiden</li>
@endsection

@push('topbar-actions')
    <a href="{{ route('ssap.incidents.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Lapor Insiden</span></a>
@endpush

@section('content')
<form method="GET" class="s-card p-3 mb-3 filter-bar">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-xl-3">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari nomor, judul, pelapor, area...">
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <select name="type" class="form-select form-select-sm">
                <option value="">Semua jenis</option>
                @foreach (config('ssap.incident_types') as $k => $t)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $t['short'] }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua status</option>
                @foreach (config('ssap.incident_statuses') as $k => $s)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $s['label'] }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <select name="location" class="form-select form-select-sm">
                <option value="">Semua lokasi</option>
                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected(($filters['location'] ?? '') == $l->id)>{{ $l->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <select name="scope" class="form-select form-select-sm">
                <option value="">Semua</option>
                <option value="open" @selected(($filters['scope'] ?? '') === 'open')>Masih berjalan</option>
                <option value="late" @selected(($filters['scope'] ?? '') === 'late')>Investigasi terlambat</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm" title="Dari tanggal kejadian"></div>
        <div class="col-6 col-md-3 col-xl-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm" title="Sampai tanggal kejadian"></div>
        <div class="col-12 col-md-auto d-flex gap-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            <a href="{{ route('ssap.incidents.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            @if (\App\Support\Access::canViewAll())
                <a href="{{ route('ssap.reports.export', ['dataset' => 'incidents'] + array_filter(['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'location' => $filters['location'] ?? null])) }}" class="btn btn-outline-success btn-sm" data-no-loading title="Unduh CSV (periode tanggal di samping)"><i class="bi bi-download me-1"></i>CSV</a>
            @endif
        </div>
    </div>
</form>

<div class="s-card">
    <div class="table-responsive">
        <table class="table table-ssap mb-0">
            <thead>
            <tr><th>Nomor</th><th>Kejadian</th><th>Jenis</th><th>Lokasi</th><th>Waktu</th><th>Pelapor</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse ($incidents as $i)
                @php($meta = $i->typeMeta())
                <tr class="row-link" data-href="{{ route('ssap.incidents.show', $i) }}">
                    <td class="fw-semibold text-nowrap">{{ $i->number }}</td>
                    <td style="min-width:240px;">
                        {{ \Illuminate\Support\Str::limit($i->title, 70) }}
                        @if ($i->investigationOverdue())<div class="small text-danger"><i class="bi bi-exclamation-circle me-1"></i>Investigasi terlambat</div>@endif
                    </td>
                    <td><span class="pill" style="background:{{ $meta['color'] }}1f;color:{{ $meta['color'] }};"><span class="legend-dot m-0" style="background:{{ $meta['color'] }}"></span>{{ $meta['short'] }}</span></td>
                    <td>{{ $i->location?->name }}</td>
                    <td class="text-nowrap">{{ $i->occurred_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $i->reporter_name }}</td>
                    <td><x-pill :meta="$i->statusMeta()" /></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-journal-x"></i>Tidak ada insiden yang cocok.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($incidents->hasPages())<div class="p-3 border-top">{{ $incidents->links() }}</div>@endif
</div>
@endsection
