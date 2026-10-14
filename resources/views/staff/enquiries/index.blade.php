<x-workspace-layout heading="Enquiries & Leads">
    <p class="mt-3 max-w-3xl text-stone-700">Property enquiries you are allowed to see. Buyer-desk staff see the queue. Other staff see enquiries assigned to them.</p>

    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Buyer', 'Lead', 'Assigned', '']">
            @forelse ($enquiries as $enquiry)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 font-medium text-lamu-900">{{ $enquiry->property->title }}</td>
                    <td class="px-4 py-4 text-sm">{{ $enquiry->buyer->name }}</td>
                    <td class="px-4 py-4 text-sm">{{ $enquiry->lead_status->label() }}</td>
                    <td class="px-4 py-4 text-sm text-stone-600">{{ $enquiry->assignee->name ?? 'Unassigned' }}</td>
                    <td class="px-4 py-4 text-right">
                        <a href="{{ route('staff.enquiries.show', $enquiry) }}" class="text-sm font-medium text-lamu-800">Open</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8">
                        <x-workspace.empty title="No enquiries" message="New property enquiries will be listed here." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4">
            <x-workspace.pagination :paginator="$enquiries" />
        </div>
    </div>
</x-workspace-layout>
