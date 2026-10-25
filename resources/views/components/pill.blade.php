@props(['meta', 'icon' => true])
<span {{ $attributes->class(['pill', 'pill-'.($meta['color'] ?? 'secondary')]) }}>
    @if ($icon && !empty($meta['icon']))<i class="bi {{ $meta['icon'] }}"></i>@endif{{ $meta['label'] ?? '' }}
</span>
