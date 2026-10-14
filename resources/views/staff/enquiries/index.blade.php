<x-workspace-layout heading="Enquiries & Leads">
    <p class="mt-3 max-w-3xl text-stone-700">Property enquiries you are allowed to see. Buyer-desk staff see the queue. Other staff see enquiries assigned to them.</p>
    <p class="mt-2 text-sm font-medium text-lamu-900">New leads: {{ $newLeadCount }}</p>

    <x-filter-bar :action="route('staff.enquiries.index')" class="mt-6 flex flex-wrap items-end gap-3">
        <div>
            <label for="lead" class="block text-sm font-medium">Lead</label>
            <select id="lead" name="lead" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">All leads</option>
                @foreach ($leadStatuses as $status)
                    <option value="{{ $status->value }}" @selected(request('lead') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="block text-sm font-medium">Enquiry status</label>
            <select id="status" name="status" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="assigned" class="block text-sm font-medium">Assignment</label>
            <select id="assigned" name="assigned" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">Anyone</option>
                <option value="unassigned" @selected(request('assigned') === 'unassigned')>Unassigned</option>
                <option value="mine" @selected(request('assigned') === 'mine')>Assigned to me</option>
            </select>
        </div>
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Filter</button>
        <a href="{{ route('staff.enquiries.index') }}" class="text-sm font-medium text-lamu-800">Clear</a>
    </x-filter-bar>

    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Buyer', 'Lead', 'Assigned', '']">
            @forelse ($enquiries as $enquiry)
                <tr class="border-b border-sand-200 last:border-b-0 {{ $enquiry->lead_status->value === 'new' ? 'bg-lamu-100/40' : '' }}">
                    <td class="px-4 py-4 font-medium text-lamu-900">
                        {{ $enquiry->property->title }}
                        @if ($enquiry->lead_status->value === 'new')
                            <span class="ml-2 rounded-full bg-lamu-900 px-2 py-0.5 text-xs font-medium text-white">New lead</span>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $enquiry->buyer->name }}</td>
                    <td class="px-4 py-4 text-sm"><x-status-badge :label="$enquiry->lead_status->label()" /> <x-status-badge :label="$enquiry->status->label()" /></td>
                    <td class="px-4 py-4 text-sm text-stone-600">
                        @can('assign', $enquiry)
                            <form method="POST" action="{{ route('staff.enquiries.assign', $enquiry) }}" data-turbo-frame="_top" data-confirm="Assign this enquiry?" class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PUT')
                                <select name="assigned_to" aria-label="Assign {{ $enquiry->property->title }}" class="rounded-lg border border-sand-200 bg-white px-2 py-1">
                                    @foreach ($assignees as $assignee)
                                        <option value="{{ $assignee->id }}" @selected($enquiry->assigned_to === $assignee->id)>{{ $assignee->name }}</option>
                                    @endforeach
                                </select>
                                <button class="text-sm font-medium text-lamu-800">Assign</button>
                            </form>
                        @else
                            {{ $enquiry->assignee->name ?? 'Unassigned' }}
                        @endcan
                    </td>
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
