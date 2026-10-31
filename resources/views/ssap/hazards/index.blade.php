@extends('layouts.app')

@section('title', 'Daftar Hazard')
@section('page-title', \App\Support\Access::canViewAll() ? 'Daftar Hazard' : 'Hazard Saya')
@section('breadcrumb')
    <li class="breadcrumb-item active">Hazard</li>
@endsection

@push('topbar-actions')
    <a href="{{ route('ssap.hazards.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Lapor Hazard</span></a>
@endpush

@section('content')
<form method="GET" class="s-card p-3 mb-3 filter-bar">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-xl-3"><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari nomor, keterangan, pelapor, PIC..."></div>
        <div class="col-6 col-md-4 col-xl-2">
            <select name="category" class="form-select form-select-sm"><option value="">TTA &amp; KTA</option>
                @foreach (config('ssap.hazard_categories') as $k => $label)<option value="{{ $k }}" @selected(($filters['category'] ?? '') === $k)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <select name="status" class="form-select form-select-sm"><option value="">Semua status</option>
                @foreach (config('ssap.hazard_statuses') as $k => $s)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $s['label'] }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-3">
            <select name="location" class="form-select form-select-sm"><option value="">Semua lokasi</option>
                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected(($filters['location'] ?? '') == $l->id)>{{ $l->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <select name="scope" class="form-select form-select-sm"><option value="">Semua</option>
                <option value="open" @selected(($filters['scope'] ?? '') === 'open')>Masih terbuka</option>
                <option value="late" @selected(($filters['scope'] ?? '') === 'late')>Melewati tenggat</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm" title="Dari tanggal temuan"></div>
        <div class="col-6 col-md-3 col-xl-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm" title="Sampai tanggal temuan"></div>
        <div class="col-12 col-md-auto d-flex gap-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            <a href="{{ route('ssap.hazards.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            @if (\App\Support\Access::canViewAll())
                <a href="{{ route('ssap.reports.export', ['dataset' => 'hazards'] + array_filter(['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'location' => $filters['location'] ?? null])) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>CSV</a>
            @endif
        </div>
    </div>
</form>

<div class="s-card">
    <div class="table-responsive">
        <table class="table table-ssap table-fit mb-0">
            <thead><tr><th>Nomor</th><th>Keterangan</th><th class="d-none d-md-table-cell">Jenis</th><th class="d-none d-md-table-cell">Lokasi</th><th class="d-none d-md-table-cell">Ditemukan</th><th class="d-none d-md-table-cell">Pelapor (PIC) / tenggat</th><th class="d-none d-md-table-cell">Status</th></tr></thead>
            <tbody>
            @forelse ($hazards as $h)
                <tr class="row-link" data-href="{{ route('ssap.hazards.show', $h) }}">
                    <td class="fw-semibold text-nowrap">{{ $h->number }}</td>
                    <td style="min-width:200px;">{{ \Illuminate\Support\Str::limit($h->description, 90) }}
                        <div class="d-md-none d-flex flex-wrap gap-1 align-items-center mt-1">
                            <x-cat :category="$h->category" /><x-pill :meta="$h->statusMeta()" />
                            <span class="small {{ $h->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $h->location?->name }} &middot; tenggat {{ $h->due_date?->translatedFormat('d M') }}</span>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell"><x-cat :category="$h->category" /></td>
                    <td class="d-none d-md-table-cell">{{ $h->location?->name }}</td>
                    <td class="text-nowrap d-none d-md-table-cell">{{ $h->observed_at->translatedFormat('d M Y') }}<div class="small text-muted">{{ $h->reporter_name }}</div></td>
                    <td class="small d-none d-md-table-cell">{{ $h->pic_name ?? '-' }}<div class="{{ $h->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $h->due_date?->translatedFormat('d M Y') }}@if ($h->isOverdue()) <i class="bi bi-exclamation-circle"></i>@endif</div></td>
                    <td class="d-none d-md-table-cell"><x-pill :meta="$h->statusMeta()" /></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-shield-check"></i>Tidak ada hazard yang cocok.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($hazards->hasPages())<div class="p-3 border-top">{{ $hazards->links() }}</div>@endif
</div>
@endsection
