@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="Application decision" :close="route('staff.applications.index')">
            @include('staff.applications.partials.decision', ['application' => $application])
        </x-modal>
    </turbo-frame>
@else
    <x-workspace-layout :heading="$application->capability->label().' application review'">
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('staff.applications.index') }}" class="text-sm font-medium text-lamu-800 underline">Back to applications</a>
            <x-status-badge :label="$application->status->label()" />
        </div>
        <div class="mt-6 max-w-6xl rounded-2xl border border-sand-200 bg-white p-4 sm:p-6">
            @include('staff.applications.partials.decision', ['application' => $application])
        </div>
    </x-workspace-layout>
@endif
