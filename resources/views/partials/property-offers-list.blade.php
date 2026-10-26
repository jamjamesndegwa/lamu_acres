<ul class="mt-3 max-w-3xl space-y-3">
    @forelse ($offers as $offer)
        <li id="offer-{{ $offer->id }}" class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
            <p class="font-medium text-lamu-900">{{ $offer->buyer->name }} · KSh {{ number_format($offer->amount) }} · {{ $offer->status->label() }}</p>
            <p class="mt-1 text-stone-600">{{ $offer->created_at?->timezone(config('app.timezone'))->format('j M Y g:i A') }}</p>
            @if ($offer->message)
                <p class="mt-2 whitespace-pre-line text-stone-700">{{ $offer->message }}</p>
            @endif
            <div class="mt-3 flex flex-wrap gap-3">
                @if ($offer->buyer->email)
                    <a class="font-medium text-lamu-800 hover:underline" href="mailto:{{ $offer->buyer->email }}">Email {{ $offer->buyer->name }}</a>
                @endif
                @if ($offer->buyer->phone)
                    <a class="font-medium text-lamu-800 hover:underline" href="tel:{{ preg_replace('/\s+/', '', $offer->buyer->phone) }}">Call {{ $offer->buyer->phone }}</a>
                @endif
                @if (($showStaffActions ?? false) && auth()->user()?->can('updateStatus', $offer))
                    <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.offers.show', $offer) }}">Open offer workspace</a>
                @endif
            </div>
        </li>
    @empty
        <li class="text-sm text-stone-600">No offers have been recorded for this listing yet.</li>
    @endforelse
</ul>
