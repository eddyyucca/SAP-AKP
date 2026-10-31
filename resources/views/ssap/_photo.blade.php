{{-- Satu foto/dokumen. $att, $phase (before|after|null), $canManage, $isHazard --}}
<a href="{{ route('ssap.attachments.show', $att) }}" target="_blank" rel="noopener" class="photo-link d-block position-relative text-decoration-none" title="{{ $att->original_name }}">
    @if ($isHazard && $phase)
        <span class="photo-badge photo-badge-{{ $phase === 'after' ? 'success' : 'warning' }}">{{ $phase === 'after' ? 'Sesudah' : 'Sebelum' }}</span>
    @endif
    @if ($att->isImage())
        <img class="thumb" src="{{ route('ssap.attachments.show', $att) }}" alt="{{ ($phase === 'after' ? 'Foto sesudah: ' : ($phase === 'before' ? 'Foto sebelum: ' : '')).$att->original_name }}" loading="lazy">
    @else
        <div class="thumb d-flex align-items-center justify-content-center text-center"><div><i class="bi bi-file-earmark-pdf fs-1 text-danger"></i><div class="small text-muted">PDF</div></div></div>
    @endif
</a>
<div class="d-flex justify-content-between align-items-center gap-1 mt-1">
    <span class="small text-muted text-truncate" title="{{ $att->original_name }}">{{ $att->original_name }} &middot; {{ $att->humanSize() }}</span>
    @if ($canManage)
        <form method="POST" action="{{ route('ssap.attachments.destroy', $att) }}" data-confirm="Hapus lampiran ini?" class="flex-shrink-0">@csrf @method('DELETE')
            <button class="btn btn-link btn-sm text-danger p-0" title="Hapus"><i class="bi bi-trash"></i></button>
        </form>
    @endif
</div>
