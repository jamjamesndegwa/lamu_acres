<x-public-layout
    title="List Property — Lamu Acres"
    description="List your land on Lamu Acres. Apply for seller access, submit property details for staff review, and publish to the public directory when approved."
>
    <x-smooth-section-scroll />
    <section id="overview" class="relative isolate overflow-hidden bg-lamu-900 text-white">
        <img src="{{ asset('images/explore_land_hero_section.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover object-[70%_50%]" fetchpriority="high">
        <div class="land-hero-fade absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="mx-auto flex min-h-[120px] w-full max-w-[1440px] flex-col justify-center px-4 py-4 sm:px-6 lg:w-[90%] lg:px-0">
            <p class="text-xs font-medium uppercase tracking-wide text-gold-500">For landowners and authorized representatives</p>
            <h1 class="home-serif mt-1 text-2xl font-semibold leading-tight lg:text-[32px]">List Your Land with Lamu Acres</h1>
            <p class="mt-1 max-w-2xl text-sm text-sand-50">Apply as a seller, submit your property and work with our team through verification and publication.</p>
        </div>
    </section>

    <div class="mx-auto w-full max-w-[1440px] px-4 py-4 sm:px-6 lg:w-[90%] lg:px-0">
        <nav aria-label="List Property sections" class="overflow-x-auto rounded-lg border border-sand-200 bg-white">
            <ul class="flex gap-1 px-2 py-1 text-xs whitespace-nowrap">
                @foreach (['how-it-works' => 'How it works', 'what-you-need' => 'What you need', 'verification' => 'Verification', 'faqs' => 'FAQs'] as $section => $label)
                    <li><a href="#{{ $section }}" class="block rounded-md px-3 py-2 font-medium text-lamu-800 hover:bg-sand-50">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
        <div class="mt-4 grid items-start gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
        <section id="how-it-works" class="scroll-mt-28 rounded-lg border border-sand-200 bg-white p-4">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">How it works</h2>
            <p class="mt-2 text-xs leading-relaxed text-stone-600">The landowner path is: account access → seller application (where required) → property submission → staff review → publication. Brokers marketing on behalf of sellers use <a href="{{ route('broker-partners') }}" class="font-medium text-lamu-800 hover:underline">Broker Partners</a> and choose sale or lease there—not on this page.</p>
            <div class="mt-3 rounded-lg border border-sand-200 bg-sand-50 p-3 text-xs leading-relaxed text-stone-700">
                <p class="font-medium text-lamu-900">This page is not a property listing</p>
                <p class="mt-1">The seller application collects eligibility details only. Property fields, photos, and ownership documents are submitted later from your seller workspace after access is approved.</p>
            </div>
            <ol class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <li class="rounded-lg border border-sand-200 bg-white p-3">
                    <p class="text-xs font-medium text-gold-600">Step 1</p>
                    <p class="mt-1 text-sm font-semibold text-lamu-900">Sign in to Lamu Acres</p>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">Use your account to apply and submit. Buyer access alone does not open the seller workspace.</p>
                </li>
                <li class="rounded-lg border border-sand-200 bg-white p-3">
                    <p class="text-xs font-medium text-gold-600">Step 2</p>
                    <p class="mt-1 text-sm font-semibold text-lamu-900">Apply for seller access</p>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">Submit organisation details (optional) and a short statement about your ownership or authority to list. Staff review the application.</p>
                </li>
                <li class="rounded-lg border border-sand-200 bg-white p-3">
                    <p class="text-xs font-medium text-gold-600">Step 3</p>
                    <p class="mt-1 text-sm font-semibold text-lamu-900">Submit property details</p>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">After approval, enter title, locality, acreage, and summary, then add photos and ownership documents from your seller workspace.</p>
                </li>
                <li class="rounded-lg border border-sand-200 bg-white p-3">
                    <p class="text-xs font-medium text-gold-600">Step 4</p>
                    <p class="mt-1 text-sm font-semibold text-lamu-900">Staff listing review</p>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">Respond to information requests while verification runs. Submission does not guarantee publication.</p>
                </li>
                <li class="rounded-lg border border-sand-200 bg-white p-3 sm:col-span-2 lg:col-span-1">
                    <p class="text-xs font-medium text-gold-600">Step 5</p>
                    <p class="mt-1 text-sm font-semibold text-lamu-900">Publication</p>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">Approved listings appear in the public directory while they remain available. Draft and rejected listings stay off the public site.</p>
                </li>
            </ol>
        </section>
        <section id="verification" class="scroll-mt-28 rounded-lg border border-sand-200 bg-white p-4">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">Verification and privacy</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <article class="rounded-lg border border-sand-200 bg-white p-3">
                    <h3 class="text-sm font-semibold text-lamu-900">Review before publication</h3>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">Seller applications and individual property submissions are reviewed by staff. Listings can be declined or returned for more information.</p>
                </article>
                <article class="rounded-lg border border-sand-200 bg-white p-3">
                    <h3 class="text-sm font-semibold text-lamu-900">Generalized public locations</h3>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">Published listings show county and locality. When a map is shown, it marks an approximate area—not a parcel boundary or restricted survey geometry.</p>
                </article>
                <article class="rounded-lg border border-sand-200 bg-white p-3 sm:col-span-2">
                    <h3 class="text-sm font-semibold text-lamu-900">Controlled documents, contacts, and photos</h3>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">Ownership uploads and seller contact fields are not published on listing pages. Public photo links serve resized gallery copies, not private originals. Buyers contact the Lamu Acres desk or use enquiry forms—not seller details on the listing.</p>
                </article>
            </div>
        </section>
        <section id="faqs" class="scroll-mt-28 rounded-lg border border-sand-200 bg-white p-4">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">FAQs</h2>
            <div class="mt-3 space-y-2">
                <details class="rounded-lg border border-sand-200 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Who can list land here?</summary>
                    <p class="mt-2 text-xs leading-relaxed text-stone-600">Landowners and authorized representatives with approved seller workspace access. Brokers listing on behalf of sellers need broker workspace access instead.</p>
                </details>
                <details class="rounded-lg border border-sand-200 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Can I list for sale and for lease?</summary>
                    <p class="mt-2 text-xs leading-relaxed text-stone-600">Sale or lease is chosen when <a href="{{ route('broker-partners') }}" class="font-medium text-lamu-800 hover:underline">starting a broker listing</a>. The seller workspace does not ask for sale or lease on the initial property form; staff review covers commercial terms before publication.</p>
                </details>
                <details class="rounded-lg border border-sand-200 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">What documents are required?</summary>
                    <p class="mt-2 text-xs leading-relaxed text-stone-600">The seller application collects private identity and applicable representative evidence. After approval, ownership documents are uploaded per property in the seller workspace (PDF, JPG, or PNG up to 10&nbsp;MB). Brokers provide marketing authority evidence with each broker listing submission.</p>
                </details>
                <details class="rounded-lg border border-sand-200 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Can I edit a submission?</summary>
                    <p class="mt-2 text-xs leading-relaxed text-stone-600">Open the property from your seller or broker workspace to add documents and respond to staff information requests while review is in progress.</p>
                </details>
                <details class="rounded-lg border border-sand-200 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Are there listing fees?</summary>
                    <p class="mt-2 text-xs leading-relaxed text-stone-600">No. Lamu Acres does not charge sellers or brokers a fee to submit or publish approved land listings. Revenue comes from transaction-specific sale commissions and optional Investor Pass subscriptions—not from listing payments.</p>
                </details>
            </div>
        </section>
            </div>
            <aside class="space-y-4">
        <section id="what-you-need" class="scroll-mt-28 rounded-lg border border-sand-200 bg-white p-4">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">What you need</h2>
            <div class="mt-3 space-y-3">
                <article class="rounded-lg border border-sand-200 bg-white p-3">
                    <h3 class="text-sm font-semibold text-lamu-900">Seller application (first)</h3>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">Collected on the seller application form—before any property record exists.</p>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-xs leading-relaxed text-stone-700">
                        <li>Your legal name, account contact and applicant type</li>
                        <li>National ID or passport evidence</li>
                        <li>Representative authority and company documents, where applicable</li>
                        <li>Organisation or holding name (optional)</li>
                        <li>Short statement describing ownership or authority to list</li>
                    </ul>
                </article>
                <article class="rounded-lg border border-sand-200 bg-white p-3">
                    <h3 class="text-sm font-semibold text-lamu-900">Property submission (after approval)</h3>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">Collected in the seller workspace when you submit a holding.</p>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-xs leading-relaxed text-stone-700">
                        <li>Title, locality, and acreage</li>
                        <li>Summary of the land</li>
                        <li>Ownership documents (PDF, JPG, or PNG up to 10&nbsp;MB each—stored privately)</li>
                        <li>Further authority or instruction details as staff request them</li>
                    </ul>
                </article>
            </div>
            <p class="mt-4 text-sm text-stone-600">Brokers submit sale or lease listings with photos, pricing, and marketing authority evidence through the broker workspace. See <a href="{{ route('broker-partners') }}" class="font-medium text-lamu-800 hover:underline">Broker Partners</a> for that path.</p>
        </section>
                <section class="rounded-lg bg-lamu-900 p-4 text-white">
                    <h2 class="home-serif text-lg font-semibold">Ready for the next step?</h2>
                    <div class="mt-3">
                        @include('public.list-property.partials.primary-action', ['primaryCta' => $primaryCta, 'tone' => 'dark', 'idPrefix' => 'sidebar'])
                    </div>
                </section>
                @if ($hasBrokerAccess || $hasSellerAccess || $sellerApplication || $brokerApplication || $inProgress->isNotEmpty())
                    <section class="rounded-lg bg-lamu-900 p-4 text-white" aria-label="Your listing progress">
                        @include('public.list-property.partials.account-status')
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-public-layout>
