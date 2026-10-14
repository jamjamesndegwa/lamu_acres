@php
    $route = $feature->exists
        ? route('admin.geography.infrastructure.update', $feature)
        : route('admin.geography.infrastructure.store');
@endphp

@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="{{ $feature->exists ? 'Edit infrastructure reference' : 'Add infrastructure reference' }}" :close="route('admin.geography.infrastructure.index')" size="xl">
            @include('admin.geography.partials.infrastructure-fields')
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="{{ $feature->exists ? 'Edit infrastructure reference' : 'Add infrastructure reference' }}">
        <x-slot:modal>
            <x-modal title="{{ $feature->exists ? 'Edit infrastructure reference' : 'Add infrastructure reference' }}" :close="route('admin.geography.infrastructure.index')" size="xl">
                @include('admin.geography.partials.infrastructure-fields')
            </x-modal>
        </x-slot:modal>
    </x-workspace-layout>
@endif
