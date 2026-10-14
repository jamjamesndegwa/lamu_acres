<x-workspace-layout heading="Development tracker">
    <div class="mt-4 flex justify-between gap-3">
        <p class="max-w-3xl text-stone-700">Record only what a source supports. A draft stays off the public site until an editor publishes it.</p>
        <a href="{{ route('staff.projects.create') }}" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">New project</a>
    </div>
    <div class="mt-6">
        <x-workspace.table :columns="['Project', 'Status', 'Publication']">
            @forelse ($projects as $project)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('staff.projects.show', $project) }}">{{ $project->name }}</a></td>
                    <td class="px-4 py-4">{{ $project->project_status->label() }}</td>
                    <td class="px-4 py-4">{{ $project->publication_status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="No projects" message="Nothing has been entered. The tracker does not invent milestones or coordinates." /></td></tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$projects" /></div>
    </div>
</x-workspace-layout>
