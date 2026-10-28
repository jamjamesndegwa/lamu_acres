@php
    use App\Models\DevelopmentProject;
    use App\Models\ImageryComparison;
    use App\Models\PlanningRecord;
    use App\Models\ResearchReport;

    $subjectRoute = function (object $subject): ?string {
        return match (true) {
            $subject instanceof ResearchReport => route('investor.reports.show', $subject),
            $subject instanceof DevelopmentProject => route('investor.projects.show', $subject),
            $subject instanceof ImageryComparison => route('investor.imagery.show', $subject),
            $subject instanceof PlanningRecord => route('investor.planning.show', $subject),
            default => null,
        };
    };
@endphp

<section class="mt-6 max-w-6xl rounded-2xl border border-lamu-800 bg-lamu-900 px-5 py-5 text-white shadow-md">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/80">{{ $investor['plan']->name }}</p>
            <p class="home-serif mt-1 text-xl font-semibold sm:text-2xl">{{ $investor['plan']->amountLabel() }}</p>
            @if ($investor['premium'])
                <p class="mt-2 text-sm text-white/90">
                    Active membership
                    @if ($investor['ends'])
                        · paid through {{ $investor['ends']->toFormattedDateString() }}
                        @if ($investor['days_remaining'] !== null && $investor['days_remaining'] >= 0)
                            · {{ $investor['days_remaining'] }} {{ $investor['days_remaining'] === 1 ? 'day' : 'days' }} remaining
                        @endif
                    @else
                        · open-ended
                    @endif
                </p>
            @else
                <p class="mt-2 text-sm text-white/90">No active entitlement on this account.</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('investor.subscription.show') }}" class="rounded-full bg-white px-4 py-2 text-sm font-medium text-lamu-900">My Subscription</a>
            @if ($investor['checkout_ready'])
                <a href="{{ route('investor.subscription.show') }}" class="rounded-full border border-white/40 px-4 py-2 text-sm font-medium text-white hover:bg-white/10">Renew with M-Pesa</a>
            @endif
        </div>
    </div>
</section>

<section class="mt-6 max-w-6xl" aria-labelledby="investor-kpis">
    <h2 id="investor-kpis" class="sr-only">Published intelligence overview</h2>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Published reports" :value="(string) $investor['counts']['reports']" :href="route('investor.reports.index')" />
        <x-kpi label="Infrastructure records" :value="(string) $investor['counts']['projects']" :href="route('investor.projects.index')" />
        <x-kpi label="My watchlist" :value="(string) $investor['counts']['watches']" :href="route('investor.watchlists.index')" />
        <x-kpi label="Unread notifications" :value="(string) $investor['counts']['notifications']" :href="route('investor.notifications.index')" />
    </div>
</section>

<section class="mt-8 max-w-6xl" aria-labelledby="investor-research-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="investor-research-heading" class="home-serif text-lg font-semibold text-lamu-900">Latest investment research</h2>
        <a href="{{ route('investor.reports.index') }}" class="text-sm font-medium text-lamu-800 hover:underline">Exclusive Research</a>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        @forelse ($investor['reports'] as $report)
            <article class="flex flex-col rounded-2xl border border-sand-200 bg-white p-5">
                <a href="{{ route('investor.reports.show', $report) }}" class="home-serif font-semibold text-lamu-900 hover:underline">{{ $report->title }}</a>
                <p class="mt-1 text-xs text-stone-500">{{ $report->report_date?->toFormattedDateString() ?: $report->published_at?->toFormattedDateString() ?: 'Date not recorded' }}</p>
                @if ($report->localities->isNotEmpty())
                    <p class="mt-1 text-xs text-stone-600">Coverage: {{ $report->localities->pluck('name')->join(', ') }}</p>
                @endif
                <p class="mt-2 line-clamp-3 text-sm text-stone-700">{{ $report->summary }}</p>
            </article>
        @empty
            <x-workspace.empty class="md:col-span-2" title="No research reports have been published yet." message="Published reports will appear here when released." />
        @endforelse
    </div>
</section>

<section class="mt-8 max-w-6xl" aria-labelledby="investor-projects-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="investor-projects-heading" class="home-serif text-lg font-semibold text-lamu-900">Infrastructure updates</h2>
        <a href="{{ route('investor.projects.index') }}" class="text-sm font-medium text-lamu-800 hover:underline">Infrastructure</a>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        @forelse ($investor['projects'] as $project)
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <p class="text-xs font-medium text-gold-600">{{ $project->project_status->label() }}</p>
                <a href="{{ route('investor.projects.show', $project) }}" class="home-serif mt-1 block font-semibold text-lamu-900 hover:underline">{{ $project->name }}</a>
                <p class="mt-1 text-xs text-stone-600">{{ $project->locality?->name }}{{ $project->county ? ', '.$project->county->name : '' }} · {{ $project->coverage }}</p>
                <p class="mt-2 text-sm text-stone-700">{{ $project->project_status->statement() }}</p>
                <p class="mt-2 text-xs text-stone-500">Verified {{ $project->verified_on?->toFormattedDateString() ?: 'not recorded' }} · Source {{ $project->source_published_on?->toFormattedDateString() ?: 'not recorded' }}</p>
            </article>
        @empty
            <x-workspace.empty class="md:col-span-2" title="No developments have been published." message="Start tracking projects using My Watchlist when records are released." />
        @endforelse
    </div>
