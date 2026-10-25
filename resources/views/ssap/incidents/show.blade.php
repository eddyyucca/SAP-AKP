@extends('layouts.app')

@php
    $meta = $incident->typeMeta();
    $sev = $incident->severityMeta();
    $risk = $incident->riskMeta();
    $steps = ['submitted' => 'Dilaporkan', 'investigating' => 'Investigasi', 'follow_up' => 'Tindak lanjut', 'closed' => 'Ditutup'];
    $order = array_keys($steps);
    $pos = array_search($incident->status, $order, true);
    $closed = in_array($incident->status, ['closed', 'cancelled'], true);
    $whyChain = $incident->why_chain ?? [];
@endphp

@section('title', $incident->number)
@section('page-title', 'Insiden '.$incident->number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ssap.incidents.index') }}">Insiden</a></li>
    <li class="breadcrumb-item active">{{ $incident->number }}</li>
@endsection

@section('content')
{{-- Kepala laporan --}}
<div class="s-card mb-3">
    <div class="s-card-body pt-3">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div style="min-width:0;flex:1;">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <span class="pill" style="background:{{ $meta['color'] }}1f;color:{{ $meta['color'] }};"><span class="legend-dot m-0" style="background:{{ $meta['color'] }}"></span>{{ $meta['label'] }}</span>
                    <x-pill :meta="$incident->statusMeta()" />
                    <span class="pill" style="background:{{ $sev['color'] }}1f;color:{{ $sev['color'] }};">Keparahan {{ $incident->severity }} &middot; {{ $sev['label'] }}</span>
                    @if ($risk)<x-risk :level="$incident->risk_level" :score="$incident->risk_score" title="Potensi risiko terburuk" />@endif
                    @if ($incident->investigationOverdue())<span class="pill pill-danger"><i class="bi bi-alarm"></i>Investigasi terlambat</span>@endif
                    @if ($incident->reportedLate())<span class="pill pill-warning" title="Dilaporkan {{ $incident->reportDelayHours() }} jam setelah kejadian; batas {{ $incident->notifyLimitHours() }} jam"><i class="bi bi-hourglass"></i>Lapor terlambat</span>@endif
                </div>
                <h2 class="h5 fw-bold mb-1">{{ $incident->title }}</h2>
                <div class="small text-muted">{{ $incident->number }} &middot; kejadian {{ $incident->occurred_at->translatedFormat('l, d F Y H:i') }} &middot; {{ $incident->location?->name }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start no-print">
                <a href="{{ route('ssap.incidents.print', $incident) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Cetak laporan</a>
                @if ($canEdit)<a href="{{ route('ssap.incidents.edit', $incident) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Ubah</a>@endif
                @if (\App\Support\Access::isAdmin())
                    <form method="POST" action="{{ route('ssap.incidents.destroy', $incident) }}" data-confirm="Hapus laporan {{ $incident->number }}? Laporan disembunyikan dari semua daftar.">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Hapus</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($incident->status !== 'cancelled')
            <div class="stepper mt-4">
                @foreach ($steps as $key => $label)
                    @php($idx = array_search($key, $order, true))
                    <div class="step {{ $idx < $pos || $incident->status === 'closed' ? 'done' : ($idx === $pos ? 'now' : '') }}">
                        <span class="dot">@if ($idx < $pos || $incident->status === 'closed')<i class="bi bi-check"></i>@else{{ $idx + 1 }}@endif</span>{{ $label }}
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-secondary mt-3 mb-0 small"><i class="bi bi-x-circle me-1"></i><strong>Dibatalkan.</strong> {{ $incident->cancel_reason }}</div>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        {{-- Rincian --}}
        <div class="s-card mb-3">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-card-text"></i>Rincian kejadian</h3></div>
            <div class="s-card-body">
                <dl class="def-list">
                    <dt>Kronologi</dt><dd style="white-space:pre-line;">{{ $incident->description }}</dd>
                    @if ($incident->immediate_action)<dt>Tindakan segera</dt><dd style="white-space:pre-line;">{{ $incident->immediate_action }}</dd>@endif
                    <dt>Lokasi</dt><dd>{{ $incident->location?->name }}@if ($incident->area_detail) &middot; {{ $incident->area_detail }}@endif</dd>
                    @if ($incident->activity)<dt>Aktivitas</dt><dd>{{ $incident->activity }}</dd>@endif
                    @if ($incident->equipment)<dt>Unit / peralatan</dt><dd>{{ $incident->equipment }}</dd>@endif
                    <dt>Korban</dt><dd>{{ $incident->injured_count }} orang &middot; {{ $incident->lost_days }} hari kerja hilang</dd>
                    @if ($incident->cost_estimate)<dt>Estimasi kerugian</dt><dd>Rp {{ number_format($incident->cost_estimate, 0, ',', '.') }}</dd>@endif
                    @if ($incident->environmental_impact)<dt>Dampak lingkungan</dt><dd>{{ $incident->environmental_impact }}</dd>@endif
                </dl>
            </div>
        </div>

        @if ($incident->persons->isNotEmpty())
            <div class="s-card mb-3">
                <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-people"></i>Orang yang terlibat</h3></div>
                <div class="table-responsive">
                    <table class="table table-ssap mb-0">
                        <thead><tr><th>Peran</th><th>Nama</th><th>Departemen / jabatan</th><th>Cedera</th><th class="num">Hari hilang</th></tr></thead>
                        <tbody>
                        @foreach ($incident->persons as $p)
                            <tr>
                                <td><span class="pill {{ $p->role === 'korban' ? 'pill-danger' : 'pill-secondary' }}">{{ $p->roleLabel() }}</span></td>
                                <td><div class="fw-semibold">{{ $p->name }}</div><div class="small text-muted">{{ $p->nik ? 'NIK '.$p->nik : ($p->company ?: '-') }}</div></td>
                                <td class="small">{{ $p->department ?: '-' }}<div class="text-muted">{{ $p->position ?: '' }}</div></td>
                                <td class="small">{{ $p->injury_type ?: '-' }}@if ($p->body_part) &middot; {{ $p->body_part }}@endif @if ($p->treatment)<div class="text-muted">{{ $p->treatment }}</div>@endif</td>
                                <td class="num">{{ $p->role === 'korban' ? $p->lost_days : '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @include('ssap._attachments', ['record' => $incident, 'canManage' => $canManage])

        {{-- Investigasi --}}
        <div class="s-card mb-3" id="investigasi">
            <div class="s-card-head">
                <h3 class="s-card-title"><i class="bi bi-search"></i>Investigasi &amp; akar masalah</h3>
                @if ($incident->investigator_name)<span class="s-card-sub">Investigator: {{ $incident->investigator_name }} &middot; batas {{ $incident->investigation_due?->translatedFormat('d M Y') ?? '-' }}</span>@endif
            </div>
            <div class="s-card-body">
                @if ($canManage && ! $closed)
                    <form method="POST" action="{{ route('ssap.incidents.investigate', $incident) }}" data-emp-scope data-no-lock>
                        @csrf @method('PUT')
                        <div class="row g-2">
                            <div class="col-12"><div class="section-h mt-0">Investigator</div></div>
                            <div class="col-12 col-md-6"><x-employee-search placeholder="Cari investigator di HRIS..." /></div>
                            <div class="col-6 col-md-3"><label class="form-label small mb-1">Batas investigasi</label><input type="date" name="investigation_due" value="{{ old('investigation_due', $incident->investigation_due?->toDateString()) }}" class="form-control form-control-sm"></div>
                            <div class="col-6 col-md-3"></div>
                            <div class="col-6 col-md-2"><input type="text" name="investigator_nik" value="{{ old('investigator_nik', $incident->investigator_nik) }}" class="form-control form-control-sm" placeholder="NIK" data-emp-field="nik"></div>
                            <div class="col-6 col-md-4"><input type="text" name="investigator_name" value="{{ old('investigator_name', $incident->investigator_name) }}" class="form-control form-control-sm @error('investigator_name') is-invalid @enderror" placeholder="Nama investigator" data-emp-field="name"></div>
                            <div class="col-6 col-md-3"><input type="text" name="investigator_department" value="{{ old('investigator_department') }}" class="form-control form-control-sm" placeholder="Departemen" data-emp-field="department"></div>
                            <div class="col-6 col-md-3"><input type="email" name="investigator_email" value="{{ old('investigator_email', $incident->investigator_email) }}" class="form-control form-control-sm" placeholder="Email" data-emp-field="email"></div>

                            <div class="col-12"><div class="section-h">Analisis</div></div>
                            <div class="col-12"><label class="form-label small mb-1">Penyebab langsung (immediate cause)</label><textarea name="immediate_cause" rows="2" class="form-control form-control-sm" maxlength="3000">{{ old('immediate_cause', $incident->immediate_cause) }}</textarea></div>
                            <div class="col-12">
                                <label class="form-label small mb-1">Analisis 5-Why <span class="text-muted">(telusuri “mengapa” hingga ke akar)</span></label>
                                @for ($n = 0; $n < 5; $n++)
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text">Mengapa {{ $n + 1 }}</span>
                                        <input type="text" name="why[]" value="{{ old('why.'.$n, $whyChain[$n] ?? '') }}" class="form-control" maxlength="500">
                                    </div>
                                @endfor
                            </div>
                            <div class="col-12 col-md-5"><label class="form-label small mb-1">Kategori akar masalah</label>
                                <select name="root_cause_category" class="form-select form-select-sm">
                                    <option value="">Pilih...</option>
                                    @foreach (config('ssap.root_cause_categories') as $k => $label)<option value="{{ $k }}" @selected(old('root_cause_category', $incident->root_cause_category) === $k)>{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-7"><label class="form-label small mb-1">Akar masalah (root cause)</label><textarea name="root_cause" rows="2" class="form-control form-control-sm" maxlength="3000">{{ old('root_cause', $incident->root_cause) }}</textarea></div>
                            <div class="col-12 col-md-6"><label class="form-label small mb-1">Faktor yang berkontribusi</label><textarea name="contributing_factors" rows="2" class="form-control form-control-sm" maxlength="3000">{{ old('contributing_factors', $incident->contributing_factors) }}</textarea></div>
                            <div class="col-12 col-md-6"><label class="form-label small mb-1">Pelajaran yang dipetik (lessons learned)</label><textarea name="lessons_learned" rows="2" class="form-control form-control-sm" maxlength="3000">{{ old('lessons_learned', $incident->lessons_learned) }}</textarea></div>

                            <div class="col-12"><div class="section-h">Dampak &amp; pelaporan eksternal</div></div>
                            <div class="col-6 col-md-3"><label class="form-label small mb-1">Hari kerja hilang</label><input type="number" min="0" name="lost_days" value="{{ old('lost_days', $incident->lost_days) }}" class="form-control form-control-sm"></div>
                            <div class="col-6 col-md-4"><label class="form-label small mb-1">Estimasi kerugian (Rp)</label><input type="number" min="0" name="cost_estimate" value="{{ old('cost_estimate', $incident->cost_estimate ? (int) $incident->cost_estimate : '') }}" class="form-control form-control-sm"></div>
                            <div class="col-12 col-md-5 d-flex align-items-end">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="reported_to_authority" value="1" id="auth" @checked(old('reported_to_authority', $incident->reported_to_authority))><label class="form-check-label small" for="auth">Sudah dilaporkan ke otoritas (mis. KTT / Inspektur Tambang)</label></div>
                            </div>
                            <div class="col-12"><input type="text" name="authority_note" value="{{ old('authority_note', $incident->authority_note) }}" class="form-control form-control-sm" maxlength="200" placeholder="Nomor / tanggal laporan ke otoritas (opsional)"></div>
                        </div>
                        <div class="mt-3 text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan investigasi</button></div>
                    </form>
                @else
                    @if ($incident->root_cause || $incident->immediate_cause || $whyChain)
                        <dl class="def-list">
                            @if ($incident->investigator_name)<dt>Investigator</dt><dd>{{ $incident->investigator_name }}</dd>@endif
                            @if ($incident->immediate_cause)<dt>Penyebab langsung</dt><dd style="white-space:pre-line;">{{ $incident->immediate_cause }}</dd>@endif
                            @if ($whyChain)<dt>Analisis 5-Why</dt><dd><ol class="mb-0 ps-3">@foreach ($whyChain as $w)<li>{{ $w }}</li>@endforeach</ol></dd>@endif
                            @if ($incident->root_cause_category)<dt>Kategori akar masalah</dt><dd>{{ config('ssap.root_cause_categories.'.$incident->root_cause_category) }}</dd>@endif
                            @if ($incident->root_cause)<dt>Akar masalah</dt><dd style="white-space:pre-line;">{{ $incident->root_cause }}</dd>@endif
                            @if ($incident->contributing_factors)<dt>Faktor kontribusi</dt><dd style="white-space:pre-line;">{{ $incident->contributing_factors }}</dd>@endif
                            @if ($incident->lessons_learned)<dt>Pelajaran</dt><dd style="white-space:pre-line;">{{ $incident->lessons_learned }}</dd>@endif
                            <dt>Lapor ke otoritas</dt><dd>{{ $incident->reported_to_authority ? 'Ya'.($incident->authority_note ? ' - '.$incident->authority_note : '') : 'Belum / tidak diperlukan' }}</dd>
                        </dl>
                    @else
                        <div class="small text-muted">Investigasi belum dimulai.</div>
                    @endif
                @endif
            </div>
        </div>

        @include('ssap._actions', ['record' => $incident, 'sourceType' => 'incident', 'canManage' => $canManage, 'closed' => $closed])
    </div>

    <div class="col-12 col-xl-4">
        @if ($canManage && count($transitions))
            <div class="s-card mb-3 no-print">
                <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-signpost-split"></i>Ubah status</h3></div>
                <div class="s-card-body">
                    <form method="POST" action="{{ route('ssap.incidents.transition', $incident) }}">
                        @csrf
                        <textarea name="note" rows="2" class="form-control form-control-sm mb-2 @error('note') is-invalid @enderror" maxlength="2000" placeholder="Catatan (wajib untuk menutup atau membatalkan)">{{ old('note') }}</textarea>
                        @error('to')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        <div class="d-grid gap-2">
                            @foreach ($transitions as $to)
                                @php($tm = config('ssap.incident_statuses.'.$to))
                                @php($label = match(true) { $incident->status === 'closed' => 'Buka kembali (Tindak lanjut)', $incident->status === 'cancelled' => 'Aktifkan kembali', $to === 'investigating' && $incident->status === 'follow_up' => 'Kembali ke Investigasi', default => ($to === 'cancelled' ? 'Batalkan laporan' : 'Lanjut ke '.$tm['label']) })
                                <button name="to" value="{{ $to }}" class="btn btn-sm {{ $to === 'closed' ? 'btn-success' : ($to === 'cancelled' ? 'btn-outline-danger' : 'btn-primary') }}"
                                        @if (in_array($to, ['cancelled', 'closed'], true)) onclick="return confirm('{{ $to === 'closed' ? 'Tutup laporan ini?' : 'Batalkan laporan ini?' }}')" @endif>
                                    <i class="bi {{ $tm['icon'] }} me-1"></i>{{ $label }}
                                </button>
                            @endforeach
                        </div>
                        @if ($pendingActions && in_array('closed', $transitions, true))
                            <div class="form-text mt-2 text-warning"><i class="bi bi-exclamation-triangle me-1"></i>{{ $pendingActions }} tindakan belum terverifikasi - harus selesai sebelum laporan ditutup.</div>
                        @endif
                    </form>
                </div>
            </div>
        @endif

        <div class="s-card mb-3">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-info-circle"></i>Informasi</h3></div>
            <div class="s-card-body">
                <dl class="def-list" style="grid-template-columns:110px 1fr;">
                    <dt>Pelapor</dt><dd>{{ $incident->reporter_name }}@if ($incident->reporter_nik)<div class="small text-muted">NIK {{ $incident->reporter_nik }}</div>@endif</dd>
                    <dt>Departemen</dt><dd>{{ $incident->reporter_department ?: '-' }}@if ($incident->reporter_position)<div class="small text-muted">{{ $incident->reporter_position }}</div>@endif</dd>
                    <dt>Dilaporkan</dt><dd>{{ $incident->created_at->translatedFormat('d M Y H:i') }}<div class="small {{ $incident->reportedLate() ? 'text-warning' : 'text-muted' }}">{{ $incident->reportDelayHours() }} jam setelah kejadian (batas {{ $incident->notifyLimitHours() }} jam)</div></dd>
                    @if ($incident->closed_at)<dt>Ditutup</dt><dd>{{ $incident->closed_at->translatedFormat('d M Y') }} oleh {{ $incident->closed_by }}@if ($incident->closure_note)<div class="small text-muted">{{ $incident->closure_note }}</div>@endif</dd>@endif
                </dl>
            </div>
        </div>

        @include('ssap._timeline', ['record' => $incident])
    </div>
</div>
@endsection
