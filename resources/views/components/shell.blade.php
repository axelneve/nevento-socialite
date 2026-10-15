@props([
    'title' => null,
    // The page title after the workspace and app in the breadcrumb; defaults to the title.
    'breadcrumb' => null,
    // Shown instead of the whole breadcrumb (console's superadmin portal).
    'portalLabel' => null,
    'homeUrl' => '/',
    // The app this is: ['name' => 'myOffice', 'logo_url' => ?]
    'app' => [],
    // ['name', 'email', 'roles' => [], 'profile_url' => ?, 'logout_url', 'locale' => ['action', 'method', 'field', 'current'] | null]
    'user' => [],
    // [['id', 'name', 'logo_url' => ?, 'active' => bool, 'url' => ? (a GET link) | 'form' => ? ['action', 'method', 'hidden' => []], 'note' => ?]]
    'workspaces' => [],
    // ['id', 'name', 'logo_url' => ?] of the workspace that is active, or null
    'activeWorkspace' => null,
    // [['id', 'name', 'logo_url' => ?, 'url', 'active' => bool]] the apps this person can open
    'apps' => [],
    // ['items' => [...], 'count' => int, 'url' => ?] or null for an app without a notification centre
    'notifications' => null,
    'success' => null,
    'error' => null,
    'errorLabel' => null,
    // The wordmark beside the N when the workspace has no wide logo; null is Nevento Digital.
    'brandName' => null,
])

@php
    $authed = auth()->check();
    $workspaceList = array_values($workspaces);
    $canSwitchWorkspace = count($workspaceList) > 0;
    $appList = array_values($apps);
    $canSwitchApp = count($appList) > 0;
    $appName = trim((string) ($app['name'] ?? config('app.name')));
    $crumb = $breadcrumb ?? $title;
    $hasWorkspace = is_array($activeWorkspace) && $activeWorkspace !== [];

    // The same account links in every app: account and password live at the IDP (user.idp_url), personal details in myOffice, which is
    // taken from the apps list (not the app you are in; user.personal_url overrides where an app knows better).
    $idp = rtrim((string) ($user['idp_url'] ?? ''), '/');
    $personalUrl = $user['personal_url'] ?? null;
    foreach ($appList as $candidate) {
        if ($personalUrl === null && ! ($candidate['active'] ?? false) && strcasecmp((string) ($candidate['name'] ?? ''), 'myOffice') === 0) {
            $personalUrl = preg_replace('#/auth/redirect$#', '', (string) $candidate['url']).'/mijn';
        }
    }
    $user['links'] = array_values(array_filter([
        $idp !== '' ? ['label' => __('nevento::shell.account_details'), 'url' => $idp.'/profile/account', 'icon' => 'person'] : null,
        $personalUrl ? ['label' => __('nevento::shell.personal_details'), 'url' => $personalUrl, 'icon' => 'badge'] : null,
        $idp !== '' ? ['label' => __('nevento::shell.change_password'), 'url' => $idp.'/profile/password', 'icon' => 'lock'] : null,
        ...(array) ($user['links'] ?? []),
    ]));
