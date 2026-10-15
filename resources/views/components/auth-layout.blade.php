@props(['title' => 'Lamu Acres'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logo.jpg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-sand-50 text-stone-900 antialiased">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-12">
        <a href="{{ route('home') }}" class="w-fit" aria-label="Lamu Acres home"><x-brand-logo /></a>
        <div class="mt-8">
            {{ $slot }}
        </div>
    </main>
    <x-flash />
</body>
</html>
