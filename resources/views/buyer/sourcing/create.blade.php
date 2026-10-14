<x-crud-page title="New search" :close="route('buyer.sourcing.index')" size="xl">
    <p class="mt-3 max-w-3xl text-stone-700">Choose a place on the map, then record the size, budget, use, and what the land must have. Matching uses this request.</p>
    <x-step-list :steps="['Place the search on the map', 'Set size, budget, and use', 'Submit the request']" />
    @include('buyer.sourcing.partials.form', [
        'action' => route('buyer.sourcing.store'),
        'method' => 'POST',
        'confirm' => 'Submit this search?',
        'submit' => 'Submit search',
        'sourcingRequest' => null,
    ])
</x-crud-page>
