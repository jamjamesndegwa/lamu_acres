@props([
    'current' => null,
    'workspaces' => [],
    'variant' => 'menu',
])

@php
    use App\Enums\Workspace;

    $user = auth()->user();
    $currentWorkspace = $current instanceof Workspace
        ? $current
        : (is_string($current) ? Workspace::tryFrom($current) : Workspace::tryFrom((string) session('workspace')));

    $accessibleWorkspaces = collect($workspaces)
        ->filter(fn (mixed $workspace): bool => $workspace instanceof Workspace)
        ->values()
        ->all();

    if ($accessibleWorkspaces === [] && $user !== null) {
        $accessibleWorkspaces = $user->workspaces();
    }

    $shortcuts = [];

    if ($user !== null) {
        foreach ($accessibleWorkspaces as $portal) {
            if ($portal === $currentWorkspace) {
                continue;
            }

            $shortcuts[] = $portal;
        }
    }

    $canSwitchWorkspace = count($accessibleWorkspaces) > 1;

    $menuButtonClass = 'block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700';
    $sidebarButtonClass = 'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-white/90 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sand-200';
    $mobileButtonClass = 'flex min-h-11 w-full items-center justify-center rounded-lg border border-lamu-900 px-3 text-sm font-medium text-lamu-900 hover:bg-lamu-50';
@endphp

@if ($shortcuts !== [] || $canSwitchWorkspace)
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
            @if ($canSwitchWorkspace)
                <a href="{{ route('workspaces.index') }}" data-turbo-frame="modal" class="{{ $sidebarButtonClass }}">
                    <i class="fa-solid fa-grid-2 w-4 text-center text-sand-200" aria-hidden="true"></i>
                    <span>All workspaces</span>
                </a>
            @endif
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
            @if ($canSwitchWorkspace)
                <a href="{{ route('workspaces.index') }}" class="mt-3 inline-flex text-sm font-medium text-lamu-700 hover:underline">Switch workspace</a>
            @endif
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
            @if ($canSwitchWorkspace)
                <a href="{{ route('workspaces.index') }}" data-turbo-frame="modal" onclick="this.closest('details')?.removeAttribute('open')" id="switch-workspace" class="{{ $menuButtonClass }}">Switch workspace</a>
            @endif
        </div>
    @endif
@endif
