<x-workspace-layout heading="Partner applications">
    <div class="mt-6 overflow-x-auto">
        <x-workspace.table :columns="['Applicant', 'Capability', 'Status', '']">
            @forelse ($applications as $application)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <span class="font-medium text-lamu-900">{{ $application->user->name }}</span>
                        <span class="mt-1 block text-sm text-stone-600">{{ $application->user->email }}</span>
                    </td>
                    <td class="px-4 py-4">{{ $application->capability->label() }}</td>
                    <td class="px-4 py-4">{{ $application->status->label() }}</td>
                    <td class="px-4 py-4 text-right">
                        @can('decide', $application)
                            <a href="{{ route('staff.applications.edit', $application) }}" data-turbo-frame="modal" class="rounded-full bg-lamu-900 px-4 py-2 text-xs font-medium text-white">Decide</a>
                        @else
                            <span class="text-xs text-stone-400">Own application</span>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8">
                        <x-workspace.empty title="No applications" message="Broker and seller applications will be listed here." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
    </div>
    <div class="mt-4">
        {{ $applications->links() }}
    </div>
</x-workspace-layout>
