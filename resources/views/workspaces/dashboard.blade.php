<x-workspace-layout :heading="$heading">
    <p class="mt-3 max-w-3xl text-stone-700">{{ $intro }}</p>

    @if (is_array($progress))
        <section class="mt-8" aria-labelledby="buyer-progress">
            <h2 id="buyer-progress" class="font-semibold text-lamu-900">Progress</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($progress as $item)
                    <div class="rounded-2xl border border-sand-200 bg-white p-5">
                        <dt class="text-sm text-stone-500">{{ $item['label'] }}</dt>
                        <dd class="mt-2 text-2xl font-semibold text-lamu-900">
                            <a href="{{ $item['href'] }}" class="hover:underline">{{ $item['value'] }}</a>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

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
            @forelse ($activity as $entry)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 text-sm text-lamu-900">{{ $entry->summary }}</td>
                    <td class="px-4 py-4 text-sm text-stone-600">{{ $entry->occurred_at->toDayDateTimeString() }}</td>
                </tr>
            @empty
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td colspan="{{ count($activityColumns) }}" class="px-4 py-8">
                        <x-workspace.empty title="Nothing to list yet" :message="$activityEmpty" />
                    </td>
                </tr>
            @endforelse
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
