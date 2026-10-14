<div wire:keydown.escape.window="close" class="lg:h-full lg:w-72 lg:shrink-0">
    <button
        type="button"
        class="fixed left-4 top-4 z-50 rounded-md bg-lamu-900 px-3 py-2 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700 lg:hidden"
        wire:click="toggle"
        aria-controls="workspace-sidebar"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
    >
        Menu
    </button>

    @if ($open)
        <button type="button" class="fixed inset-0 z-30 bg-stone-900/40 lg:hidden" wire:click="close" aria-label="Close menu"></button>
    @endif

    <aside
        id="workspace-sidebar"
        data-open="{{ $open ? 'true' : 'false' }}"
        @class([
            'fixed inset-y-0 left-0 z-40 flex h-dvh w-72 flex-col overflow-hidden bg-lamu-900 text-sand-50 transition lg:static lg:z-auto lg:h-full lg:translate-x-0',
            'translate-x-0' => $open,
            '-translate-x-full' => ! $open,
        ])
    >
        <div class="border-b border-white/10 px-5 py-6">
            <p class="text-lg font-semibold">Lamu Acres</p>
            @if ($workspace)
                <p class="mt-1 text-sm text-sand-200">{{ $eyebrow ?: $workspace->label() }}</p>
            @endif
        </div>

        <nav class="sidebar-scroll min-h-0 flex-1 space-y-1 px-3 py-5" aria-label="Workspace">
            @php
                $moduleIcons = [
                    'overview' => 'fa-gauge',
                    'tracker' => 'fa-diagram-project',
                    'alerts' => 'fa-bell',
                    'prices' => 'fa-chart-line',
                    'library' => 'fa-book-open',
                    'imagery' => 'fa-satellite',
                    'planning' => 'fa-map',
                    'dashboard' => 'fa-gauge',
                    'explore' => 'fa-magnifying-glass',
                    'saved' => 'fa-bookmark',
                    'properties' => 'fa-map-location-dot',
                    'matches' => 'fa-clone',
                    'buyers' => 'fa-users',
                    'sourcing' => 'fa-compass',
                    'brokerage' => 'fa-handshake',
                    'listings' => 'fa-building',
                    'referrals' => 'fa-user-plus',
                    'deals' => 'fa-briefcase',
                    'sales' => 'fa-file-signature',
                    'verification' => 'fa-shield-halved',
                    'intelligence' => 'fa-satellite',
                    'finance' => 'fa-coins',
                    'notifications' => 'fa-bell',
                    'enquiries' => 'fa-comments',
                    'visits' => 'fa-calendar-check',
                    'offers' => 'fa-file-contract',
                    'profile' => 'fa-id-badge',
                    'investor' => 'fa-chart-line',
                    'watchlists' => 'fa-bookmark',
                    'subscription' => 'fa-id-card',
                    'oversight' => 'fa-chart-column',
                    'approvals' => 'fa-circle-check',
                    'users' => 'fa-user-gear',
                    'geography' => 'fa-earth-africa',
                    'commercial' => 'fa-tags',
                    'settings' => 'fa-gear',
                    'reports' => 'fa-chart-pie',
                ];
            @endphp
            @foreach ($modules as $module)
                @php
                    $icon = $moduleIcons[$module['key']] ?? 'fa-folder';
                    $children = collect($module['items'] ?? [])->filter(
                        fn (array $item): bool => ($item['available'] ?? false) && ($item['method'] ?? 'get') === 'get',
                    )->values();
                    $repeatHeading = $children->count() === 1 && ($children->first()['label'] ?? null) === $module['label'];
                @endphp
                @if ($module['href'])
                    <a
                        href="{{ $module['href'] }}"
                        data-module="{{ $module['key'] }}"
                        @if ($module['active'])
                            aria-current="page"
                        @endif
                        @class([
                            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200',
                            'bg-white/10 text-white' => $module['active'],
                            'text-white/80 hover:bg-white/10' => ! $module['active'],
                        ])
                    >
                        <i class="fa-solid {{ $icon }} w-4 text-center text-sand-200" aria-hidden="true"></i>
                        <span>{{ $module['label'] }}</span>
                    </a>
                @else
                    <button
                        type="button"
                        wire:click="select('{{ $module['key'] }}')"
                        data-module="{{ $module['key'] }}"
                        @if ($module['active'])
                            aria-current="page"
                        @endif
                        @class([
                            'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200',
                            'bg-white/10 text-white' => $module['active'],
                            'text-white/80 hover:bg-white/10' => ! $module['active'],
                        ])
                    >
                        <i class="fa-solid {{ $icon }} w-4 text-center text-sand-200" aria-hidden="true"></i>
                        <span>{{ $module['label'] }}</span>
                    </button>
                @endif
                @if ($workspace && $workspace->value === 'staff' && ! $repeatHeading && $children->isNotEmpty())
                        <ul class="mb-2 ml-8 space-y-0.5">
                            @foreach ($children as $item)
                                <li>
                                    <a href="{{ $item['href'] }}" class="block rounded-lg px-3 py-1.5 text-sm text-white/70 hover:bg-white/10 hover:text-white">{{ $item['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                @endif
            @endforeach
        </nav>

        @if (count($account) > 0)
            <div class="shrink-0 space-y-1 border-t border-white/10 px-3 py-3">
                @foreach ($account as $item)
                    @php
                        $accountIcon = match ($item['label']) {
                            'Logout' => 'fa-right-from-bracket',
                            'Profile' => 'fa-user',
                            default => 'fa-file-lines',
                        };
                    @endphp
                    @if ($item['method'] === 'post' && $item['href'])
                        <form method="POST" action="{{ $item['href'] }}" data-turbo="false" data-confirm="Log out of Lamu Acres?">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-white/80 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200">
                                <i class="fa-solid {{ $accountIcon }} w-4 text-center text-sand-200" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </button>
                        </form>
                    @elseif ($item['available'])
                        <a
                            href="{{ $item['href'] }}"
                            @if ($item['active'])
                                aria-current="page"
                            @endif
                            @if ($item['frame'])
                                data-turbo-frame="{{ $item['frame'] }}"
                            @endif
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-white/80 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200"
                        >
                            <i class="fa-solid {{ $accountIcon }} w-4 text-center text-sand-200" aria-hidden="true"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </aside>
</div>
