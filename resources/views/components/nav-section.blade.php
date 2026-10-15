@props([
    'label' => null,
])

{{-- A labelled block of menu items in the sidebar, in console's look: a small capital label above the items. With a label the block folds
     away (a native <details>); the shell remembers per label whether it was folded, and never folds the block that holds the open page. --}}
<li>
    @if ($label)
        <details class="group/section" data-nav-section="{{ $label }}" open>
            <summary class="mb-2 flex cursor-pointer list-none items-center justify-between rounded-[6px] px-2 text-[10px] font-bold uppercase tracking-wider text-ink-faint transition hover:text-ink-soft [&::-webkit-details-marker]:hidden">
                <span>{{ $label }}</span>
                <span class="material-symbols-rounded text-[16px] transition group-open/section:rotate-180" aria-hidden="true">expand_more</span>
            </summary>
            <ul role="list" class="-mx-2 space-y-1">
                {{ $slot }}
            </ul>
        </details>
    @else
        <ul role="list" class="-mx-2 space-y-1">
            {{ $slot }}
        </ul>
    @endif
</li>
