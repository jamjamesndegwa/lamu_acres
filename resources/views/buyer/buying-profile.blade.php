@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="Buying profile" :close="route('buyer.dashboard')">
            @include('buyer.partials.buying-profile-form', ['profile' => $profile])
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="Buying profile">
        <div class="mt-6 max-w-xl">
            @include('buyer.partials.buying-profile-form', ['profile' => $profile])
        </div>
    </x-workspace-layout>
@endif
