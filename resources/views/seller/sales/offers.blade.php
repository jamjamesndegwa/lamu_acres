<x-workspace-layout heading="Offers">
    <p class="mt-3 text-stone-700">Responding to an offer does not mark the land as sold and does not pay commission.</p>
    <div class="mt-6 space-y-4">
        @forelse ($presentations as $presentation)
            <article class="rounded-2xl border border-sand-200 bg-white p-5 text-sm">
                <p class="font-medium text-lamu-900">{{ $presentation->property->title }}</p>
                <p class="mt-1">KSh {{ number_format($presentation->amount) }}</p>
                @if ($presentation->offer?->requested_acreage !== null)
                    <p class="mt-1 text-stone-600">{{ rtrim(rtrim(number_format((float) $presentation->offer->requested_acreage, 4, '.', ''), '0'), '.') }} acres requested</p>
                @endif
                <p class="mt-2">{{ $presentation->summary }}</p>
                @if ($presentation->seller_response)
                    <p class="mt-2">Your response: {{ $presentation->seller_response }}</p>
                @else
                    <form method="POST" action="{{ route('seller.offers.respond', $presentation) }}" data-turbo-frame="_top" data-confirm="Record this offer response?" class="mt-3 flex gap-3">
                        @csrf
                        <select name="seller_response" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                            <option value="accept">Accept</option>
                            <option value="decline">Decline</option>
                        </select>
                        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Respond</button>
                    </form>
                @endif
            </article>
        @empty
            <x-workspace.empty title="No presented offers" message="Staff present offers before you respond." />
        @endforelse
    </div>
</x-workspace-layout>
