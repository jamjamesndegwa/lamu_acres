@php
    $deskEmail = config('lamu.desk_email');
    $deskEmail = is_string($deskEmail) && filter_var($deskEmail, FILTER_VALIDATE_EMAIL) ? $deskEmail : null;
@endphp

<footer class="home-footer bg-lamu-900 text-sand-50">
    <div class="mx-auto grid w-full max-w-[1440px] gap-5 px-4 py-5 text-xs sm:grid-cols-2 lg:w-[85%] lg:grid-cols-5 lg:px-0">
        <div>
            <x-brand-logo tone="dark" />
            <p class="mt-1.5 text-sand-200">Published land listings and location guides for the Lamu coast.</p>
        </div>
        <nav aria-label="Properties">
            <p class="font-medium text-gold-500">Properties</p>
            <a class="mt-1.5 block hover:text-white" href="{{ route('land.index') }}">Published land</a>
            <a class="mt-1 block hover:text-white" href="{{ route('land.index', ['purpose' => 'sale']) }}">For sale</a>
            <a class="mt-1 block hover:text-white" href="{{ route('land.index', ['purpose' => 'lease']) }}">For lease</a>
            <a class="mt-1 block hover:text-white" href="{{ route('list-property') }}">List property</a>
        </nav>
        <nav aria-label="Investor intelligence">
            <p class="font-medium text-gold-500">Investor Intelligence</p>
            <a class="mt-1.5 block hover:text-white" href="{{ route('investor-intelligence.index') }}">Overview</a>
            <a class="mt-1 block hover:text-white" href="{{ route('intelligence.projects.index') }}">Developments</a>
            <a class="mt-1 block hover:text-white" href="{{ route('intelligence.imagery.index') }}">Imagery</a>
            <a class="mt-1 block hover:text-white" href="{{ route('intelligence.planning.index') }}">Planning</a>
            <a class="mt-1 block hover:text-white" href="{{ route('intelligence.prices') }}">Prices</a>
            <a class="mt-1 block hover:text-white" href="{{ route('intelligence.reports.index') }}">Reports</a>
        </nav>
        <nav aria-label="Brokers and company">
            <p class="font-medium text-gold-500">For Brokers</p>
            <a class="mt-1.5 block hover:text-white" href="{{ route('broker-partners') }}">Broker Partners</a>
            <p class="mt-3 font-medium text-gold-500">Company</p>
            <a class="mt-1.5 block hover:text-white" href="{{ route('home') }}">Home</a>
            <a class="mt-1 block hover:text-white" href="{{ route('login') }}">Login</a>
            @if ($deskEmail)
                <a class="mt-1 block hover:text-white" href="mailto:{{ $deskEmail }}">Contact</a>
            @endif
        </nav>
        <div>
            <p class="font-medium text-gold-500">Updates</p>
            <p class="mt-1.5 text-sand-200">New listings appear in the public directory. This site does not run a newsletter form.</p>
        </div>
    </div>
    <div class="border-t border-white/10">
        <p class="mx-auto w-full max-w-[1440px] px-4 py-2.5 text-[11px] text-sand-200 lg:w-[85%] lg:px-0">© {{ now()->year }} Lamu Acres</p>
    </div>
</footer>