</section>

<section class="mt-8 max-w-6xl" aria-labelledby="investor-imagery-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="investor-imagery-heading" class="home-serif text-lg font-semibold text-lamu-900">Satellite intelligence</h2>
        <a href="{{ route('investor.imagery.index') }}" class="text-sm font-medium text-lamu-800 hover:underline">Satellite Intelligence</a>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        @forelse ($investor['comparisons'] as $comparison)
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <a href="{{ route('investor.imagery.show', $comparison) }}" class="home-serif font-semibold text-lamu-900 hover:underline">{{ $comparison->area_label }}</a>
                <p class="mt-2 text-xs text-stone-600">Before {{ $comparison->beforeScene?->acquired_on?->toFormattedDateString() ?: '—' }} · After {{ $comparison->afterScene?->acquired_on?->toFormattedDateString() ?: '—' }}</p>
                <p class="mt-2 line-clamp-2 text-sm text-stone-700">{{ $comparison->observed_change ?: 'Open the comparison for documented changes and licensing.' }}</p>
            </article>
        @empty
            <x-workspace.empty class="md:col-span-2" title="No satellite comparisons are currently available." message="Published imagery comparisons will list here when released." />
        @endforelse
    </div>
</section>

<section class="mt-8 max-w-6xl" aria-labelledby="investor-prices-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="investor-prices-heading" class="home-serif text-lg font-semibold text-lamu-900">Land price snapshot</h2>
        <a href="{{ route('investor.prices') }}" class="text-sm font-medium text-lamu-800 hover:underline">Land Prices</a>
    </div>
    <p class="mt-1 text-xs text-stone-600">Asking prices from published listings—not completed sale prices.</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($investor['price_rows'] as $row)
            <article class="rounded-2xl border border-sand-200 bg-white p-4">
                <h3 class="font-semibold text-lamu-900">{{ $row['locality']->name }}</h3>
                <p class="mt-1 text-xs text-stone-600">{{ $row['sample'] }} asking {{ $row['sample'] === 1 ? 'observation' : 'observations' }}</p>
                @if ($row['median'] !== null)
                    <p class="mt-2 text-sm text-stone-800">Median asking/acre: KSh {{ number_format($row['median']) }}</p>
                @else
                    <p class="mt-2 text-sm text-stone-700">Insufficient observations for a reliable locality median.</p>
                @endif
                <p class="mt-1 text-xs text-stone-500">Freshness: {{ $row['fresh_at'] ? \Illuminate\Support\Carbon::parse($row['fresh_at'])->toFormattedDateString() : 'not recorded' }}</p>
            </article>
        @empty
            <x-workspace.empty class="sm:col-span-2 lg:col-span-4" title="Price observations are not yet sufficient for this area." message="Published listings with locality data will populate the observatory." />
        @endforelse
    </div>
</section>

<section class="mt-8 max-w-6xl grid gap-8 lg:grid-cols-2">
    <div aria-labelledby="investor-watch-heading">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="investor-watch-heading" class="home-serif text-lg font-semibold text-lamu-900">My watchlist</h2>
            <a href="{{ route('investor.watchlists.index') }}" class="text-sm font-medium text-lamu-800 hover:underline">My Watchlist</a>
        </div>
        <ul class="mt-4 space-y-2 rounded-2xl border border-sand-200 bg-white p-5 text-sm text-stone-700">
            @forelse ($investor['watches'] as $watch)
                <li>
                    @if ($watch->project)
                        {{ $watch->project->name }} · {{ $watch->project->project_status->label() }}
                    @elseif ($watch->locality)
                        Location: {{ $watch->locality->name }}
                    @endif
                </li>
            @empty
                <li class="text-stone-600">Start tracking developments using My Watchlist.</li>
            @endforelse
        </ul>
    </div>
    <div aria-labelledby="investor-updates-heading">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="investor-updates-heading" class="home-serif text-lg font-semibold text-lamu-900">Recent updates</h2>
            <a href="{{ route('investor.archive.index') }}" class="text-sm font-medium text-lamu-800 hover:underline">Research Archive</a>
        </div>
        <ul class="mt-4 space-y-2 rounded-2xl border border-sand-200 bg-white p-5 text-sm">
            @forelse ($investor['deliveries'] as $delivery)
                @php $href = $subjectRoute($delivery->subject); @endphp
                <li class="text-stone-700">
                    <span class="text-xs text-stone-500">{{ $delivery->delivered_at?->toDayDateTimeString() }}</span>
                    @if ($href)
                        <a href="{{ $href }}" class="mt-0.5 block font-medium text-lamu-900 hover:underline">{{ $delivery->subject->title ?? $delivery->subject->name ?? 'Update' }}</a>
                    @else
                        <span class="mt-0.5 block">{{ class_basename($delivery->subject) }} update</span>
                    @endif
                </li>
            @empty
                <li class="text-stone-600">Research alerts delivered to this account will appear here.</li>
            @endforelse
        </ul>
    </div>
</section>
