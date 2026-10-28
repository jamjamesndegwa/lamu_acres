@props([
    'accountRole' => '',
    'workspace' => null,
    'workspaces' => [],
])

@php
    $deskEmail = config('lamu.desk_email');
    $deskEmail = is_string($deskEmail) && filter_var($deskEmail, FILTER_VALIDATE_EMAIL) ? $deskEmail : null;
    $findLandHref = route('find-land.index');
    $onHome = request()->routeIs('home');
    $intelligenceActive = request()->routeIs('investor-intelligence.*');
    $intelligenceHref = route('investor-intelligence.index');
    $searchHref = $onHome ? '#home-search' : route('land.index');
    $exploreActive = request()->routeIs('land.*');
    $brokerPartnersActive = request()->routeIs('broker-partners')
        || (request()->routeIs('account.applications.create') && request()->query('capability') === 'broker');
    $listPropertyActive = request()->routeIs('list-property')
        || (request()->routeIs('account.applications.create') && request()->query('capability') === 'seller');
    $linkClass = 'rounded-sm border-b-2 py-1 hover:text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800';
@endphp

<header class="home-header sticky top-0 z-30 border-b border-sand-200 bg-sand-50">
    <div class="mx-auto flex h-16 w-full max-w-[1440px] items-center justify-between gap-3 px-4 lg:w-[85%] lg:px-0">
        <a href="{{ route('home') }}" class="min-w-0 shrink-0 rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800" aria-label="Lamu Acres home">
            <x-brand-logo tagline />
        </a>
        <nav class="hidden items-center gap-x-4 text-[13px] text-stone-700 xl:flex" aria-label="Public">
            <details class="relative">
                <summary @class([
                    'cursor-pointer list-none',
                    $linkClass,
                    'border-gold-500 font-medium text-gold-500' => $exploreActive,
                    'border-transparent' => ! $exploreActive,
                ])>Explore Land</summary>
                <div class="absolute left-0 z-20 mt-2 w-52 rounded-lg border border-sand-200 bg-white p-2 text-sm shadow-sm">
                    <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="{{ route('land.index') }}" @if (request()->routeIs('land.index')) aria-current="page" @endif>All published land</a>
                    <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="{{ route('land.index', ['purpose' => 'lease']) }}">Lease land</a>
                </div>
            </details>
            <a @class([$linkClass, 'border-transparent']) href="{{ $findLandHref }}" @if (request()->routeIs('find-land.index')) aria-current="page" @endif>Find Land for Me</a>
            <a @class([$linkClass, 'border-gold-500 font-medium text-gold-500' => $intelligenceActive, 'border-transparent' => ! $intelligenceActive]) href="{{ $intelligenceHref }}" @if ($intelligenceActive) aria-current="page" @endif>Investor Intelligence</a>
            <a @class([$linkClass, 'border-gold-500 font-medium text-gold-500' => $brokerPartnersActive, 'border-transparent' => ! $brokerPartnersActive]) href="{{ route('broker-partners') }}" @if ($brokerPartnersActive) aria-current="page" @endif>Broker Partners</a>
            <a @class([$linkClass, 'border-gold-500 font-medium text-gold-500' => $listPropertyActive, 'border-transparent' => ! $listPropertyActive]) href="{{ route('list-property') }}" @if ($listPropertyActive) aria-current="page" @endif>List Property</a>
            @if ($deskEmail)
                <a @class([$linkClass, 'border-transparent']) href="mailto:{{ $deskEmail }}">Contact</a>
            @endif
        </nav>
        <div class="flex items-center gap-2">
            <a href="{{ $searchHref }}" class="inline-flex size-9 items-center justify-center rounded-md border border-sand-200 bg-white text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800" aria-label="Search published land">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            </a>
            @auth
                <div class="hidden xl:block"><x-account-menu :role="$accountRole" :workspace="$workspace" :workspaces="$workspaces" /></div>
            @else
                <a href="{{ route('login') }}" class="hidden h-9 items-center rounded-md border border-lamu-900 px-3 text-xs font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800 xl:inline-flex">Login / Account</a>
            @endauth
            <button type="button" data-public-menu-open aria-controls="public-mobile-menu" aria-expanded="false" aria-label="Open navigation menu" class="inline-flex size-10 items-center justify-center rounded-md border border-sand-200 bg-white text-lamu-900 xl:hidden">
                <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <dialog id="public-mobile-menu" class="public-mobile-menu" aria-labelledby="public-menu-title">
                <div class="flex items-center justify-between border-b border-sand-200 px-5 py-4">
                    <h2 id="public-menu-title" class="home-serif text-lg font-semibold text-lamu-900">Lamu Acres</h2>
                    <button type="button" data-public-menu-close class="inline-flex size-10 items-center justify-center rounded-full hover:bg-lamu-100" aria-label="Close navigation menu"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
                </div>
                <div class="border-b border-sand-200 bg-lamu-100/60 px-5 py-5">
                    @auth
                        <p class="text-xs text-stone-500">Signed in as</p>
                        <p class="mt-1 break-words text-base font-semibold text-lamu-900">{{ auth()->user()->name }}</p>
                    @else
                        <a href="{{ route('login') }}" class="flex min-h-11 items-center justify-center rounded-lg bg-lamu-900 px-4 text-sm font-medium text-white">Login</a>
                        <a href="{{ route('register') }}" class="mt-3 block text-center text-sm text-lamu-700 hover:underline">Create an account</a>
                    @endauth
                </div>
                @auth
                    <x-workspace-portal-switcher :current="$workspace ?? null" :workspaces="$workspaces ?? []" variant="mobile" />
                @endauth
                <nav class="public-mobile-links flex-1 overflow-y-auto px-3 py-4" aria-label="Mobile public navigation">
                    <a href="{{ route('home') }}" @if ($onHome) aria-current="page" @endif>Home</a>
                    <a href="{{ route('land.index') }}" @if ($exploreActive) aria-current="page" @endif>Explore Land</a>
                    <a href="{{ route('land.index', ['purpose' => 'lease']) }}">Lease Land</a>
                    <a href="{{ $findLandHref }}" @if (request()->routeIs('find-land.index')) aria-current="page" @endif>Find Land for Me</a>
                    <a href="{{ $intelligenceHref }}" @if ($intelligenceActive) aria-current="page" @endif>Investor Intelligence</a>
                    <a href="{{ route('broker-partners') }}" @if ($brokerPartnersActive) aria-current="page" @endif>Broker Partners</a>
                    <a href="{{ route('list-property') }}" @if ($listPropertyActive) aria-current="page" @endif>List Property</a>
                    @if ($deskEmail)
                        <a href="mailto:{{ $deskEmail }}">Contact</a>
                    @endif
                </nav>
                @auth
                    <form method="POST" action="{{ route('logout') }}" data-turbo="false" class="border-t border-sand-200 p-5">
                        @csrf
                        <button type="submit" class="min-h-11 w-full rounded-lg border border-lamu-900 text-sm font-medium text-lamu-900 hover:bg-lamu-100">Logout</button>
                    </form>
                @endauth
            </dialog>
        </div>
    </div>
</header>
