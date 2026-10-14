<x-workspace-layout heading="Saved Properties">
    <p class="mt-3 max-w-3xl text-stone-700">Land you have kept to review. Open a property to enquire, or remove it from this list.</p>

    @if ($saved->isEmpty())
        <x-buyer.next-step
            label="Explore land"
            :href="route('land.index')"
            detail="Nothing is saved yet. Browse published land, save a property, then send an enquiry."
        />
    @else
        <x-buyer.next-step
            label="Send an enquiry"
            :href="$saved->first()->property->publicUrl()"
            detail="Open a saved property and ask the desk a question. After that you can book a visit or make an offer."
        />
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($saved as $item)
                <article class="flex h-full flex-col overflow-hidden rounded-2xl border border-sand-200 bg-white">
                    <div class="border-b border-sand-200 bg-lamu-100 px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-gold-500">{{ $item->property->reference }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-lamu-900">
                            <a href="{{ $item->property->publicUrl() }}" class="hover:underline">{{ $item->property->title }}</a>
                        </h2>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 px-5 py-4 text-sm text-stone-700">
                        <p>{{ $item->property->locationLabel() }}</p>
                        @if ($item->property->acreage)
                            <p>{{ rtrim(rtrim(number_format((float) $item->property->acreage, 2), '0'), '.') }} acres</p>
                        @endif
                        <p class="font-medium text-lamu-900">{{ $item->property->formattedPrice() ?? 'Price on request' }}</p>
                        @if ($item->property->isPubliclyListed())
                            <p>Available</p>
                        @else
                            <p class="text-stone-800">No longer available · {{ $item->property->status->label() }}. It stays in this list so you can see what changed.</p>
                        @endif
                        <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-3">
                            <span class="flex flex-wrap gap-3">
                                @if ($item->property->isPubliclyListed())
                                    <a href="{{ $item->property->publicUrl() }}" class="text-sm font-medium text-lamu-800 hover:underline">Open details</a>
                                    <a href="{{ route('land.enquiries.create', $item->property) }}" data-turbo-frame="modal" class="text-sm font-medium text-lamu-800 hover:underline">Enquire</a>
                                    <a href="{{ route('land.visits.create', $item->property) }}" data-turbo-frame="modal" class="text-sm font-medium text-lamu-800 hover:underline">Request visit</a>
                                @else
                                    <span class="text-sm text-stone-500">The public page is closed</span>
                                @endif
                            </span>
                            <form method="POST" action="{{ route('buyer.saved.destroy', $item->property) }}" data-confirm="Remove this saved property?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-lamu-800">Remove</button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-4">
            <x-workspace.pagination :paginator="$saved" />
        </div>
    @endif
</x-workspace-layout>
