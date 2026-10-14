<x-workspace-layout heading="Offers">
    <p class="mt-3 max-w-3xl text-stone-700">Changing an offer status leaves the property availability unchanged.</p>
    <div class="mt-6 space-y-4">
        @forelse ($offers as $offer)
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <p class="font-medium text-lamu-900">{{ $offer->property->title }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ $offer->buyer->name }} · KSh {{ number_format($offer->amount) }} · {{ $offer->status->label() }}</p>
                @can('updateStatus', $offer)
                    <form method="POST" action="{{ route('staff.offers.update', $offer) }}" data-turbo-frame="_top" data-confirm="Update this offer status?" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PUT')
                        <select name="status" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save status</button>
                    </form>
                @endcan
            </article>
        @empty
            <x-workspace.empty title="No offers" message="Buyer offers will appear here." />
        @endforelse
    </div>
    <div class="mt-4"><x-workspace.pagination :paginator="$offers" /></div>
</x-workspace-layout>
