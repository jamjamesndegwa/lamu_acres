<x-workspace-layout :heading="$transaction->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $transaction->property->title }} · {{ $transaction->status->label() }}</p>
    <p class="mt-4 whitespace-pre-line text-sm text-stone-700">{{ $transaction->statement ?: 'No statement note has been recorded.' }}</p>
</x-workspace-layout>
