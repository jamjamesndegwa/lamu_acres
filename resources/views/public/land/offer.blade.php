<turbo-frame id="modal">
    <x-modal title="Make an offer on {{ $property->title }}">
        <p class="text-sm text-stone-600">{{ $property->reference }} · {{ $property->locationLabel() }}</p>
        <div class="mt-5">
            @include('land.partials.offer-form', ['property' => $property, 'prefix' => 'modal-'])
        </div>
    </x-modal>
</turbo-frame>
