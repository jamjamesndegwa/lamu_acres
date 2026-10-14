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
            @foreach ($modules as $module)
                @if ($module['href'])
                    <a
                        href="{{ $module['href'] }}"
                        data-module="{{ $module['key'] }}"
                        @if ($module['active']) aria-current="page" @endif
                        @class([
                            'block rounded-lg px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200',
                            'bg-white/10 text-white' => $module['active'],
                            'text-white/80 hover:bg-white/10' => ! $module['active'],
                        ])
                    >
                        {{ $module['label'] }}
                    </a>
                @else
                    <button
                        type="button"
                        wire:click="select('{{ $module['key'] }}')"
                        data-module="{{ $module['key'] }}"
                        @if ($module['active']) aria-current="page" @endif
                        @class([
                            'block w-full rounded-lg px-3 py-2 text-left text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200',
                            'bg-white/10 text-white' => $module['active'],
                            'text-white/80 hover:bg-white/10' => ! $module['active'],
                        ])
                    >
                        {{ $module['label'] }}
                    </button>
                @endif
            @endforeach
        </nav>

        @if (count($account) > 0)
            <div class="shrink-0 space-y-1 border-t border-white/10 px-3 py-3">
                @foreach ($account as $item)
                    @if ($item['method'] === 'post' && $item['href'])
                        <form method="POST" action="{{ $item['href'] }}" data-turbo="false" data-confirm="Log out of Lamu Acres?">
                            @csrf
                            <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-white/80 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200">
                                {{ $item['label'] }}
                            </button>
                        </form>
                    @elseif ($item['available'])
                        <a
                            href="{{ $item['href'] }}"
                            @if ($item['active']) aria-current="page" @endif
                            @if ($item['frame']) data-turbo-frame="{{ $item['frame'] }}" @endif
                            class="block rounded-lg px-3 py-2 text-sm text-white/80 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </aside>
</div>
