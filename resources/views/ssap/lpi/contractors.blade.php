@extends('layouts.app')

@section('title', 'Kontraktor')
@section('page-title', 'Kontraktor Area IUP / IUJP')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ssap.lpi.index') }}">Monitoring LPI</a></li>
    <li class="breadcrumb-item active">Kontraktor</li>
@endsection

@section('content')
@php($areas = config('ssap.contractor_areas'))
<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-plus-circle"></i>Tambah kontraktor</h3></div>
    <div class="s-card-body">
        <form method="POST" action="{{ route('ssap.contractors.store') }}" class="row g-2">
            @csrf
            @include('ssap.lpi._contractor_fields', ['c' => null, 'areas' => $areas])
            <div class="col-12 text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah</button></div>
        </form>
    </div>
</div>

@forelse ($contractors as $c)
    <div class="s-card mb-2 {{ $c->is_active ? '' : 'opacity-75' }}">
        <div class="s-card-body pt-3">
            <form method="POST" action="{{ route('ssap.contractors.update', $c) }}" class="row g-2">
                @csrf @method('PUT')
                @include('ssap.lpi._contractor_fields', ['c' => $c, 'areas' => $areas])
                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ca{{ $c->id }}" @checked($c->is_active)><label class="form-check-label small" for="ca{{ $c->id }}">Aktif</label></div>
                        <span class="small text-muted">{{ $usage[$c->id] ?? 0 }} insiden</span>
                    </div>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </form>
            @if (! ($usage[$c->id] ?? 0))
                <form method="POST" action="{{ route('ssap.contractors.destroy', $c) }}" data-confirm="Hapus kontraktor {{ $c->name }}?" class="mt-1">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash me-1"></i>Hapus</button></form>
            @endif
        </div>
    </div>
@empty
    <div class="s-card"><div class="empty-state"><i class="bi bi-buildings"></i>Belum ada kontraktor. Tambahkan kontraktor di atas.</div></div>
@endforelse
@endsection
