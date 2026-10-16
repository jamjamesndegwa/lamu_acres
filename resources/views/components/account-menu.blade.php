@props([
    'role' => '',
    'workspace' => null,
    'workspaces' => [],
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
    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full py-1 pl-1 pr-3 text-sm font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700" aria-label="Account menu">
        @if ($user->avatarUrl())
            <img src="{{ $user->avatarUrl() }}" alt="" class="h-9 w-9 rounded-full object-cover">
        @else
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-lamu-900 text-xs font-medium text-white" aria-hidden="true">{{ $initials }}</span>
        @endif
        <span>{{ $user->name }}</span>
    </summary>
    <div class="absolute right-0 z-30 mt-2 w-72 rounded-xl border border-sand-200 bg-white p-3 shadow-sm">
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

        <div class="mt-3 space-y-1 border-t border-sand-200 pt-2">
            @if ($workspace?->value === 'buyer')
                <a href="{{ route('buyer.dashboard') }}" data-turbo-frame="_top" onclick="this.closest('details')?.removeAttribute('open')" class="block rounded-lg px-3 py-2 text-sm hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Buyer panel</a>
            @endif
            @can('create', \App\Models\CapabilityApplication::class)
                <a href="{{ route('account.applications.index') }}" data-turbo-frame="_top" class="block rounded-lg px-3 py-2 text-sm hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Seller &amp; broker applications</a>
            @endcan
            @if ($workspace)
                @php($settingsRoute = $workspace->value === 'buyer' ? 'buyer.profile' : $workspace->value.'.profile')
                <a href="{{ route($settingsRoute) }}" @if ($workspace->value !== 'buyer') data-turbo-frame="modal" @endif class="block rounded-lg px-3 py-2 text-sm hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Profile</a>
            @endif
            @if (count($workspaces) > 1)
                <a id="switch-workspace" href="{{ route('workspaces.index') }}" data-turbo-frame="modal" onclick="this.closest('details')?.removeAttribute('open')" class="block rounded-lg px-3 py-2 text-sm hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Switch Workspace</a>
            @endif
            <form method="POST" action="{{ route('logout') }}" data-turbo="false" data-confirm="Log out of Lamu Acres?" class="pt-1">
                @csrf
                <button type="submit" class="w-full rounded-full border border-lamu-900 px-3 py-2 text-sm font-medium text-lamu-900 hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Logout</button>
            </form>
        </div>
    </div>
</details>
