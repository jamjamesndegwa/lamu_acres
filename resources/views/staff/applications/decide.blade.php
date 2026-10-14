@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="Application decision" :close="route('staff.applications.index')">
            @include('staff.applications.partials.decision', ['application' => $application])
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout heading="Application decision">
        <x-slot:modal>
            <x-modal title="Application decision" :close="route('staff.applications.index')">
                @include('staff.applications.partials.decision', ['application' => $application])
            </x-modal>
        </x-slot:modal>
    </x-workspace-layout>
@endif
