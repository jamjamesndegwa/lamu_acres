@props(['label', 'value', 'href' => null])

<div class="rounded-2xl border border-sand-200 bg-white p-5">
    <p class="text-sm text-stone-500">{{ $label }}</p>
    <p class="mt-2 text-2xl font-semibold text-lamu-900">
        @if ($href)
            <a href="{{ $href }}" class="hover:underline">{{ $value }}</a>
        @else
            {{ $value }}
        @endif
    </p>
</div>
