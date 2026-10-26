@php
    $current = $tab ?? (request()->routeIs('staff.properties.media') ? 'media' : 'overview');
    $items = [
        'overview' => ['Overview', route('staff.properties.show', $property)],
        'media' => ['Media', route('staff.properties.media', $property)],
        'owners' => ['Owners', route('staff.properties.show', [$property, 'tab' => 'owners'])],
        'verification' => ['Verification', route('staff.properties.show', [$property, 'tab' => 'verification'])],
        'marketing' => ['Marketing', route('staff.properties.show', [$property, 'tab' => 'marketing'])],
        'enquiries' => ['Enquiries', route('staff.properties.show', [$property, 'tab' => 'enquiries'])],
        'offers' => ['Offers', route('staff.properties.show', [$property, 'tab' => 'offers'])],
        'transactions' => ['Transactions', route('staff.properties.show', [$property, 'tab' => 'transactions'])],
        'history' => ['History', route('staff.properties.show', [$property, 'tab' => 'history'])],
    ];
@endphp
<nav class="mt-4 flex gap-1 overflow-x-auto border-b border-sand-200" aria-label="Property sections">
    @foreach ($items as $key => [$label, $href])
        <a href="{{ $href }}" @class([
            'shrink-0 border-b-2 px-3 py-2 text-sm',
            'border-lamu-900 font-medium text-lamu-900' => $current === $key,
            'border-transparent text-stone-600 hover:text-lamu-900' => $current !== $key,
        ])>{{ $label }}</a>
    @endforeach
</nav>
