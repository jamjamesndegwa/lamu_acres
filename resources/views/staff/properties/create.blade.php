<x-workspace-layout heading="Register land">
    <p class="mt-2 text-sm"><a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.properties.index') }}">Back to property register</a></p>
    <x-step-list :steps="['Describe the land, owner and terms', 'Add photographs (optional now)', 'Save as draft and continue verification', 'Publish when checks are complete']" />
    @include('seller.properties.partials.form', [
        'action' => route('staff.properties.store'),
        'method' => 'POST',
        'submitLabel' => 'Save property draft',
        'staffEntry' => true,
        'marketingRole' => \App\Enums\MarketingRole::AuthorizedMarketer->value,
        'owners' => $owners,
    ])
</x-workspace-layout>
