<x-workspace-layout heading="Research Archive">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">Published alerts delivered to your Investor Pass account. Withdrawn research stays listed here as unavailable rather than exposing restricted content.</p>

    @if ($deliveries->isEmpty())
        <x-workspace.empty class="mt-6" title="No relevant published updates yet." message="Follow a location or project to receive alerts when matching research is released." />
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($deliveries as $delivery)
                @php
                    $available = $archive->isAvailable($delivery);
                    $url = $archive->subjectUrl($delivery);
                    $watchLabel = $archive->watchLabel($delivery, auth()->id());
                @endphp
                <li class="rounded-2xl border border-sand-200 bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $archive->categoryLabel($delivery) }}</p>
                            <p class="mt-1 font-medium text-lamu-900">{{ $archive->subjectTitle($delivery) }}</p>
                            @if ($watchLabel)
                                <p class="mt-1 text-xs text-stone-500">{{ $watchLabel }}</p>
                            @endif
                            <p class="mt-1 text-xs text-stone-500">
                                Delivered {{ $delivery->delivered_at->toDayDateTimeString() }}
                                @if ($archive->sourceDate($delivery))
                                    · Source {{ $archive->sourceDate($delivery) }}
                                @endif
                            </p>
                        </div>
                        @if ($available && $url)
                            <a href="{{ $url }}" class="shrink-0 rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Open</a>
                        @else
                            <span class="shrink-0 rounded-full border border-sand-200 px-4 py-2 text-sm text-stone-600">No longer available</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $deliveries->links() }}</div>
    @endif
</x-workspace-layout>
