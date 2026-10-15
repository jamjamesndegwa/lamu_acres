<x-workspace-layout :heading="'Edit '.$property->title">
    <p class="mt-2 text-sm text-stone-600">{{ $property->reference }} · {{ $property->status->label() }} · <a class="font-medium text-lamu-800 hover:underline" href="{{ route('seller.properties.show', $property) }}">Back to listing</a></p>
    @include('seller.properties.partials.form', [
        'action' => route('seller.properties.update', $property),
        'method' => 'PUT',
        'submitLabel' => 'Save changes',
    ])
</x-workspace-layout>
