<x-workspace-layout :heading="$heading">
    @if ($workspace === \App\Enums\Workspace::Staff)
        <div class="mt-4 max-w-3xl rounded-2xl border border-lamu-800 bg-lamu-900 px-5 py-4 text-white shadow-md">
            <p class="text-sm leading-relaxed text-white/90">{{ $intro }}</p>
        </div>
    @else
        <p class="mt-3 max-w-3xl text-base leading-relaxed text-stone-800">{{ $intro }}</p>
    @endif

    @if (is_array($investor ?? null))
        @include('investor.partials.dashboard')
    @endif

    @if (is_array($nextAction ?? null))
        <section class="mt-8 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5" aria-labelledby="buyer-next">
            <h2 id="buyer-next" class="font-semibold text-lamu-900">Next step</h2>
            <p class="mt-2 text-sm text-stone-700">{{ $nextAction['detail'] }}</p>
            <a href="{{ $nextAction['href'] }}" @if (! empty($nextAction['frame'])) data-turbo-frame="{{ $nextAction['frame'] }}" @endif class="mt-4 inline-block rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">{{ $nextAction['label'] }}</a>
        </section>
    @endif

    @if (is_array($progress ?? null))
        <section class="mt-8" aria-labelledby="buyer-progress">
            <h2 id="buyer-progress" class="font-semibold text-lamu-900">Progress</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($progress as $item)
                    <x-kpi :label="$item['label']" :value="$item['value']" :href="$item['href']" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($executiveDenied ?? false)
        <section class="mt-8" aria-labelledby="oversight-denied">
            <h2 id="oversight-denied" class="font-semibold text-lamu-900">Business oversight</h2>
            <p class="mt-2 text-sm text-stone-700">You are not authorized to view business oversight.</p>
        </section>
    @endif

    @if ($executive ?? null)
        @include('admin.partials.executive', [
            'report' => $executive,
            'activity' => $executive->activity(),
        ])
    @endif

    @if ($panels !== [] && $workspace !== \App\Enums\Workspace::Investor)
        <section class="mt-8" aria-labelledby="workspace-desks">
            <h2 id="workspace-desks" class="home-serif text-lg font-semibold text-lamu-900">Your desks</h2>
            <p class="mt-1 text-sm text-stone-800">Each card opens the queue you are allowed to work. Desks without a link are not switched on yet.</p>
            <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($panels as $panel)
                    <x-workspace.card
                        :title="$panel['title']"
                        :description="$panel['description']"
                        :href="$panel['href']"
                        :frame="$panel['frame'] ?? null"
                        :icon="$panel['icon'] ?? null"
                    />
                @endforeach
            </div>
        </section>
    @endif

    @unless (($executive ?? null) || ($executiveDenied ?? false) || $workspace === \App\Enums\Workspace::Investor)
    <section class="mt-10 rounded-2xl border border-sand-200 bg-white p-5 shadow-sm" aria-labelledby="recent-activity">
        <h2 id="recent-activity" class="home-serif text-lg font-semibold text-lamu-900">Recent activity</h2>
        <p class="mt-1 text-sm text-stone-800">Latest updates assigned to your account on this workspace.</p>
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
    @endunless

    @if ($accessSummary ?? null)
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
                <a href="{{ route('admin.users.index') }}" class="mt-4 inline-block text-sm font-medium text-lamu-700">Partner accounts</a>
            @endif
        </section>
    @endif
</x-workspace-layout>
