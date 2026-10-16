<x-capability-application-page
    title="Apply as a Broker Partner — Lamu Acres"
    description="Submit your broker partner application for Lamu Acres. Staff review every application before workspace access is granted."
    eyebrow="Broker Partners"
    heading="Apply as a Broker Partner"
    :compact="true"
    intro="Tell us about your brokerage activity. Submitting this form does not grant broker permissions until staff approve your application."
    :back-href="route('broker-partners')"
    back-label="Back to Broker Partners"
    :application="$application"
    :help-href="route('broker-partners').'#faqs'"
    help-label="Read the Broker Partners FAQs"
>
    <x-slot:breadcrumb>
        @include('account.applications.partials.breadcrumb', [
            'items' => [
                ['label' => 'Home', 'href' => route('home')],
                ['label' => 'Broker Partners', 'href' => route('broker-partners')],
                ['label' => 'Apply', 'current' => true],
            ],
        ])
    </x-slot:breadcrumb>

    <x-slot:form>
        @if (auth()->user()->email_verified_at === null && auth()->user()->phone_verified_at === null)
            <section class="mb-4 rounded-lg border border-sand-200 bg-white p-4">
                <h2 class="home-serif text-lg font-semibold text-lamu-900">Confirm your contact before approval</h2>
                <p class="mt-2 text-sm text-stone-600">Confirm {{ auth()->user()->email }} using the link sent to your inbox. You must open it while signed in to this account. Alternatively, confirm a phone number with a delivered code in your account Settings.</p>
                @if (\App\Notifications\AccountEmailChanged::canDeliverEmail())
                    <form method="POST" action="{{ route('account.email.verification.send') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Send email verification link</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-amber-900">Email verification is temporarily unavailable. Contact the desk for help; your application can still be submitted for review.</p>
                @endif
                @error('email_delivery')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </section>
        @endif
        <div class="rounded-lg border border-sand-200 bg-white p-4 shadow-sm">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">Application details</h2>
            <p class="mt-1 text-xs leading-relaxed text-stone-600">Provide your identity and professional credentials, then describe the areas and listing types you handle.</p>
            <div class="mt-4">
                @include('account.applications.partials.form', [
                    'capability' => $capability,
                    'application' => $application,
                    'submitLabel' => 'Submit application',
                ])
            </div>
        </div>
    </x-slot:form>
    <x-slot:guidance>
        <div class="space-y-4">
            <section class="rounded-lg border border-sand-200 bg-white p-4">
                <h2 class="home-serif text-lg font-semibold text-lamu-900">Before you submit</h2>
                <ul class="mt-3 list-disc space-y-2 pl-4 text-xs leading-relaxed text-stone-600">
                    <li>Use the legal name that appears on your credentials.</li>
                    <li>Provide your national ID or passport and current practising certificate.</li>
                    <li>Enter your EARB registration number and certificate expiry.</li>
                    <li>Include business documents if applying as a firm.</li>
                    <li>Upload PDF, JPG or PNG files up to 2 MB each.</li>
                </ul>
            </section>
            <section class="rounded-lg bg-lamu-900 p-4 text-white">
                <h2 class="home-serif text-lg font-semibold">What happens next?</h2>
                <p class="mt-2 text-xs leading-relaxed text-sand-50">Staff reviews your identity evidence and checks professional standing against authoritative EARB records. Uploading credentials does not automatically grant broker access.</p>
                <a href="{{ route('account.applications.index') }}" class="mt-3 inline-block text-xs font-semibold text-white underline">Track your application</a>
            </section>
        </div>
    </x-slot:guidance>
</x-capability-application-page>
