<x-workspace-layout heading="Enquiries">
    <p class="mt-3 text-stone-700">Staff choose what is shared. Buyer contact details are not shown here.</p>
    <div class="mt-6 space-y-3">
        @forelse ($briefings as $briefing)
            <article class="rounded-2xl border border-sand-200 bg-white p-5 text-sm">
                <p class="font-medium text-lamu-900">{{ $briefing->property->title }}</p>
                <p class="mt-2">{{ $briefing->body }}</p>
            </article>
        @empty
            <x-workspace.empty title="No shared enquiries" message="A briefing appears when staff share one." />
        @endforelse
    </div>
</x-workspace-layout>
