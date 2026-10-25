@extends('layouts.app')

@section('title', 'Jam Kerja')
@section('page-title', 'Jam Kerja Bulanan')
@section('breadcrumb')
    <li class="breadcrumb-item active">Jam kerja</li>
@endsection

@section('content')
<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Jam kerja total seluruh karyawan &amp; kontraktor per lokasi per bulan. Dipakai menghitung LTIFR, TRIR, dan severity rate. Kosongkan bulan yang belum ada datanya.</div>

<form method="GET" class="mb-3 d-flex gap-2 align-items-center">
    <label class="small mb-0">Tahun</label>
    <select name="year" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">@foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach</select>
</form>

@if ($locations->isEmpty())
    <div class="alert alert-warning">Belum ada lokasi aktif. Administrator dapat menambahkannya di menu Lokasi / Site.</div>
@else
<form method="POST" action="{{ route('ssap.manhours.store') }}">
    @csrf
    <input type="hidden" name="year" value="{{ $year }}">
    <div class="s-card">
        <div class="table-responsive">
            <table class="table table-ssap mb-0 align-middle">
                <thead><tr><th style="min-width:160px;">Lokasi</th>@for ($m = 1; $m <= 12; $m++)<th class="text-center">{{ \Illuminate\Support\Carbon::create(2000, $m, 1)->translatedFormat('M') }}</th>@endfor<th class="num">Total</th></tr></thead>
                <tbody>
                @foreach ($locations as $loc)
                    <tr>
                        <td class="fw-semibold">{{ $loc->name }}<div class="small text-muted fw-normal">{{ $loc->code }}</div></td>
                        @for ($m = 1; $m <= 12; $m++)
                            <td style="min-width:96px;"><input type="number" min="0" max="99999999" name="hours[{{ $loc->id }}][{{ $m }}]" value="{{ old('hours.'.$loc->id.'.'.$m, $hours[$loc->id][$m] ?? '') }}" class="form-control form-control-sm text-end"></td>
                        @endfor
                        <td class="num fw-bold">{{ number_format(collect($hours[$loc->id] ?? [])->sum(), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top text-end"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan jam kerja {{ $year }}</button></div>
    </div>
</form>
@endif
@endsection
