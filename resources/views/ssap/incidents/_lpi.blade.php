{{-- LPI kontraktor pada insiden: $incident, $canManage --}}
@php
    $m = $incident->lpiMeta();
    $overdue = $incident->lpiOverdue();
    $onTime = $incident->lpiOnTime();
@endphp
<div class="s-card mb-3" id="lpi">
    <div class="s-card-head">
        <h3 class="s-card-title"><i class="bi bi-file-earmark-text"></i>LPI kontraktor</h3>
        <div class="d-flex gap-2 flex-wrap"><x-pill :meta="$m" />@if ($overdue)<span class="pill pill-danger"><i class="bi bi-alarm"></i>Terlambat {{ $incident->lpi_due->diffInDays(today()) }} hari</span>@endif</div>
    </div>
    <div class="s-card-body">
        <dl class="def-list mb-3">
            <dt>Kontraktor</dt><dd>{{ $incident->contractor?->name }} ({{ $incident->contractor?->areaMeta()['label'] }})@if ($incident->contractor?->pic_name)<div class="small text-muted">PIC: {{ $incident->contractor->pic_name }}{{ $incident->contractor->pic_email ? ' · '.$incident->contractor->pic_email : '' }}</div>@endif</dd>
            <dt>Tenggat LPI</dt><dd class="{{ $overdue ? 'text-danger fw-semibold' : '' }}">{{ $incident->lpi_due?->translatedFormat('d F Y') }}</dd>
            @if ($incident->lpi_submitted_at)
                <dt>LPI diterima</dt><dd>{{ $incident->lpi_submitted_at->translatedFormat('d F Y') }} &middot; {{ $incident->lpi_number }}
                    <span class="pill pill-{{ $onTime ? 'success' : 'danger' }} ms-1">{{ $onTime ? 'Tepat waktu' : 'Terlambat' }}</span>
                    @if ($incident->lpi_revisions)<span class="pill pill-secondary ms-1">{{ $incident->lpi_revisions }}x revisi</span>@endif</dd>
            @endif
            @if ($incident->lpi_note)<dt>Catatan HSE</dt><dd style="white-space:pre-line;">{{ $incident->lpi_note }}</dd>@endif
            @if ($incident->lpi_reviewed_by)<dt>Ditinjau</dt><dd>{{ $incident->lpi_reviewed_by }} &middot; {{ $incident->lpi_reviewed_at?->translatedFormat('d M Y H:i') }}</dd>@endif
        </dl>

        @if ($canManage)
            @if (in_array($incident->lpi_status, ['pending', 'revision'], true))
                <form method="POST" action="{{ route('ssap.incidents.lpi', $incident) }}" enctype="multipart/form-data" class="person-row no-print" data-no-lock>
                    @csrf
                    <input type="hidden" name="action" value="receive">
                    <div class="fw-semibold small mb-2">{{ $incident->lpi_status === 'revision' ? 'Catat LPI revisi dari kontraktor' : 'Catat LPI diterima dari kontraktor' }}</div>
                    <div class="row g-2">
                        <div class="col-12 col-md-6"><input type="text" name="lpi_number" value="{{ old('lpi_number') }}" class="form-control form-control-sm @error('lpi_number') is-invalid @enderror" placeholder="Nomor / judul dokumen LPI *" maxlength="80" required></div>
                        <div class="col-12 col-md-6"><input type="date" name="lpi_submitted_at" value="{{ old('lpi_submitted_at', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" title="Tanggal LPI diterima"></div>
                        <div class="col-12"><input type="file" name="files[]" class="form-control form-control-sm" multiple accept=".jpg,.jpeg,.png,.webp,.pdf"><div class="form-text">Lampirkan berkas LPI (PDF) bila ada.</div></div>
                    </div>
                    <div class="mt-2 text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-inbox me-1"></i>Catat diterima</button></div>
                </form>
            @elseif ($incident->lpi_status === 'submitted')
                <form method="POST" action="{{ route('ssap.incidents.lpi', $incident) }}" class="person-row no-print" data-no-lock>
                    @csrf
                    <div class="fw-semibold small mb-2">Tinjau LPI</div>
                    <textarea name="note" rows="2" class="form-control form-control-sm mb-2 @error('note') is-invalid @enderror" maxlength="2000" placeholder="Catatan (wajib bila dikembalikan untuk revisi)">{{ old('note') }}</textarea>
                    @error('action')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    <div class="d-flex gap-2">
                        <button name="action" value="accept" class="btn btn-success btn-sm"><i class="bi bi-patch-check me-1"></i>Setujui LPI</button>
                        <button name="action" value="revise" class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Minta revisi</button>
                    </div>
                </form>
            @endif
        @endif
    </div>
</div>
