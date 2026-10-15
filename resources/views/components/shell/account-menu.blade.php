@props([
    // ['name', 'email', 'roles' => [], 'profile_url' => ?, 'logout_url', 'locale' => ['action', 'method' => 'PUT', 'field' => 'locale', 'current' => 'nl'] | null]
    'user' => [],
    // topbar: the user mark that opens the dialog; compact: the account block inside the mobile sidebar
    'compact' => false,
    'id' => 'account-menu-modal',
])

@php
    $name = trim((string) ($user['name'] ?? ''));
    $parts = preg_split('/\s+/', $name) ?: [];
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
    $roles = (array) ($user['roles'] ?? []);
    $profile = $user['profile_url'] ?? null;
    $locale = $user['locale'] ?? null;
    $initial = strtoupper(mb_substr($name !== '' ? $name : 'U', 0, 1));
    $logoutMethod = strtoupper((string) ($user['logout_method'] ?? 'POST'));
    $links = (array) ($user['links'] ?? []);
@endphp

@if ($compact)
    <div class="space-y-4 p-4">
        @if ($profile) <a href="{{ $profile }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-[16px] bg-paper p-4 transition hover:bg-sunken"> @else <div class="flex items-center gap-3 rounded-[16px] bg-paper p-4"> @endif
            <span class="material-symbols-rounded text-[32px] text-ink-faint">account_circle</span>
            <div class="min-w-0 flex-1">
                <div class="truncate text-base font-semibold text-ink">{{ $name }}</div>
                <div class="truncate text-sm text-ink-faint">{{ $user['email'] ?? '' }}</div>
            </div>
            @if ($profile) <span class="material-symbols-rounded text-[18px] text-ink-faint">chevron_right</span> @endif
        @if ($profile) </a> @else </div> @endif

        @if ($roles !== [])
            <div class="rounded-[16px] bg-white p-4">
                <div class="mb-2 text-[10px] font-bold uppercase tracking-wider text-ink-faint">{{ __('nevento::shell.roles') }}</div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($roles as $role)
                        <span class="inline-flex items-center rounded-full bg-sunken px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-ink-soft ring-1 ring-hairline">{{ $role }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($locale)
            <x-nevento::shell.language :locale="$locale" class="rounded-[16px] bg-white p-4" />
        @endif

        @foreach ($links as $link)
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-[16px] bg-white px-4 py-3 text-sm font-semibold text-ink transition hover:bg-paper">
                <span class="material-symbols-rounded text-lg text-ink-faint">{{ $link['icon'] ?? 'open_in_new' }}</span>
                <span class="flex-1 text-left">{{ $link['label'] }}</span>
                <span class="material-symbols-rounded text-base text-ink-faint">open_in_new</span>
            </a>
        @endforeach

        {{ $slot }}

        <form action="{{ $user['logout_url'] ?? '/logout' }}" method="POST">
            @csrf
            @if ($logoutMethod !== 'POST') @method($logoutMethod) @endif
            <button type="submit" class="flex w-full items-center gap-3 rounded-[16px] bg-white px-4 py-3 text-sm font-semibold text-ink transition hover:bg-paper focus:outline-hidden">
                <span class="material-symbols-rounded text-lg text-ink-faint">logout</span>
                <span class="flex-1 text-left">{{ __('nevento::shell.logout') }}</span>
            </button>
        </form>
    </div>
@else
    <button type="button" command="show-modal" commandfor="{{ $id }}" class="inline-flex items-center justify-center rounded-[10px] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-white" aria-label="{{ __('nevento::shell.open_account_menu') }}">
        <span class="flex size-8 items-center justify-center rounded-[10px] bg-brand/15 text-sm font-bold text-brand">{{ $initial }}</span>
    </button>

    <el-dialog>
        <dialog id="{{ $id }}" class="backdrop:bg-transparent">
            <el-dialog-backdrop class="fixed inset-0 bg-white/55 backdrop-blur-[2px] transition-opacity duration-200 ease-out data-closed:opacity-0"></el-dialog-backdrop>
            <div tabindex="0" class="fixed inset-0 z-[70] flex items-center justify-center p-4 focus:outline-none">
                <el-dialog-panel class="w-full max-w-[420px] transform overflow-hidden rounded-[16px] bg-white shadow-[0_24px_80px_rgba(0,0,0,0.25)] transition duration-200 ease-out data-closed:translate-y-2 data-closed:opacity-0">
                    <div class="flex items-center justify-between px-5 py-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-ink-faint">{{ __('nevento::shell.my_account') }}</div>
                        <button type="button" command="close" commandfor="{{ $id }}" class="rounded-[10px] p-2 text-ink-faint transition hover:bg-sunken hover:text-ink-soft" aria-label="{{ __('nevento::shell.close_sidebar') }}">
                            <span class="material-symbols-rounded text-[20px]">close</span>
                        </button>
                    </div>

                    <div class="max-h-[80vh] space-y-3 overflow-y-auto p-4">
                        @if ($profile) <a href="{{ $profile }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-[16px] bg-paper p-4 transition hover:bg-sunken"> @else <div class="flex items-center gap-3 rounded-[16px] bg-paper p-4"> @endif
                            <span class="material-symbols-rounded text-[36px] text-ink-soft">account_circle</span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-xl font-semibold text-ink">{{ $name }}</div>
                                <div class="truncate text-sm text-ink-faint">{{ $user['email'] ?? '' }}</div>
                            </div>
                            @if ($profile) <span class="material-symbols-rounded text-[20px] text-ink-faint">chevron_right</span> @endif
                        @if ($profile) </a> @else </div> @endif

                        @if ($roles !== [])
                            <div class="rounded-[16px] bg-paper p-4">
                                <div class="mb-2 text-[10px] font-bold uppercase tracking-wider text-ink-faint">{{ __('nevento::shell.roles') }}</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($roles as $role)
                                        <span class="inline-flex items-center rounded-full bg-sunken px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-ink-soft ring-1 ring-hairline">{{ $role }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($locale)
                            <x-nevento::shell.language :locale="$locale" class="rounded-[16px] bg-paper p-4" />
                        @endif

                        @foreach ($links as $link)
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-[16px] bg-paper px-4 py-3 text-sm font-semibold text-ink transition hover:bg-paper">
                                <span class="material-symbols-rounded text-lg text-ink-faint">{{ $link['icon'] ?? 'open_in_new' }}</span>
                                <span class="flex-1 text-left">{{ $link['label'] }}</span>
                                <span class="material-symbols-rounded text-base text-ink-faint">open_in_new</span>
                            </a>
                        @endforeach

                        {{ $slot }}

                        <form action="{{ $user['logout_url'] ?? '/logout' }}" method="POST" class="pt-1">
                            @csrf
                            @if ($logoutMethod !== 'POST') @method($logoutMethod) @endif
                            <button type="submit" class="inline-flex w-full items-center gap-2 rounded-[10px] bg-white px-4 py-3 text-left text-base font-semibold text-ink-soft transition hover:bg-paper">
                                <span class="material-symbols-rounded text-[18px] text-ink-faint">logout</span>
                                <span>{{ __('nevento::shell.logout') }}</span>
                            </button>
                        </form>
                    </div>
                </el-dialog-panel>
            </div>
        </dialog>
    </el-dialog>
@endif
