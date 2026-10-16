<x-public-layout
    title="Broker Partners — Lamu Acres"
    description="Apply to list and manage coastal land on Lamu Acres as a broker partner. Staff review every application before workspace access is granted."
>
    <section id="overview" class="bg-lamu-900 text-white">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <p class="text-sm font-medium uppercase tracking-wide text-gold-500">Broker Partners</p>
            <h1 class="mt-2 max-w-3xl text-3xl font-semibold tracking-tight sm:text-4xl">Listings and enquiries for licensed brokers and agencies</h1>
            <p class="mt-4 max-w-2xl text-sand-50">The broker workspace is for professionals marketing land on behalf of sellers. Start your application by registering an account, or sign in if you already have one. Staff verifies your identity and professional standing before granting access.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('broker-partners.apply') }}" class="inline-flex rounded-full bg-white px-5 py-3 text-sm font-medium text-lamu-900">Apply as a Broker Partner</a>
                @auth
                    <a href="{{ route('account.applications.index') }}" class="inline-flex rounded-full border border-white/40 px-5 py-3 text-sm font-medium text-white">Your applications</a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex rounded-full border border-white/40 px-5 py-3 text-sm font-medium text-white">Login</a>
                @endauth
            </div>
            @if ($hasBrokerAccess)
                <p class="mt-6 max-w-2xl rounded-2xl bg-lamu-800/80 px-4 py-3 text-sm text-sand-100">
                    Your account already has broker workspace access.
                    <a href="{{ route('broker.listings.index') }}" class="font-medium text-white underline">Open your listings</a>.
                </p>
            @elseif ($application)
                <p class="mt-6 max-w-2xl rounded-2xl bg-lamu-800/80 px-4 py-3 text-sm text-sand-100">
                    Broker application status: <span class="font-medium text-white">{{ $application->status->label() }}</span>.
                    @if ($application->decision_reason)
                        {{ $application->decision_reason }}
                    @endif
                    @if ($application->status->isOpenForApplicant())
                        <a href="{{ route('account.applications.create', ['capability' => 'broker']) }}" data-turbo-frame="modal" class="ml-1 font-medium text-white underline">Continue application</a>
                    @endif
                </p>
            @endif
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <x-public-section-nav
            label="Broker Partners sections"
            :items="[
                ['href' => '#overview', 'label' => 'Overview'],
                ['href' => '#benefits', 'label' => 'Benefits'],
                ['href' => '#how-it-works', 'label' => 'How It Works'],
                ['href' => '#requirements', 'label' => 'Requirements'],
                ['href' => '#faqs', 'label' => 'FAQs'],
            ]"
            class="mb-10"
        />

        <section id="benefits" class="scroll-mt-28">
            <h2 class="text-2xl font-semibold text-lamu-900">Benefits</h2>
            <p class="mt-3 max-w-3xl text-stone-700">Approved partners use the broker workspace already built into Lamu Acres. Nothing here guarantees leads, commissions, or publication.</p>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <h3 class="font-semibold text-lamu-900">Listing submissions</h3>
                    <p class="mt-2 text-sm text-stone-600">Submit sale or lease listings with photos, pricing, locality, and evidence of marketing authority. Staff review each submission before publication.</p>
                </article>
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <h3 class="font-semibold text-lamu-900">Listing management</h3>
                    <p class="mt-2 text-sm text-stone-600">Track submitted properties, respond to staff information requests, and manage listing media from your workspace.</p>
                </article>
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <h3 class="font-semibold text-lamu-900">Buyer introductions</h3>
                    <p class="mt-2 text-sm text-stone-600">Record buyer introductions and supporting evidence so staff can review referral claims. Commission protection follows staff review, not submission alone.</p>
                </article>
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <h3 class="font-semibold text-lamu-900">Private seller details</h3>
                    <p class="mt-2 text-sm text-stone-600">Seller names and contacts stay out of the public directory. Published pages show location at county and locality level only.</p>
                </article>
            </div>
        </section>

        <section id="how-it-works" class="scroll-mt-28 mt-16">
            <h2 class="text-2xl font-semibold text-lamu-900">How it works</h2>
            <ol class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <li class="rounded-2xl border border-sand-200 p-5">
                    <p class="text-sm font-medium text-gold-600">Step 1</p>
                    <p class="mt-2 font-semibold text-lamu-900">Create or sign in</p>
                    <p class="mt-2 text-sm text-stone-600">Use your Lamu Acres account. Buyer access alone does not open the broker workspace.</p>
                </li>
                <li class="rounded-2xl border border-sand-200 p-5">
                    <p class="text-sm font-medium text-gold-600">Step 2</p>
                    <p class="mt-2 font-semibold text-lamu-900">Submit broker details</p>
                    <p class="mt-2 text-sm text-stone-600">Provide your organisation name (if applicable) and a short statement about your brokerage activity.</p>
                </li>
                <li class="rounded-2xl border border-sand-200 p-5">
                    <p class="text-sm font-medium text-gold-600">Step 3</p>
                    <p class="mt-2 font-semibold text-lamu-900">Staff verification</p>
                    <p class="mt-2 text-sm text-stone-600">The brokerage team reviews your application and may request more information before deciding.</p>
                </li>
                <li class="rounded-2xl border border-sand-200 p-5">
                    <p class="text-sm font-medium text-gold-600">Step 4</p>
                    <p class="mt-2 font-semibold text-lamu-900">Application outcome</p>
                    <p class="mt-2 text-sm text-stone-600">If approved, broker workspace permissions are attached to your account. If not, you receive the recorded decision reason.</p>
                </li>
            </ol>
        </section>

        <section id="requirements" class="scroll-mt-28 mt-16">
            <h2 class="text-2xl font-semibold text-lamu-900">Requirements</h2>
            <p class="mt-3 max-w-3xl text-stone-700">Match what the application form collects today. Additional evidence may be requested during review.</p>
            <ul class="mt-6 space-y-3 text-sm text-stone-700">
                <li class="flex gap-3 rounded-2xl border border-sand-200 bg-white p-4">
                    <span class="font-medium text-lamu-900">Identity &amp; brokerage profile</span>
                    <span class="text-stone-600">Active Lamu Acres account with organisation name optional for individuals.</span>
                </li>
                <li class="flex gap-3 rounded-2xl border border-sand-200 bg-white p-4">
                    <span class="font-medium text-lamu-900">Service focus</span>
                    <span class="text-stone-600">Your statement should describe the coastal areas and listing types you handle.</span>
                </li>
                <li class="flex gap-3 rounded-2xl border border-sand-200 bg-white p-4">
                    <span class="font-medium text-lamu-900">Marketing authority on listings</span>
                    <span class="text-stone-600">Each property submission requires evidence of authority to market that parcel. Staff verify before publication.</span>
                </li>
            </ul>

            <div class="mt-10 rounded-3xl bg-sand-100 px-6 py-8">
                <h3 class="text-lg font-semibold text-lamu-900">Inside the broker workspace</h3>
                <p class="mt-2 max-w-2xl text-sm text-stone-700">After approval you can submit listings, upload photos, and file buyer introductions. Enquiries from published listings are handled through the platform workflows already connected to your workspace.</p>
            </div>
        </section>

        <section id="faqs" class="scroll-mt-28 mt-16">
            <h2 class="text-2xl font-semibold text-lamu-900">FAQs</h2>
            <div class="mt-6 space-y-4">
                <details class="rounded-2xl border border-sand-200 bg-white p-5">
                    <summary class="cursor-pointer font-medium text-lamu-900">Who can apply?</summary>
                    <p class="mt-3 text-sm text-stone-600">Licensed brokers and agencies marketing land in Lamu. Landowners listing their own holdings should use the List Property path and apply for seller access instead.</p>
                </details>
                <details class="rounded-2xl border border-sand-200 bg-white p-5">
                    <summary class="cursor-pointer font-medium text-lamu-900">How long does verification take?</summary>
                    <p class="mt-3 text-sm text-stone-600">Timing depends on staff review volume. You can see status and any decision reason on your applications page.</p>
                </details>
                <details class="rounded-2xl border border-sand-200 bg-white p-5">
                    <summary class="cursor-pointer font-medium text-lamu-900">Can I publish immediately after approval?</summary>
                    <p class="mt-3 text-sm text-stone-600">No. Each listing still passes staff review and verification before it appears on the public directory.</p>
                </details>
                <details class="rounded-2xl border border-sand-200 bg-white p-5">
                    <summary class="cursor-pointer font-medium text-lamu-900">Are there listing fees?</summary>
                    <p class="mt-3 text-sm text-stone-600">Lamu Acres does not publish a separate broker listing fee on this page. Commercial terms, if any, are confirmed directly with staff.</p>
                </details>
            </div>
        </section>

        <section class="mt-16 rounded-3xl bg-lamu-800 px-6 py-10 text-center text-white sm:px-10">
            <h2 class="text-2xl font-semibold">Ready to apply?</h2>
            <p class="mx-auto mt-3 max-w-xl text-sm text-sand-100">Submitting an application does not grant broker permissions until staff approve it.</p>
            <a href="{{ route('broker-partners.apply') }}" class="mt-6 inline-flex rounded-full bg-white px-5 py-3 text-sm font-medium text-lamu-900">Apply as a Broker Partner</a>
        </section>
    </div>
</x-public-layout>
