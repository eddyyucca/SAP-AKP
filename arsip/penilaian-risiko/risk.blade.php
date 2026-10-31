@props(['level', 'score' => null])
@php($meta = \App\Support\Risk::meta($level))
<span {{ $attributes->class(['pill']) }} style="background:{{ $meta['bg'] }};color:{{ $meta['color'] }};">
    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>{{ $meta['label'] }}@if ($score) &middot; {{ $score }}@endif
</span>
