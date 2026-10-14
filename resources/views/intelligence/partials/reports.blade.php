<div class="mt-6 grid gap-4">
    @forelse ($reports as $report)
        <article class="rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-lamu-900"><a class="hover:underline" href="{{ route($frame === 'workspace' ? 'investor.reports.show' : 'intelligence.reports.show', $report) }}">{{ $report->title }}</a></h2>
            <p class="mt-2 text-sm text-stone-600">{{ $report->summary }}</p>
        </article>
    @empty
        <p class="text-stone-600">No research report has been published.</p>
    @endforelse
</div>
<div class="mt-4">{{ $reports->links() }}</div>
