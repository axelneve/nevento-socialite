@props([
    'label' => null,
])

{{-- A labelled block of menu items in the sidebar, in console's look: a small capital label above the items. --}}
<li>
    @if ($label)
        <div class="mb-2 px-2 text-[10px] font-bold uppercase tracking-wider text-ink-faint">{{ $label }}</div>
    @endif
    <ul role="list" class="-mx-2 space-y-1">
        {{ $slot }}
    </ul>
</li>
