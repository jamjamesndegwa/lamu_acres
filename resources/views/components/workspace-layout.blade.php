@props(['heading' => 'Dashboard'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} — Lamu Acres</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logo.jpg') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-dvh overflow-hidden bg-sand-50 text-stone-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2">Skip to content</a>

    <turbo-frame id="workspace-shell" data-turbo-action="advance" class="flex h-dvh min-h-0">
        <livewire:workspace-sidebar />

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <header class="relative z-20 shrink-0 border-b border-sand-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 max-lg:pl-24 sm:px-8">
                    <p class="text-sm text-stone-500">
                        <span>{{ $currentWorkspace?->label() }}</span>
                        @if (($workspaceRoles ?? '') !== '')
                            <span aria-hidden="true"> · </span>
                            <span>{{ $workspaceRoles }}</span>
                        @endif
                    </p>
                    <div class="ml-auto flex items-center gap-4">
                        <x-notification-centre :workspace="$currentWorkspace" :count="$unreadNotifications ?? 0" />
                        <x-account-menu :role="$accountRole" :workspace="$currentWorkspace" :workspaces="$availableWorkspaces" />
                    </div>
                </div>
            </header>

            <livewire:workspace-subnav />

            <main id="main" class="panel-scroll min-h-0 flex-1 px-4 py-8 sm:px-8">
                <h1 class="text-2xl font-semibold text-lamu-900">{{ $heading }}</h1>
                {{ $slot }}
            </main>

            <footer class="shrink-0 border-t border-sand-200 bg-white px-4 py-3 text-xs text-stone-500 sm:px-8">
                <p>&copy; {{ now()->year }} Lamu Acres</p>
            </footer>
        </div>
    </turbo-frame>

    <x-page-loading />
    <x-flash />
    <turbo-frame id="modal">{{ $modal ?? '' }}</turbo-frame>
</body>
</html>
