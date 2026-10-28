@php
    $checkoutLabel = $checkoutReady && ($mpesaAmountKes ?? null)
        ? 'KES '.number_format($mpesaAmountKes)
        : null;
@endphp
<x-public-layout
    title="Investor Intelligence — Lamu Acres"
    description="Explore land investment research, infrastructure developments, location intelligence, price observations and planning information for the Lamu coast. Investor Pass unlocks deeper due diligence."
>
    <x-smooth-section-scroll />
    <section class="relative isolate overflow-hidden bg-lamu-900 text-white">
        <img src="{{ asset('images/investor_intelligence.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover object-right" fetchpriority="high">
        <div class="land-hero-fade absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="mx-auto flex min-h-[220px] w-full max-w-[1440px] flex-col justify-center px-4 py-10 sm:px-6 lg:min-h-[280px] lg:w-[90%] lg:px-0">
            <div class="max-w-2xl">
                <p class="text-xs font-medium uppercase tracking-wide text-gold-500">Lamu Acres · Investor Intelligence</p>
                <h1 class="home-serif mt-2 text-2xl font-semibold leading-tight sm:text-3xl lg:text-[36px]">Invest in Land with Better Information.</h1>
                <p class="mt-3 text-sm leading-relaxed text-sand-50 sm:text-base">Explore land investment research, infrastructure developments, location intelligence, price observations and planning information. Go beyond property listings and make better-informed land decisions.</p>
                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <a href="#investor-pass" class="inline-flex min-h-11 items-center justify-center rounded-md bg-gold-500 px-5 text-sm font-semibold text-white shadow-sm hover:bg-gold-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Explore Investor Pass</a>
                    <a href="{{ route('intelligence.projects.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-white/70 bg-white/10 px-5 text-sm font-semibold text-white backdrop-blur-sm hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Browse Public Research</a>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 lg:w-[90%] lg:px-0">
        <nav aria-label="Investor Intelligence sections" class="overflow-x-auto rounded-lg border border-sand-200 bg-white">
            <ul class="flex gap-1 px-2 py-1 text-xs whitespace-nowrap">
                @foreach ([
                    'why-research' => 'Why research land',
                    'features' => 'Features',
                    'previews' => 'Published research',
                    'access' => 'Public vs Pass',
                    'how-it-works' => 'How it works',
                    'investor-pass' => 'Pricing',
                    'faqs' => 'FAQs',
                ] as $section => $label)
                    <li><a href="#{{ $section }}" class="block rounded-md px-3 py-2 font-medium text-lamu-800 hover:bg-sand-50">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        <section id="why-research" class="scroll-mt-28 mt-6 rounded-lg border border-sand-200 bg-white p-5 sm:p-6">
            <h2 class="home-serif text-xl font-semibold text-lamu-900">Research land before you commit</h2>
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-stone-600">Land in Lamu is shaped by infrastructure plans, access routes, planning controls and market asking prices. Lamu Acres publishes verified research so you can compare locations, read public summaries and—when subscribed—go deeper with authorized analysis.</p>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'Infrastructure developments' => 'Track documented projects and milestones that may affect access and value.',
                    'Land asking prices' => 'Review published asking-price observations—not the same as completed sale deeds.',
                    'Location context' => 'Understand county and locality guides alongside development activity.',
                    'Planning restrictions' => 'Read official planning records where Lamu Acres has published them.',
                    'Historical imagery' => 'Compare dated imagery when licences allow public redistribution.',
                    'Independent due diligence' => 'Use research alongside your own lawyer, surveyor, valuer and official searches.',
                ] as $title => $description)
                    <li class="rounded-md border border-sand-200 bg-sand-50 p-3">
                        <h3 class="text-sm font-semibold text-lamu-900">{{ $title }}</h3>
                        <p class="mt-1 text-xs leading-relaxed text-stone-600">{{ $description }}</p>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-stone-500">Investor Pass is a research subscription. It does not replace professional advisers or guarantee any investment outcome.</p>
        </section>

        <section id="features" class="scroll-mt-28 mt-6">
            <h2 class="home-serif text-xl font-semibold text-lamu-900">What you can explore</h2>
            <p class="mt-1 max-w-2xl text-sm text-stone-600">Public previews are free. Investor Pass opens eligible premium analysis when content is published.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    [
                        'title' => 'Infrastructure Tracker',
                        'body' => 'Research documented developments and project milestones from published records.',
                        'route' => 'intelligence.projects.index',
                        'label' => 'View developments',
                    ],
                    [
                        'title' => 'Land Price Intelligence',
                        'body' => 'Compare asking-price observations and samples from published listings. These are asking prices, not verified completed-sale prices.',
                        'route' => 'intelligence.prices',
                        'label' => 'View price context',
                    ],
                    [
                        'title' => 'Satellite Intelligence',
                        'body' => 'Browse dated before-and-after imagery comparisons where redistribution is permitted. Coverage depends on published scenes.',
                        'route' => 'intelligence.imagery.index',
                        'label' => 'View imagery',
                    ],
                    [
                        'title' => 'Planning & Zoning',
                        'body' => 'Read official planning records and development restrictions that Lamu Acres has verified and published.',
                        'route' => 'intelligence.planning.index',
                        'label' => 'View planning records',
                    ],
                    [
                        'title' => 'Exclusive Research Reports',
                        'body' => 'Public summaries introduce deeper subscriber reports with citations, analysis and permitted downloads where released.',
                        'route' => 'intelligence.reports.index',
                        'label' => 'View report summaries',
                    ],
                    [
                        'title' => 'Investor Watchlists & Alerts',
                        'body' => 'Active Investor Pass subscribers can follow localities and infrastructure projects and receive notifications when relevant research is published.',
                        'route' => null,
                        'label' => null,
                        'subscriberOnly' => true,
                    ],
                ] as $feature)
                    <article class="flex flex-col rounded-lg border border-sand-200 bg-white p-4 shadow-sm">
                        <h3 class="home-serif text-base font-semibold text-lamu-900">{{ $feature['title'] }}</h3>
                        <p class="mt-2 flex-1 text-xs leading-relaxed text-stone-600">{{ $feature['body'] }}</p>
                        @if (! empty($feature['subscriberOnly']))
                            <a href="#investor-pass" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-md border border-lamu-900 px-3 text-xs font-semibold text-lamu-900 hover:bg-lamu-50">{{ $subscriptionCta['label'] }}</a>
                        @else
                            <a href="{{ route($feature['route']) }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-md bg-lamu-900 px-3 text-xs font-semibold text-white hover:bg-lamu-800">{{ $feature['label'] }}</a>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section id="previews" class="scroll-mt-28 mt-6 space-y-6">
            <div>
                <h2 class="home-serif text-xl font-semibold text-lamu-900">Latest published research</h2>
                <p class="mt-1 text-sm text-stone-600">Real records currently released for public preview. Premium notes and downloads stay in the Investor workspace.</p>
            </div>

            @include('public.partials.investor-intelligence-preview-block', [
                'heading' => 'Infrastructure developments',
                'empty' => 'No infrastructure projects are published yet.',
                'browseRoute' => 'intelligence.projects.index',
                'browseLabel' => 'Browse Development Tracker',
                'items' => $latestProjects,
                'type' => 'project',
            ])

            @include('public.partials.investor-intelligence-preview-block', [
                'heading' => 'Research reports',
                'empty' => 'No research reports are published yet.',
                'browseRoute' => 'intelligence.reports.index',
                'browseLabel' => 'Browse report summaries',
                'items' => $latestReports,
                'type' => 'report',
            ])

            @include('public.partials.investor-intelligence-preview-block', [
                'heading' => 'Imagery comparisons',
                'empty' => 'No imagery comparisons are published yet.',
                'browseRoute' => 'intelligence.imagery.index',
                'browseLabel' => 'Browse satellite intelligence',
                'items' => $latestComparisons,
                'type' => 'imagery',
            ])
        </section>

        <section id="access" class="scroll-mt-28 mt-6 grid gap-4 lg:grid-cols-2">
            <article class="rounded-lg border border-sand-200 bg-white p-5">
                <h2 class="home-serif text-lg font-semibold text-lamu-900">Public access</h2>
                <ul class="mt-3 list-disc space-y-2 pl-4 text-xs leading-relaxed text-stone-700">
                    <li>Browse published land listings on the public directory.</li>
                    <li>View public research summaries and preview pages.</li>
                    <li>Explore basic published project, planning and price context.</li>
                    <li>Discover Investor Pass benefits on this page.</li>
                </ul>
            </article>
            <article class="rounded-lg border border-lamu-800 bg-lamu-900 p-5 text-white">
                <h2 class="home-serif text-lg font-semibold">Investor Pass subscription</h2>
                <ul class="mt-3 list-disc space-y-2 pl-4 text-xs leading-relaxed text-sand-50">
                    <li>Full eligible research analysis where published.</li>
                    <li>Deeper infrastructure and location intelligence in the Investor workspace.</li>
                    <li>Premium planning and price observations when released.</li>
                    <li>Authorized research downloads where permitted.</li>
                    <li>Investment Opportunities with location context.</li>
                    <li>Personal watchlists and relevant research alerts.</li>
                    <li>Research archive of delivered publications.</li>
                </ul>
                <p class="mt-3 text-[11px] text-sand-200">Benefits depend on verified content being published. Lamu Acres does not guarantee a fixed number of releases.</p>
            </article>
        </section>

        <section id="how-it-works" class="scroll-mt-28 mt-6 rounded-lg border border-sand-200 bg-white p-5 sm:p-6">
            <h2 class="home-serif text-xl font-semibold text-lamu-900">How Investor Pass works</h2>
            <p class="mt-1 text-sm text-stone-600">Investor Pass is a research subscription—not ownership of land or an investment fund.</p>
            <ol class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    'Create an account or sign in' => 'Use your existing Lamu Acres login. Buyers and brokers subscribe from their workspace; one account covers every role you hold.',
                    'Choose Investor Pass' => 'Open the subscription page from your buyer or broker workspace, or after registering as a buyer.',
                    'Pay with M-Pesa STK Push' => 'When a KES checkout amount is configured, pay through the existing M-Pesa prompt. Premium research stays closed until payment is verified.',
                    'Access premium research' => 'After verification, open the Investor workspace for eligible published analysis, opportunities and watchlists.',
                ] as $title => $description)
                    <li class="flex gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-lamu-900 text-xs font-semibold text-white">{{ $loop->iteration }}</span>
                        <div>
                            <h3 class="text-sm font-semibold text-lamu-900">{{ $title }}</h3>
                            <p class="mt-1 text-xs leading-relaxed text-stone-600">{{ $description }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <section id="investor-pass" class="scroll-mt-28 mt-6">
            <div class="rounded-2xl border border-lamu-800 bg-lamu-900 p-6 text-white shadow-md sm:p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-gold-500">Subscription</p>
                        <h2 class="home-serif mt-1 text-2xl font-semibold">{{ $plan->name }}</h2>
                        <p class="mt-2 text-lg font-medium">{{ $plan->amountLabel() }}</p>
                        <p class="mt-1 text-sm text-sand-100">Billed every {{ $plan->interval }}</p>
                        @if ($checkoutLabel)
                            <p class="mt-2 text-sm text-white">M-Pesa checkout: <span class="font-semibold">{{ $checkoutLabel }}</span></p>
                            @if ($plan->checkout_policy)
                                <p class="mt-1 text-xs text-sand-200">{{ $plan->checkout_policy }}</p>
                            @endif
                        @else
                            <p class="mt-2 text-sm text-amber-100">Online M-Pesa checkout is not available until a payable KES amount is configured.</p>
                        @endif
                        <p class="mt-3 text-sm leading-relaxed text-sand-50">{{ $plan->terms }}</p>
                    </div>
                    <div class="flex w-full flex-col gap-3 lg:max-w-xs">
                        <a href="{{ $subscriptionCta['url'] }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-gold-500 px-4 text-sm font-semibold text-white hover:bg-gold-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">{{ $subscriptionCta['label'] }}</a>
                        @if ($checkoutReady && $mpesaConfigured)
                            <p class="text-xs text-sand-200">Payment is completed on the subscription page with M-Pesa STK Push—not on this landing page.</p>
                        @elseif (! $checkoutReady)
                            <p class="text-xs text-sand-200">No payable KES amount has been set. Subscription requests may still be available from your workspace when staff enable them.</p>
                        @endif
                        @guest
                            <p class="text-xs text-sand-200">Already have an account? <a href="{{ route('login') }}" class="font-medium text-white underline">Sign in</a> to subscribe.</p>
                        @endguest
                    </div>
                </div>
            </div>
        </section>

        <section id="faqs" class="scroll-mt-28 mt-6 rounded-lg border border-sand-200 bg-white p-5 sm:p-6">
            <h2 class="home-serif text-xl font-semibold text-lamu-900">Frequently asked questions</h2>
            <div class="mt-3 divide-y divide-sand-200">
                @foreach ([
                    'What is Investor Pass?' => 'Investor Pass is a Lamu Acres research subscription. It unlocks the Investor workspace, premium analysis where published, investment opportunities with location context, watchlists and your research archive.',
                    'What information is included?' => 'Public visitors see published summaries on the intelligence preview pages. Subscribers with an active pass see eligible premium fields, downloads and workspace tools for content that staff have released.',
                    'Is Investor Pass the same as buying land?' => 'No. It is access to research and workspace tools. Buying or leasing land still happens through published listings and your own due diligence.',
                    'How often is research updated?' => 'Research is updated when verified content is published. Lamu Acres does not promise daily or weekly releases.',
                    'Can I cancel?' => 'Yes. Cancelling ends premium access immediately—the Investor workspace and premium research close right away; access does not continue until a billing period ends.',
                    'What happens when my subscription expires?' => 'Premium research and the Investor workspace close. Renew from your buyer or broker subscription page with M-Pesa when checkout is configured.',
                    'How do I pay?' => 'When configured, pay through M-Pesa STK Push on the subscription page in your buyer or broker workspace after staff or the payment provider confirms the amount.',
                    'Does Lamu Acres guarantee price appreciation?' => 'No. Asking-price observations and research are for information only. Land values can fall as well as rise.',
                ] as $question => $answer)
                    <details class="py-3">
                        <summary class="cursor-pointer text-sm font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">{{ $question }}</summary>
                        <p class="mt-2 text-xs leading-relaxed text-stone-600">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    </div>
</x-public-layout>
