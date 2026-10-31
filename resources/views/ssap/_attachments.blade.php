{{-- Lampiran: $record (Hazard/Inspection), $canManage --}}
<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-paperclip"></i>Lampiran <span class="text-muted fw-normal">({{ $record->attachments->count() }})</span></h3></div>
    <div class="s-card-body">
        @if ($record->attachments->isEmpty())
            <div class="small text-muted">Belum ada lampiran.</div>
        @else
            <div class="row g-2">
                @foreach ($record->attachments as $att)
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="{{ route('ssap.attachments.show', $att) }}" target="_blank" rel="noopener" class="text-decoration-none d-block">
                            @if ($att->isImage())
                                <img class="thumb" src="{{ route('ssap.attachments.show', $att) }}" alt="{{ $att->original_name }}" loading="lazy">
                            @else
                                <div class="thumb d-grid place-items-center text-center" style="place-items:center;"><div><i class="bi bi-file-earmark-pdf fs-1 text-danger"></i><div class="small text-muted">PDF</div></div></div>
                            @endif
                        </a>
                        <div class="small mt-1 text-truncate" title="{{ $att->original_name }}">{{ $att->original_name }}</div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">{{ $att->humanSize() }}</span>
                            @if ($canManage)
                                <form method="POST" action="{{ route('ssap.attachments.destroy', $att) }}" data-confirm="Hapus lampiran ini?">@csrf @method('DELETE')
                                    <button class="btn btn-link btn-sm text-danger p-0" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
