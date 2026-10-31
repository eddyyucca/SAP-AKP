@extends('layouts.app')

@section('title', 'Lokasi / Site')
@section('page-title', 'Lokasi / Site')
@section('breadcrumb')
    <li class="breadcrumb-item active">Lokasi</li>
@endsection

@section('content')
<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-plus-circle"></i>Tambah lokasi</h3></div>
    <div class="s-card-body">
        <form method="POST" action="{{ route('ssap.locations.store') }}" class="row g-2">
            @csrf
            <div class="col-5 col-md-2"><input type="text" name="code" value="{{ old('code') }}" class="form-control form-control-sm" placeholder="Kode (mis. PIT-N)" maxlength="20" required></div>
            <div class="col-7 col-md-6"><input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-sm" placeholder="Nama lokasi / site" maxlength="120" required></div>
            <div class="col-12 col-md-auto"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah</button></div>
        </form>
    </div>
</div>

<div class="s-card">
    <div class="table-responsive">
        <table class="table table-ssap mb-0 align-middle">
            <thead><tr><th>Kode</th><th>Nama</th><th class="num">Inspeksi</th><th class="num">Hazard</th><th>Aktif</th><th></th></tr></thead>
            <tbody>
            @forelse ($locations as $l)
                <tr>
                    <td style="width:140px;"><input form="loc{{ $l->id }}" type="text" name="code" value="{{ $l->code }}" class="form-control form-control-sm" maxlength="20"></td>
                    <td><input form="loc{{ $l->id }}" type="text" name="name" value="{{ $l->name }}" class="form-control form-control-sm" maxlength="120"></td>
                    <td class="num">{{ $inspections[$l->id] ?? 0 }}</td>
                    <td class="num">{{ $hazards[$l->id] ?? 0 }}</td>
                    <td><div class="form-check form-switch"><input form="loc{{ $l->id }}" class="form-check-input" type="checkbox" name="is_active" value="1" @checked($l->is_active)></div></td>
                    <td class="text-end text-nowrap">
                        <button form="loc{{ $l->id }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-save"></i></button>
                        <form method="POST" action="{{ route('ssap.locations.destroy', $l) }}" class="d-inline" data-confirm="Hapus lokasi {{ $l->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-geo-alt"></i>Belum ada lokasi. Tambahkan lokasi pertama di atas.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @foreach ($locations as $l)
        <form method="POST" action="{{ route('ssap.locations.update', $l) }}" id="loc{{ $l->id }}" class="d-none">@csrf @method('PUT')</form>
    @endforeach
</div>
@endsection
