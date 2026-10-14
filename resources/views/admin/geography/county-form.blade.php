@php
    $route = $county->exists ? route('admin.geography.counties.update', $county) : route('admin.geography.counties.store');
@endphp

@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="{{ $county->exists ? 'Edit county' : 'Add county' }}" :close="route('admin.geography.index')">
            @include('admin.geography.partials.county-fields', ['county' => $county, 'route' => $route])
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="County">
        <div class="mt-6 max-w-xl">
            @include('admin.geography.partials.county-fields', ['county' => $county, 'route' => $route])
        </div>
    </x-workspace-layout>
@endif
