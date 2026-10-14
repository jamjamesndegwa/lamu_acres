<x-workspace-layout :heading="$heading">
    <p class="mt-3 max-w-3xl text-stone-700">
        @if ($heading === 'Approvals')
            Searches with proposed matches. Open one to approve or reject each candidate.
        @elseif ($heading === 'Matching')
            Searches that already have candidate properties.
        @else
            Incoming buyer specifications, with progress and assignment.
        @endif
    </p>

    <x-filter-bar :action="url()->current()" class="mt-6 flex flex-wrap items-end gap-3">
        <div>
            <label for="status" class="block text-sm font-medium">Progress</label>
            <select id="status" name="status" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">All stages</option>
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
        <a href="{{ url()->current() }}" class="text-sm font-medium text-lamu-800">Clear</a>
    </x-filter-bar>

    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Specification', 'Progress', 'Assignment']">
            @forelse ($requests as $sourcingRequest)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('staff.sourcing.show', $sourcingRequest) }}" class="font-medium text-lamu-900 hover:underline">{{ $sourcingRequest->reference }}</a>
                        <p class="mt-1 text-sm text-stone-600">{{ $sourcingRequest->buyer->name }}</p>
                    </td>
                    <td class="px-4 py-4 text-sm text-stone-700">
                        <p>{{ $sourcingRequest->location_label ?: 'Map centre only' }}</p>
                        <p class="mt-1">{{ $sourcingRequest->radius_km ? $sourcingRequest->radius_km.' km' : 'Radius not set' }} · {{ $sourcingRequest->min_acreage ?: 'Any' }}–{{ $sourcingRequest->max_acreage ?: 'Any' }} acres</p>
                        <p class="mt-1">{{ $sourcingRequest->maximum_budget ? 'KSh '.number_format($sourcingRequest->maximum_budget) : 'Budget not set' }}{{ $sourcingRequest->intended_use ? ' · '.$sourcingRequest->intended_use->label() : '' }}</p>
                    </td>
                    <td class="px-4 py-4 text-sm">
                        <x-status-badge :label="$sourcingRequest->status->label()" />
                        <p class="mt-1 text-stone-600">{{ $sourcingRequest->proposed_count }} proposed of {{ $sourcingRequest->matches_count }}</p>
                    </td>
                    <td class="px-4 py-4 text-sm">
                        @can('assign', $sourcingRequest)
                            <form method="POST" action="{{ route('staff.sourcing.assign', $sourcingRequest) }}" data-turbo-frame="_top" data-confirm="Assign this search?" class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PUT')
                                <select name="assigned_to" aria-label="Assign {{ $sourcingRequest->reference }}" class="rounded-lg border border-sand-200 bg-white px-2 py-1">
                                    @foreach ($assignees as $assignee)
                                        <option value="{{ $assignee->id }}" @selected($sourcingRequest->assigned_to === $assignee->id)>{{ $assignee->name }}</option>
                                    @endforeach
                                </select>
                                <button class="font-medium text-lamu-800">Assign</button>
                            </form>
                        @else
                            {{ $sourcingRequest->assignee->name ?? 'Unassigned' }}
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8">
                        <x-workspace.empty title="Queue is clear" message="New buyer searches will appear here." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$requests" /></div>
    </div>
</x-workspace-layout>
