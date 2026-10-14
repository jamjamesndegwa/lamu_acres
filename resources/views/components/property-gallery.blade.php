@props([
    'photos' => [],
])

<div {{ $attributes->merge(['class' => 'grid gap-3 sm:grid-cols-2']) }}>
    @forelse ($photos as $photo)
        <figure @class(['overflow-hidden rounded-2xl border border-sand-200 bg-white', 'sm:col-span-2' => (bool) ($photo['cover'] ?? false)])>
            <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] ?? 'Property photo' }}" class="h-64 w-full object-cover sm:h-80">
            @if (! empty($photo['name']))
                <figcaption class="px-3 py-2 text-xs text-stone-500">{{ $photo['name'] }}</figcaption>
            @endif
        </figure>
    @empty
        <p class="text-sm text-stone-600">No public photos have been released.</p>
    @endforelse
</div>
