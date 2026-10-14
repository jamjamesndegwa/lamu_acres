@props(['steps' => []])

<ol class="mt-4 flex flex-wrap gap-2" aria-label="Steps">
    @foreach ($steps as $index => $step)
        <li class="rounded-full border border-sand-200 bg-white px-3 py-1 text-sm text-stone-700">{{ $index + 1 }}. {{ $step }}</li>
    @endforeach
</ol>
