<x-workspace-layout heading="Offers">
    <p class="mt-3 max-w-3xl text-stone-700">Changing an offer status leaves the property availability unchanged.</p>
    <div class="mt-6 space-y-4">
        @forelse ($offers as $offer)
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <p class="font-medium text-lamu-900">{{ $offer->property->title }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ $offer->buyer->name }} · KSh {{ number_format($offer->amount) }} · {{ $offer->status->label() }}</p>
                <h2 class="mt-4 text-sm font-medium text-lamu-900">Buyer offer</h2>
                <p class="mt-1 whitespace-pre-line text-sm text-stone-700">{{ $offer->message ?: 'No message was included with this offer.' }}</p>
                @if ($offer->presentations->isNotEmpty())
                    <h2 class="mt-4 text-sm font-medium text-lamu-900">Responses</h2>
                    <div class="mt-2 space-y-3">
                        @foreach ($offer->presentations as $presentation)
                            <div class="rounded-xl border border-sand-200 p-3 text-sm">
                                <p class="font-medium text-lamu-900">KSh {{ number_format($presentation->amount) }}</p>
                                @if ($presentation->summary)
                                    <p class="mt-1 text-stone-700">{{ $presentation->summary }}</p>
                                @endif
                                <p class="mt-1 text-stone-600">{{ $presentation->seller_response ?: 'Waiting for a seller response' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
                @can('updateStatus', $offer)
                    @php
                        $responses = collect($offer->status->transitions())->reject(fn ($status) => $status->value === 'withdrawn');
                    @endphp
                    @if ($responses->isNotEmpty())
                        <form method="POST" action="{{ route('staff.offers.update', $offer) }}" data-turbo-frame="_top" data-confirm="Update this offer status?" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            @method('PUT')
                            <div>
                                <label class="block text-sm font-medium" for="status-{{ $offer->id }}">Review response</label>
                                <select id="status-{{ $offer->id }}" name="status" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                                    @foreach ($responses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save status</button>
                        </form>
                    @endif
                @endcan
            </article>
        @empty
            <x-workspace.empty title="No offers" message="Buyer offers will appear here." />
        @endforelse
    </div>
    <div class="mt-4"><x-workspace.pagination :paginator="$offers" /></div>
</x-workspace-layout>
