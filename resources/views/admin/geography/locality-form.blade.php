@php
    $route = $locality->exists
        ? route('admin.geography.localities.update', $locality)
        : route('admin.geography.localities.store', $county);
@endphp

@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="{{ $locality->exists ? 'Edit place' : 'Add place' }}" :close="route('admin.geography.counties.show', $county)">
            @include('admin.geography.partials.locality-fields')
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="{{ $locality->exists ? 'Edit place' : 'Add place' }}">
        <x-slot:modal>
            <x-modal title="{{ $locality->exists ? 'Edit place' : 'Add place' }}" :close="route('admin.geography.counties.show', $county)" size="xl">
                @include('admin.geography.partials.locality-fields')
            </x-modal>
        </x-slot:modal>
    </x-workspace-layout>
@endif
