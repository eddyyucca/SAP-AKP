{{-- Riwayat kejadian laporan: $record->logs --}}
<div class="s-card mb-3">
    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clock-history"></i>Riwayat</h3></div>
    <div class="s-card-body">
        <ul class="timeline">
            @forelse ($record->logs as $log)
                @php([$label, $icon, $tone] = $log->meta())
                <li>
                    <span class="tl-ico tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                    <div class="fw-semibold">{{ $label }}</div>
                    @if ($log->note)<div class="text-muted">{{ $log->note }}</div>@endif
                    <div class="small text-muted">{{ $log->actor_name }} &middot; {{ $log->created_at->translatedFormat('d M Y H:i') }}</div>
                </li>
            @empty
                <li class="text-muted">Belum ada riwayat.</li>
            @endforelse
        </ul>
    </div>
</div>
