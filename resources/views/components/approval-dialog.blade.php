@props([
    'title',
    'action',
    'method' => 'POST',
    'confirm' => 'Apply this decision?',
    'close' => null,
    'submit' => 'Confirm',
])

<x-modal :title="$title" :close="$close">
    <form method="POST" action="{{ $action }}" data-turbo-frame="_top" data-confirm="{{ $confirm }}" class="space-y-4">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        {{ $slot }}
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">{{ $submit }}</button>
    </form>
</x-modal>
