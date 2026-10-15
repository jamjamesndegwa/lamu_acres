<x-workspace-layout heading="List your land">
    <p class="mt-2 text-sm"><a class="font-medium text-lamu-800 hover:underline" href="{{ route('seller.properties.index') }}">Back to my properties</a></p>
    <x-step-list :steps="['Describe the land and your terms', 'Add photographs and private documents', 'Review and submit to staff', 'Staff verify before publishing']" />
    @include('seller.properties.partials.form', [
        'action' => route('seller.properties.store'),
        'method' => 'POST',
        'submitLabel' => 'Save draft',
    ])
</x-workspace-layout>
