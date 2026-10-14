<x-capability-application-page
    title="Apply as a Broker Partner — Lamu Acres"
    description="Submit your broker partner application for Lamu Acres. Staff review every application before workspace access is granted."
    eyebrow="Broker Partners"
    heading="Apply as a Broker Partner"
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
        <div class="rounded-2xl border border-sand-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-semibold text-lamu-900">Application details</h2>
            <p class="mt-2 text-sm text-stone-600">Provide your organisation name if you operate as an agency, and a short statement about the areas and listing types you handle.</p>
            <div class="mt-6">
                @include('account.applications.partials.form', [
                    'capability' => $capability,
                    'application' => $application,
                    'submitLabel' => 'Submit application',
                ])
            </div>
        </div>
    </x-slot:form>
</x-capability-application-page>
