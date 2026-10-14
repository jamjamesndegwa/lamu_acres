@props(['title' => 'Account — Lamu Acres'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-sand-50 text-stone-900 antialiased">
    <header class="border-b border-sand-200 bg-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="text-lg font-semibold text-lamu-900">Lamu Acres</a>
            <form method="POST" action="{{ route('logout') }}" data-turbo="false" data-confirm="Log out of Lamu Acres?">
                @csrf
                <button type="submit" class="text-sm font-medium text-lamu-800">Logout</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        {{ $slot }}
    </main>

    <x-flash />
    <turbo-frame id="modal">{{ $modal ?? '' }}</turbo-frame>
</body>
</html>
