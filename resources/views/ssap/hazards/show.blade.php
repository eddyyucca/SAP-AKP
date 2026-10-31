@extends('layouts.app')

@php
    $closed = ! $hazard->isActive();
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
                    <dt>PIC</dt><dd>{{ $hazard->pic_name ? $hazard->pic_name.($hazard->pic_department ? ' - '.$hazard->pic_department : '') : 'Belum ditetapkan' }}</dd>
                    <dt>Tenggat</dt><dd class="{{ $hazard->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $hazard->due_date?->translatedFormat('d F Y') ?? '-' }}</dd>
                    @if ($hazard->closed_at)<dt>Selesai</dt><dd>{{ $hazard->closed_at->translatedFormat('d F Y') }} oleh {{ $hazard->closed_by }}@if ($hazard->closure_note)<div class="small text-muted">{{ $hazard->closure_note }}</div>@endif</dd>@endif
                    @if ($hazard->reject_reason)<dt>Alasan penolakan</dt><dd>{{ $hazard->reject_reason }}</dd>@endif
                </dl>
            </div>
        </div>

        @include('ssap._attachments', ['record' => $hazard, 'canManage' => $canManage])
        @include('ssap._actions', ['record' => $hazard, 'sourceType' => 'hazard', 'canManage' => $canManage, 'canClose' => $canClose, 'closed' => $closed])
    </div>

    <div class="col-12 col-xl-4">
        @if ($canClose)
            <div class="s-card mb-3 no-print">
                <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-patch-check"></i>Penutupan oleh pelapor</h3></div>
                <div class="s-card-body">
                    @if ($hazard->isActive())
                        <p class="small text-muted mb-2">Pelapor yang memastikan perbaikan sudah benar-benar selesai di lapangan.
                            @if ($pendingActions) Ada <strong>{{ $pendingActions }}</strong> tindakan yang belum terverifikasi; verifikasi tiap tindakan di bagian <a href="#tindakan">Tindakan korektif</a>, dan hazard otomatis ditutup setelah semuanya terverifikasi.
                            @else Semua tindakan sudah terverifikasi atau belum ada tindakan; Anda dapat menutup hazard ini.@endif</p>
                        @if ($pendingActions === 0)
                            <form method="POST" action="{{ route('ssap.hazards.resolve', $hazard) }}">
                                @csrf
                                <input type="hidden" name="to" value="closed">
                                <textarea name="note" rows="2" class="form-control form-control-sm mb-2 @error('note') is-invalid @enderror" maxlength="2000" placeholder="Hasil / kondisi setelah diperbaiki (wajib)" required>{{ old('note') }}</textarea>
                                @error('note')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                                @error('to')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                                <button class="btn btn-success btn-sm w-100" onclick="return confirm('Tutup hazard ini? Pastikan perbaikan sudah selesai.')"><i class="bi bi-check2-circle me-1"></i>Tutup hazard</button>
                            </form>
                        @endif
                    @else
                        <form method="POST" action="{{ route('ssap.hazards.resolve', $hazard) }}">
                            @csrf
                            <input type="hidden" name="to" value="open">
                            <input type="text" name="note" class="form-control form-control-sm mb-2" placeholder="Alasan membuka kembali">
                            <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-arrow-counterclockwise me-1"></i>Buka kembali</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        @if ($canManage && $hazard->isActive())
            <div class="s-card mb-3 no-print">
                <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-signpost-split"></i>Penanganan HSE</h3></div>
                <div class="s-card-body">
                        @if (! $hazard->pic_name)
                            <form method="POST" action="{{ route('ssap.hazards.assign', $hazard) }}" data-emp-scope class="mb-3">
                                @csrf
                                <div class="small fw-semibold mb-2">Tugaskan PIC</div>
                                <x-employee-search placeholder="Cari PIC di HRIS..." />
                                <div class="row g-2 mt-1">
                                    <div class="col-5"><input type="text" name="pic_nik" class="form-control form-control-sm" placeholder="NIK" data-emp-field="nik"></div>
                                    <div class="col-7"><input type="text" name="pic_name" class="form-control form-control-sm @error('pic_name') is-invalid @enderror" placeholder="Nama PIC *" data-emp-field="name" required></div>
                                    <div class="col-6"><input type="text" name="pic_department" class="form-control form-control-sm" placeholder="Departemen" data-emp-field="department"></div>
                                    <div class="col-6"><input type="email" name="pic_email" class="form-control form-control-sm" placeholder="Email" data-emp-field="email"></div>
                                    <div class="col-6"><select name="type" class="form-select form-select-sm">@foreach (config('ssap.action_types') as $k => $l)<option value="{{ $k }}" @selected($k === 'corrective')>{{ $l }}</option>@endforeach</select></div>
                                    <div class="col-6"><input type="date" name="due_date" value="{{ $hazard->due_date?->toDateString() ?? today()->toDateString() }}" min="{{ today()->toDateString() }}" class="form-control form-control-sm @error('due_date') is-invalid @enderror" required></div>
                                    <div class="col-12"><textarea name="action" rows="2" class="form-control form-control-sm @error('action') is-invalid @enderror" placeholder="Tindakan yang harus dikerjakan *" required>{{ old('action', $hazard->suggestion) }}</textarea></div>
                                </div>
                                <button class="btn btn-primary btn-sm w-100 mt-2"><i class="bi bi-person-check me-1"></i>Tugaskan</button>
                            </form>
                        @endif

                    @if ($hazard->status === 'open')
                        <form method="POST" action="{{ route('ssap.hazards.resolve', $hazard) }}" class="{{ $hazard->pic_name ? '' : 'border-top pt-3 mt-1' }}">
                            @csrf
                            <input type="hidden" name="to" value="rejected">
                            <textarea name="note" rows="2" class="form-control form-control-sm mb-2" maxlength="2000" placeholder="Alasan penolakan laporan (wajib)"></textarea>
                            <button class="btn btn-outline-danger btn-sm w-100" onclick="return confirm('Tolak laporan ini?')"><i class="bi bi-x-circle me-1"></i>Tolak laporan</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <div class="s-card mb-3">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-info-circle"></i>Informasi</h3></div>
            <div class="s-card-body">
                <dl class="def-list" style="grid-template-columns:100px 1fr;">
                    <dt>Pelapor</dt><dd>{{ $hazard->reporter_name }}@if ($hazard->reporter_nik)<div class="small text-muted">NIK {{ $hazard->reporter_nik }}</div>@endif</dd>
                    <dt>Departemen</dt><dd>{{ $hazard->reporter_department ?: '-' }}</dd>
                    <dt>Dilaporkan</dt><dd>{{ $hazard->created_at->translatedFormat('d M Y H:i') }}</dd>
                </dl>
            </div>
        </div>

        @include('ssap._timeline', ['record' => $hazard])
    </div>
</div>
@endsection
