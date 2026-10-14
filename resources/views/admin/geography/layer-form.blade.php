@php
    $route = $layer->exists
        ? route('admin.geography.layers.update', $layer)
        : route('admin.geography.layers.store');
@endphp

@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="{{ $layer->exists ? 'Edit map layer' : 'Add map layer' }}" :close="route('admin.geography.layers.index')" size="xl">
            @include('admin.geography.partials.layer-fields')
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="{{ $layer->exists ? 'Edit map layer' : 'Add map layer' }}">
        <x-slot:modal>
            <x-modal title="{{ $layer->exists ? 'Edit map layer' : 'Add map layer' }}" :close="route('admin.geography.layers.index')" size="xl">
                @include('admin.geography.partials.layer-fields')
            </x-modal>
        </x-slot:modal>
    </x-workspace-layout>
@endif
