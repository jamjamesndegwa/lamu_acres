@props([
    'label',
    'items',
])

<nav {{ $attributes->merge(['class' => 'subnav-scroll -mx-4 border-b border-sand-200 bg-white px-4 sm:mx-0 sm:rounded-2xl sm:border sm:px-2']) }} aria-label="{{ $label }}">
    <ul class="flex gap-1 py-2 text-sm whitespace-nowrap">
        @foreach ($items as $item)
            <li>
                <a
                    href="{{ $item['href'] }}"
                    class="block rounded-full px-3 py-2 text-stone-600 hover:bg-sand-50 hover:text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800"
                >{{ $item['label'] }}</a>
            </li>
        @endforeach
    </ul>
</nav>
