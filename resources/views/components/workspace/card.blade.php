@props(['title', 'description' => null, 'href' => null])

<section class="flex h-full flex-col rounded-2xl border border-sand-200 bg-white p-5">
    <h2 class="font-semibold text-lamu-900">{{ $title }}</h2>
    @if ($description)
        <p class="mt-2 text-sm text-stone-600">{{ $description }}</p>
    @endif
    <div class="mt-4">
        @if ($href)
            <a href="{{ $href }}" class="text-sm font-medium text-lamu-700 underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">Open records</a>
        @else
            <x-workspace.empty title="Not available yet" message="There is no data to show because this module has not been switched on." />
        @endif
    </div>
</section>
