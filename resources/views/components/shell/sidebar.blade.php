@props([
    'user' => [],
])

{{-- The sidebar frame, used twice by the shell (static on desktop, a drawer on mobile). The app supplies the navigation as the slot, as
     <x-nevento::nav-group> blocks; the report entry and, on mobile, the account block are added here so every app has them in the same place. --}}
<div class="hide-scrollbar flex grow flex-col gap-y-5 overflow-y-auto bg-white px-6 pb-2">
    <nav class="flex flex-1 flex-col">
        <ul role="list" class="flex flex-1 flex-col gap-y-7">
            {{ $slot }}

            @if (filled(config('nevento-system.token')))
                <li class="mt-auto">
                    <ul role="list" class="-mx-2 space-y-1">
                        <x-nevento::nav-item href="#" icon="feedback" data-nevento-report>{{ __('nevento::shell.report') }}</x-nevento::nav-item>
                    </ul>
                </li>
            @endif
        </ul>
    </nav>

    <div class="mt-auto -mx-6 lg:hidden">
        <div class="mt-4 px-6 py-4">
            <h3 class="mb-2 text-[10px] font-bold uppercase tracking-wider text-ink-faint">{{ __('nevento::shell.my_account') }}</h3>
            <x-nevento::shell.account-menu :user="$user" :compact="true">{{ $account ?? '' }}</x-nevento::shell.account-menu>
        </div>
    </div>
</div>
