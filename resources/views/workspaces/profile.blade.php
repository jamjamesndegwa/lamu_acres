@php
    $isModal = \App\Support\Turbo::wantsModal(request());
@endphp

@if ($isModal)
    <turbo-frame id="modal">
        <x-modal title="Profile">
            <div class="mb-6">
                @include('workspaces.partials.avatar-form', ['user' => $user, 'prefix' => 'modal-profile-', 'breakout' => true])
            </div>
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
    <x-workspace-layout heading="Profile">
        <x-slot:modal>
            <x-modal title="Profile" size="xl">
                <div class="mb-6">
                    @include('workspaces.partials.avatar-form', ['user' => $user, 'prefix' => 'modal-profile-', 'breakout' => true])
                </div>
                @include('workspaces.partials.profile-form', [
                    'workspace' => $workspace,
                    'user' => $user,
                    'prefix' => 'modal-',
                    'breakout' => true,
                ])
                @include('workspaces.partials.phone-confirmation', ['user' => $user, 'prefix' => 'modal-'])
            </x-modal>
        </x-slot:modal>
    </x-workspace-layout>
@endif
