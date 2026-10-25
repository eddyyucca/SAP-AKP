@extends('layouts.app')

@php
    $editing = $incident->exists;
    $types = config('ssap.incident_types');
    $persons = old('persons', $editing ? $incident->persons->map->only(['role', 'nik', 'name', 'company', 'department', 'position', 'injury_type', 'body_part', 'treatment', 'lost_days'])->all() : []);
    $occurred = old('occurred_at', optional($incident->occurred_at)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i'));
    $reporterDept = old('reporter_department', $editing ? $incident->reporter_department : ($me['department'] ?? ''));
    $reporterPos = old('reporter_position', $editing ? $incident->reporter_position : ($me['position'] ?? ''));
@endphp

@section('title', $editing ? 'Ubah '.$incident->number : 'Lapor Insiden')
@section('page-title', $editing ? 'Ubah Laporan '.$incident->number : 'Lapor Insiden')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ssap.incidents.index') }}">Insiden</a></li>
    <li class="breadcrumb-item active">{{ $editing ? 'Ubah' : 'Baru' }}</li>
@endsection

@section('content')
@if ($locations->isEmpty())
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Belum ada lokasi. Minta Administrator menambahkannya di menu <strong>Lokasi / Site</strong> sebelum membuat laporan.</div>
@endif

<form method="POST" action="{{ $editing ? route('ssap.incidents.update', $incident) : route('ssap.incidents.store') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="s-card mb-3">
                <div class="s-card-body pt-3">
                    <div class="section-h">1. Apa yang terjadi</div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label req" for="incidentType">Jenis kejadian</label>
                            <select name="type" id="incidentType" class="form-select @error('type') is-invalid @enderror" required
                                    data-severity='@json(collect($types)->map->default_severity)' data-injury='@json(collect($types)->map->injury)'>
                                @foreach ($types as $k => $t)
                                    <option value="{{ $k }}" @selected(old('type', $incident->type ?? 'near_miss') === $k)>{{ $t['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="form-text d-none" id="injuryHint"><i class="bi bi-info-circle me-1"></i>Jenis ini melibatkan cedera: tambahkan korban di bagian 3.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label req" for="incidentSeverity">Keparahan aktual</label>
                            <select name="severity" id="incidentSeverity" class="form-select @error('severity') is-invalid @enderror" data-touched="{{ $editing || old('severity') ? 1 : 0 }}">
                                @foreach (config('ssap.severity') as $k => $s)
                                    <option value="{{ $k }}" @selected((int) old('severity', $incident->severity ?? 1) === $k)>{{ $k }} - {{ $s['label'] }} ({{ $s['hint'] }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label req" for="occurred_at">Waktu kejadian</label>
                            <input type="datetime-local" name="occurred_at" id="occurred_at" value="{{ $occurred }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-control @error('occurred_at') is-invalid @enderror" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label req" for="location_id">Lokasi / site</label>
                            <select name="location_id" id="location_id" class="form-select @error('location_id') is-invalid @enderror" required>
                                <option value="">Pilih lokasi...</option>
                                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected((int) old('location_id', $incident->location_id) === $l->id)>{{ $l->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4"><label class="form-label" for="area_detail">Detail area</label><input type="text" name="area_detail" id="area_detail" value="{{ old('area_detail', $incident->area_detail) }}" class="form-control" maxlength="160" placeholder="mis. KM 3 jalan hauling"></div>
                        <div class="col-12 col-md-4"><label class="form-label" for="activity">Aktivitas saat kejadian</label><input type="text" name="activity" id="activity" value="{{ old('activity', $incident->activity) }}" class="form-control" maxlength="160" placeholder="mis. Penggantian ban"></div>
                        <div class="col-12 col-md-4"><label class="form-label" for="equipment">Unit / peralatan terkait</label><input type="text" name="equipment" id="equipment" value="{{ old('equipment', $incident->equipment) }}" class="form-control" maxlength="160" placeholder="mis. DT-045"></div>
                        <div class="col-12">
                            <label class="form-label req" for="title">Judul singkat</label>
                            <input type="text" name="title" id="title" value="{{ old('title', $incident->title) }}" class="form-control @error('title') is-invalid @enderror" maxlength="160" required placeholder="Ringkas dalam satu kalimat">
                        </div>
                        <div class="col-12">
                            <label class="form-label req" for="description">Kronologi kejadian</label>
                            <textarea name="description" id="description" rows="5" class="form-control @error('description') is-invalid @enderror" maxlength="5000" required placeholder="Apa yang terjadi, siapa yang terlibat, bagaimana urutannya (minimal 20 karakter)">{{ old('description', $incident->description) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="immediate_action">Tindakan segera yang sudah dilakukan</label>
                            <textarea name="immediate_action" id="immediate_action" rows="2" class="form-control" maxlength="3000" placeholder="mis. Area diamankan, korban dibawa ke klinik">{{ old('immediate_action', $incident->immediate_action) }}</textarea>
                        </div>
                    </div>

                    <div class="section-h">2. Orang yang terlibat</div>
                    <p class="small text-muted mb-2">Tambahkan korban dan saksi. Pilih dari HRIS agar departemen &amp; jabatan terisi otomatis, atau ketik manual untuk kontraktor/tamu.</p>
                    @error('persons')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                    <div id="personRows" data-next="{{ count($persons) }}">
                        @foreach ($persons as $idx => $p)
                            @include('ssap.incidents._person', ['i' => $idx, 'p' => $p])
                        @endforeach
                    </div>
                    <div id="personEmpty" class="small text-muted mb-2 {{ count($persons) ? 'd-none' : '' }}">Belum ada orang yang ditambahkan.</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addPerson"><i class="bi bi-person-plus me-1"></i>Tambah korban</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="addWitness"><i class="bi bi-eye me-1"></i>Tambah saksi</button>
                    </div>

                    <div class="section-h">3. Dampak</div>
                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="cost_estimate">Estimasi kerugian (Rp)</label>
                            <input type="number" min="0" step="1" name="cost_estimate" id="cost_estimate" value="{{ old('cost_estimate', $incident->cost_estimate ? (int) $incident->cost_estimate : '') }}" class="form-control @error('cost_estimate') is-invalid @enderror" placeholder="0">
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label" for="environmental_impact">Dampak lingkungan (bila ada)</label>
                            <input type="text" name="environmental_impact" id="environmental_impact" value="{{ old('environmental_impact', $incident->environmental_impact) }}" class="form-control" maxlength="2000" placeholder="mis. tumpahan oli ± 20 liter ke tanah">
                        </div>
                    </div>

                    <div class="section-h">4. Potensi risiko terburuk <span class="fw-normal text-muted text-lowercase">(opsional)</span></div>
                    <p class="small text-muted mb-2">Bila kejadian ini berulang dalam kondisi terburuk, seberapa parah dan seberapa mungkin?</p>
                    @error('likelihood')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                    <x-risk-picker :likelihood="$incident->likelihood" :consequence="$incident->consequence" :optional="true" />

                    <div class="section-h">5. Lampiran foto / dokumen</div>
                    <input type="file" name="files[]" id="files" class="form-control @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <div class="form-text">Maksimal {{ config('ssap.attachments.max_files') }} berkas, {{ config('ssap.attachments.max_kb') / 1024 }} MB per berkas (JPG, PNG, WEBP, PDF).</div>
                    @error('files')<div class="text-danger small">{{ $message }}</div>@enderror
                    @error('files.*')<div class="text-danger small">{{ $message }}</div>@enderror
                    @if ($editing && $incident->attachments->isNotEmpty())
                        <div class="small text-muted mt-2">{{ $incident->attachments->count() }} lampiran tersimpan (dikelola di halaman detail).</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="s-card mb-3">
                <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-person-badge"></i>Pelapor</h3></div>
                <div class="s-card-body">
                    <dl class="def-list mb-3" style="grid-template-columns:90px 1fr;">
                        <dt>Nama</dt><dd class="fw-semibold">{{ $editing ? $incident->reporter_name : ($me['name'] ?? $portalUser->name) }}</dd>
                        <dt>NIK</dt><dd>{{ $editing ? ($incident->reporter_nik ?: '-') : ($portalUser->employee_no ?: '-') }}</dd>
                    </dl>
                    <div class="mb-2"><label class="form-label small mb-1" for="reporter_department">Departemen</label><input type="text" name="reporter_department" id="reporter_department" value="{{ $reporterDept }}" class="form-control form-control-sm" maxlength="120"></div>
                    <div><label class="form-label small mb-1" for="reporter_position">Jabatan</label><input type="text" name="reporter_position" id="reporter_position" value="{{ $reporterPos }}" class="form-control form-control-sm" maxlength="120"></div>
                    @if (!$editing && !$me)
                        <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Data HRIS tidak ditemukan untuk akun ini; lengkapi departemen &amp; jabatan secara manual.</div>
                    @endif
                </div>
            </div>

            <div class="s-card mb-3">
                <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-stopwatch"></i>Ketentuan waktu</h3></div>
                <div class="s-card-body small text-muted">
                    <p class="mb-2">Insiden berat harus dilaporkan ke HSE secepatnya (fatal 1 jam, LTI 4 jam). Kasus lain maksimal 24 jam.</p>
                    <p class="mb-0">Investigasi: fatal {{ config('ssap.investigation_days.fatality') }} hari, LTI {{ config('ssap.investigation_days.lost_time') }} hari, lainnya {{ config('ssap.investigation_days.default') }} hari. <a href="{{ route('ssap.standard') }}">Lihat standar lengkap</a>.</p>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>{{ $editing ? 'Simpan perubahan' : 'Kirim laporan' }}</button>
                <a href="{{ $editing ? route('ssap.incidents.show', $incident) : route('ssap.incidents.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </div>
    </div>
</form>

<template id="personTpl">@include('ssap.incidents._person', ['i' => '__i__', 'p' => []])</template>
@endsection
