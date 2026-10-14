@props(['label', 'href', 'detail', 'frame' => null])

<section {{ $attributes->merge(['class' => 'mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5']) }}>
    <h2 class="font-semibold text-lamu-900">Next step</h2>
    <p class="mt-2 text-sm text-stone-700">{{ $detail }}</p>
    <a href="{{ $href }}" @if ($frame) data-turbo-frame="{{ $frame }}" @endif class="mt-4 inline-block rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">{{ $label }}</a>
</section>
