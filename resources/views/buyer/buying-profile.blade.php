@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="Buying profile" :close="route('buyer.dashboard')">
            @include('buyer.partials.buying-profile-form', ['profile' => $profile])
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="Buying Profile">
        <x-slot:modal>
            <x-modal title="Buying profile" :close="route('buyer.dashboard')" size="xl">
                <p class="mb-4 text-sm text-stone-700">Record your budget, acreage, and the kind of purchase you want. Searches you start later still use the map form.</p>
                @include('buyer.partials.buying-profile-form', ['profile' => $profile])
            </x-modal>
        </x-slot:modal>
    </x-workspace-layout>
@endif
