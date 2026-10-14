<x-workspace-layout heading="System Operations">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">Read-only status for the application, jobs, schedule, and integrations. This screen does not reset data, delete jobs, or change a payment.</p>

    <div class="mt-8 space-y-10">
        <section id="health" class="space-y-4">
            <div>
                <h2 class="font-serif text-2xl text-lamu-900">Application Health</h2>
                <p class="mt-1 text-sm text-stone-600">Database, cache, queue, and storage. A check reports configured, healthy, unhealthy, or not configured.</p>
            </div>
            @include('admin.operations.checks', ['checks' => $health])
        </section>

        <section id="jobs" class="space-y-4">
            <div>
                <h2 class="font-serif text-2xl text-lamu-900">Background Jobs</h2>
                <p class="mt-1 text-sm text-stone-600">Queue settings and the latest recorded outcomes. Job payloads are not shown.</p>
            </div>
            @include('admin.operations.checks', ['checks' => $jobs['checks']])
            <h3 class="text-sm font-semibold text-lamu-900">Last known execution results</h3>
            <x-workspace.table :columns="['Result', 'When', 'Detail']">
                @forelse ($jobs['results'] as $result)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <th scope="row" class="px-4 py-4 font-medium text-lamu-900">{{ $result['label'] }}</th>
                        <td class="px-4 py-4 text-sm text-stone-700">{{ $result['at'] !== '' ? $result['at'] : '—' }}</td>
                        <td class="px-4 py-4 text-sm text-stone-700">{{ $result['detail'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-sm text-stone-600">No execution has been recorded.</td>
                    </tr>
                @endforelse
            </x-workspace.table>
        </section>

        <section id="schedules" class="space-y-4">
            <div>
                <h2 class="font-serif text-2xl text-lamu-900">Scheduled Tasks</h2>
                <p class="mt-1 text-sm text-stone-600">Research commitments, M-Pesa reconciliation, and any other task on the schedule.</p>
            </div>
            <x-workspace.table :columns="['Task', 'Status', 'Frequency', 'Next run', 'Last result']">
                @forelse ($schedules as $task)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <th scope="row" class="px-4 py-4 font-medium text-lamu-900">
                            {{ $task['name'] }}
                            <span class="mt-1 block text-xs font-normal text-stone-500">{{ $task['command'] }}</span>
                        </th>
                        <td class="px-4 py-4">@include('admin.operations.status', ['status' => $task['status'], 'label' => $task['label']])</td>
                        <td class="px-4 py-4 text-sm text-stone-700">{{ $task['frequency'] }}</td>
                        <td class="px-4 py-4 text-sm text-stone-700">{{ $task['next'] }}</td>
                        <td class="px-4 py-4 text-sm text-stone-700">{{ $task['result'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-sm text-stone-600">No scheduled task is configured.</td>
                    </tr>
                @endforelse
            </x-workspace.table>
        </section>

        <section id="integrations" class="space-y-4">
            <div>
                <h2 class="font-serif text-2xl text-lamu-900">Integration Status</h2>
                <p class="mt-1 text-sm text-stone-600">Whether M-Pesa, SMS, and mapping are configured. Credentials are not shown and no provider is called.</p>
            </div>
            @include('admin.operations.checks', ['checks' => $integrations])
        </section>

        <section id="audit" class="space-y-4">
            <div>
                <h2 class="font-serif text-2xl text-lamu-900">Audit and Activity</h2>
                <p class="mt-1 text-sm text-stone-600">Recent authorized system events. The actor, action, time, and affected record are shown.</p>
            </div>
            @if ($activity_unreadable)
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-lamu-900">Recent activity could not be read in full.</p>
            @endif
            <x-workspace.table :columns="['Actor', 'Action', 'Timestamp', 'Affected record']">
                @forelse ($activity as $event)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <td class="px-4 py-4 text-sm text-lamu-900">{{ $event['actor'] }}</td>
                        <td class="px-4 py-4 text-sm text-stone-700">{{ $event['action'] }}</td>
                        <td class="px-4 py-4 text-sm text-stone-700">{{ $event['at'] !== '' ? $event['at'] : '—' }}</td>
                        <td class="px-4 py-4 text-sm text-stone-700">{{ $event['record'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-sm text-stone-600">No authorized system event has been recorded.</td>
                    </tr>
                @endforelse
            </x-workspace.table>
        </section>
    </div>
</x-workspace-layout>
