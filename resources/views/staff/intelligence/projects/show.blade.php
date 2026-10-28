<x-workspace-layout :heading="$project->name">
    <x-editorial-workflow :status="$project->publication_status" />
    <p class="mt-3 text-sm text-stone-600">{{ $project->reference }} · {{ $project->project_status->label() }} · {{ $project->publication_status->label() }} · {{ $project->coverage }}</p>
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('staff.projects.edit', $project) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Edit</a>
        @if (in_array($project->publication_status, [\App\Enums\PublicationStatus::Draft, \App\Enums\PublicationStatus::Withdrawn], true))
            <form method="POST" action="{{ route('staff.projects.submit', $project) }}">@csrf <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Submit for review</button></form>
        @endif
        @if ($project->publication_status === \App\Enums\PublicationStatus::Review && auth()->user()->hasPermission('intelligence.publish'))
            <form method="POST" action="{{ route('staff.projects.publish', $project) }}">@csrf @include('admin.approvals.return-field') <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">Publish</button></form>
        @endif
        @include('staff.intelligence.partials.withdraw-form', ['subject' => $project, 'action' => route('staff.projects.withdraw', $project)])
    </div>
    <h2 class="mt-8 font-semibold">Add a sourced milestone</h2>
    <form method="POST" action="{{ route('staff.projects.milestone', $project) }}" class="mt-3 grid max-w-3xl gap-3 sm:grid-cols-2">
        @csrf
        <input name="title" placeholder="Milestone" required class="rounded-lg border border-sand-200 px-3 py-2">
        <input name="source_name" placeholder="Source" required class="rounded-lg border border-sand-200 px-3 py-2">
        <input type="date" name="occurred_on" class="rounded-lg border border-sand-200 px-3 py-2">
        <input name="note" placeholder="Note" class="rounded-lg border border-sand-200 px-3 py-2">
        <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">Add milestone</button>
    </form>
    <ul class="mt-3 text-sm">
        @forelse ($project->milestones as $milestone)
            <li>{{ $milestone->title }} · {{ $milestone->source_name }}</li>
        @empty
            <li>No milestones have been recorded.</li>
        @endforelse
    </ul>
    <h2 class="mt-8 font-semibold">Evidence</h2>
    <form method="POST" action="{{ route('staff.projects.evidence', $project) }}" enctype="multipart/form-data" class="mt-3 max-w-xl space-y-3">
        @csrf
        <x-upload-manager name="evidence" label="Source evidence" required />
        <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Attach</button>
    </form>
    <h2 class="mt-8 font-semibold">History</h2>
    <ul class="mt-3 text-sm text-stone-700">
        @foreach ($project->audits as $audit)
            <li>{{ $audit->created_at?->toDayDateTimeString() }} · {{ $audit->actor?->name }} · {{ $audit->action }} @if ($audit->note) — {{ $audit->note }} @endif</li>
        @endforeach
    </ul>
</x-workspace-layout>