@endphp
<!doctype html>
<html class="h-full bg-paper" style="scrollbar-gutter: stable;" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?: $appName }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        {{-- Space Grotesk carries headings and labels, Figtree the running text. --}}
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.3.2/css/flag-icons.min.css">
        <script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script>

        {{ $head ?? '' }}
        @stack('head')
    </head>
    <body class="h-full antialiased text-ink bg-paper">

        @if ($authed)
            {{-- Rendered once, outside both top bars, so the mobile triggers reach them too (the desktop top bar is hidden below `lg`). --}}
            @if ($canSwitchWorkspace)
                <x-nevento::shell.switch-dialog id="workspace-switch-modal" :title="__('nevento::shell.switch_workspace')" :items="$workspaceList" kind="workspace" />
            @endif
            @if ($canSwitchApp)
                <x-nevento::shell.switch-dialog id="app-switch-modal" :title="__('nevento::shell.switch_app')" :items="$appList" kind="app" />
            @endif

            <!-- Mobile off-canvas sidebar -->
            <el-dialog>
                <dialog id="sidebar" class="backdrop:bg-transparent lg:hidden">
                    <el-dialog-backdrop class="fixed inset-0 bg-white/80 transition-opacity duration-300 ease-linear data-closed:opacity-0"></el-dialog-backdrop>
                    <div tabindex="0" class="fixed inset-0 flex focus:outline-none">
                        <el-dialog-panel class="group/dialog-panel relative mr-16 flex w-full max-w-xs flex-1 transform transition duration-300 ease-in-out data-closed:-translate-x-full bg-white">
                            <div class="absolute top-0 left-full flex w-16 justify-center pt-5 duration-300 ease-in-out group-data-closed/dialog-panel:opacity-0">
                                <button type="button" command="close" commandfor="sidebar" class="-m-2.5 p-2.5" aria-label="{{ __('nevento::shell.close_sidebar') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6 text-white" aria-hidden="true">
                                        <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </div>
                            <x-nevento::shell.sidebar :user="$user">
                                {{ $sidebar ?? '' }}
                                <x-slot:account>{{ $account ?? '' }}</x-slot:account>
                            </x-nevento::shell.sidebar>
                        </el-dialog-panel>
                    </div>
                </dialog>
            </el-dialog>

            <!-- Static sidebar, desktop -->
            <div class="hidden bg-white lg:fixed lg:top-16 lg:bottom-0 lg:z-40 lg:flex lg:w-72 lg:flex-col lg:pt-5 lg:pb-5">
                <x-nevento::shell.sidebar :user="$user">
                    {{ $sidebar ?? '' }}
                    <x-slot:account>{{ $account ?? '' }}</x-slot:account>
                </x-nevento::shell.sidebar>
            </div>

            <!-- Top bar, desktop -->
            <div class="sticky top-0 z-50 hidden h-16 grid-cols-[18rem_minmax(0,1fr)] border-b border-hairline bg-white shadow-xs lg:grid">
                <div class="flex items-center px-6">
                    <a href="{{ $homeUrl }}" class="inline-flex items-center" aria-label="{{ __('nevento::shell.go_overview') }}">
                        <x-nevento::brand-lockup :size="30" :name="$brandName" :logo="$hasWorkspace ? ($activeWorkspace['logo_wide_url'] ?? null) : null" />
                    </a>
                </div>

                <div class="flex items-center justify-between lg:pl-15 lg:pr-6">
                    @if ($portalLabel)
                        <div class="flex items-center text-base font-medium tracking-tight text-ink-soft">
                            <span class="inline-flex items-center gap-1.5 leading-none">
                                <span class="material-symbols-rounded text-[18px] leading-none text-brand">admin_panel_settings</span>
                                <span>{{ $portalLabel }}</span>
                            </span>
                        </div>
                    @else
                        <div class="flex items-center text-base font-medium tracking-tight text-ink-soft">
                            {{-- Workspace --}}
                            @if ($canSwitchWorkspace)
                                <button type="button" command="show-modal" commandfor="workspace-switch-modal"
                                        class="group inline-flex items-center gap-1.5 rounded-[10px] px-2 py-1 leading-none transition hover:bg-sunken focus:outline-none focus:ring-2 focus:ring-brand"
                                        title="{{ __('nevento::shell.switch_workspace') }}">
                                    <x-nevento::workspace-mark :workspace="$hasWorkspace ? $activeWorkspace : []" :size="22" />
                                    <span>{{ $hasWorkspace ? $activeWorkspace['name'] : __('nevento::shell.no_workspace') }}</span>
                                    <span class="material-symbols-rounded text-[16px] leading-none text-ink-faint transition group-hover:text-ink-soft">unfold_more</span>
                                </button>
                            @elseif ($hasWorkspace)
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 leading-none">
                                    <x-nevento::workspace-mark :workspace="$activeWorkspace" :size="22" />
                                    <span>{{ $activeWorkspace['name'] }}</span>
                                </span>
                            @endif

                            @if ($hasWorkspace)
                                <span class="mx-2 inline-flex items-center text-ink-faint"><span class="material-symbols-rounded text-[16px] leading-none">chevron_right</span></span>
                            @endif

                            {{-- App --}}
                            @if ($canSwitchApp)
                                <button type="button" command="show-modal" commandfor="app-switch-modal"
                                        class="group inline-flex items-center gap-1.5 rounded-[10px] px-2 py-1 leading-none transition hover:bg-sunken focus:outline-none focus:ring-2 focus:ring-brand"
                                        title="{{ __('nevento::shell.switch_app') }}">
                                    <x-nevento::app-mark :app="$app" :size="22" />
                                    <span>{{ $appName }}</span>
                                    <span class="material-symbols-rounded text-[16px] leading-none text-ink-faint transition group-hover:text-ink-soft">unfold_more</span>
                                </button>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 leading-none">
                                    <x-nevento::app-mark :app="$app" :size="22" />
                                    <span>{{ $appName }}</span>
                                </span>
                            @endif

                            @if ($crumb)
                                <span class="mx-2 inline-flex items-center text-ink-faint"><span class="material-symbols-rounded text-[16px] leading-none">chevron_right</span></span>
                                <span class="inline-flex items-center gap-1.5 leading-none">{{ $crumb }}</span>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center gap-2">
                        @if ($notifications !== null)
                            <x-nevento::shell.notifications :notifications="$notifications" />
                        @endif
                        <x-nevento::shell.account-menu :user="$user">{{ $account ?? '' }}</x-nevento::shell.account-menu>
                    </div>
                </div>
            </div>

            <!-- Top bar, mobile -->
            <div class="sticky top-0 z-40 flex h-16 items-center justify-between gap-x-6 border-b border-hairline bg-white px-4 shadow-xs sm:px-6 lg:hidden">
                <button type="button" command="show-modal" commandfor="sidebar" class="-m-2.5 p-2.5 text-ink-soft lg:hidden" aria-label="{{ __('nevento::shell.open_sidebar') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true">
                        <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <a href="{{ $homeUrl }}" class="inline-flex items-center" aria-label="{{ __('nevento::shell.go_overview') }}">
                    <x-nevento::brand-lockup :size="30" :name="$brandName" :logo="$hasWorkspace ? ($activeWorkspace['logo_wide_url'] ?? null) : null" />
                </a>
                <div class="flex items-center gap-1">
                    @if ($canSwitchWorkspace && ! $portalLabel)
                        <button type="button" command="show-modal" commandfor="workspace-switch-modal"
                                class="inline-flex items-center gap-1 rounded-[10px] p-2 text-ink-faint transition hover:bg-sunken focus:outline-none focus:ring-2 focus:ring-brand"
                                title="{{ __('nevento::shell.switch_workspace') }}" aria-label="{{ __('nevento::shell.switch_workspace') }}">
                            <span class="material-symbols-rounded text-[20px] leading-none">folder</span>
                        </button>
                    @endif
                    @if ($canSwitchApp && ! $portalLabel)
                        <button type="button" command="show-modal" commandfor="app-switch-modal"
                                class="inline-flex items-center gap-1 rounded-[10px] p-2 text-ink-faint transition hover:bg-sunken focus:outline-none focus:ring-2 focus:ring-brand"
                                title="{{ __('nevento::shell.switch_app') }}" aria-label="{{ __('nevento::shell.switch_app') }}">
                            <span class="material-symbols-rounded text-[20px] leading-none">apps</span>
                        </button>
                    @endif
                    @if ($notifications !== null)
                        <x-nevento::shell.notifications :notifications="$notifications" />
                    @endif
                    <x-nevento::shell.account-menu :user="$user" id="account-menu-modal-mobile">{{ $account ?? '' }}</x-nevento::shell.account-menu>
                </div>
            </div>
        @endif

        {{-- Flash toasts: fixed so feedback is visible whatever the scroll position. --}}
        @if ($success || $error)
            <div class="pointer-events-none fixed inset-x-4 top-20 z-[100] flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:items-end">
                @if ($success)
                    <div data-toast="success" role="status" class="pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-[10px] border border-brand bg-brand-tint px-4 py-3 text-sm text-brand-strong shadow-lg">
                        <span class="material-symbols-rounded mt-0.5 shrink-0 text-[18px] leading-none">check_circle</span>
                        <span class="flex-1">{{ $success }}</span>
                        <button type="button" data-toast-dismiss class="-m-1 shrink-0 rounded p-1 text-brand-strong transition" aria-label="{{ __('nevento::shell.dismiss') }}">
                            <span class="material-symbols-rounded text-[16px] leading-none">close</span>
                        </button>
                    </div>
                @endif
                @if ($error)
                    <div data-toast="error" role="alert" class="pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-[10px] border border-alert bg-alert/5 px-4 py-3 text-sm text-alert shadow-lg">
                        @if ($errorLabel)
                            <span class="mt-0.5 shrink-0 rounded bg-alert/15 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-alert">{{ $errorLabel }}</span>
                        @endif
                        <span class="flex-1">{{ $error }}</span>
                        <button type="button" data-toast-dismiss class="-m-1 shrink-0 rounded p-1 text-alert transition" aria-label="{{ __('nevento::shell.dismiss') }}">
                            <span class="material-symbols-rounded text-[16px] leading-none">close</span>
                        </button>
                    </div>
                @endif
            </div>
        @endif

        <main @class(['pt-5 pb-10 lg:pl-72' => $authed, 'pt-5' => ! $authed])>
            <div @class(['px-6 lg:px-15' => $authed, 'mx-auto max-w-7xl px-6' => ! $authed])>
                {{ $before ?? '' }}
                {{ $slot }}
            </div>
        </main>

        <x-nevento::report-button :floating="false" />

        {{ $after ?? '' }}

        <script>
            // Sidebar blocks fold away; remember per label what was folded, except for the block that holds the open page.
            document.querySelectorAll('details[data-nav-section]').forEach((block) => {
                const key = 'nevento-nav:' + block.dataset.navSection;
                try { if (localStorage.getItem(key) === '0' && ! block.querySelector('[aria-current="page"]')) block.open = false; } catch (e) {}
                block.addEventListener('toggle', () => { try { localStorage.setItem(key, block.open ? '1' : '0'); } catch (e) {} });
            });
        </script>

        @stack('scripts')
    </body>
</html>
