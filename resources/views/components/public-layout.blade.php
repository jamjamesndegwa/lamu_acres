@props([
    'title' => 'Lamu Acres',
    'description' => 'Lamu Acres publishes land listings and location guides for the Lamu coast. Private coordinates and landowner contacts stay off the public site.',
    'home' => false,
])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logo.jpg') }}">
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @class(['min-h-screen bg-sand-50 text-stone-900 antialiased', 'home-shell' => $home])>
    <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2">Skip to content</a>
    @unless ($home)
    <x-public-header :account-role="$accountRole ?? ''" :workspace="$currentWorkspace ?? null" :workspaces="$availableWorkspaces ?? []" />
    @else
        {{ $banner ?? '' }}
    @endunless

    <main id="content">
        {{ $slot }}
    </main>

    <x-public-footer />

    <x-page-loading />
    <x-flash />
    <turbo-frame id="modal">{{ $modal ?? '' }}</turbo-frame>
</body>
</html>
