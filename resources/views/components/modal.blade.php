@props([
    'title',
    'close' => null,
    'size' => 'lg',
])

<div class="fixed inset-0 z-50 flex items-end justify-center bg-stone-900/50 p-4 sm:items-center" data-modal-overlay>
    <button type="button" class="absolute inset-0 cursor-default" data-close-modal aria-label="Close dialog"></button>

    <div
        {{ $attributes->merge(['class' => 'relative z-10 max-h-[90vh] w-full overflow-y-auto rounded-2xl bg-white p-6 shadow-xl '.($size === 'xl' ? 'max-w-3xl' : 'max-w-lg')]) }}
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
    >
        <div class="flex items-start justify-between gap-4">
            <h2 id="modal-title" class="text-xl font-semibold text-lamu-900">{{ $title }}</h2>
            @if ($close)
                <a href="{{ $close }}" data-turbo-frame="_top" class="rounded-md px-2 py-1 text-sm text-stone-500 hover:bg-sand-50">Close</a>
            @else
                <button type="button" data-close-modal class="rounded-md px-2 py-1 text-sm text-stone-500 hover:bg-sand-50">Close</button>
            @endif
        </div>
        <div class="mt-5">
            {{ $slot }}
        </div>
    </div>
</div>
