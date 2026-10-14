@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="Choose a workspace">
            @include('account.partials.workspace-chooser')
        </x-modal>
    </turbo-frame>
@else
    <x-account-layout title="Workspaces — Lamu Acres">
        <h1 class="text-2xl font-semibold text-lamu-900">Choose a workspace</h1>
        <div class="mt-6">
            @include('account.partials.workspace-chooser')
        </div>
    </x-account-layout>
@endif
