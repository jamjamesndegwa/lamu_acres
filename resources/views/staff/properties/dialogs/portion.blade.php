<turbo-frame id="modal">
    <x-modal title="Partial sale" :close="route('staff.properties.show', $property->parent ?? $property)">
        @php($parcel = $property->parent ?? $property)
        @php($parcel->loadMissing('portions'))
        <p class="text-sm text-stone-700">{{ $parcel->reference }} holds {{ number_format((float) $parcel->acreage, 2) }} acres. {{ number_format($parcel->remainingAcreage(), 2) }} acres remain after sold portions.</p>
        <ul class="mt-4 space-y-3 text-sm">
            @forelse ($parcel->portions as $portion)
                <li class="rounded-xl border border-sand-200 p-3">
                    <p class="font-medium text-lamu-900">{{ $portion->portion_label ?: $portion->title }}</p>
                    <p class="text-stone-600">{{ number_format((float) $portion->acreage, 2) }} acres · {{ $portion->status->label() }}</p>
                    @if ($portion->status !== \App\Enums\PropertyStatus::Sold && in_array(\App\Enums\PropertyStatus::Sold, $portion->status->transitions(), true))
                        <form method="POST" action="{{ route('staff.properties.sell-portion', $portion) }}" data-turbo-frame="_top" data-confirm="Mark only this portion sold?" class="mt-2 space-y-2">
                            @csrf
                            @if (auth()->user()->hasPermission('staff.inventory.correct'))
                                <input name="correction_reason" placeholder="Correction reason (required without a completed sale)" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm">
                            @endif
                            <button class="font-medium text-lamu-800">Sell this portion</button>
                        </form>
                        <p class="mt-1 text-xs text-stone-600">Commercial sales close through the sales desk. This action is for a completed sale or an authorized correction.</p>
                    @endif
                </li>
            @empty
                <li class="text-stone-600">No portion has been recorded. The parent parcel is still whole.</li>
            @endforelse
        </ul>
        @error('portion')
            <p class="mt-3 text-sm text-red-700">{{ $message }}</p>
        @enderror
        <a href="{{ route('staff.properties.create', ['parent' => $parcel->id]) }}" data-turbo-frame="modal" class="mt-4 inline-block text-sm font-medium text-lamu-800">Add a portion</a>
    </x-modal>
</turbo-frame>
