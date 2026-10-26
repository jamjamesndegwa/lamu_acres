<x-public-layout
    title="Broker Partners — Lamu Acres"
    description="Apply to list and manage coastal land on Lamu Acres as a broker partner. Staff review every application before workspace access is granted."
>
    <x-smooth-section-scroll />
    <section id="overview" class="relative isolate overflow-hidden bg-lamu-900 text-white">
        @guest
            <img src="{{ asset('images/explore_land_hero_section.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover object-[70%_50%]" fetchpriority="high">
            <div class="land-hero-fade absolute inset-0 -z-10" aria-hidden="true"></div>
        @endguest
        <div class="mx-auto flex min-h-[120px] w-full max-w-[1440px] flex-col justify-center px-4 py-4 sm:px-6 lg:w-[90%] lg:px-0">
            <div class="max-w-2xl">
                <p class="text-xs font-medium uppercase tracking-wide text-gold-500">Partner with Lamu Acres</p>
                <h1 class="home-serif mt-1 text-2xl font-semibold leading-tight lg:text-[32px]">Broker Partners</h1>
                <p class="mt-1 text-sm text-sand-50">List land, manage enquiries and introduce buyers as an approved broker.</p>
            </div>
        </div>
    </section>

    <div class="mx-auto w-full max-w-[1440px] px-4 py-4 sm:px-6 lg:w-[90%] lg:px-0">
        <nav aria-label="Broker Partners sections" class="overflow-x-auto rounded-lg border border-sand-200 bg-white">
            <ul class="flex gap-1 px-2 py-1 text-xs whitespace-nowrap">
                @foreach (['benefits' => 'Partner benefits', 'how-it-works' => 'How to join', 'requirements' => 'Requirements', 'faqs' => 'FAQs'] as $section => $label)
                    <li><a href="#{{ $section }}" class="block rounded-md px-3 py-2 font-medium text-lamu-800 hover:bg-sand-50">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        @if ($hasBrokerAccess)
            <div class="mt-4 rounded-lg border border-sand-200 bg-white p-3 text-sm text-lamu-900">
                Your broker workspace is ready. <a href="{{ route('broker.listings.index') }}" class="font-semibold underline">Open your listings</a>.
            </div>
        @elseif ($application)
            <div class="mt-4 rounded-lg border border-sand-200 bg-white p-3 text-sm text-lamu-900">
                Application status: <span class="font-semibold">{{ $application->status->label() }}</span>.
                @if ($application->decision_reason)<p class="mt-1 text-xs text-stone-600">{{ $application->decision_reason }}</p>@endif
                @if ($application->status->isOpenForApplicant())
                    <a href="{{ route('account.applications.create', ['capability' => 'broker']) }}" class="mt-2 inline-block text-xs font-semibold underline">Continue application</a>
                @endif
            </div>
        @endif

        <div class="mt-4 grid items-start gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <section id="benefits" class="scroll-mt-28 rounded-lg border border-sand-200 bg-white p-4">
                    <h2 class="home-serif text-lg font-semibold text-lamu-900">Built for licensed brokers and agencies</h2>
                    <p class="mt-1 text-xs leading-relaxed text-stone-600">A single workspace for professionals marketing land on behalf of sellers, with staff review throughout.</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            'Submit land for sale or lease' => 'Add property details, photos, pricing and marketing authority for staff review.',
                            'Manage your listings' => 'Track progress, update media and respond to staff requests from your workspace.',
                            'Introduce buyers' => 'Record introductions and supporting evidence for referral and commission review.',
                            'Protect private information' => 'Seller contacts and restricted property coordinates stay off the public directory.',
                        ] as $title => $description)
                            <article class="rounded-md border border-sand-200 bg-sand-50 p-3">
                                <h3 class="text-sm font-semibold text-lamu-900">{{ $title }}</h3>
                                <p class="mt-1 text-xs leading-relaxed text-stone-600">{{ $description }}</p>
                            </article>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-stone-500">Partner approval does not guarantee leads, commissions or listing publication.</p>
                </section>

                <section id="how-it-works" class="scroll-mt-28 rounded-lg border border-sand-200 bg-white p-4">
                    <h2 class="home-serif text-lg font-semibold text-lamu-900">How to join</h2>
                    <ol class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            'Create your account' => 'Register as a broker, or sign in to continue your application.',
                            'Provide your credentials' => 'Submit your legal name, identity evidence, EARB number and current practising certificate.',
                            'Complete staff verification' => 'Staff checks identity and professional standing and may request further information.',
                            'Start after approval' => 'Access your broker workspace once approved. Every property still needs its own authority and publication review.',
                        ] as $title => $description)
                            <li class="flex gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-lamu-900 text-xs font-semibold text-white">{{ $loop->iteration }}</span>
                                <div><h3 class="text-sm font-semibold text-lamu-900">{{ $title }}</h3><p class="mt-1 text-xs leading-relaxed text-stone-600">{{ $description }}</p></div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section id="faqs" class="scroll-mt-28 rounded-lg border border-sand-200 bg-white p-4">
                    <h2 class="home-serif text-lg font-semibold text-lamu-900">Common questions</h2>
                    <div class="mt-2 divide-y divide-sand-200">
                        @foreach ([
                            'Who can apply?' => 'Licensed brokers and agencies marketing land on behalf of sellers. Owners selling their own land should use List Property and apply as a seller.',
                            'How long does verification take?' => 'Timing depends on staff review volume. Track your status and any requests through Your applications.',
                            'Can I publish immediately after approval?' => 'Each property still requires marketing authority, verification and staff approval before publication.',
                            'Are there listing fees?' => 'No. Broker listings are submitted and published without a listing fee. Commissions are agreed per completed sale; Investor Pass is separate.',
                        ] as $question => $answer)
                            <details class="py-3"><summary class="cursor-pointer text-sm font-medium text-lamu-900">{{ $question }}</summary><p class="mt-2 text-xs leading-relaxed text-stone-600">{{ $answer }}</p></details>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside id="requirements" class="scroll-mt-28 space-y-4">
                <section class="rounded-lg border border-sand-200 bg-white p-4">
                    <h2 class="home-serif text-lg font-semibold text-lamu-900">Before you apply</h2>
                    <p class="mt-1 text-xs text-stone-600">Have these ready for your application:</p>
                    <ul class="mt-3 list-disc space-y-2 pl-4 text-xs leading-relaxed text-stone-700">
                        <li>Legal name and a verified email address or phone number.</li>
                        <li>National ID or passport evidence.</li>
                        <li>EARB registration number.</li>
                        <li>Current practising certificate and expiry date.</li>
                        <li>Business documents and organisation details, if applying as a firm.</li>
                        <li>A short statement about your brokerage activity.</li>
                    </ul>
                    <p class="mt-3 border-t border-sand-200 pt-3 text-xs leading-relaxed text-stone-600">Evidence is private. Staff verifies your credentials before granting access. Each listing also requires authority to market the property.</p>
                </section>
                <section class="rounded-lg bg-lamu-900 p-4 text-white">
                    <h2 class="home-serif text-lg font-semibold">Ready to become a partner?</h2>
                    <p class="mt-2 text-xs leading-relaxed text-sand-50">Create an account and continue straight to your broker application.</p>
                    <a href="{{ route('broker-partners.apply') }}" class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-md bg-white px-3 py-2 text-xs font-semibold text-lamu-900">Apply as a Broker Partner</a>
                    <a href="{{ route('list-property') }}" class="mt-3 block text-xs font-medium text-sand-50 underline">Own the land? Apply as a seller</a>
                </section>
            </aside>
        </div>
    </div>
</x-public-layout>
