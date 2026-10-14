@php
    $route = $property->exists ? route('staff.properties.update', $property) : route('staff.properties.store');
@endphp

@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="{{ $property->exists ? 'Edit property' : 'Add property' }}" :close="$property->exists ? route('staff.properties.show', $property) : route('staff.properties.index')">
            @include('staff.properties.partials.fields')
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout :heading="$property->exists ? 'Edit '.$property->title : 'Add property'">
        @if ($property->exists)
            @include('staff.properties.partials.tabs')
        @endif
        <div class="mt-6 max-w-xl">@include('staff.properties.partials.fields')</div>
    </x-workspace-layout>
@endif
