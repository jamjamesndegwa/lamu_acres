@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="Application decision" :close="route('staff.applications.index')">
            @include('staff.applications.partials.decision', ['application' => $application])
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="Application decision">
        <div class="mt-6 max-w-xl">
            @include('staff.applications.partials.decision', ['application' => $application])
        </div>
    </x-workspace-layout>
@endif
