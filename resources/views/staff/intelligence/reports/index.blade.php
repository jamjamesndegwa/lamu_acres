<x-workspace-layout heading="Research reports">
    <div class="mt-4 flex justify-end"><a href="{{ route('staff.reports.create') }}" class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">New report</a></div>
    <div class="mt-6">
        <x-workspace.table :columns="['Report', 'Publication']">
            @forelse ($reports as $report)
                <tr class="border-b border-sand-200"><td class="px-4 py-3"><a class="text-lamu-800 hover:underline" href="{{ route('staff.reports.show', $report) }}">{{ $report->title }}</a></td><td class="px-4 py-3">{{ $report->publication_status->label() }}</td></tr>
            @empty
                <tr><td colspan="2" class="px-4 py-8"><x-workspace.empty title="No reports" message="Drafts stay off the public preview until an editor publishes them." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
