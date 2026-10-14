@if ($frame === 'workspace')
    <x-workspace-layout :heading="$heading">
        <p class="mt-3 max-w-3xl text-stone-700">{{ $description }}</p>
        @include('intelligence.partials.access')
        @include($partial)
    </x-workspace-layout>
@else
    <x-public-layout :title="$heading.' — Lamu Acres'" :description="$description">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <h1 class="text-3xl font-semibold text-lamu-900">{{ $heading }}</h1>
            <p class="mt-3 max-w-3xl text-stone-700">{{ $description }}</p>
            <nav class="mt-6 flex flex-wrap gap-2 text-sm" aria-label="Research preview">
                @foreach ([
                    ['intelligence.projects.*', 'intelligence.projects.index', 'Development Tracker'],
                    ['intelligence.imagery.*', 'intelligence.imagery.index', 'Satellite & Aerial Imagery'],
                    ['intelligence.planning.*', 'intelligence.planning.index', 'Planning & Zoning'],
                    ['intelligence.prices', 'intelligence.prices', 'Land Price Observatory'],
                    ['intelligence.reports.*', 'intelligence.reports.index', 'Research Reports'],
                ] as [$pattern, $name, $label])
                    <a href="{{ route($name) }}" @class([
                        'rounded-full px-4 py-2 font-medium',
                        'bg-lamu-900 text-white' => request()->routeIs($pattern),
                        'border border-sand-200 bg-white text-lamu-900' => ! request()->routeIs($pattern),
                    ])>{{ $label }}</a>
                @endforeach
                @guest
                    <a href="{{ route('register') }}" class="rounded-full border border-lamu-900 px-4 py-2 font-medium text-lamu-900">Subscribe</a>
                @else
                    @if (auth()->user()->hasActiveInvestorPass())
                        <a href="{{ route('investor.dashboard') }}" class="rounded-full border border-lamu-900 px-4 py-2 font-medium text-lamu-900">Investor Dashboard</a>
                    @elseif (auth()->user()->hasPermission('workspace.buyer'))
                        <a href="{{ route('buyer.subscription.show') }}" class="rounded-full border border-lamu-900 px-4 py-2 font-medium text-lamu-900">Subscribe</a>
                    @elseif (auth()->user()->hasPermission('workspace.broker'))
                        <a href="{{ route('broker.subscription.show') }}" class="rounded-full border border-lamu-900 px-4 py-2 font-medium text-lamu-900">Subscribe</a>
                    @endif
                @endguest
            </nav>
            @include('intelligence.partials.access')
            @include($partial)
        </div>
    </x-public-layout>
@endif
