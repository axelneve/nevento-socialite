@props([
    'active' => false,
    'icon' => '',
    'sub' => false,
    'count' => 0,
])

<li>
    <a
        class="group flex gap-x-3 rounded-[10px] p-2 text-sm/6 transition
            {{ $sub ? 'font-normal' : 'font-semibold' }}
            {{ $active ? 'bg-brand-tint text-brand' : 'text-ink-soft hover:text-brand hover:bg-brand-tint' }}"
        aria-current="{{ $active ? 'page' : 'false' }}"
        {{ $attributes }}
    >
        @if ($icon)
            <span class="material-symbols-rounded text-[20px] {{ $active ? 'text-brand' : 'text-ink-faint group-hover:text-brand' }}">{{ $icon }}</span>
        @endif

        <span class="flex-1">{{ $slot }}</span>

        @if ($count > 0)
            <span class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-alert px-1.5 text-[11px] font-bold text-white">{{ $count }}</span>
        @endif
    </a>
</li>
