@php
    $query = array_merge($window->query(), $filters->query());
@endphp

<form method="get" action="{{ route(request()->route()->getName()) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
    @foreach ($window->query() as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <label class="text-sm text-stone-600">
        County ID
        <input type="number" name="county_id" value="{{ $filters->countyId }}" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2" min="1">
    </label>
    <label class="text-sm text-stone-600">
        Locality ID
        <input type="number" name="locality_id" value="{{ $filters->localityId }}" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2" min="1">
    </label>
    <label class="text-sm text-stone-600">
        Assigned staff ID
        <input type="number" name="assigned_to" value="{{ $filters->assignedTo }}" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2" min="1">
    </label>
    <label class="text-sm text-stone-600">
        Broker ID
        <input type="number" name="broker_id" value="{{ $filters->brokerId }}" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2" min="1">
    </label>
    <label class="text-sm text-stone-600">
        Property ID
        <input type="number" name="property_id" value="{{ $filters->propertyId }}" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2" min="1">
    </label>
    <div class="flex items-end gap-2">
        <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Apply filters</button>
        <a href="{{ route(request()->route()->getName(), $window->query()) }}" class="rounded-full bg-white px-4 py-2 text-sm font-medium text-lamu-900 ring-1 ring-sand-200">Clear</a>
    </div>
</form>
