@props([
    'kind' => 'empty',
    'title' => null,
    'message',
])

@php
    $title = $title ?? match ($kind) {
        'error' => 'Something went wrong',
        'loading' => 'Loading',
        default => null,
    };
@endphp

<div role="status" {{ $attributes->merge(['class' => 'text-sm text-stone-600']) }}>
    @if ($title)
        <p class="font-medium text-lamu-900">{{ $title }}</p>
    @endif
    <p @class(['mt-1' => filled($title)])>{{ $message }}</p>
</div>
