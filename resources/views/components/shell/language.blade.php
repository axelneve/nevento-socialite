@props([
    // ['action' => url, 'method' => 'PUT', 'field' => 'locale', 'current' => 'nl']
    'locale' => [],
])

@php
    $current = (string) ($locale['current'] ?? 'nl');
    $method = strtoupper((string) ($locale['method'] ?? 'PUT'));
    $field = (string) ($locale['field'] ?? 'locale');
    $options = ['nl' => ['fi-nl', __('nevento::shell.language_nl')], 'en' => ['fi-gb', __('nevento::shell.language_en')]];
@endphp

<div {{ $attributes }}>
    <div class="mb-2 block text-[10px] font-bold uppercase tracking-wider text-ink-faint">{{ __('nevento::shell.language') }}</div>
    <div class="grid grid-cols-2 gap-2">
        @foreach ($options as $code => [$flag, $label])
            <form action="{{ $locale['action'] }}" method="POST">
                @csrf
                @if ($method !== 'POST') @method($method) @endif
                <input type="hidden" name="{{ $field }}" value="{{ $code }}">
                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-[10px] border px-3 py-2 text-sm font-semibold transition {{ $current === $code ? 'border-brand bg-white text-brand' : 'border-hairline bg-white text-ink-soft hover:bg-paper' }}">
                    <span class="fi {{ $flag }} rounded-sm"></span>
                    <span>{{ $label }}</span>
                </button>
            </form>
        @endforeach
    </div>
</div>
