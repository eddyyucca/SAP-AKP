@props(['category', 'long' => false])
@php($color = config('ssap.hazard_category_colors.'.$category, '#64748b'))
<span class="pill" style="background:{{ $color }}1f;color:{{ $color }};">{{ $long ? config('ssap.hazard_categories.'.$category, $category) : strtoupper($category) }}</span>
