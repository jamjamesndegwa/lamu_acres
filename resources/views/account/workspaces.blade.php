@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="Choose a workspace">
            <p class="mb-5 text-sm leading-6 text-stone-600">Open a workspace for the work you need to do.</p>
            @include('account.partials.workspace-chooser')
        </x-modal>
    </turbo-frame>
@else
    <x-account-layout title="Account workspaces — Lamu Acres">
        <header class="mb-8 rounded-3xl bg-lamu-900 px-6 py-7 text-white sm:px-8 sm:py-9">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sand-200">Your account</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">Your workspaces</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-white/80">Open the workspace for the work you need to do, or check the status of a seller or broker application.</p>
        </header>

        @include('account.partials.workspace-chooser')
    </x-account-layout>
@endif
