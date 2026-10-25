{{--
    Tindakan korektif pada sebuah laporan.
    $record (Incident/Hazard), $sourceType ('incident'|'hazard'), $canManage, $closed (laporan sudah ditutup)
--}}
@php
    $myNik = $portalUser->employee_no;
@endphp
<div class="s-card mb-3" id="tindakan">
    <div class="s-card-head">
        <h3 class="s-card-title"><i class="bi bi-list-check"></i>Tindakan korektif &amp; pencegahan <span class="text-muted fw-normal">({{ $record->actions->count() }})</span></h3>
        @if ($canManage && ! $closed)
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addAction"><i class="bi bi-plus-lg me-1"></i>Tambah tindakan</button>
        @endif
    </div>
    <div class="s-card-body">
        @if ($canManage && ! $closed)
            <div class="collapse {{ $errors->has('description') || $errors->has('pic_name') || $errors->has('due_date') ? 'show' : '' }}" id="addAction">
                <form method="POST" action="{{ route('ssap.actions.store') }}" class="person-row mb-3" data-emp-scope>
                    @csrf
                    <input type="hidden" name="source_type" value="{{ $sourceType }}">
                    <input type="hidden" name="source_id" value="{{ $record->id }}">
                    <div class="row g-2">
                        <div class="col-12 col-md-4">
                            <label class="form-label small mb-1">Jenis tindakan</label>
                            <select name="type" class="form-select form-select-sm">@foreach (config('ssap.action_types') as $k => $l)<option value="{{ $k }}" @selected(old('type', 'corrective') === $k)>{{ $l }}</option>@endforeach</select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small mb-1">Tenggat</label>
                            <input type="date" name="due_date" value="{{ old('due_date', now()->addDays(7)->toDateString()) }}" min="{{ today()->toDateString() }}" class="form-control form-control-sm @error('due_date') is-invalid @enderror" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small mb-1">Cari PIC di HRIS</label>
                            <x-employee-search placeholder="NIK atau nama PIC..." />
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Uraian tindakan</label>
                            <textarea name="description" rows="2" class="form-control form-control-sm @error('description') is-invalid @enderror" required maxlength="3000" placeholder="Apa yang harus dikerjakan, hasil yang diharapkan">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-6 col-md-2"><input type="text" name="pic_nik" value="{{ old('pic_nik') }}" class="form-control form-control-sm" placeholder="NIK PIC" data-emp-field="nik"></div>
                        <div class="col-6 col-md-4"><input type="text" name="pic_name" value="{{ old('pic_name') }}" class="form-control form-control-sm @error('pic_name') is-invalid @enderror" placeholder="Nama PIC *" data-emp-field="name" required></div>
                        <div class="col-6 col-md-3"><input type="text" name="pic_department" value="{{ old('pic_department') }}" class="form-control form-control-sm" placeholder="Departemen" data-emp-field="department"></div>
                        <div class="col-6 col-md-3"><input type="email" name="pic_email" value="{{ old('pic_email') }}" class="form-control form-control-sm" placeholder="Email (untuk notifikasi)" data-emp-field="email"></div>
                    </div>
                    <div class="mt-2 text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Tugaskan</button></div>
                </form>
            </div>
        @endif

        @forelse ($record->actions as $a)
            @php($meta = $a->statusMeta())
            @php($mine = filled($myNik) && $myNik === $a->pic_nik)
            <div class="border rounded-3 p-3 mb-2 {{ $a->isOverdue() ? 'border-danger-subtle bg-danger-subtle bg-opacity-25' : '' }}">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <x-pill :meta="$meta" />
                        <span class="pill pill-secondary">{{ $a->typeLabel() }}</span>
                        @if ($a->isOverdue())<span class="pill pill-danger"><i class="bi bi-alarm"></i>Terlambat {{ $a->due_date->diffInDays(today()) }} hari</span>@endif
                    </div>
                    <div class="small text-muted"><i class="bi bi-calendar-event me-1"></i>Tenggat {{ $a->due_date->translatedFormat('d M Y') }}</div>
                </div>
                <div class="mt-2">{{ $a->description }}</div>
                <div class="small text-muted mt-1"><i class="bi bi-person me-1"></i>{{ $a->pic_name }}@if ($a->pic_department) &middot; {{ $a->pic_department }}@endif</div>

                @if ($a->completion_note)
                    <div class="small mt-2 p-2 rounded-2 bg-light"><strong>Penyelesaian{{ $a->completed_at ? ' ('.$a->completed_at->translatedFormat('d M Y').')' : '' }}:</strong> {{ $a->completion_note }}</div>
                @endif
                @if ($a->verification_note || $a->verified_by)
                    <div class="small mt-2 p-2 rounded-2 bg-light"><strong>Verifikasi{{ $a->verified_by ? ' oleh '.$a->verified_by : '' }}:</strong> {{ $a->verification_note ?: 'Diterima.' }}</div>
                @endif

                <div class="d-flex flex-wrap gap-2 mt-2">
                    {{-- PIC: perbarui progres --}}
                    @if (($mine || $canManage) && in_array($a->status, ['open', 'in_progress'], true))
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#prog{{ $a->id }}"><i class="bi bi-pencil-square me-1"></i>Perbarui progres</button>
                    @endif
                    @if ($canManage && $a->status === 'done')
                        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#ver{{ $a->id }}"><i class="bi bi-patch-check me-1"></i>Verifikasi</button>
                    @endif
                    @if ($canManage)
                        <form method="POST" action="{{ route('ssap.actions.destroy', $a) }}" data-confirm="Hapus tindakan ini?">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    @endif
                </div>

                @if (($mine || $canManage) && in_array($a->status, ['open', 'in_progress'], true))
                    <div class="collapse mt-2" id="prog{{ $a->id }}">
                        <form method="POST" action="{{ route('ssap.actions.progress', $a) }}" enctype="multipart/form-data" class="person-row">
                            @csrf @method('PUT')
                            <div class="row g-2">
                                <div class="col-12"><textarea name="completion_note" rows="2" class="form-control form-control-sm" maxlength="3000" placeholder="Apa yang sudah dikerjakan? (wajib bila ditandai selesai)">{{ $a->completion_note }}</textarea></div>
                                <div class="col-12 col-md-7"><input type="file" name="files[]" class="form-control form-control-sm" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" title="Foto bukti penyelesaian"><div class="form-text">Foto/dokumen bukti (opsional, sangat dianjurkan).</div></div>
                                <div class="col-12 col-md-5 d-flex gap-2 justify-content-md-end align-items-start">
                                    <button name="to" value="in_progress" class="btn btn-outline-warning btn-sm"><i class="bi bi-hourglass-split me-1"></i>Sedang dikerjakan</button>
                                    <button name="to" value="done" class="btn btn-success btn-sm"><i class="bi bi-check2 me-1"></i>Tandai selesai</button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endif

                @if ($canManage && $a->status === 'done')
                    <div class="collapse mt-2" id="ver{{ $a->id }}">
                        <form method="POST" action="{{ route('ssap.actions.verify', $a) }}" class="person-row">
                            @csrf
                            <textarea name="note" rows="2" class="form-control form-control-sm mb-2" maxlength="2000" placeholder="Catatan verifikasi (wajib bila dikembalikan)"></textarea>
                            <div class="d-flex gap-2">
                                <button name="decision" value="accept" class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Terima &amp; verifikasi</button>
                                <button name="decision" value="reject" class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Kembalikan ke PIC</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <div class="small text-muted">Belum ada tindakan.@if ($canManage && ! $closed) Gunakan <strong>Tambah tindakan</strong> untuk menugaskan PIC.@endif</div>
        @endforelse
    </div>
</div>
