@props([
    'title' => null,
    // The app this is, shown under the mark: ['name' => 'Rento']
    'app' => [],
    'homeUrl' => '/',
])

{{-- The page frame for pages without a logged-in person (login error, dev login): the same centred card as the IDP's front door, in the shared
     theme, so a person who lands here from any Nevento app sees one look. --}}
@php($appName = trim((string) ($app['name'] ?? config('app.name'))))
<!doctype html>
<html class="h-full bg-paper" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ? $title.' · '.$appName : $appName }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
        @stack('head')
    </head>
    <body class="h-full bg-paper text-ink antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center px-5 py-10 sm:px-8">
            <a href="{{ $homeUrl }}" class="mb-7 flex flex-col items-center gap-2" aria-label="{{ $appName }}">
                <x-nevento::brand-lockup :size="44" />
                @if ($appName !== '')
                    <span class="text-sm text-ink-faint">{{ $appName }}</span>
                @endif
            </a>

            <main class="w-full rounded-[16px] border border-hairline bg-white px-6 py-7 shadow-[var(--shadow-card)] sm:max-w-lg sm:px-9 sm:py-9">
                {{ $slot }}
            </main>

            @isset($footer)
                <p class="mt-5 max-w-lg text-center text-sm text-ink-faint">{{ $footer }}</p>
            @endisset
        </div>

        @stack('scripts')
    </body>
</html>
