@props([
    'items',
    'variant' => 'light',
])

@php
    $onDark = $variant === 'dark';
@endphp

<nav @class(['text-sm', 'text-sand-200' => $onDark, 'text-stone-600' => ! $onDark]) aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-1">
        @foreach ($items as $index => $item)
            @if ($index > 0)
                <li aria-hidden="true" @class(['text-sand-400' => $onDark, 'text-stone-400' => ! $onDark])>/</li>
            @endif
            <li @if ($item['current'] ?? false) @class(['font-medium', 'text-white' => $onDark, 'text-lamu-900' => ! $onDark]) aria-current="page" @endif>
                @if ($item['current'] ?? false)
                    {{ $item['label'] }}
                @else
                    <a href="{{ $item['href'] }}" @class(['hover:underline', 'text-white' => $onDark, 'text-lamu-800' => ! $onDark])>{{ $item['label'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
