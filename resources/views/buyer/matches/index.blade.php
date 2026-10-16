<x-workspace-layout heading="My property matches">
    <p class="mt-3 max-w-3xl text-stone-700">Properties a member of staff has approved for you. Suitability notes come from the search that found the land.</p>

    @if ($matches->isEmpty())
        <x-buyer.next-step
            label="Find land for me"
            :href="route('buyer.sourcing.create')"
            frame="_top"
            detail="No approved match is ready. Record a search on the map, or explore published land while matching continues."
        />
        <p class="mt-4 text-sm">
            <a href="{{ route('land.index') }}" class="font-medium text-lamu-800 hover:underline">Explore land</a>
        </p>
    @else
        <x-buyer.next-step
            label="Open a match"
            :href="route('buyer.matches.show', $matches->first())"
            detail="Compare the land with your criteria, then request a visit, send an offer, or ask for documents."
        />
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($matches as $match)
                @php
                    $fit = $match->sourcingRequest?->matches?->firstWhere('property_id', $match->property_id);
                @endphp
                <article class="flex h-full flex-col overflow-hidden rounded-2xl border border-sand-200 bg-white">
                    <div class="border-b border-sand-200 bg-lamu-100 px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-gold-500">{{ $fit?->band?->label() ?? 'Approved' }} · {{ $match->status->label() }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-lamu-900">
                            <a href="{{ route('buyer.matches.show', $match) }}" class="hover:underline">{{ $match->property->title }}</a>
                        </h2>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 px-5 py-4 text-sm text-stone-700">
                        <p>{{ $match->property->locationLabel() }}</p>
                        <p class="font-medium text-lamu-900">{{ $match->property->formattedPrice() ?? 'Price on request' }}</p>
                        <p>{{ $fit && filled($fit->reasons) ? implode(' ', $fit->reasons) : 'Approved for your shortlist.' }}</p>
                        <a href="{{ route('buyer.matches.show', $match) }}" class="mt-auto pt-3 text-sm font-medium text-lamu-800 hover:underline">Compare and respond</a>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-4"><x-workspace.pagination :paginator="$matches" /></div>
    @endif
</x-workspace-layout>
