<x-workspace-layout :heading="$heading">
    <p class="mt-3 max-w-3xl text-stone-700">{{ $intro }}</p>

    <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($panels as $panel)
            <x-workspace.card
                :title="$panel['title']"
                :description="$panel['description']"
                :href="$panel['href']"
            />
        @endforeach
    </div>

    <section class="mt-8" aria-labelledby="recent-activity">
        <h2 id="recent-activity" class="font-semibold text-lamu-900">Recent activity</h2>
        <x-workspace.table class="mt-4" labelled-by="recent-activity" :columns="$activityColumns">
            <tr class="border-b border-sand-200 last:border-b-0">
                <td colspan="{{ count($activityColumns) }}" class="px-4 py-8">
                    <x-workspace.empty title="Nothing to list yet" :message="$activityEmpty" />
                </td>
            </tr>
        </x-workspace.table>
    </section>

    @if ($accessSummary)
        <section class="mt-8" aria-labelledby="access-summary">
            <h2 id="access-summary" class="font-semibold text-lamu-900">Access summary</h2>
            <x-workspace.table class="mt-4" labelled-by="access-summary" :columns="['Role', 'Accounts']">
                @foreach ($accessSummary as $role)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <th scope="row" class="px-4 py-3 font-medium text-lamu-900">{{ $role->label }}</th>
                        <td class="px-4 py-3 text-stone-600">{{ $role->users_count }} {{ $role->users_count === 1 ? 'account' : 'accounts' }}</td>
                    </tr>
                @endforeach
            </x-workspace.table>
            @if (auth()->user()->hasPermission('admin.users'))
                <a href="{{ route('admin.users.index') }}" class="mt-4 inline-block text-sm font-medium text-lamu-700">User directory</a>
            @endif
        </section>
    @endif
</x-workspace-layout>
