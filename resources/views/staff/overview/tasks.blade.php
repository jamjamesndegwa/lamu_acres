<x-workspace-layout heading="Tasks">
    <p class="mt-3 max-w-3xl text-stone-700">Messages and work updates assigned to this login.</p>
    <div class="mt-6 space-y-3">
        @forelse ($notifications as $notification)
            <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                <p class="font-medium text-lamu-900">{{ $notification->data['title'] ?? 'Update' }}</p>
                @if (! empty($notification->data['message']))
                    <p class="mt-1 text-stone-700">{{ $notification->data['message'] }}</p>
                @endif
                <p class="mt-1 text-xs text-stone-500">{{ $notification->created_at?->toDayDateTimeString() }}</p>
            </article>
        @empty
            <x-workspace.empty title="No tasks" message="Assigned work will be listed here when a desk places it on you." />
        @endforelse
    </div>
</x-workspace-layout>
