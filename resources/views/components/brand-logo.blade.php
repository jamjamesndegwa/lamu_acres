@props([
    'tone' => 'light',
    'tagline' => false,
    'size' => 'md',
])

@php
    $onDark = $tone === 'dark';
    $isLarge = $size === 'lg';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span @class(['inline-flex shrink-0 items-center justify-center', 'rounded-md bg-white px-1 py-0.5' => $onDark])>
        <img src="{{ asset('images/logo.jpg') }}" alt="" width="359" height="250" @class(['w-auto object-contain', 'h-10' => $isLarge, 'h-8' => ! $isLarge])>
    </span>
    <span class="leading-none">
        <span @class([
            'home-serif block font-semibold tracking-[0.08em] uppercase',
            'text-xl' => $isLarge,
            'text-lg' => ! $isLarge,
            'text-white' => $onDark,
            'text-lamu-900' => ! $onDark,
        ])>Lamu Acres</span>
        @if ($tagline)
            <span @class([
                'mt-1 block text-[9px] tracking-[0.18em] uppercase',
                'text-sand-200' => $onDark,
                'text-stone-500' => ! $onDark,
            ])>Land · People · Opportunity</span>
        @endif
    </span>
</span>
