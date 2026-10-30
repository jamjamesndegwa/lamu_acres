@props(['title' => 'Lamu Acres'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logo.jpg') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-sand-50 text-stone-900 antialiased">
    <a href="#auth-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2">Skip to sign in</a>
    <div class="grid min-h-screen lg:grid-cols-2">
        <aside class="relative hidden overflow-hidden bg-lamu-900 lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
            <img src="{{ asset('images/explore_land_hero_section.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-lamu-900 via-lamu-900/70 to-lamu-900/30" aria-hidden="true"></div>
            <a href="{{ route('home') }}" class="relative w-fit" aria-label="Lamu Acres home"><x-brand-logo tone="dark" size="lg" /></a>
            <div class="relative max-w-lg py-16 text-white">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sand-200">Land. Possibility. Lamu.</p>
                <h2 class="home-serif mt-5 text-4xl font-semibold leading-tight xl:text-5xl">Your next chapter starts with a place.</h2>
                <p class="mt-6 max-w-md text-base leading-relaxed text-sand-50">Discover land along the Lamu coast. Keep your favourites together and let our desk help you take the next step.</p>
                <ul class="mt-8 space-y-4 text-sm text-sand-50">
                    @foreach (['Save properties to your shortlist', 'Ask questions and arrange site visits', 'Keep track of enquiries in your buyer account'] as $benefit)
                        <li class="flex items-center gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full border border-white/30" aria-hidden="true">✓</span>{{ $benefit }}</li>
                    @endforeach
                </ul>
            </div>
            <a href="{{ route('land.index') }}" class="relative w-fit text-sm font-medium text-white underline underline-offset-4">Explore available land &rarr;</a>
        </aside>
        <main id="auth-content" class="flex min-h-screen flex-col px-5 py-6 sm:px-10 lg:px-12 xl:px-20">
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="lg:hidden" aria-label="Lamu Acres home"><x-brand-logo /></a>
                <a href="{{ route('land.index') }}" class="ml-auto text-sm font-medium text-lamu-800 hover:underline">Back to land listings &rarr;</a>
            </div>
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10 lg:py-14">
                <div class="rounded-2xl border border-sand-200 bg-white p-6 shadow-sm sm:p-8">{{ $slot }}</div>
                <p class="mt-6 text-center text-xs leading-relaxed text-stone-500">Lamu Acres · Land listings and a local team to help you move forward.</p>
            </div>
        </main>
    </div>
    <x-flash />
</body>
</html>
