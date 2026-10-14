@php
    $route = $property->exists ? route('staff.properties.update', $property) : route('staff.properties.store');
@endphp

@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="{{ $property->exists ? 'Edit property' : 'Add property' }}" :close="$property->exists ? route('staff.properties.show', $property) : route('staff.properties.index')" size="xl">
            @include('staff.properties.partials.fields')
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout :heading="$property->exists ? 'Edit '.$property->title : 'Add property'">
        <x-slot:modal>
            <x-modal title="{{ $property->exists ? 'Edit property' : 'Add property' }}" :close="$property->exists ? route('staff.properties.show', $property) : route('staff.properties.index')" size="xl">
                @include('staff.properties.partials.fields')
            </x-modal>
        </x-slot:modal>
    </x-workspace-layout>
@endif
