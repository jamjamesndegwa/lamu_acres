@props([
    'title',
    'heading' => null,
    'close' => null,
    'size' => 'lg',
])

@if (\App\Support\Turbo::wantsModal(request()))
    <turbo-frame id="modal">
        <x-modal :title="$title" :close="$close" :size="$size">
            {{ $slot }}
        </x-modal>
    </turbo-frame>
@elseif ($close)
    <x-workspace-layout :heading="$heading ?? $title">
        <x-slot:modal>
            <x-modal :title="$title" :close="$close" :size="$size">
                {{ $slot }}
            </x-modal>
        </x-slot:modal>
    </x-workspace-layout>
@else
    <x-workspace-layout :heading="$heading ?? $title">
        {{ $slot }}
    </x-workspace-layout>
@endif
