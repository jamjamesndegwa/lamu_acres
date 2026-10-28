<x-workspace-layout heading="Find land for me">
    <div class="mt-4 max-w-4xl rounded-2xl border border-lamu-800 bg-lamu-900 px-5 py-4 text-white shadow-md">
        <p class="text-sm leading-relaxed text-white/90">Place a pin where you want land, set a search radius, then tell us your budget and requirements. Staff use your map centre and radius to match published listings by location.</p>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 max-w-4xl">
        <x-step-list :steps="['Drop a pin on the map', 'Set size, budget, and amenities', 'Send to the Lamu Acres team']" />
        <a href="{{ route('buyer.sourcing.index') }}" class="text-sm font-medium text-lamu-800 underline">View your searches</a>
    </div>
    @include('buyer.sourcing.partials.form', [
        'action' => route('buyer.sourcing.store'),
        'method' => 'POST',
        'confirm' => 'Send this land search request?',
        'submit' => 'Submit search request',
        'sourcingRequest' => null,
    ])
</x-workspace-layout>
