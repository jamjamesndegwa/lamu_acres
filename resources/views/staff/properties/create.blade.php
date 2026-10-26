<x-workspace-layout heading="Register land">
    <p class="mt-2 text-sm"><a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.properties.index') }}">Back to property register</a></p>
    <x-step-list :steps="['Complete every land detail (same as a seller submission)', 'Link the owner and upload photographs', 'Pick the map location', 'Register — listing goes live immediately']" />
    @include('seller.properties.partials.form', [
        'action' => route('staff.properties.store'),
        'method' => 'POST',
        'submitLabel' => 'Register and publish listing',
        'staffEntry' => true,
        'marketingRole' => \App\Enums\MarketingRole::AuthorizedMarketer->value,
        'owners' => $owners,
    ])
</x-workspace-layout>
