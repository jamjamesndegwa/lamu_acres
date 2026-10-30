@props([
    'title' => 'Something went wrong',
    'code' => null,
    'message' => 'We could not complete that request.',
    'showBack' => true,
])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Lamu Acres</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logo.jpg') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-sand-50 text-stone-900 antialiased">
    <main class="flex min-h-screen flex-col items-center justify-center px-5 py-12">
        <a href="{{ route('home') }}" class="mb-8" aria-label="Lamu Acres home">
            <x-brand-logo />
        </a>
        <div class="w-full max-w-md rounded-2xl border border-sand-200 bg-white p-8 text-center shadow-sm">
            @if ($code)
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-lamu-700">{{ $code }}</p>
            @endif
            <h1 class="home-serif mt-3 text-2xl font-semibold text-lamu-900 sm:text-3xl">{{ $title }}</h1>
            <p class="mt-4 text-sm leading-relaxed text-stone-600">{{ $message }}</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                @if ($showBack)
                    <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.assign(@js(route('home')))" class="inline-flex min-h-11 items-center justify-center rounded-full border border-sand-200 bg-white px-5 text-sm font-semibold text-lamu-900 hover:bg-sand-50">
                        Go back
                    </button>
                @endif
                <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center justify-center rounded-full bg-lamu-900 px-5 text-sm font-semibold text-white hover:bg-lamu-800">
                    Go to home
                </a>
            </div>
        </div>
        <p class="mt-8 text-xs text-stone-500">&copy; {{ now()->year }} Lamu Acres</p>
    </main>
</body>
</html>
