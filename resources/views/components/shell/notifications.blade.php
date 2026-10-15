@props([
    // ['items' => [['title', 'url', 'icon', 'priority' ('high' or other), 'source', 'time']], 'count' => int, 'url' => 'all notifications']
    'notifications' => [],
])

@php
    $items = $notifications['items'] ?? [];
    $count = (int) ($notifications['count'] ?? count($items));
@endphp

{{-- The notification centre: a bell with the number of unread ones and a list under it. --}}
<el-dropdown class="inline-block">
    <button type="button" class="relative inline-flex items-center rounded-[10px] p-2 text-ink-faint transition hover:bg-sunken hover:text-ink-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-brand" aria-label="{{ __('nevento::shell.notifications') }}">
        <span class="material-symbols-rounded text-[20px]">notifications</span>
        @if ($count > 0)
            <span class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-alert px-1 text-[10px] font-bold text-white">{{ $count }}</span>
        @endif
    </button>
    <el-menu anchor="bottom end" popover class="w-80 origin-top-right rounded-[16px] bg-white shadow-popover outline-1 outline-hairline transition transition-discrete [--anchor-gap:--spacing(2)] data-closed:scale-95 data-closed:opacity-0">
        <div class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-ink-faint">{{ __('nevento::shell.notifications') }}</div>
        <div class="max-h-96 overflow-y-auto p-1">
            @forelse ($items as $item)
                <a href="{{ $item['url'] ?? '#' }}" class="flex items-start gap-2 rounded-[10px] px-3 py-2 text-sm text-ink-soft hover:bg-paper">
                    <span class="material-symbols-rounded mt-0.5 shrink-0 text-[16px] {{ ($item['priority'] ?? '') === 'high' ? 'text-alert' : 'text-ink-faint' }}">{{ $item['icon'] ?? 'notifications' }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium text-ink">{{ $item['title'] }}</span>
                        <span class="mt-0.5 flex items-center gap-1.5 text-xs text-ink-faint">
                            @if (! empty($item['source']))
                                <span class="uppercase tracking-wide">{{ $item['source'] }}</span>
                                <span>&middot;</span>
                            @endif
                            <span>{{ $item['time'] ?? '' }}</span>
                        </span>
                    </span>
                </a>
            @empty
                <p class="px-3 py-2 text-sm text-ink-faint">{{ __('nevento::shell.no_notifications') }}</p>
            @endforelse
        </div>
        @if (! empty($notifications['url']))
            <div class="border-t border-hairline p-1">
                <a href="{{ $notifications['url'] }}" class="block rounded-[10px] px-3 py-2 text-center text-sm font-semibold text-brand hover:bg-paper">{{ __('nevento::shell.all_notifications') }}</a>
            </div>
        @endif
    </el-menu>
</el-dropdown>
