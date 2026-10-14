@php
    $route = $property->exists ? route('staff.properties.update', $property) : route('staff.properties.store');
@endphp

@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="{{ $property->exists ? 'Edit property' : 'Add property' }}" :close="route('staff.properties.index')">
            @include('staff.properties.partials.fields')
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="Property">
        <div class="mt-6 max-w-xl">@include('staff.properties.partials.fields')</div>
    </x-workspace-layout>
@endif
