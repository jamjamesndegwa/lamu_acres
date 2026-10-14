@props(['label'])

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full bg-sand-100 px-2.5 py-0.5 text-xs font-medium text-stone-700']) }}>{{ $label }}</span>
