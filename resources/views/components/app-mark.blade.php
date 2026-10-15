@props([
    'app' => [],
    'size' => 28,
])

@php
    $logoUrl = is_array($app) ? trim((string) ($app['logo_url'] ?? '')) : '';
    $name = is_array($app) ? trim((string) ($app['name'] ?? '')) : '';
    // Rounded square, never a circle: the brand's shape language.
    $radius = max(6, (int) round($size * 0.19));
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center overflow-hidden bg-white font-display font-bold text-ink-soft ring-1 ring-hairline']) }}
      style="width: {{ $size }}px; height: {{ $size }}px; border-radius: {{ $radius }}px; font-size: {{ max(10, (int) round($size * 0.42)) }}px;"
      @if($name) title="{{ $name }}" @endif>
    @if($logoUrl !== '')
        <img src="{{ $logoUrl }}" alt="" class="h-full w-full object-contain">
    @else
        {{ mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1)) }}
    @endif
</span>
