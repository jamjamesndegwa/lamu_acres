<x-workspace-layout :heading="$comparison->area_label">
    <x-editorial-workflow :status="$comparison->publication_status" />
    <p class="mt-3 text-sm text-stone-600">{{ $comparison->reference }} · {{ $comparison->publication_status->label() }}</p>
    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <article class="rounded-2xl border border-sand-200 bg-white p-4">
            <h2 class="font-medium">Before · {{ $comparison->beforeScene?->acquired_on?->toFormattedDateString() }}</h2>
            <p class="text-sm text-stone-600">{{ $comparison->beforeScene?->provider }} · {{ $comparison->beforeScene?->license_name }}</p>
            @if ($file = $comparison->beforeScene?->files->first())
                <img src="{{ route('intelligence.files.show', $file) }}" alt="Before" class="mt-3 w-full rounded-xl">
            @endif
        </article>
        <article class="rounded-2xl border border-sand-200 bg-white p-4">
            <h2 class="font-medium">After · {{ $comparison->afterScene?->acquired_on?->toFormattedDateString() }}</h2>
            <p class="text-sm text-stone-600">{{ $comparison->afterScene?->provider }} · {{ $comparison->afterScene?->license_name }}</p>
            @if ($file = $comparison->afterScene?->files->first())
                <img src="{{ route('intelligence.files.show', $file) }}" alt="After" class="mt-3 w-full rounded-xl">
            @endif
        </article>
    </div>
    <p class="mt-4 max-w-3xl">{{ $comparison->observed_change }}</p>
    @if ($comparison->annotation)
        <p class="mt-3 max-w-3xl text-sm text-stone-700">{{ $comparison->annotation }}</p>
    @endif
    @if ($comparison->project)
        <p class="mt-3 text-sm">Linked project: {{ $comparison->project->name }}</p>
    @endif
    <h2 class="mt-8 font-semibold">History</h2>
    <ul class="mt-3 text-sm text-stone-700">
        @forelse ($comparison->audits as $audit)
            <li>{{ $audit->created_at?->toDayDateTimeString() }} · {{ $audit->actor?->name }} · {{ $audit->action }}</li>
        @empty
            <li>No changes have been recorded.</li>
        @endforelse
    </ul>
    <div class="mt-4 flex gap-2">
        @if (in_array($comparison->publication_status, [\App\Enums\PublicationStatus::Draft, \App\Enums\PublicationStatus::Withdrawn], true))
            <form method="POST" action="{{ route('staff.imagery.comparisons.submit', $comparison) }}">@csrf <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Submit</button></form>
        @endif
        @if ($comparison->publication_status === \App\Enums\PublicationStatus::Review && auth()->user()->hasPermission('intelligence.publish'))
            <form method="POST" action="{{ route('staff.imagery.comparisons.publish', $comparison) }}">@csrf @include('admin.approvals.return-field') <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">Publish</button></form>
        @endif
    </div>
</x-workspace-layout>
