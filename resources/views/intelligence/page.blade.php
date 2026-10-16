@php
    $compactResearch = request()->routeIs('intelligence.projects.index', 'intelligence.imagery.index', 'intelligence.planning.index', 'intelligence.prices', 'intelligence.reports.index');
@endphp
@if ($frame === 'workspace')
    <x-workspace-layout :heading="$heading">
        <p class="mt-3 max-w-3xl text-stone-700">{{ $description }}</p>
        @include('intelligence.partials.access')
        @include($partial)
    </x-workspace-layout>
@else
    <x-public-layout :title="$heading.' — Lamu Acres'" :description="$description">
        @if ($compactResearch)
            <section class="relative isolate overflow-hidden bg-lamu-900 text-white">
                <img src="{{ asset('images/explore_land_hero_section.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover object-[70%_50%]" fetchpriority="high">
                <div class="land-hero-fade absolute inset-0 -z-10" aria-hidden="true"></div>
                <div class="mx-auto flex min-h-[120px] w-full max-w-[1440px] flex-col justify-center px-4 py-4 sm:px-6 lg:w-[90%] lg:px-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gold-500">Lamu Acres · Investor Intelligence</p>
                    <h1 class="home-serif mt-1 text-2xl font-semibold leading-tight lg:text-[32px]">{{ $heading }}</h1>
                    <p class="mt-1 max-w-2xl text-sm text-sand-50">{{ request()->routeIs('intelligence.projects.index') ? 'Explore published infrastructure and development records by location, category and project status.' : $description }}</p>
                </div>
            </section>
        @endif
        <div @class([
            'mx-auto px-4 sm:px-6',
            'w-full max-w-[1440px] py-4 lg:w-[90%] lg:px-0' => $compactResearch,
            'max-w-6xl py-10' => ! $compactResearch,
        ])>
            @unless ($compactResearch)
            <h1 class="text-3xl font-semibold text-lamu-900">{{ $heading }}</h1>
            <p class="mt-3 max-w-3xl text-stone-700">{{ $description }}</p>
            @endunless
            <nav @class(['flex flex-wrap gap-2', 'rounded-lg border border-sand-200 bg-white p-2 text-xs' => $compactResearch, 'mt-6 text-sm' => ! $compactResearch]) aria-label="Research preview">
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
