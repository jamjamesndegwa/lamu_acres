@php
    $termLabels = \App\Models\Property::listingTermLabels();
    $terms = collect($property->listing_terms ?? [])->filter(fn ($value) => filled($value));
@endphp
<section class="mt-6 max-w-4xl">
    <h2 class="font-semibold text-lamu-900">Listing details</h2>
    <dl class="mt-3 grid gap-4 rounded-2xl border border-sand-200 bg-white p-5 text-sm sm:grid-cols-2">
        <div><dt class="text-stone-500">Purpose</dt><dd>{{ $property->purpose?->label() ?? 'Not recorded' }}</dd></div>
        <div><dt class="text-stone-500">{{ $property->isLease() ? 'Asking rent' : 'Asking price' }}</dt><dd>{{ $property->formattedPrice() ?? 'Not recorded' }}</dd></div>
        <div><dt class="text-stone-500">{{ $property->isLease() ? 'Rent basis' : 'Price basis' }}</dt><dd>{{ $property->priceBasis()->label() }}@if ($property->price_negotiable) · Negotiable @endif</dd></div>
        <div><dt class="text-stone-500">Available from</dt><dd>{{ $property->available_from?->format('j M Y') ?? 'Not stated' }}</dd></div>
        @if ($property->isLease())
            <div><dt class="text-stone-500">Lease duration</dt><dd>{{ $property->lease_min_months ? $property->lease_min_months.' months minimum' : 'Minimum not stated' }}@if ($property->lease_max_months) · {{ $property->lease_max_months }} months maximum @endif</dd></div>
        @endif
        <div><dt class="text-stone-500">Land reference</dt><dd>{{ $property->parcel_identifier ?? 'Not recorded' }}@if ($property->parcel_reference_type) ({{ $property->parcel_reference_type->label() }})@endif</dd></div>
        <div><dt class="text-stone-500">Survey, RIM or deed plan</dt><dd>{{ $property->survey_reference ?? 'Not recorded' }}</dd></div>
        <div><dt class="text-stone-500">Registered acreage</dt><dd>{{ $property->acreage !== null ? number_format((float) $property->acreage, 2).' acres' : 'Not recorded' }}</dd></div>
        <div><dt class="text-stone-500">Acreage offered</dt><dd>{{ $property->offered_acreage !== null ? number_format((float) $property->offered_acreage, 2).' acres (portion)' : 'Whole parcel' }}</dd></div>
        <div><dt class="text-stone-500">Minimum portion</dt><dd>{{ $property->minimum_acreage !== null ? number_format((float) $property->minimum_acreage, 2).' acres' : 'Not stated' }}</dd></div>
        <div><dt class="text-stone-500">Subdivision</dt><dd>{{ $property->subdivision_status?->label() ?? 'Not stated' }}</dd></div>
        <div><dt class="text-stone-500">Boundaries</dt><dd>{{ $property->boundary_status?->label() ?? 'Not stated' }}</dd></div>
        <div><dt class="text-stone-500">Tenure</dt><dd>{{ $property->tenure?->label() ?? 'Not stated' }}</dd></div>
        <div><dt class="text-stone-500">Ownership</dt><dd>{{ $property->ownership_structure?->label() ?? 'Not stated' }}</dd></div>
        <div><dt class="text-stone-500">Occupation</dt><dd>{{ $property->occupation_status?->label() ?? 'Not stated' }}</dd></div>
        <div><dt class="text-stone-500">Road access</dt><dd>{{ $property->road_access?->label() ?? 'Unknown' }}</dd></div>
        <div><dt class="text-stone-500">Nearby landmark</dt><dd>{{ $property->landmark ?? 'Not stated' }}</dd></div>
        <div><dt class="text-stone-500">Restricted GPS (staff only)</dt><dd>{{ $property->restricted_latitude !== null ? $property->restricted_latitude.', '.$property->restricted_longitude : 'Not supplied' }}</dd></div>
        @if ($property->access_description)
            <div class="sm:col-span-2"><dt class="text-stone-500">Access description</dt><dd>{{ $property->access_description }}</dd></div>
        @endif
        @foreach ($terms as $key => $value)
            <div class="sm:col-span-2"><dt class="text-stone-500">{{ $termLabels[$key] ?? $key }}</dt><dd>{{ $value }}</dd></div>
        @endforeach
        @if ($property->submitted_at)
            <div><dt class="text-stone-500">Submitted for review</dt><dd>{{ $property->submitted_at->format('j M Y H:i') }}</dd></div>
        @endif
    </dl>
</section>
