{{-- Lampiran: $record (Hazard/Inspection), $canManage.
     Hazard: dua kolom berdampingan, SEBELUM (kondisi saat dilaporkan) dan SESUDAH (bukti perbaikan dari pelapor), supaya mudah dibandingkan. --}}
@php
    $isHazard = $record instanceof \App\Models\Hazard;
    $photo = function ($att, ?string $phase) use ($canManage, $isHazard) {
        return view('ssap._photo', ['att' => $att, 'phase' => $phase, 'canManage' => $canManage, 'isHazard' => $isHazard])->render();
    };
@endphp
<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-images"></i>{{ $isHazard ? 'Foto sebelum & sesudah' : 'Lampiran' }} <span class="text-muted fw-normal">({{ $record->attachments->count() }})</span></h3></div>
    <div class="s-card-body">
        @if ($isHazard)
            @php
                $columns = [
                    'before' => ['Sebelum', 'Kondisi saat ditemukan', 'bi-camera', 'warning', 'Belum ada foto sebelum.'],
                    'after' => ['Sesudah', 'Bukti perbaikan', 'bi-patch-check', 'success', 'Belum ada foto sesudah perbaikan. Foto diunggah pelapor saat mencatat tindakan perbaikan.'],
                ];
            @endphp
            <div class="row g-3">
                @foreach ($columns as $phase => [$title, $hint, $icon, $tone, $empty])
                    @php($items = $record->attachments->filter(fn ($a) => ($a->phase ?: 'before') === $phase)->values())
                    <div class="col-12 col-lg-6">
                        <div class="photo-col photo-col-{{ $tone }} h-100">
                            <div class="photo-col-head"><span class="pill pill-{{ $tone }}"><i class="bi {{ $icon }}"></i>{{ $title }}</span><span class="small text-muted">{{ $hint }} &middot; {{ $items->count() }} berkas</span></div>
                            @if ($items->isEmpty())
                                <div class="photo-empty"><i class="bi {{ $icon }}"></i><span>{{ $empty }}</span></div>
                            @else
                                <div class="row g-2">
                                    @foreach ($items as $att)
                                        <div class="col-6">{!! $photo($att, $phase) !!}</div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($record->attachments->isEmpty())
            <div class="small text-muted">Belum ada lampiran.</div>
        @else
            <div class="row g-2">
                @foreach ($record->attachments as $att)
                    <div class="col-6 col-md-4 col-lg-3">{!! $photo($att, null) !!}</div>
                @endforeach
            </div>
        @endif
    </div>
</div>
