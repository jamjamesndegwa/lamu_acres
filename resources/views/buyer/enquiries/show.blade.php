<x-workspace-layout :heading="$enquiry->property->title">
    <p class="mt-3 text-sm text-stone-600">{{ $enquiry->property->reference }} · {{ $enquiry->status->label() }}</p>
    <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
        <h2 class="font-semibold text-lamu-900">Your enquiry</h2>
        <p class="mt-3 whitespace-pre-line text-sm text-stone-700">{{ $enquiry->message }}</p>
        <p class="mt-4 text-xs text-stone-500">Sent {{ $enquiry->created_at->toDayDateTimeString() }}</p>
    </section>

    <section class="mt-6 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Responses</h2>
        <div class="mt-4 space-y-3">
            @forelse ($enquiry->responses as $response)
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <p class="text-sm font-medium text-lamu-900">{{ $response->author->name }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-stone-700">{{ $response->body }}</p>
                    <p class="mt-3 text-xs text-stone-500">{{ $response->created_at->toDayDateTimeString() }}</p>
                </article>
            @empty
                <x-workspace.empty title="No response yet" message="Replies from Lamu Acres will appear here." />
            @endforelse
        </div>
    </section>
</x-workspace-layout>
