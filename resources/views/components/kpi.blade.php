@props(['label', 'value', 'sub' => null, 'icon' => 'bi-activity', 'tone' => 'primary', 'href' => null])
@if ($href)
<a href="{{ $href }}" class="kpi">
@else
<div class="kpi">
@endif
    <div class="kpi-head">
        <span class="kpi-name">{{ $label }}</span>
        <span class="kpi-ico tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
    </div>
    <div class="kpi-num {{ mb_strlen((string) $value) > 7 ? 'kpi-num-long' : '' }}">{{ $value }}</div>
    @if ($sub)<div class="kpi-sub">{{ $sub }}</div>@endif
@if ($href)
</a>
@else
</div>
@endif
