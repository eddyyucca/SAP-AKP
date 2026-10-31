@extends('layouts.app')

@php
@endphp

@section('title', $hazard->number)
@section('page-title', 'Hazard '.$hazard->number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ssap.hazards.index') }}">Hazard</a></li>
    <li class="breadcrumb-item active">{{ $hazard->number }}</li>
@endsection

@section('content')
<div class="s-card mb-3">
    <div class="s-card-body pt-3">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div style="min-width:0;flex:1;">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <x-cat :category="$hazard->category" :long="true" />
                    <x-pill :meta="$hazard->statusMeta()" />
                    @if ($hazard->isOverdue())<span class="pill pill-danger"><i class="bi bi-alarm"></i>Lewat tenggat {{ $hazard->due_date->diffInDays(today()) }} hari</span>@endif
                </div>
                <h2 class="h5 fw-bold mb-1" style="white-space:pre-line;">{{ \Illuminate\Support\Str::limit($hazard->description, 220) }}</h2>
                <div class="small text-muted">{{ $hazard->number }} &middot; ditemukan {{ $hazard->observed_at->translatedFormat('l, d F Y H:i') }} &middot; {{ $hazard->location?->name }}@if ($hazard->area_detail) &middot; {{ $hazard->area_detail }}@endif</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start no-print">
                <button class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer me-1"></i>Cetak</button>
                @if ($canEdit)<a href="{{ route('ssap.hazards.edit', $hazard) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Ubah</a>@endif
                @if (\App\Support\Access::isAdmin())
                    <form method="POST" action="{{ route('ssap.hazards.destroy', $hazard) }}" data-confirm="Hapus hazard {{ $hazard->number }}?">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Hapus</button></form>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="s-card mb-3">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-card-text"></i>Rincian</h3></div>
            <div class="s-card-body">
                <dl class="def-list">
                    <dt>Keterangan</dt><dd style="white-space:pre-line;">{{ $hazard->description }}</dd>
                    @if ($hazard->immediate_action)<dt>Tindakan segera</dt><dd style="white-space:pre-line;">{{ $hazard->immediate_action }}</dd>@endif
                    @if ($hazard->suggestion)<dt>Saran perbaikan</dt><dd style="white-space:pre-line;">{{ $hazard->suggestion }}</dd>@endif
                    <dt>Jenis</dt><dd>{{ $hazard->categoryLabel() }}</dd>
                    <dt>Pelapor (PIC)</dt><dd>{{ $hazard->pic_name ?: $hazard->reporter_name }}{{ ($hazard->pic_department ?: $hazard->reporter_department) ? ' - '.($hazard->pic_department ?: $hazard->reporter_department) : '' }}</dd>
                    <dt>Tenggat</dt><dd class="{{ $hazard->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $hazard->due_date?->translatedFormat('d F Y') ?? '-' }}</dd>
                    @if ($hazard->closed_at)<dt>Selesai</dt><dd>{{ $hazard->closed_at->translatedFormat('d F Y') }} oleh {{ $hazard->closed_by }}@if ($hazard->due_date && $hazard->closed_at->copy()->startOfDay()->gt($hazard->due_date)) <span class="pill pill-danger">terlambat</span>@else <span class="pill pill-success">tepat waktu</span>@endif</dd>@endif
                </dl>
            </div>
        </div>

        @include('ssap._attachments', ['record' => $hazard, 'canManage' => $canClose])
        {{-- Tindakan perbaikan: dikerjakan secara manual di lapangan; pelapor (PIC) hanya mencatatnya di sini. --}}
        <div class="s-card mb-3" id="perbaikan">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-tools"></i>Tindakan perbaikan</h3></div>
            <div class="s-card-body">
                @if ($hazard->status === 'closed')
                    <div class="mb-2" style="white-space:pre-line;">{{ $hazard->closure_note }}</div>
                    <div class="small text-muted">Dicatat oleh {{ $hazard->closed_by }} pada {{ $hazard->closed_at?->translatedFormat('d F Y H:i') }}. Foto sebelum dan sesudah ada di bagian Foto & dokumen.</div>
                    @if ($canClose)
                        <form method="POST" action="{{ route('ssap.hazards.resolve', $hazard) }}" class="mt-3 no-print d-flex gap-2" data-confirm="Buka kembali hazard ini?">
                            @csrf
                            <input type="hidden" name="to" value="open">
                            <input type="text" name="note" class="form-control form-control-sm" placeholder="Alasan membuka kembali (perbaikan belum tuntas)">
                            <button class="btn btn-outline-primary btn-sm text-nowrap"><i class="bi bi-arrow-counterclockwise me-1"></i>Buka kembali</button>
                        </form>
                    @endif
                @elseif ($canClose)
                    <p class="small text-muted">Perbaikan dikerjakan secara manual di lapangan. Setelah selesai, catat tindakan perbaikannya di sini; laporan otomatis ditutup. Tim safety hanya memantau.</p>
                    <form method="POST" action="{{ route('ssap.hazards.resolve', $hazard) }}" enctype="multipart/form-data" class="no-print">
                        @csrf
                        <textarea name="note" rows="3" class="form-control form-control-sm mb-2 @error('note') is-invalid @enderror" maxlength="3000" placeholder="Tindakan perbaikan yang sudah dilakukan (wajib untuk menutup)">{{ old('note') }}</textarea>
                        @error('note')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        @error('to')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        <div class="row g-2 align-items-start">
                            <div class="col-12 col-md-6"><input type="file" name="files[]" class="form-control form-control-sm" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" title="Foto sesudah perbaikan"><div class="form-text">Foto sesudah perbaikan (bukti)</div></div>
                            <div class="col-12 col-md-6 d-flex gap-2 justify-content-md-end">
                                @if ($hazard->status === 'open')<button name="to" value="in_progress" class="btn btn-outline-warning btn-sm"><i class="bi bi-hourglass-split me-1"></i>Sedang diperbaiki</button>@endif
                                <button name="to" value="closed" class="btn btn-success btn-sm" onclick="return confirm('Catat tindakan perbaikan dan tutup hazard ini?')"><i class="bi bi-check2-circle me-1"></i>Selesai &amp; tutup</button>
                            </div>
                        </div>
                        @error('files.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </form>
                @else
                    <div class="small text-muted">Belum ada tindakan perbaikan. Pelapor ({{ $hazard->reporter_name }}) akan mencatatnya setelah perbaikan selesai dikerjakan.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="s-card mb-3">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-info-circle"></i>Informasi</h3></div>
            <div class="s-card-body">
                <dl class="def-list" style="grid-template-columns:100px 1fr;">
                    <dt>Pelapor = PIC</dt><dd>{{ $hazard->reporter_name }}@if ($hazard->reporter_nik)<div class="small text-muted">NIK {{ $hazard->reporter_nik }}</div>@endif</dd>
                    <dt>Departemen</dt><dd>{{ $hazard->reporter_department ?: '-' }}</dd>
                    <dt>Dilaporkan</dt><dd>{{ $hazard->created_at->translatedFormat('d M Y H:i') }}</dd>
                </dl>
            </div>
        </div>

        @include('ssap._timeline', ['record' => $hazard])
    </div>
</div>
@endsection
