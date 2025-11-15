<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <script defer src="https://unpkg.com/alpinejs@3.13.3/dist/cdn.min.js"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-800">
        <div class="relative min-h-screen bg-gradient-to-br from-slate-100 via-slate-200 to-white">
            <div class="pointer-events-none absolute inset-x-0 top-0 flex justify-center overflow-hidden">
                <div class="h-64 w-[55rem] -translate-y-1/3 transform rounded-full bg-indigo-300/40 blur-3xl"></div>
            </div>
            <div class="pointer-events-none absolute inset-y-0 right-0 hidden w-2/5 translate-x-1/3 rounded-full bg-sky-200/40 blur-3xl lg:block"></div>

            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="relative z-10 mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
                    <div class="rounded-3xl border border-white/60 bg-white/70 px-6 py-5 shadow-lg backdrop-blur sm:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="relative z-10">
                <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @stack('scripts')
    </body>
</html>
