<x-crud-page :title="'Edit '.$sourcingRequest->reference" :close="route('buyer.sourcing.show', $sourcingRequest)" size="xl">
    @include('buyer.sourcing.partials.form', [
        'action' => route('buyer.sourcing.update', $sourcingRequest),
        'method' => 'PUT',
        'confirm' => 'Save these search changes?',
        'submit' => 'Save search',
    ])
</x-crud-page>
