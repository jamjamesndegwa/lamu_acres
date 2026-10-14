<x-workspace-layout :heading="'Edit '.$sourcingRequest->reference">
    @include('buyer.sourcing.partials.form', [
        'action' => route('buyer.sourcing.update', $sourcingRequest),
        'method' => 'PUT',
        'confirm' => 'Save these search changes?',
        'submit' => 'Save search',
    ])
</x-workspace-layout>
