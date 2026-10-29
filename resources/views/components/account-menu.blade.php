@props([
    'role' => '',
    'workspace' => null,
    'workspaces' => [],
    'compact' => false,
])

@php
    $user = auth()->user();
    $parts = array_values(array_filter(preg_split('/\s+/', trim((string) $user->name)) ?: []));
    $initials = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
@endphp

<details class="relative">
    <summary @class([
        'flex cursor-pointer list-none items-center rounded-full text-sm font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700',
        'gap-0 p-0.5' => $compact,
        'gap-2 py-1 pl-1 pr-3' => ! $compact,
    ]) aria-label="Account menu">
        @if ($user->avatarUrl())
            <img src="{{ $user->avatarUrl() }}" alt="" @class(['rounded-full object-cover', 'h-8 w-8' => $compact, 'h-9 w-9' => ! $compact])>
        @else
            <span @class(['flex items-center justify-center rounded-full bg-lamu-900 font-medium text-white', 'h-8 w-8 text-[10px]' => $compact, 'h-9 w-9 text-xs' => ! $compact]) aria-hidden="true">{{ $initials }}</span>
        @endif
        <span @class(['truncate', 'sr-only' => $compact, 'max-w-[9rem]' => ! $compact])>{{ $user->name }}</span>
    </summary>
    <div class="absolute right-0 z-30 mt-2 w-[min(18rem,calc(100vw-1.5rem))] rounded-xl border border-sand-200 bg-white p-3 shadow-sm sm:w-72">
        <div class="flex items-center gap-3 px-1">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="h-14 w-14 rounded-full object-cover">
            @else
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-lamu-900 text-sm font-medium text-white" aria-hidden="true">{{ $initials }}</span>
            @endif
            <div class="min-w-0">
                <p class="truncate font-medium text-lamu-900">{{ $user->name }}</p>
                @if ($role !== '')
                    <p class="truncate text-sm text-stone-500">{{ $role }}</p>
                @endif
            </div>
        </div>

        <x-workspace-portal-switcher :current="$workspace" :workspaces="$workspaces" variant="menu" />

        <div class="mt-3 space-y-1 border-t border-sand-200 pt-2">
            @can('create', \App\Models\CapabilityApplication::class)
                <a href="{{ route('account.applications.index') }}" data-turbo-frame="_top" class="block rounded-lg px-3 py-2 text-sm hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Seller &amp; broker applications</a>
            @endcan
            @if ($workspace)
                @php($settingsRoute = $workspace->value === 'buyer' ? 'buyer.profile' : $workspace->value.'.profile')
                <a href="{{ route($settingsRoute) }}" @if ($workspace->value !== 'buyer') data-turbo-frame="modal" @endif class="block rounded-lg px-3 py-2 text-sm hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Profile</a>
            @else
                <a href="{{ route('workspaces.index') }}" data-turbo-frame="_top" class="block rounded-lg px-3 py-2 text-sm hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Account &amp; workspaces</a>
            @endif
            @if (count($workspaces) > 1 && $workspace === null)
                <a id="switch-workspace" href="{{ route('workspaces.index') }}" data-turbo-frame="modal" onclick="this.closest('details')?.removeAttribute('open')" class="block rounded-lg px-3 py-2 text-sm hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Switch Workspace</a>
            @endif
            <form method="POST" action="{{ route('logout') }}" data-turbo="false" data-confirm="Log out of Lamu Acres?" class="pt-1">
                @csrf
                <button type="submit" class="w-full rounded-full border border-lamu-900 px-3 py-2 text-sm font-medium text-lamu-900 hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Logout</button>
            </form>
        </div>
    </div>
</details>
