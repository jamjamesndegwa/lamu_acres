<x-workspace-layout heading="Alerts & Archive">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">Published alerts and history. Only published records that were delivered to this account are listed.</p>
    <ul class="mt-6 space-y-3">
        @forelse ($deliveries as $delivery)
            <li class="rounded-2xl border border-sand-200 bg-white p-4">
                <p class="font-medium text-lamu-900">{{ $delivery->assignment?->commitment->label() ?? 'Development alert' }}</p>
                <p class="mt-1 text-sm text-stone-700">{{ $delivery->subject->title ?? $delivery->subject->name ?? $delivery->subject->area_label }}</p>
                <p class="mt-1 text-xs text-stone-500">{{ $delivery->delivered_at->toDayDateTimeString() }}</p>
            </li>
        @empty
            <li class="text-sm text-stone-600">Nothing has been delivered.</li>
        @endforelse
    </ul>
</x-workspace-layout>
