@props([
    'id',
    'title',
    // Each item: name, logo_url?, active?, url? (a link) or form? ['action', 'method', 'hidden' => [name => value]], note?, external?
    'items' => [],
    'kind' => 'workspace',
])

{{-- The one dialog behind both switchers (workspace and app), so they look exactly the same. Rendered once per page, outside the top bars, so the
     mobile trigger can reach it too (the desktop top bar is hidden below `lg`). --}}
<el-dialog>
    <dialog id="{{ $id }}" class="backdrop:bg-transparent">
        <el-dialog-backdrop class="fixed inset-0 bg-white/55 backdrop-blur-[2px] transition-opacity duration-200 ease-out data-closed:opacity-0"></el-dialog-backdrop>
        <div tabindex="0" class="fixed inset-0 z-[70] flex items-center justify-center p-4 focus:outline-none">
            <el-dialog-panel class="w-full max-w-[420px] transform overflow-hidden rounded-[16px] bg-white shadow-[0_24px_80px_rgba(0,0,0,0.25)] transition duration-200 ease-out data-closed:translate-y-2 data-closed:opacity-0">
                <div class="flex items-center justify-between px-5 py-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-ink-faint">{{ $title }}</div>
                    <button type="button" command="close" commandfor="{{ $id }}" class="rounded-[10px] p-2 text-ink-faint transition hover:bg-sunken hover:text-ink-soft" aria-label="{{ __('nevento::shell.close_sidebar') }}">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="max-h-[80vh] space-y-2 overflow-y-auto p-4">
                    @foreach ($items as $item)
                        @php
                            $active = (bool) ($item['active'] ?? false);
                            $row = 'inline-flex w-full items-center justify-between gap-3 rounded-[10px] border px-4 py-3 text-left text-sm font-medium transition '
                                .($active ? 'border-brand bg-brand/5 text-brand cursor-default' : 'border-hairline bg-white text-ink-soft hover:bg-paper');
                        @endphp

                        @if (! $active && isset($item['form']))
                            <form method="POST" action="{{ $item['form']['action'] }}">
                                @csrf
                                @if (strtoupper($item['form']['method'] ?? 'POST') !== 'POST') @method($item['form']['method']) @endif
                                @foreach (($item['form']['hidden'] ?? []) as $name => $value)
                                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                @endforeach
                                <button type="submit" class="{{ $row }}">
                                    <span class="inline-flex min-w-0 items-center gap-2.5">
                                        <x-nevento::shell.mark :kind="$kind" :item="$item" :size="24" />
                                        <span class="truncate">{{ $item['name'] }}</span>
                                    </span>
                                    @if (! empty($item['note'])) <span class="shrink-0 text-xs text-ink-faint">{{ $item['note'] }}</span> @endif
                                </button>
                            </form>
                        @elseif (! $active && ! empty($item['url']))
                            <a href="{{ $item['url'] }}" class="{{ $row }}">
                                <span class="inline-flex min-w-0 items-center gap-2.5">
                                    <x-nevento::shell.mark :kind="$kind" :item="$item" :size="24" />
                                    <span class="truncate">{{ $item['name'] }}</span>
                                </span>
                                @if (! empty($item['note']))
                                    <span class="shrink-0 text-xs text-ink-faint">{{ $item['note'] }}</span>
                                @elseif (! empty($item['external']))
                                    <span class="material-symbols-rounded text-[16px] leading-none text-ink-faint">open_in_new</span>
                                @endif
                            </a>
                        @else
                            <div class="{{ $row }}" @if ($active) aria-current="true" @endif>
                                <span class="inline-flex min-w-0 items-center gap-2.5">
                                    <x-nevento::shell.mark :kind="$kind" :item="$item" :size="24" />
                                    <span class="truncate">{{ $item['name'] }}</span>
                                </span>
                                @if ($active) <span class="material-symbols-rounded text-[16px] leading-none">check</span> @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </el-dialog-panel>
        </div>
    </dialog>
</el-dialog>
