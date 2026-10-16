<x-workspace-layout heading="Request staff assistance">
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-3xl text-sm text-stone-600">Tell the Lamu Acres team where you want land, your budget and acreage, and any requirements. Your request stays in your buyer account for staff review and property matching.</p>
        <a href="{{ route('buyer.sourcing.index') }}" class="text-sm font-medium text-lamu-800 underline">View your requests</a>
    </div>
    <x-step-list :steps="['Choose your preferred area', 'Describe your budget and requirements', 'Send your request to the team']" />
    @include('buyer.sourcing.partials.form', [
        'action' => route('buyer.sourcing.store'),
        'method' => 'POST',
        'confirm' => 'Send this staff assistance request?',
        'submit' => 'Request staff assistance',
        'sourcingRequest' => null,
    ])
</x-workspace-layout>
