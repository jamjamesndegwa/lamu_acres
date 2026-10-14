@php
    $isModal = \App\Support\Turbo::wantsModal(request());
@endphp

@if ($isModal)
    <turbo-frame id="modal">
        <x-modal title="Edit roles" class="max-w-xl">
            <p class="text-sm text-stone-600">{{ $account->name }} · {{ $account->email }}</p>
            <div class="mt-5">
                @include('admin.users.partials.roles-form', [
                    'account' => $account,
                    'roles' => $roles,
                    'breakout' => true,
                ])
            </div>
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="Edit roles">
        <p class="mt-3 text-sm text-stone-600">{{ $account->name }} · {{ $account->email }}</p>
        <div class="mt-6 max-w-xl">
            @include('admin.users.partials.roles-form', [
                'account' => $account,
                'roles' => $roles,
                'breakout' => false,
            ])
        </div>
    </x-workspace-layout>
@endif
