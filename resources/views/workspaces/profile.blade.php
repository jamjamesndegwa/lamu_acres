@php
    $isModal = \App\Support\Turbo::wantsModal(request());
@endphp

@if ($isModal)
    <turbo-frame id="modal">
        <x-modal title="Profile & Security">
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
    <x-workspace-layout heading="Profile & Security">
        <div class="mt-6 max-w-xl space-y-8">
            @include('workspaces.partials.avatar-form', ['user' => $user, 'prefix' => 'profile-', 'breakout' => false])
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
