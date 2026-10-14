<x-workspace-layout heading="Viewings">
    <div class="mt-6 space-y-4">
        @forelse ($visits as $visit)
            <article class="rounded-2xl border border-sand-200 bg-white p-5 text-sm">
                <p class="font-medium text-lamu-900">{{ $visit->property->title }}</p>
                <p class="mt-1">{{ $visit->status->label() }} · {{ $visit->requested_for->toDayDateTimeString() }}</p>
                <form method="POST" action="{{ route('seller.viewings.instruct', $visit) }}" data-turbo-frame="_top" data-confirm="Send this viewing instruction?" class="mt-3 space-y-2">
                    @csrf
                    <textarea name="body" required minlength="5" class="w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="Available times"></textarea>
                    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Send instruction</button>
                </form>
            </article>
        @empty
            <x-workspace.empty title="No viewings" message="Visits on your verified land will appear here." />
        @endforelse
    </div>
</x-workspace-layout>
