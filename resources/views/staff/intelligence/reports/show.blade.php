<x-workspace-layout :heading="$report->title">
    <x-editorial-workflow :status="$report->publication_status" />
    <p class="mt-3 text-sm text-stone-600">{{ $report->reference }} · {{ $report->publication_status->label() }}</p>
    <p class="mt-3 max-w-3xl">{{ $report->summary }}</p>
    <div class="mt-4 flex gap-2">
        <a href="{{ route('staff.reports.edit', $report) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Edit</a>
        @if (in_array($report->publication_status, [\App\Enums\PublicationStatus::Draft, \App\Enums\PublicationStatus::Withdrawn], true))
            <form method="POST" action="{{ route('staff.reports.submit', $report) }}">@csrf <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Submit</button></form>
        @endif
        @if ($report->publication_status === \App\Enums\PublicationStatus::Review && auth()->user()->hasPermission('intelligence.publish'))
            <form method="POST" action="{{ route('staff.reports.publish', $report) }}" data-confirm="Approve and publish {{ $report->reference }}. Resulting state: Published. Subscribers are notified. Until this is published, the draft is not a public fact.">@csrf @include('admin.approvals.return-field') <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">Approve and publish</button></form>
        @endif
    </div>
    <h2 class="mt-8 font-semibold">History</h2>
    <ul class="mt-3 text-sm">
        @foreach ($report->audits as $audit)
            <li>{{ $audit->created_at?->toDayDateTimeString() }} · {{ $audit->action }}</li>
        @endforeach
    </ul>
</x-workspace-layout>
