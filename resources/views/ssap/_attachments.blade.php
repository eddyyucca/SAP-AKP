{{-- Lampiran: $record (Hazard/Inspection), $canManage. Hazard dikelompokkan: foto SEBELUM (saat dilaporkan) dan SESUDAH (bukti perbaikan). --}}
@php
    $isHazard = $record instanceof \App\Models\Hazard;
    $groups = $isHazard
        ? [
            'before' => ['Foto sebelum', 'Kondisi saat ditemukan/dilaporkan', 'bi-camera', 'text-warning'],
            'after' => ['Foto sesudah', 'Bukti perbaikan yang dicatat pelapor', 'bi-patch-check', 'text-success'],
        ]
        : [null => ['Lampiran', null, 'bi-paperclip', 'text-secondary']];
@endphp
<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-paperclip"></i>{{ $isHazard ? 'Foto & dokumen' : 'Lampiran' }} <span class="text-muted fw-normal">({{ $record->attachments->count() }})</span></h3></div>
    <div class="s-card-body">
        @foreach ($groups as $phase => [$title, $hint, $icon, $tone])
            @php($items = $isHazard ? $record->attachments->filter(fn ($a) => ($a->phase ?: 'before') === $phase) : $record->attachments)
            @if ($isHazard)
                <div class="d-flex align-items-center gap-2 mb-2 {{ $loop->first ? '' : 'mt-3 pt-3 border-top' }}">
                    <i class="bi {{ $icon }} {{ $tone }}"></i><strong class="small">{{ $title }}</strong>
                    <span class="small text-muted">{{ $hint }} &middot; {{ $items->count() }}</span>
                </div>
            @endif

            @if ($items->isEmpty())
                <div class="small text-muted">{{ $isHazard ? ($phase === 'after' ? 'Belum ada foto sesudah perbaikan.' : 'Belum ada foto sebelum.') : 'Belum ada lampiran.' }}</div>
            @else
                <div class="row g-2">
                    @foreach ($items as $att)
                        <div class="col-6 col-md-4 col-lg-3">
                            <a href="{{ route('ssap.attachments.show', $att) }}" target="_blank" rel="noopener" class="text-decoration-none d-block position-relative">
                                @if ($isHazard)<span class="pill {{ $phase === 'after' ? 'pill-success' : 'pill-warning' }}" style="position:absolute;top:6px;left:6px;z-index:1;">{{ $phase === 'after' ? 'Sesudah' : 'Sebelum' }}</span>@endif
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
        @endforeach
    </div>
</div>
