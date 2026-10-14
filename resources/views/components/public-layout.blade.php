@props([
    'title' => 'Lamu Acres',
    'description' => 'Lamu Acres publishes land listings and location guides for the Lamu coast. Private coordinates and landowner contacts stay off the public site.',
])

@php
    $brokerPartnersNavActive = request()->routeIs('broker-partners')
        || (request()->routeIs('account.applications.create') && request()->query('capability') === 'broker');
    $listPropertyNavActive = request()->routeIs('list-property')
        || (request()->routeIs('account.applications.create') && request()->query('capability') === 'seller');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-sand-50 text-stone-900 antialiased">
    <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2">Skip to content</a>
    <header class="sticky top-0 z-30 border-b border-sand-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="text-lg font-semibold text-lamu-900">Lamu Acres</a>
            <nav class="hidden items-center gap-x-5 text-sm text-stone-600 lg:flex" aria-label="Public">
                <a href="{{ route('land.index') }}" @class(['font-medium text-lamu-900' => request()->routeIs('land.*')])>Explore Land</a>
                <a href="{{ route('home') }}#find-land">Find Land for Me</a>
                <a href="{{ route('land.index', ['purpose' => 'lease']) }}">Lease Land</a>
                <a href="{{ route('home') }}#intelligence">Investment Intelligence</a>
                <a href="{{ route('broker-partners') }}" @class(['font-medium text-lamu-900' => $brokerPartnersNavActive])>Broker Partners</a>
                <a href="{{ route('list-property') }}" @class(['font-medium text-lamu-900' => $listPropertyNavActive])>List Property</a>
            </nav>
            <div class="flex items-center gap-3 text-sm">
                @auth
                    <x-account-menu :role="$accountRole ?? ''" :workspace="$currentWorkspace ?? null" :workspaces="$availableWorkspaces ?? []" />
                @else
                    <a href="{{ route('login') }}" class="font-medium text-lamu-800">Login</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-lamu-900 px-4 py-2 font-medium text-white">Register</a>
                @endauth
                <details class="relative lg:hidden">
                    <summary class="cursor-pointer list-none rounded-full border border-sand-200 px-3 py-2 text-sm font-medium text-lamu-900">Menu</summary>
                    <nav class="absolute right-0 z-20 mt-2 w-56 rounded-2xl border border-sand-200 bg-white p-3 text-sm shadow-lg" aria-label="Mobile">
                        <a class="block rounded-lg px-3 py-2 hover:bg-sand-50" href="{{ route('land.index') }}">Explore Land</a>
                        <a class="block rounded-lg px-3 py-2 hover:bg-sand-50" href="{{ route('home') }}#find-land">Find Land for Me</a>
                        <a class="block rounded-lg px-3 py-2 hover:bg-sand-50" href="{{ route('land.index', ['purpose' => 'lease']) }}">Lease Land</a>
                        <a class="block rounded-lg px-3 py-2 hover:bg-sand-50" href="{{ route('home') }}#intelligence">Investment Intelligence</a>
                        <a @class(['block rounded-lg px-3 py-2 hover:bg-sand-50', 'bg-sand-50 font-medium text-lamu-900' => $brokerPartnersNavActive]) href="{{ route('broker-partners') }}">Broker Partners</a>
                        <a @class(['block rounded-lg px-3 py-2 hover:bg-sand-50', 'bg-sand-50 font-medium text-lamu-900' => $listPropertyNavActive]) href="{{ route('list-property') }}">List Property</a>
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <main id="content">
        {{ $slot }}
    </main>

    <footer class="border-t border-sand-200 bg-lamu-900 text-sand-50">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3">
            <div>
                <p class="text-lg font-semibold">Lamu Acres</p>
                <p class="mt-3 text-sm text-sand-200">Published land listings and location guides for the Lamu coast.</p>
            </div>
            <nav class="text-sm" aria-label="Footer">
                <a class="block py-1 text-sand-50 hover:text-white" href="{{ route('land.index') }}">Explore Land</a>
                <a class="block py-1 text-sand-50 hover:text-white" href="{{ route('home') }}#find-land">Find Land for Me</a>
                <a class="block py-1 text-sand-50 hover:text-white" href="{{ route('land.index', ['purpose' => 'lease']) }}">Lease Land</a>
            </nav>
            <nav class="text-sm" aria-label="Footer partnerships">
                <a class="block py-1 text-sand-50 hover:text-white" href="{{ route('home') }}#intelligence">Investment Intelligence</a>
                <a class="block py-1 text-sand-50 hover:text-white" href="{{ route('broker-partners') }}">Broker Partners</a>
                <a class="block py-1 text-sand-50 hover:text-white" href="{{ route('list-property') }}">List Property</a>
            </nav>
        </div>
    </footer>

    <x-flash />
    <turbo-frame id="modal">{{ $modal ?? '' }}</turbo-frame>
</body>
</html>
