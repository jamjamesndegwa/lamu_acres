@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="Buying profile" :close="route('buyer.dashboard')">
            @include('buyer.partials.buying-profile-form', ['profile' => $profile])
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="Buying Profile">
        <p class="mt-3 max-w-xl text-sm text-stone-700">Record your budget, acreage, and the kind of purchase you want. Searches you start later still use the map form.</p>
        <div class="mt-6 max-w-xl">
            @include('buyer.partials.buying-profile-form', ['profile' => $profile])
        </div>
    </x-workspace-layout>
@endif
