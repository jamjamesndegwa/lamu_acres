<x-workspace-layout heading="New search">
    <p class="mt-3 max-w-3xl text-stone-700">Choose a place on the map, then record the size, budget, use, and what the land must have. Matching uses this request.</p>
    @include('buyer.sourcing.partials.form', [
        'action' => route('buyer.sourcing.store'),
        'method' => 'POST',
        'confirm' => 'Submit this search?',
        'submit' => 'Submit search',
        'sourcingRequest' => null,
    ])
</x-workspace-layout>
