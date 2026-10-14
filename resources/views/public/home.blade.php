<x-public-layout
    title="Lamu Acres"
    description="Lamu Acres publishes land for sale and lease on the Lamu coast, with location guides and a separate investor research offer."
>
    <section class="relative isolate min-h-[32rem] overflow-hidden bg-lamu-900 text-white">
        <img
            src="{{ asset('images/hero-coast.jpg') }}"
            alt="Coastal shoreline, mangroves, and open water in Lamu, Kenya"
            width="1600"
            height="900"
            fetchpriority="high"
            decoding="async"
            class="absolute inset-0 h-full w-full object-cover"
        >
        <div class="absolute inset-0 bg-lamu-900/55"></div>
        <div class="relative mx-auto flex min-h-[32rem] max-w-6xl flex-col justify-end px-4 py-16 sm:px-6">
            <p class="text-sm font-medium uppercase tracking-wide text-sand-200">Lamu, Kenya</p>
            <h1 class="mt-3 max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">Managed land marketplace and investment intelligence</h1>
            <p class="mt-5 max-w-2xl text-lg text-sand-50">Read published land without an account. Only listings that have been published and are still available appear here.</p>
            <form method="GET" action="{{ route('land.index') }}" class="mt-8 flex max-w-2xl flex-col gap-3 rounded-2xl bg-white p-3 text-stone-900 shadow-lg sm:flex-row" role="search">
                <label class="sr-only" for="home-search">Location or property</label>
                <input id="home-search" name="q" type="search" placeholder="Search location or property" class="min-w-0 flex-1 rounded-xl border border-sand-200 px-4 py-3">
                <button type="submit" class="rounded-xl bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Search land</button>
            </form>
        </div>
    </section>

    <section id="featured" class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-2xl font-semibold text-lamu-900">Featured land</h2>
        @if ($featured->isEmpty())
            <p class="mt-4 text-stone-600">No featured land is published yet.</p>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($featured as $listing)
                    <x-listing-card :listing="$listing" />
                @endforeach
            </div>
        @endif
    </section>

    <section id="locations" class="bg-white">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="text-2xl font-semibold text-lamu-900">Explore Lamu by location</h2>
            @if ($counties->isEmpty())
                <p class="mt-4 text-stone-600">Location guides are not published yet.</p>
            @else
                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($counties as $county)
                        <article class="rounded-2xl border border-sand-200 p-5">
                            <h3 class="font-semibold text-lamu-900">
                                <a href="{{ route('land.index', $county) }}">{{ $county->name }} ({{ $county->available_listings_count }})</a>
                            </h3>
                            <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                                @foreach ($county->localities as $place)
                                    <li>
                                        <a class="text-lamu-800 hover:underline" href="{{ route('land.index', [$county, $place]) }}">{{ $place->name }} ({{ $place->available_listings_count }})</a>
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section id="find-land" class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="rounded-3xl bg-lamu-800 px-6 py-10 text-white sm:px-10">
            <p class="text-sm font-medium uppercase tracking-wide text-gold-500">Find Land for Me</p>
            <h2 class="mt-2 text-3xl font-semibold">Tell a buyer account what you are looking for</h2>
            <p class="mt-4 max-w-2xl text-sand-50">Create a buyer account to keep a search brief. This page does not store a request.</p>
            <a href="{{ route('register') }}" class="mt-6 inline-flex rounded-full bg-white px-5 py-3 text-sm font-medium text-lamu-900">Create a buyer account</a>
        </div>
    </section>

    <section id="intelligence" class="bg-sand-50">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <p class="text-sm font-medium uppercase tracking-wide text-gold-500">Premium Investor Intelligence</p>
            <h2 class="mt-2 text-3xl font-semibold text-lamu-900">Research stays separate from the public directory</h2>
            <p class="mt-4 max-w-2xl text-stone-700">An Investor Pass is a paid subscription. It does not publish private property records, and this page does not show research that has not been released.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('intelligence.projects.index') }}" class="inline-flex rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Published research previews</a>
                <a href="{{ route('register') }}" class="inline-flex rounded-full border border-lamu-900 px-5 py-3 text-sm font-medium text-lamu-900">Register to ask about a pass</a>
            </div>
        </div>
    </section>

    <section id="trust" class="bg-white">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="text-2xl font-semibold text-lamu-900">Verification and trust</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article class="rounded-2xl border border-sand-200 p-5">
                    <h3 class="font-semibold text-lamu-900">Published inventory only</h3>
                    <p class="mt-2 text-sm text-stone-600">A listing appears after it is published and while it is still available. Drafts stay off this site.</p>
                </article>
                <article class="rounded-2xl border border-sand-200 p-5">
                    <h3 class="font-semibold text-lamu-900">Private coordinates stay private</h3>
                    <p class="mt-2 text-sm text-stone-600">Public pages show the county and locality. They do not show GPS coordinates.</p>
                </article>
                <article class="rounded-2xl border border-sand-200 p-5">
                    <h3 class="font-semibold text-lamu-900">Landowner contacts stay private</h3>
                    <p class="mt-2 text-sm text-stone-600">Seller names and direct contacts are not part of the public listing.</p>
                </article>
            </div>
        </div>
    </section>

    <section id="partners" class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-2xl font-semibold text-lamu-900">Brokers and sellers</h2>
        <p class="mt-4 max-w-2xl text-stone-700">Apply for a broker or seller workspace, or submit land for review. An account does not by itself publish a listing or approve a commission.</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('broker-partners') }}" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Broker Partners</a>
            <a href="{{ route('list-property') }}" class="rounded-full border border-lamu-900 px-5 py-3 text-sm font-medium text-lamu-900">List Property</a>
        </div>
    </section>
</x-public-layout>
