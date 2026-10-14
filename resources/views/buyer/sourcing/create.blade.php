<x-workspace-layout heading="New search">
    <p class="mt-3 max-w-3xl text-stone-700">Use a place name, a map centre, or both. A drawn polygon is optional.</p>
    @include('buyer.sourcing.partials.form', [
        'action' => route('buyer.sourcing.store'),
        'method' => 'POST',
        'confirm' => 'Submit this search?',
        'submit' => 'Submit search',
        'sourcingRequest' => null,
    ])
</x-workspace-layout>
