<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Search' }} — {{ config('app.name', 'Phumpanya') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|libre-baskerville:400,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="font-sans antialiased bg-white text-gray-900">
        <div class="flex min-h-screen">
            <x-phumpanya.sidebar :recent-searches="$recentSearches ?? collect()" :active="$activeNav ?? 'search'" />

            <div class="flex min-h-screen flex-1 flex-col">
                <header class="flex items-center justify-end border-b border-gray-100 px-6 py-4 lg:px-10">
                    <x-phumpanya.logo class="text-lg" />
                </header>

                <main class="flex-1 overflow-y-auto px-6 py-6 lg:px-10 lg:py-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
