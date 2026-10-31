@extends('layouts.app')

@php
    $editing = $hazard->exists;
    $observed = old('observed_at', optional($hazard->observed_at)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i'));
@endphp

@section('title', $editing ? 'Ubah '.$hazard->number : 'Lapor Hazard')
@section('page-title', $editing ? 'Ubah Hazard '.$hazard->number : 'Lapor Hazard')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ssap.hazards.index') }}">Hazard</a></li>
    <li class="breadcrumb-item active">{{ $editing ? 'Ubah' : 'Baru' }}</li>
@endsection

@section('content')
@if ($locations->isEmpty())
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Belum ada lokasi. Minta Administrator menambahkannya di menu <strong>Lokasi / Site</strong>.</div>
@endif

<form method="POST" action="{{ $editing ? route('ssap.hazards.update', $hazard) : route('ssap.hazards.store') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="s-card mb-3">
                <div class="s-card-body pt-3">
                    <div class="section-h">1. Apa yang Anda temukan</div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label req">Jenis hazard</label>
                            <div class="row g-2">
                                @foreach (config('ssap.hazard_categories') as $k => $label)
                                    @php($checked = old('category', $hazard->category ?? 'kta') === $k)
                                    <div class="col-12 col-sm-6">
                                        <input type="radio" class="btn-check" name="category" id="cat_{{ $k }}" value="{{ $k }}" @checked($checked) required>
                                        <label class="btn btn-outline-secondary w-100 text-start py-2" for="cat_{{ $k }}" style="--bs-btn-active-bg:{{ config('ssap.hazard_category_colors.'.$k) }};--bs-btn-active-border-color:{{ config('ssap.hazard_category_colors.'.$k) }};--bs-btn-hover-bg:{{ config('ssap.hazard_category_colors.'.$k) }};--bs-btn-hover-border-color:{{ config('ssap.hazard_category_colors.'.$k) }};">
                                            <strong>{{ strtoupper($k) }}</strong> <span class="small">{{ $k === 'tta' ? 'Perilaku / tindakan orang yang tidak aman' : 'Kondisi lokasi, alat, atau lingkungan yang tidak aman' }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('category')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label req" for="observed_at">Waktu ditemukan</label>
                            <input type="datetime-local" name="observed_at" id="observed_at" value="{{ $observed }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-control @error('observed_at') is-invalid @enderror" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label req" for="location_id">Lokasi / site</label>
                            <select name="location_id" id="location_id" class="form-select @error('location_id') is-invalid @enderror" required>
                                <option value="">Pilih lokasi...</option>
                                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected((int) old('location_id', $hazard->location_id) === $l->id)>{{ $l->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6"><label class="form-label" for="area_detail">Detail area</label><input type="text" name="area_detail" id="area_detail" value="{{ old('area_detail', $hazard->area_detail) }}" class="form-control" maxlength="160" placeholder="mis. Persimpangan KM 3"></div>
                        <div class="col-12">
                            <label class="form-label req" for="description">Keterangan</label>
                            <textarea name="description" id="description" rows="4" class="form-control @error('description') is-invalid @enderror" maxlength="5000" required placeholder="Jelaskan temuan Anda: apa, di mana, dan siapa yang berisiko">{{ old('description', $hazard->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6"><label class="form-label" for="immediate_action">Tindakan segera yang sudah dilakukan</label><textarea name="immediate_action" id="immediate_action" rows="2" class="form-control" maxlength="3000" placeholder="mis. Area diberi pembatas">{{ old('immediate_action', $hazard->immediate_action) }}</textarea></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="suggestion">Saran perbaikan</label><textarea name="suggestion" id="suggestion" rows="2" class="form-control" maxlength="3000">{{ old('suggestion', $hazard->suggestion) }}</textarea></div>
                    </div>

                    <div class="section-h">2. Foto / dokumen</div>
                    <input type="file" name="files[]" id="files" class="form-control" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <div class="form-text">Foto sangat membantu. Maksimal {{ config('ssap.attachments.max_files') }} berkas, {{ config('ssap.attachments.max_kb') / 1024 }} MB per berkas.</div>
                    @error('files')<div class="text-danger small">{{ $message }}</div>@enderror
                    @error('files.*')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="s-card mb-3">
                <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-person-badge"></i>Pelapor</h3></div>
                <div class="s-card-body">
                    <dl class="def-list mb-3" style="grid-template-columns:90px 1fr;">
                        <dt>Nama</dt><dd class="fw-semibold">{{ $editing ? $hazard->reporter_name : ($me['name'] ?? $portalUser->name) }}</dd>
                        <dt>NIK</dt><dd>{{ $editing ? ($hazard->reporter_nik ?: '-') : ($portalUser->employee_no ?: '-') }}</dd>
                    </dl>
                    <dl class="def-list mb-2" style="grid-template-columns:90px 1fr;">
                        <dt>Departemen</dt><dd>{{ $editing ? ($hazard->reporter_department ?: '-') : ($me['department'] ?? '-') }}</dd>
                        <dt>Jabatan</dt><dd>{{ $editing ? ($hazard->reporter_position ?: '-') : ($me['position'] ?? '-') }}</dd>
                    </dl>
                    <div class="form-text"><i class="bi bi-lock me-1"></i>Data pelapor (PIC) diambil dari HRIS dan tidak dapat diubah.</div>
                </div>
            </div>
            <div class="s-card mb-3">
                <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-stopwatch"></i>Target penyelesaian</h3></div>
                <div class="s-card-body small">Hazard perlu ditutup dalam <strong>{{ config('ssap.hazard_sla_days') }} hari</strong>. Anda sebagai PIC mencatat tindakan perbaikan di halaman hazard setelah perbaikan selesai; tim safety hanya memantau.</div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>{{ $editing ? 'Simpan perubahan' : 'Kirim laporan' }}</button>
                <a href="{{ $editing ? route('ssap.hazards.show', $hazard) : route('ssap.hazards.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </div>
    </div>
</form>
@endsection
