@props(['title' => null, 'message', 'action' => null, 'href' => null])

<div role="status" {{ $attributes->merge(['class' => 'text-sm text-stone-600']) }}>
    @if ($title)
        <p class="font-medium text-lamu-900">{{ $title }}</p>
    @endif
    <p @class(['mt-1' => filled($title)])>{{ $message }}</p>
    @if (filled($action) && filled($href))
        <a href="{{ $href }}" class="mt-3 inline-block text-sm font-medium text-lamu-800 hover:underline">{{ $action }}</a>
    @endif
</div>
