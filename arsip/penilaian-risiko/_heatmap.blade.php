{{-- Peta panas risiko 5x5 hazard terbuka. $heatmap dari SafetyStats::heatmap() --}}
<div class="heat">
    <div class="axis"></div>
    @for ($c = 1; $c <= 5; $c++)<div class="axis">{{ $c }}</div>@endfor
    @foreach (collect($heatmap['cells'])->groupBy('l') as $l => $row)
        <div class="axis">{{ $l }}</div>
        @foreach ($row as $cell)
            <a href="{{ $cell['count'] ? route('ssap.hazards.index', ['scope' => 'open', 'risk' => $cell['level']]) : '#' }}" class="cell lv-{{ $cell['level'] }} {{ $cell['count'] ? '' : 'zero' }} text-decoration-none" title="Kemungkinan {{ $cell['l'] }} x Konsekuensi {{ $cell['c'] }} = skor {{ $cell['score'] }}">
                <small>{{ $cell['score'] }}</small>{{ $cell['count'] ?: '' }}
            </a>
        @endforeach
    @endforeach
</div>
<div class="d-flex justify-content-between small text-muted mt-2"><span>Baris: kemungkinan (5 = hampir pasti)</span><span>Kolom: konsekuensi</span></div>
<div class="d-flex flex-wrap gap-2 mt-2">
    @foreach (config('ssap.risk_levels') as $lv)
        <span class="small"><span class="legend-dot" style="background:{{ $lv['color'] }}"></span>{{ $lv['label'] }}</span>
    @endforeach
</div>
