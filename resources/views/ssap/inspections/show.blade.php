@extends('layouts.app')

@php($band = $inspection->scoreMeta())

@section('title', $inspection->number)
@section('page-title', 'Inspeksi '.$inspection->number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ssap.inspections.index') }}">Inspeksi</a></li>
    <li class="breadcrumb-item active">{{ $inspection->number }}</li>
@endsection

@section('content')
<div class="s-card mb-3">
    <div class="s-card-body pt-3">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="pill pill-primary"><i class="bi bi-clipboard2-check"></i>{{ $inspection->template_name }}</span>
                    <span class="pill pill-{{ $band['color'] }}">Skor {{ $inspection->score !== null ? number_format($inspection->score, 1, ',', '.').'%' : '-' }} &middot; {{ $band['label'] }}</span>
                </div>
                <h2 class="h5 fw-bold mb-1">{{ $inspection->number }} &middot; {{ $inspection->location?->name }}@if ($inspection->area_detail) &middot; {{ $inspection->area_detail }}@endif</h2>
                <div class="small text-muted">{{ $inspection->inspected_at->translatedFormat('l, d F Y H:i') }} &middot; Inspektor {{ $inspection->inspector_name }}@if ($inspection->inspector_department) ({{ $inspection->inspector_department }})@endif</div>
            </div>
            <div class="d-flex gap-2 align-items-start no-print">
                <button class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer me-1"></i>Cetak</button>
                @if (\App\Support\Access::isAdmin())
                    <form method="POST" action="{{ route('ssap.inspections.destroy', $inspection) }}" data-confirm="Hapus inspeksi {{ $inspection->number }}?">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Hapus</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="row g-2 mt-3 text-center">
            <div class="col-3"><div class="kpi py-2"><div class="kpi-num">{{ $inspection->total_items }}</div><div class="kpi-sub mt-0">Butir</div></div></div>
            <div class="col-3"><div class="kpi py-2"><div class="kpi-num text-success">{{ $inspection->ok_count }}</div><div class="kpi-sub mt-0">Sesuai</div></div></div>
            <div class="col-3"><div class="kpi py-2"><div class="kpi-num text-danger">{{ $inspection->nok_count }}</div><div class="kpi-sub mt-0">Temuan</div></div></div>
            <div class="col-3"><div class="kpi py-2"><div class="kpi-num text-muted">{{ $inspection->na_count }}</div><div class="kpi-sub mt-0">N/A</div></div></div>
        </div>
    </div>
</div>

<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-list-check"></i>Hasil pemeriksaan</h3></div>
    <div class="table-responsive">
        <table class="table table-ssap mb-0">
            <thead><tr><th style="width:40px;">#</th><th>Butir</th><th>Hasil</th><th>Catatan / tindak lanjut</th></tr></thead>
            <tbody>
            @foreach ($inspection->items as $n => $item)
                <tr>
                    <td>{{ $n + 1 }}</td>
                    <td style="min-width:240px;">{{ $item->question }}</td>
                    <td>
                        @if ($item->result === 'ok')<span class="pill pill-success"><i class="bi bi-check-lg"></i>Sesuai</span>
                        @elseif ($item->result === 'nok')<span class="pill pill-danger"><i class="bi bi-x-lg"></i>Tidak sesuai</span>
                        @else<span class="pill pill-secondary">N/A</span>@endif
                    </td>
                    <td class="small">
                        {{ $item->note }}
                        @if ($item->hazard)
                            <div class="mt-1">
                                @if (\App\Models\Hazard::query()->visibleTo($portalUser)->whereKey($item->hazard->id)->exists())
                                    <a href="{{ route('ssap.hazards.show', $item->hazard) }}">{{ $item->hazard->number }}</a>
                                @else{{ $item->hazard->number }}@endif
                                <x-pill :meta="config('ssap.hazard_statuses.'.$item->hazard->status)" :icon="false" />
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if ($inspection->notes)
    <div class="s-card mb-3"><div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-chat-left-text"></i>Catatan umum</h3></div><div class="s-card-body" style="white-space:pre-line;">{{ $inspection->notes }}</div></div>
@endif

@include('ssap._attachments', ['record' => $inspection, 'canManage' => $canManage])
@endsection
