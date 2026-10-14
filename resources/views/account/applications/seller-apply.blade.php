<x-capability-application-page
    title="Apply to List Your Property — Lamu Acres"
    description="Apply for seller workspace access on Lamu Acres before submitting a property. Staff review every application."
    eyebrow="List Property"
    heading="Apply to List Your Property"
    intro="This application is for seller workspace access—not a property listing. After staff review your eligibility, you can submit land details, photos, and ownership documents from your seller workspace."
    hero-tone="light"
    :back-href="route('list-property')"
    back-label="Back to List Property"
    :application="$application"
    :help-href="route('list-property').'#faqs'"
    help-label="Read the List Property FAQs"
>
    <x-slot:breadcrumb>
        @include('account.applications.partials.breadcrumb', [
            'items' => [
                ['label' => 'Home', 'href' => route('home')],
                ['label' => 'List Property', 'href' => route('list-property')],
                ['label' => 'Seller Application', 'current' => true],
            ],
        ])
    </x-slot:breadcrumb>

    <x-slot:form>
        <div class="rounded-2xl border border-sand-200 bg-white p-6 shadow-sm sm:p-8">
            @include('account.applications.partials.seller-form', [
                'capability' => $capability,
                'application' => $application,
            ])
        </div>
    </x-slot:form>

    <x-slot:guidance>
        <div class="space-y-4">
            <div class="rounded-2xl border border-sand-200 bg-sand-50 p-5">
                <h2 class="text-sm font-semibold text-lamu-900">Prepare for listing later</h2>
                <p class="mt-2 text-sm text-stone-600">After seller access is granted, property submission collects:</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-stone-700">
                    <li>Title, locality, and acreage</li>
                    <li>A summary of the holding</li>
                    <li>Ownership documents (stored privately, not on the public site)</li>
                    <li>Marketing authority where applicable</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-lamu-900">What happens next</h2>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-stone-700">
                    <li>Staff review this seller application.</li>
                    <li>You receive a recorded decision on your applications page.</li>
                    <li>If approved, open <a href="{{ route('seller.properties.create') }}" class="font-medium text-lamu-800 hover:underline">Submit property</a> in the seller workspace.</li>
                    <li>Each property still passes verification before publication.</li>
                </ol>
            </div>
            <p class="text-sm text-stone-600">
                Marketing land as a broker?
                <a href="{{ route('broker-partners') }}" class="font-medium text-lamu-800 hover:underline">See Broker Partners</a>.
            </p>
        </div>
    </x-slot:guidance>
</x-capability-application-page>
