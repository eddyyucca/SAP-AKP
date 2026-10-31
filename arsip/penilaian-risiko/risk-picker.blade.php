@props(['likelihood' => null, 'consequence' => null, 'optional' => false])
@php
    $levels = config('ssap.risk_levels');
    $lk = config('ssap.likelihood');
    $cq = config('ssap.consequence');
@endphp
<div data-risk-picker data-levels='@json($levels)' data-optional="{{ $optional ? 1 : 0 }}">
    <input type="hidden" name="likelihood" value="{{ old('likelihood', $likelihood) }}">
    <input type="hidden" name="consequence" value="{{ old('consequence', $consequence) }}">

    <div class="risk-picker" role="group" aria-label="Matriks risiko">
        <div class="axis"></div>
        @foreach ($cq as $c => $meta)
            <div class="axis" title="{{ $meta['hint'] }}">{{ $c }}<br><span class="fw-normal">{{ $meta['label'] }}</span></div>
        @endforeach

        @foreach (array_reverse(array_keys($lk)) as $l)
            <div class="axis" title="{{ $lk[$l]['hint'] }}">{{ $l }}<br><span class="fw-normal">{{ $lk[$l]['label'] }}</span></div>
            @foreach ($cq as $c => $meta)
                @php($level = \App\Support\Risk::level($l * $c))
                <button type="button" class="cell lv-{{ $level }} border-0" data-l="{{ $l }}" data-c="{{ $c }}" aria-pressed="false"
                        title="Kemungkinan {{ $lk[$l]['label'] }} x Konsekuensi {{ $meta['label'] }} = {{ $l * $c }}">{{ $l * $c }}</button>
            @endforeach
        @endforeach
    </div>
    <div class="d-flex justify-content-between small text-muted mt-1" style="max-width:540px;">
        <span>Baris = kemungkinan terjadi, kolom = tingkat konsekuensi terburuk</span>
        @if ($optional)<a href="#" data-risk-clear onclick="return false;">Kosongkan</a>@endif
    </div>
    <div class="risk-result mt-2" data-risk-result></div>
</div>
