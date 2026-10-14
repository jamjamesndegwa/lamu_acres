<x-public-layout
    title="List Property — Lamu Acres"
    description="List your land on Lamu Acres. Apply for seller access, submit property details for staff review, and publish to the public directory when approved."
>
    <section id="overview" class="relative isolate overflow-hidden bg-lamu-900 text-white">
        <img
            src="{{ asset('images/hero-coast.jpg') }}"
            alt=""
            width="1600"
            height="900"
            decoding="async"
            class="absolute inset-0 h-full w-full object-cover opacity-35"
            aria-hidden="true"
        >
        <div class="absolute inset-0 bg-lamu-900/65" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:py-16">
            @include('account.applications.partials.breadcrumb', [
                'variant' => 'dark',
                'items' => [
                    ['label' => 'Home', 'href' => route('home')],
                    ['label' => 'List Property', 'current' => true],
                ],
            ])

            <p class="mt-6 text-sm font-medium uppercase tracking-wide text-gold-500">List Property</p>
            <h1 class="mt-2 max-w-3xl text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">List Your Land with LAMU ACRES</h1>
            <p class="mt-4 max-w-2xl text-lg text-sand-50">Landowners and authorized representatives apply for seller access, then submit property details for review. Signing in through a protected link returns you to that page after login; registering a new account does not automatically return you here.</p>

            <div class="mt-8">
                @include('public.list-property.partials.primary-action', ['primaryCta' => $primaryCta, 'tone' => 'dark', 'idPrefix' => 'hero'])
            </div>

            @include('public.list-property.partials.account-status')
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <x-public-section-nav
            label="List Property sections"
            :items="[
                ['href' => '#overview', 'label' => 'Overview'],
                ['href' => '#how-it-works', 'label' => 'How It Works'],
                ['href' => '#what-you-need', 'label' => 'What You Need'],
                ['href' => '#verification', 'label' => 'Verification'],
                ['href' => '#faqs', 'label' => 'FAQs'],
            ]"
            class="mb-10 scroll-mt-28"
        />

        <section id="how-it-works" class="scroll-mt-28">
            <h2 class="text-2xl font-semibold text-lamu-900">How it works</h2>
            <p class="mt-3 max-w-3xl text-stone-700">The landowner path is: account access → seller application (where required) → property submission → staff review → publication. Brokers marketing on behalf of sellers use <a href="{{ route('broker-partners') }}" class="font-medium text-lamu-800 hover:underline">Broker Partners</a> and choose sale or lease there—not on this page.</p>
            <div class="mt-4 max-w-3xl rounded-2xl border border-sand-200 bg-sand-50 px-5 py-4 text-sm text-stone-700">
                <p class="font-medium text-lamu-900">This page is not a property listing</p>
                <p class="mt-1">The seller application collects eligibility details only. Property fields, photos, and ownership documents are submitted later from your seller workspace after access is approved.</p>
            </div>
            <ol class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li class="rounded-2xl border border-sand-200 bg-white p-5">
                    <p class="text-sm font-medium text-gold-600">Step 1</p>
                    <p class="mt-2 font-semibold text-lamu-900">Sign in to Lamu Acres</p>
                    <p class="mt-2 text-sm text-stone-600">Use your account to apply and submit. Buyer access alone does not open the seller workspace.</p>
                </li>
                <li class="rounded-2xl border border-sand-200 bg-white p-5">
                    <p class="text-sm font-medium text-gold-600">Step 2</p>
                    <p class="mt-2 font-semibold text-lamu-900">Apply for seller access</p>
                    <p class="mt-2 text-sm text-stone-600">Submit organisation details (optional) and a short statement about your ownership or authority to list. Staff review the application.</p>
                </li>
                <li class="rounded-2xl border border-sand-200 bg-white p-5">
                    <p class="text-sm font-medium text-gold-600">Step 3</p>
                    <p class="mt-2 font-semibold text-lamu-900">Submit property details</p>
                    <p class="mt-2 text-sm text-stone-600">After approval, enter title, locality, acreage, and summary, then add photos and ownership documents from your seller workspace.</p>
                </li>
                <li class="rounded-2xl border border-sand-200 bg-white p-5">
                    <p class="text-sm font-medium text-gold-600">Step 4</p>
                    <p class="mt-2 font-semibold text-lamu-900">Staff listing review</p>
                    <p class="mt-2 text-sm text-stone-600">Respond to information requests while verification runs. Submission does not guarantee publication.</p>
                </li>
                <li class="rounded-2xl border border-sand-200 bg-white p-5 sm:col-span-2 lg:col-span-1">
                    <p class="text-sm font-medium text-gold-600">Step 5</p>
                    <p class="mt-2 font-semibold text-lamu-900">Publication</p>
                    <p class="mt-2 text-sm text-stone-600">Approved listings appear in the public directory while they remain available. Draft and rejected listings stay off the public site.</p>
                </li>
            </ol>
        </section>

        <section id="what-you-need" class="scroll-mt-28 mt-16">
            <h2 class="text-2xl font-semibold text-lamu-900">What you need</h2>
            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <h3 class="font-semibold text-lamu-900">Seller application (first)</h3>
                    <p class="mt-2 text-sm text-stone-600">Collected on the seller application form—before any property record exists.</p>
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-stone-700">
                        <li>Your account name and email (from login)</li>
                        <li>Organisation or holding name (optional)</li>
                        <li>Short statement describing ownership or authority to list</li>
                    </ul>
                </article>
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <h3 class="font-semibold text-lamu-900">Property submission (after approval)</h3>
                    <p class="mt-2 text-sm text-stone-600">Collected in the seller workspace when you submit a holding.</p>
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-stone-700">
                        <li>Title, locality, and acreage</li>
                        <li>Summary of the land</li>
                        <li>Ownership documents (PDF, JPG, or PNG up to 10&nbsp;MB each—stored privately)</li>
                        <li>Further authority or instruction details as staff request them</li>
                    </ul>
                </article>
            </div>
            <p class="mt-4 text-sm text-stone-600">Brokers submit sale or lease listings with photos, pricing, and marketing authority evidence through the broker workspace. See <a href="{{ route('broker-partners') }}" class="font-medium text-lamu-800 hover:underline">Broker Partners</a> for that path.</p>
        </section>

        <section id="verification" class="scroll-mt-28 mt-16">
            <h2 class="text-2xl font-semibold text-lamu-900">Verification and privacy</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <h3 class="font-semibold text-lamu-900">Review before publication</h3>
                    <p class="mt-2 text-sm text-stone-600">Seller applications and individual property submissions are reviewed by staff. Listings can be declined or returned for more information.</p>
                </article>
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <h3 class="font-semibold text-lamu-900">Generalized public locations</h3>
                    <p class="mt-2 text-sm text-stone-600">Published listings show county and locality. When a map is shown, it marks an approximate area—not a parcel boundary or restricted survey geometry.</p>
                </article>
                <article class="rounded-2xl border border-sand-200 bg-white p-5 sm:col-span-2">
                    <h3 class="font-semibold text-lamu-900">Controlled documents, contacts, and photos</h3>
                    <p class="mt-2 text-sm text-stone-600">Ownership uploads and seller contact fields are not published on listing pages. Public photo links serve resized gallery copies, not private originals. Buyers contact the Lamu Acres desk or use enquiry forms—not seller details on the listing.</p>
                </article>
            </div>
        </section>

        <section id="faqs" class="scroll-mt-28 mt-16">
            <h2 class="text-2xl font-semibold text-lamu-900">FAQs</h2>
            <div class="mt-6 space-y-4">
                <details class="rounded-2xl border border-sand-200 bg-white p-5">
                    <summary class="cursor-pointer font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Who can list land here?</summary>
                    <p class="mt-3 text-sm text-stone-600">Landowners and authorized representatives with approved seller workspace access. Brokers listing on behalf of sellers need broker workspace access instead.</p>
                </details>
                <details class="rounded-2xl border border-sand-200 bg-white p-5">
                    <summary class="cursor-pointer font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Can I list for sale and for lease?</summary>
                    <p class="mt-3 text-sm text-stone-600">Sale or lease is chosen when <a href="{{ route('broker-partners') }}" class="font-medium text-lamu-800 hover:underline">starting a broker listing</a>. The seller workspace does not ask for sale or lease on the initial property form; staff review covers commercial terms before publication.</p>
                </details>
                <details class="rounded-2xl border border-sand-200 bg-white p-5">
                    <summary class="cursor-pointer font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">What documents are required?</summary>
                    <p class="mt-3 text-sm text-stone-600">The seller application does not upload files. After approval, ownership documents are uploaded per property in the seller workspace (PDF, JPG, or PNG up to 10&nbsp;MB). Brokers provide marketing authority evidence with each broker listing submission.</p>
                </details>
                <details class="rounded-2xl border border-sand-200 bg-white p-5">
                    <summary class="cursor-pointer font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Can I edit a submission?</summary>
                    <p class="mt-3 text-sm text-stone-600">Open the property from your seller or broker workspace to add documents and respond to staff information requests while review is in progress.</p>
                </details>
                <details class="rounded-2xl border border-sand-200 bg-white p-5">
                    <summary class="cursor-pointer font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Are there listing fees?</summary>
                    <p class="mt-3 text-sm text-stone-600">No public listing fee is published on this site. The Investor Pass subscription is separate and does not replace listing or application review.</p>
                </details>
            </div>
        </section>

        <section class="mt-16 scroll-mt-28 rounded-3xl bg-lamu-900 px-6 py-10 text-white sm:px-10">
            <h2 class="text-center text-2xl font-semibold">Ready for the next step?</h2>
            <p class="mx-auto mt-3 max-w-xl text-center text-sm text-sand-100">{{ $primaryCta['helper'] }}</p>
            <div class="mt-6 flex justify-center">
                @include('public.list-property.partials.primary-action', ['primaryCta' => $primaryCta, 'tone' => 'dark', 'idPrefix' => 'footer'])
            </div>
        </section>
    </div>
</x-public-layout>
