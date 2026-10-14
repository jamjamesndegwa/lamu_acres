<x-workspace-layout heading="Publication calendar">
    <x-editorial-workflow />
    <p class="mt-3 max-w-3xl text-sm text-stone-600">A period with no usable published imagery stays empty. The calendar does not invent a comparison or a development.</p>
    <dl class="mt-6 grid gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-sand-200 bg-white p-4"><dt class="text-sm text-stone-500">Due</dt><dd class="text-2xl font-semibold text-lamu-900">{{ $counts['due'] }}</dd></div>
        <div class="rounded-2xl border border-sand-200 bg-white p-4"><dt class="text-sm text-stone-500">Overdue</dt><dd class="text-2xl font-semibold text-lamu-900">{{ $counts['overdue'] }}</dd></div>
        <div class="rounded-2xl border border-sand-200 bg-white p-4"><dt class="text-sm text-stone-500">Published</dt><dd class="text-2xl font-semibold text-lamu-900">{{ $counts['published'] }}</dd></div>
        <div class="rounded-2xl border border-sand-200 bg-white p-4"><dt class="text-sm text-stone-500">No usable source</dt><dd class="text-2xl font-semibold text-lamu-900">{{ $counts['unavailable'] }}</dd></div>
    </dl>
    <div class="mt-6">
        <x-workspace.table :columns="['Commitment', 'Period', 'Status', 'Note']">
            @forelse ($assignments as $assignment)
                <tr class="border-b border-sand-200">
                    <td class="px-4 py-3">{{ $assignment->commitment->label() }}</td>
                    <td class="px-4 py-3">{{ $assignment->period_start->toFormattedDateString() }} – {{ $assignment->period_end->toFormattedDateString() }}</td>
                    <td class="px-4 py-3">{{ $assignment->status->label() }}</td>
                    <td class="px-4 py-3 text-sm text-stone-600">{{ $assignment->note }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No periods yet" message="The daily calendar command has not opened a period." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
