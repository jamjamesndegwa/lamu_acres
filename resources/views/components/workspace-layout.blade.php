@props(['heading' => 'Dashboard', 'fullWidth' => false, 'showHeading' => true])

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

    <turbo-frame id="workspace-shell" data-turbo-action="advance" data-turbo-prefetch="false" class="flex h-dvh min-h-0">
        <livewire:workspace-sidebar />

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <header class="relative z-20 shrink-0 border-b border-sand-200 bg-white pt-[env(safe-area-inset-top)] lg:pt-0">
                {{-- Mobile app bar --}}
                <div class="flex h-14 items-center gap-2 px-3 lg:hidden">
                    <button
                        type="button"
                        data-workspace-drawer-toggle
                        aria-controls="workspace-sidebar"
                        aria-label="Open navigation menu"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-sand-200 bg-white text-lamu-900 hover:bg-sand-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700"
                    >
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-lamu-900">{{ $heading }}</p>
                        <p class="truncate text-xs text-stone-500">
                            {{ $currentWorkspace?->label() }}
                            @if (($workspaceRoles ?? '') !== '')
                                · {{ $workspaceRoles }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        @if ($currentWorkspace?->value === 'buyer')
                            <a href="{{ route('buyer.sourcing.create') }}" data-turbo-frame="_top" aria-label="New land search" class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-lamu-900 text-lg font-medium leading-none text-white hover:bg-lamu-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">
                                +
                            </a>
                        @endif
                        <x-notification-centre :workspace="$currentWorkspace" :count="$unreadNotifications ?? 0" compact />
                        <x-account-menu :role="$accountRole" :workspace="$currentWorkspace" :workspaces="$availableWorkspaces" compact />
                    </div>
                </div>

                {{-- Desktop --}}
                <div class="hidden flex-wrap items-center justify-between gap-x-6 gap-y-3 px-8 py-4 lg:flex">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-lamu-700">
                            {{ $currentWorkspace?->label() }} workspace
                        </p>
                        <p class="mt-1 truncate text-sm text-stone-500">
                            @if (($workspaceRoles ?? '') !== '')
                                {{ $workspaceRoles }}
                            @else
                                Your searches, saved properties, and account activity
                            @endif
                        </p>
                    </div>
                    <div class="ml-auto flex items-center gap-4">
                        @if ($currentWorkspace?->value === 'buyer')
                            <a href="{{ route('buyer.sourcing.create') }}" data-turbo-frame="_top" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-lamu-900 px-4 text-sm font-medium text-white transition hover:bg-lamu-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">
                                <span aria-hidden="true" class="text-lg leading-none">+</span>
                                <span>New land search</span>
                            </a>
                        @endif
                        <x-notification-centre :workspace="$currentWorkspace" :count="$unreadNotifications ?? 0" />
                        <x-account-menu :role="$accountRole" :workspace="$currentWorkspace" :workspaces="$availableWorkspaces" />
                    </div>
                </div>
            </header>

            <livewire:workspace-subnav />

            <main id="main" @class(['panel-scroll min-h-0 flex-1 bg-sand-50', 'px-3 py-4 sm:px-8 sm:py-8' => ! $fullWidth])>
                @if ($showHeading)
                    <h1 class="hidden text-xl font-semibold text-lamu-900 sm:text-2xl lg:block">{{ $heading }}</h1>
                @endif
                {{ $slot }}
            </main>

            <footer class="hidden shrink-0 border-t border-sand-200 bg-white px-3 py-2.5 text-xs text-stone-500 sm:block sm:px-8 sm:py-3">
                <p>&copy; {{ now()->year }} Lamu Acres</p>
            </footer>
        </div>
    </turbo-frame>

    <x-page-loading />
    <x-flash />
    <turbo-frame id="modal">{{ $modal ?? '' }}</turbo-frame>
</body>
</html>
