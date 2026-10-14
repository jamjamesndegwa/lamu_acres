@props([
    'items' => [],
    'empty' => 'No document check has been recorded.',
])

<ul class="mt-3 space-y-1 text-sm">
    @forelse ($items as $item)
        <li>{{ $item['label'] }} · {{ $item['state'] }}@if (! empty($item['note'])) · {{ $item['note'] }}@endif</li>
    @empty
        <li class="text-stone-600">{{ $empty }}</li>
    @endforelse
</ul>
