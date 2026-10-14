@php
    $isModal = \App\Support\Turbo::wantsModal(request());
@endphp

@if ($isModal)
    <turbo-frame id="modal">
        <x-modal title="Profile & Security">
            @include('workspaces.partials.profile-form', [
                'workspace' => $workspace,
                'user' => $user,
                'prefix' => 'modal-',
                'breakout' => true,
            ])
            @include('workspaces.partials.phone-confirmation', ['user' => $user, 'prefix' => 'modal-'])
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="Profile & Security">
        <div class="mt-6 max-w-xl">
            @include('workspaces.partials.profile-form', [
                'workspace' => $workspace,
                'user' => $user,
                'prefix' => '',
                'breakout' => false,
            ])
            @include('workspaces.partials.phone-confirmation', ['user' => $user, 'prefix' => ''])
        </div>
    </x-workspace-layout>
@endif
