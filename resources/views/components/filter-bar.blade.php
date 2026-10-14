@props(['action' => null])

@php
    $classes = $attributes->get('class') ?: 'flex flex-wrap items-end gap-3';
@endphp

<form
    method="GET"
    @if (filled($action)) action="{{ $action }}" @endif
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    {{ $slot }}
</form>
