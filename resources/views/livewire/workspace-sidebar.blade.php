<div class="workspace-sidebar-shell lg:h-full lg:shrink-0">
    <label
        for="workspace-nav-open"
        id="workspace-drawer-backdrop"
        class="workspace-drawer-backdrop fixed inset-0 z-[45] bg-stone-900/50 lg:hidden"
        aria-hidden="true"
    ></label>

    <aside
        id="workspace-sidebar"
        class="workspace-drawer-panel fixed inset-y-0 left-0 z-50 flex h-dvh flex-col overflow-hidden bg-lamu-900 text-sand-50 lg:static lg:z-auto lg:h-full lg:translate-x-0"
    >
        <div class="flex items-center justify-between border-b border-white/10 px-4 py-4 lg:block">
            <div class="min-w-0">
                <x-brand-logo tone="dark" />
                @if ($workspace)
                    <p class="mt-1 text-sm text-sand-200">{{ $eyebrow ?: $workspace->label() }}</p>
                @endif
            </div>
            <label
                for="workspace-nav-open"
                class="inline-flex size-10 shrink-0 cursor-pointer touch-manipulation items-center justify-center rounded-lg text-sand-100 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200 lg:hidden"
                aria-label="Close menu"
            >
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </label>
        </div>

        <nav class="sidebar-scroll min-h-0 flex-1 space-y-1 px-2 py-3" aria-label="Workspace">
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
                    'submit-offer' => 'fa-file-contract',
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
                @endphp
                @if ($module['href'])
                    <a
                        href="{{ $module['href'] }}"
                        data-module="{{ $module['key'] }}"
                        data-workspace-nav-close
                        @if ($module['active'])
                            aria-current="page"
                        @endif
                        @if ($module['frame'] ?? null)
                            data-turbo-frame="{{ $module['frame'] }}"
                        @endif
                        @class([
                            'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200',
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
                        data-workspace-nav-close
                        data-module="{{ $module['key'] }}"
                        @if ($module['active'])
                            aria-current="page"
                        @endif
                        @if ($module['frame'] ?? null)
                            data-turbo-frame="{{ $module['frame'] }}"
                        @endif
                        @class([
                            'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200',
                            'bg-white/10 text-white' => $module['active'],
                            'text-white/80 hover:bg-white/10' => ! $module['active'],
                        ])
                    >
                        <i class="fa-solid {{ $icon }} w-4 text-center text-sand-200" aria-hidden="true"></i>
                        <span>{{ $module['label'] }}</span>
                    </button>
                @endif
            @endforeach
        </nav>

        <x-workspace-portal-switcher :current="$workspace" variant="sidebar" />

        @if (count($account) > 0)
            <div class="shrink-0 space-y-1 border-t border-white/10 px-3 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                @foreach ($account as $item)
                    @php
                        $accountIcon = match ($item['label']) {
                            'Logout' => 'fa-right-from-bracket',
                            'Profile' => 'fa-user',
                            default => 'fa-file-lines',
                        };
                    @endphp
                    @if ($item['method'] === 'post' && $item['href'])
                        <form method="POST" action="{{ $item['href'] }}" data-turbo="false" data-confirm="Log out of Lamu Acres">
                            @csrf
                            <button type="submit" data-workspace-drawer-close class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm text-white/80 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200">
                                <i class="fa-solid {{ $accountIcon }} w-4 text-center text-sand-200" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </button>
                        </form>
                    @elseif ($item['available'])
                        <a
                            href="{{ $item['href'] }}"
                            data-workspace-nav-close
                            @if ($item['active'])
                                aria-current="page"
                            @endif
                            @if ($item['frame'])
                                data-turbo-frame="{{ $item['frame'] }}"
                            @endif
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-white/80 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200"
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
