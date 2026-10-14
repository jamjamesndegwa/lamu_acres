<x-workspace-layout :heading="$heading">
    <p class="mt-3 max-w-3xl text-stone-700">{{ $intro }}</p>

    @if (is_array($investor ?? null))
        <section class="mt-8 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">Membership</h2>
            @if ($investor['premium'])
                <p class="mt-2 text-sm text-stone-700">Active entitlement. @if ($investor['ends']) Renewal date {{ $investor['ends']->toFormattedDateString() }}. @endif Premium research is open. Seller identity and parcel records stay closed.</p>
            @else
                <p class="mt-2 text-sm text-stone-700">No active entitlement. Public research previews stay available. Premium material stays closed until a verified payment is on file.</p>
            @endif
            <a href="{{ route('investor.subscription.show') }}" class="mt-4 inline-block text-sm font-medium text-lamu-800">Subscription</a>
        </section>
        <section class="mt-8">
            <h2 class="font-semibold text-lamu-900">Latest research</h2>
            <div class="mt-4 grid gap-4">
                @forelse ($investor['reports'] as $report)
                    <article class="rounded-2xl border border-sand-200 bg-white p-5">
                        <a href="{{ route('investor.reports.show', $report) }}" class="font-medium text-lamu-900 hover:underline">{{ $report->title }}</a>
                        <p class="mt-1 text-sm text-stone-600">{{ $report->summary }}</p>
                        @include('intelligence.partials.report-facts', ['report' => $report, 'premium' => $investor['premium']])
                    </article>
                @empty
                    <p class="text-sm text-stone-600">No research report has been published.</p>
                @endforelse
            </div>
        </section>
        <section class="mt-8">
            <h2 class="font-semibold text-lamu-900">Published developments</h2>
            <div class="mt-4 grid gap-4">
                @forelse ($investor['projects'] as $project)
                    <article class="rounded-2xl border border-sand-200 bg-white p-5">
                        <a href="{{ route('investor.projects.show', $project) }}" class="font-medium text-lamu-900 hover:underline">{{ $project->name }}</a>
                        <p class="mt-1 text-sm text-stone-600">{{ $project->project_status->label() }}. {{ $project->project_status->statement() }}</p>
                    </article>
                @empty
                    <p class="text-sm text-stone-600">No development has been published.</p>
                @endforelse
            </div>
        </section>
        <section class="mt-8">
            <h2 class="font-semibold text-lamu-900">Watched projects</h2>
            <ul class="mt-4 space-y-2 text-sm">
                @forelse ($investor['watches'] as $watch)
                    <li>{{ $watch->locality?->name }} {{ $watch->project?->name }} @if ($watch->project) · {{ $watch->project->project_status->label() }} @endif</li>
                @empty
                    <li class="text-stone-600">No watches yet.</li>
                @endforelse
            </ul>
            <a href="{{ route('investor.watchlists.index') }}" class="mt-4 inline-block text-sm font-medium text-lamu-800">My Watchlists</a>
        </section>
    @endif

    @if (is_array($nextAction ?? null))
        <section class="mt-8 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5" aria-labelledby="buyer-next">
            <h2 id="buyer-next" class="font-semibold text-lamu-900">Next step</h2>
            <p class="mt-2 text-sm text-stone-700">{{ $nextAction['detail'] }}</p>
            <a href="{{ $nextAction['href'] }}" class="mt-4 inline-block rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">{{ $nextAction['label'] }}</a>
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
            'window' => $window,
            'report' => $executive,
            'activity' => $executive->activity(),
        ])
    @endif

    @if ($panels !== [])
        <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($panels as $panel)
                <x-workspace.card
                    :title="$panel['title']"
                    :description="$panel['description']"
                    :href="$panel['href']"
                    :frame="$panel['frame'] ?? null"
                />
            @endforeach
        </div>
    @endif

    @unless (($executive ?? null) || ($executiveDenied ?? false))
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
                <a href="{{ route('admin.users.index') }}" class="mt-4 inline-block text-sm font-medium text-lamu-700">User directory</a>
            @endif
        </section>
    @endif
</x-workspace-layout>
