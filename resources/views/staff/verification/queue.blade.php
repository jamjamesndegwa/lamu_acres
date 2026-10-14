<x-workspace-layout :heading="$queue->label()">
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($summary as $row)
            <a href="{{ route('staff.verification.'.$row['key']) }}" class="rounded-2xl border border-sand-200 bg-white p-5">
                <p class="text-sm text-stone-500">{{ $row['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold text-lamu-900">{{ $row['count'] }}</p>
            </a>
        @endforeach
    </div>
    <div class="mt-6">
        @if ($mode === 'items')
            <x-workspace.table :columns="['Property', 'Check', 'Review date']">
                @forelse ($rows as $item)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('staff.verification.history', $item->property) }}">{{ $item->property->title }}</a></td>
                        <td class="px-4 py-4 text-sm">{{ $item->category->label() }}</td>
                        <td class="px-4 py-4 text-sm">{{ $item->review_due_at?->toFormattedDateString() ?? $item->expires_at?->toFormattedDateString() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="Nothing in this queue" message="Items appear here from the current checks." /></td></tr>
                @endforelse
            </x-workspace.table>
        @else
            <x-workspace.table :columns="['Property', 'Level', 'Deadline']">
                @forelse ($rows as $property)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('staff.verification.history', $property) }}">{{ $property->title }}</a></td>
                        <td class="px-4 py-4 text-sm">{{ $property->passport?->level->label() ?? 'Level 0 — Advertised' }}</td>
                        <td class="px-4 py-4 text-sm">{{ $property->passport?->deadline_at?->toFormattedDateString() ?? 'No deadline' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="Nothing in this queue" message="Properties appear here from the current checks." /></td></tr>
                @endforelse
            </x-workspace.table>
        @endif
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-workspace-layout>
