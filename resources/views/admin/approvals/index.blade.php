<x-workspace-layout heading="Approvals Centre">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">
        Open a record, inspect the evidence on its review page, then record the decision that page already allows.
        Sale closing and commission payment recording stay on their own desks.
    </p>

    @if (count($tabs) === 1)
        <div class="mt-6">
            <x-workspace.empty
                title="No approval category is open"
                message="No approval category is open for this account. Listing publication, verification review, commission authorization, and intelligence publication each stay behind their own permission."
            />
        </div>
    @else
        <nav class="mt-6 flex flex-wrap gap-2" aria-label="Approval categories">
            @foreach ($tabs as $item)
                <a
                    href="{{ route('admin.approvals.index', ['tab' => $item['key']]) }}"
                    @class([
                        'rounded-full px-4 py-2 text-sm font-medium',
                        'bg-lamu-900 text-white' => $tab === $item['key'],
                        'border border-sand-200 bg-white text-lamu-900 hover:bg-lamu-100' => $tab !== $item['key'],
                    ])
                >
                    {{ $item['label'] }} ({{ $item['count'] }})
                </a>
            @endforeach
        </nav>

        <x-filter-bar :action="route('admin.approvals.index')" class="mt-6 flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div>
                <label for="q" class="block text-sm font-medium text-lamu-900">Search</label>
                <input id="q" name="q" value="{{ $search }}" maxlength="80" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2" placeholder="Reference, type, or name">
            </div>
            @if ($statuses !== [])
                <div>
                    <label for="status" class="block text-sm font-medium text-lamu-900">Status</label>
                    <select id="status" name="status" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($statusFilter === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
        </x-filter-bar>

        <div class="mt-6">
            @if ($rows->isEmpty())
                <x-workspace.empty
                    title="Nothing is waiting"
                    :message="$search !== '' || $statusFilter !== '' ? 'No records match this search.' : 'This queue is empty.'"
                />
            @else
                <x-workspace.table :columns="['Reference', 'Type', 'Submitted by', 'Submitted date', 'Current status', 'Details', 'Decision']">
                    @foreach ($rows as $row)
                        <tr class="border-b border-sand-200 last:border-0">
                            <td class="px-4 py-3 font-medium text-lamu-900">{{ $row['reference'] }}</td>
                            <td class="px-4 py-3">{{ $row['type'] }}</td>
                            <td class="px-4 py-3">{{ $row['submitted_by'] }}</td>
                            <td class="px-4 py-3">{{ $row['submitted_at']?->toFormattedDateString() ?? 'Not recorded' }}</td>
                            <td class="px-4 py-3"><x-status-badge :label="$row['status']" /></td>
                            <td class="px-4 py-3">
                                <a class="font-medium text-lamu-800 hover:underline" href="{{ $row['details_url'] }}">Open review</a>
                            </td>
                            <td class="px-4 py-3">
                                @if ($row['decision'])
                                    <a class="font-medium text-lamu-800 hover:underline" href="{{ $row['details_url'] }}">{{ $row['decision'] }}</a>
                                @else
                                    <span>No review page exists. Publication stays on the price desk.</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-workspace.table>
                <div class="mt-4">{{ $rows->links() }}</div>
            @endif
        </div>
    @endif

    @if ($gaps !== [])
        <ul class="mt-6 max-w-3xl list-disc space-y-1 pl-5 text-sm text-stone-600">
            @foreach ($gaps as $gap)
                <li>{{ $gap }}</li>
            @endforeach
        </ul>
    @endif
</x-workspace-layout>
