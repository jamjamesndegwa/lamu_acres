<turbo-frame id="modal">
    <x-modal title="Enquire about {{ $property->title }}">
        <p class="text-sm text-stone-600">{{ $property->reference }} · {{ $property->locationLabel() }}</p>
        <div class="mt-5">
            @include('land.partials.enquiry-form', ['property' => $property, 'prefix' => 'modal-'])
        </div>
    </x-modal>
</turbo-frame>
