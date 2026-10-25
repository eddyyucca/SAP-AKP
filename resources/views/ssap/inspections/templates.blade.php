@extends('layouts.app')

@section('title', 'Template Inspeksi')
@section('page-title', 'Template Inspeksi')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ssap.inspections.index') }}">Inspeksi</a></li>
    <li class="breadcrumb-item active">Template</li>
@endsection

@section('content')
<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-plus-circle"></i>Template baru</h3></div>
    <div class="s-card-body">
        <form method="POST" action="{{ route('ssap.inspection-templates.store') }}" class="row g-2">
            @csrf
            @include('ssap.inspections._template_fields', ['t' => null, 'prefix' => 'new'])
            <div class="col-12 text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Buat template</button></div>
        </form>
    </div>
</div>

@foreach ($templates as $t)
    <div class="s-card mb-3 {{ $t->is_active ? '' : 'opacity-75' }}">
        <div class="s-card-head">
            <h3 class="s-card-title"><i class="bi bi-ui-checks"></i>{{ $t->name }} @unless ($t->is_active)<span class="pill pill-secondary">Nonaktif</span>@endunless</h3>
            <div class="s-card-sub">{{ $t->items_count }} butir &middot; target {{ $t->target_per_month }}x/bulan &middot; dipakai {{ $usage[$t->id] ?? 0 }}x</div>
        </div>
        <div class="s-card-body">
            <form method="POST" action="{{ route('ssap.inspection-templates.update', $t) }}" class="row g-2">
                @csrf @method('PUT')
                @include('ssap.inspections._template_fields', ['t' => $t, 'prefix' => 't'.$t->id])
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act{{ $t->id }}" @checked($t->is_active)><label class="form-check-label small" for="act{{ $t->id }}">Aktif (muncul saat memilih inspeksi)</label></div>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </form>
            @if (! ($usage[$t->id] ?? 0))
                <form method="POST" action="{{ route('ssap.inspection-templates.destroy', $t) }}" data-confirm="Hapus template ini?" class="mt-2">@csrf @method('DELETE')
                    <button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash me-1"></i>Hapus template</button>
                </form>
            @endif
        </div>
    </div>
@endforeach
@endsection
