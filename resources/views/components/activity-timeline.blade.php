@props([
    'items' => [],
    'empty' => 'No activity has been recorded.',
])

<ol class="mt-3 space-y-3">
    @forelse ($items as $item)
        <li class="border-l-2 border-sand-200 pl-3 text-sm text-stone-700">
            @if (! empty($item['when']))
                <p class="text-xs text-stone-500">{{ $item['when'] }}</p>
            @endif
            <p>{{ $item['text'] }}</p>
        </li>
    @empty
        <li><x-state :message="$empty" /></li>
    @endforelse
</ol>
