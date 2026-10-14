<x-workspace-layout :heading="$report->title">
    <p class="mt-3 text-sm text-stone-600">{{ $report->reference }} · {{ $report->publication_status->label() }}</p>
    <p class="mt-3 max-w-3xl">{{ $report->summary }}</p>
    <div class="mt-4 flex gap-2">
        <a href="{{ route('staff.reports.edit', $report) }}" class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Edit</a>
        @if (in_array($report->publication_status, [\App\Enums\PublicationStatus::Draft, \App\Enums\PublicationStatus::Withdrawn], true))
            <form method="POST" action="{{ route('staff.reports.submit', $report) }}">@csrf <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Submit</button></form>
        @endif
        @if ($report->publication_status === \App\Enums\PublicationStatus::Review && auth()->user()->hasPermission('intelligence.publish'))
            <form method="POST" action="{{ route('staff.reports.publish', $report) }}">@csrf <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">Publish</button></form>
        @endif
    </div>
    <h2 class="mt-8 font-semibold">History</h2>
    <ul class="mt-3 text-sm">
        @foreach ($report->audits as $audit)
            <li>{{ $audit->created_at?->toDayDateTimeString() }} · {{ $audit->action }}</li>
        @endforeach
    </ul>
</x-workspace-layout>
