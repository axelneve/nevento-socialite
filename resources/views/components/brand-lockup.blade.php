@props([
    'size' => 30,
    // A workspace's wide logo: shown beside the mark, after a divider, instead of the wordmark.
    'logo' => null,
    // The wordmark when there is no logo: the app's own name (myOffice, Rento); the platform's name by default.
    'name' => null,
])

{{-- The Nevento Digital mark and wordmark. The mark is inline SVG (no asset to publish into each app) and the wordmark is live text in the
     display face, so it picks up the font the page has already loaded and stays selectable. --}}
<span {{ $attributes->class(['inline-flex items-center']) }} style="gap: {{ max(6, (int) round($size * 0.3)) }}px;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" width="{{ $size }}" height="{{ $size }}" style="width: {{ $size }}px; height: {{ $size }}px;" role="img" aria-hidden="true">
        <defs>
            <linearGradient id="nevento-tile" x1="0" y1="0" x2="600" y2="600" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#7FB069"/>
                <stop offset="0.5" stop-color="#4C9A5B"/>
                <stop offset="1" stop-color="#2E7D4F"/>
            </linearGradient>
        </defs>
        <rect width="600" height="600" rx="114" fill="url(#nevento-tile)"/>
        <g transform="translate(121.4,143.3) scale(0.72)" fill="#FBF3E6">
            <path d="M114.84,1.61h0c32.36,0,58.59,26.23,58.59,58.59v312.48c0,32.36-26.23,58.59-58.59,58.59h0c-32.36,0-58.59-26.23-58.59-58.59V60.2c0-32.36,26.23-58.59,58.59-58.59Z"/>
            <path d="M76.82,15.68h0c24.62-21,61.6-18.06,82.6,6.56l267.83,314.04c21,24.62,18.06,61.6-6.56,82.6h0c-24.62,21-61.6,18.06-82.6-6.56L70.26,98.28c-21-24.62-18.06-61.6,6.56-82.6Z"/>
            <path d="M382.54,3.68h0c32.36,0,58.59,26.23,58.59,58.59v312.48c0,32.36-26.23,58.59-58.59,58.59h0c-32.36,0-58.59-26.23-58.59-58.59V62.27c0-32.36,26.23-58.59,58.59-58.59Z"/>
        </g>
        <g fill="none" stroke="#FBF3E6" stroke-width="22" stroke-linecap="round" stroke-linejoin="round">
            <path d="M156,114 L122,114 L122,486 L156,486"/>
            <path d="M444,114 L478,114 L478,486 L444,486"/>
        </g>
    </svg>
    @if (filled($logo))
        <span class="w-px bg-hairline-strong" style="height: {{ (int) round($size * 0.8) }}px;" aria-hidden="true"></span>
        <img src="{{ $logo }}" alt="" onerror="this.previousElementSibling.remove(); this.remove()" class="w-auto object-contain" style="height: {{ (int) round($size * 0.9) }}px; max-width: 9rem;">
    @else
        <span class="font-display font-bold text-brand-strong"
              style="font-size: {{ max(13, (int) round($size * 0.52)) }}px; letter-spacing: -0.02em; line-height: 1;">
            {!! filled($name) ? e($name) : 'Nevento&nbsp;Digital' !!}
        </span>
    @endif
</span>
