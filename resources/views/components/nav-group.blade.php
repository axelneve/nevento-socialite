@props([
    'active' => false,
    'icon' => '',
    'label' => '',
])

{{-- A menu item with sub-items. A native <details>, so it needs no script: it is open while one of its pages is open and can be folded
     away by hand. The sub-items are ordinary <x-nevento::nav-item>s without an icon. --}}
<li>
    <details class="group/nav" @if ($active) open @endif>
        <summary
            class="flex cursor-pointer list-none gap-x-3 rounded-[10px] p-2 text-sm/6 font-semibold transition [&::-webkit-details-marker]:hidden
                {{ $active ? 'text-brand' : 'text-ink-soft hover:text-brand hover:bg-brand-tint' }}"
        >
            @if ($icon)
                <span class="material-symbols-rounded text-[20px] {{ $active ? 'text-brand' : 'text-ink-faint' }}">{{ $icon }}</span>
            @endif

            <span class="flex-1">{{ $label }}</span>

            <span class="material-symbols-rounded text-[18px] text-ink-faint transition group-open/nav:rotate-180" aria-hidden="true">expand_more</span>
        </summary>

        <ul role="list" class="mt-1 space-y-1 pl-8">
            {{ $slot }}
        </ul>
    </details>
</li>
