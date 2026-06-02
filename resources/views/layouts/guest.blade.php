<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Phumpanya') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|libre-baskerville:400,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-2">
            <div class="flex flex-col justify-between bg-white px-6 py-8 sm:px-12 lg:px-16">
                <x-phumpanya-logo />

                <div class="mx-auto w-full max-w-md py-8">
                    @if (session('status'))
                        <div class="mb-4 text-sm font-medium text-green-600">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if (session('ku-all-login.error'))
                        <div class="mb-4 text-sm font-medium text-red-600">
                            {{ session('ku-all-login.error') }}
                        </div>
                    @endif

                    {{ $slot }}
                </div>

                <p class="text-xs text-gray-400">&copy; {{ date('Y') }} Phumpanya</p>
            </div>

            <div class="relative hidden min-h-[280px] bg-phumpanya-100 lg:block">
                <div class="absolute inset-0 flex items-center justify-center p-12">
                    <img
                        src="{{ asset('images/auth/hero-illustration.png') }}"
                        alt=""
                        class="max-h-full max-w-full object-contain opacity-90"
                    />
                </div>
            </div>
        </div>
    </body>
</html>
