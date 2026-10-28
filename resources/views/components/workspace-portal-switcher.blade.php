@props([
    'current' => null,
    'variant' => 'menu',
])

@php
    use App\Enums\Workspace;

    $user = auth()->user();
    $currentWorkspace = $current instanceof Workspace
        ? $current
        : (is_string($current) ? Workspace::tryFrom($current) : Workspace::tryFrom((string) session('workspace')));

    $portals = [
        Workspace::Buyer,
        Workspace::Broker,
        Workspace::Seller,
        Workspace::Investor,
    ];

    $shortcuts = [];

    if ($user !== null) {
        foreach ($portals as $portal) {
            if ($portal === $currentWorkspace || ! $user->canAccessWorkspace($portal)) {
                continue;
            }

            $shortcuts[] = $portal;
        }
    }

    $menuButtonClass = 'block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700';
    $sidebarButtonClass = 'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-white/90 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200';
    $mobileButtonClass = 'flex min-h-11 w-full items-center justify-center rounded-lg border border-lamu-900 px-3 text-sm font-medium text-lamu-900 hover:bg-lamu-50';
@endphp

@if ($shortcuts !== [])
    @if ($variant === 'sidebar')
        <div class="shrink-0 space-y-1 border-t border-white/10 px-3 py-3" aria-label="Switch workspace">
            <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-sand-300">Switch workspace</p>
            @foreach ($shortcuts as $portal)
                <form method="POST" action="{{ route('workspaces.switch', $portal->value) }}" data-turbo="false">
                    @csrf
                    <button type="submit" class="{{ $sidebarButtonClass }}">
                        <i class="fa-solid fa-arrow-right-arrow-left w-4 text-center text-sand-200" aria-hidden="true"></i>
                        <span>Open {{ $portal->label() }} workspace</span>
                    </button>
                </form>
            @endforeach
        </div>
    @elseif ($variant === 'mobile')
        <div class="border-b border-sand-200 px-5 py-4" aria-label="Your portals">
            <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Your portals</p>
            <div class="mt-3 space-y-2">
                @foreach ($shortcuts as $portal)
                    <form method="POST" action="{{ route('workspaces.switch', $portal->value) }}" data-turbo="false">
                        @csrf
                        <button type="submit" class="{{ $mobileButtonClass }}">Open {{ $portal->label() }} workspace</button>
                    </form>
                @endforeach
            </div>
            <a href="{{ route('workspaces.index') }}" class="mt-3 inline-flex text-sm font-medium text-lamu-700 hover:underline">All workspaces</a>
        </div>
    @else
        <div class="space-y-1 border-t border-sand-200 pt-2" aria-label="Your portals">
            <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-stone-500">Your portals</p>
            @foreach ($shortcuts as $portal)
                <form method="POST" action="{{ route('workspaces.switch', $portal->value) }}" data-turbo="false">
                    @csrf
                    <button type="submit" class="{{ $menuButtonClass }}" onclick="this.closest('details')?.removeAttribute('open')">
                        Open {{ $portal->label() }} workspace
                    </button>
                </form>
            @endforeach
            @if (count($workspaces ?? []) > 1)
                <a href="{{ route('workspaces.index') }}" data-turbo-frame="modal" onclick="this.closest('details')?.removeAttribute('open')" class="{{ $menuButtonClass }}">All workspaces</a>
            @endif
        </div>
    @endif
@endif
