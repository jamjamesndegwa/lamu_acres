@php
    $completedAreas = collect($approvalSteps)->where('complete', true)->count();
    $nextStep = collect($approvalSteps)->firstWhere('complete', false);
    $published = $property->status === \App\Enums\PropertyStatus::Published;
    $canTransitionToPublished = in_array(\App\Enums\PropertyStatus::Published, $property->allowedTransitions(), true);
    $readyToPublish = $blockers === [] && $canTransitionToPublished;
@endphp
<section aria-labelledby="approval-heading" class="mt-6 overflow-hidden rounded-2xl border border-sand-200 bg-white">
    <div class="bg-lamu-900 p-5 text-white sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-sand-200">Staff approval workflow</p>
                <h2 id="approval-heading" class="mt-2 text-2xl font-semibold">{{ $published ? 'Listing published' : 'Take this listing through approval' }}</h2>
                <p class="mt-2 max-w-3xl text-sm text-sand-100">Work through the review areas, record each decision, then approve publication. You can save your work and return to any step.</p>
            </div>
            <span class="rounded-full border border-white/30 px-4 py-2 text-sm font-medium">{{ $completedAreas }} of 6 review areas clear</span>
        </div>
        @if (! $published && $nextStep)
            <p class="mt-4 text-sm"><span class="font-semibold">Next review:</span> {{ $nextStep['title'] }}</p>
        @endif
    </div>
    <details @if ($tab === 'overview') open @endif class="group">
        <summary class="cursor-pointer border-b border-sand-200 px-5 py-4 text-sm font-semibold text-lamu-900 sm:px-6">Step-by-step approval checklist <span class="ml-2 font-normal text-stone-500">Open a step to take action</span></summary>
        <ol class="grid gap-4 p-5 sm:p-6 lg:grid-cols-2">
            @foreach ($approvalSteps as $step)
                @php
                    $href = match ($step['tab']) {
                        'media' => route('staff.properties.media', $property),
                        'verification' => auth()->user()->hasPermission('staff.verification')
                            ? route('staff.verification.show', $property)
                            : route('staff.properties.show', [$property, 'tab' => 'verification']),
                        default => route('staff.properties.show', [$property, 'tab' => $step['tab']]),
                    };
                    if ($step['key'] === 'documents') {
                        $href .= '#seller-documents';
                    }
                    if ($step['key'] === 'details') {
                        $href = route('staff.properties.edit', $property);
                    }
                @endphp
                <li @class(['rounded-xl border p-4', 'border-sand-200 bg-stone-50' => $step['complete'], 'border-amber-200 bg-amber-50' => ! $step['complete']])>
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-lamu-900 text-sm font-semibold text-white" aria-label="Step {{ $loop->iteration }}">{{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap justify-between gap-2">
                                <h3 class="font-semibold text-lamu-900">{{ $step['title'] }}</h3>
                                <span @class(['text-xs font-semibold', 'text-lamu-800' => $step['complete'], 'text-amber-900' => ! $step['complete']])>{{ $step['complete'] ? 'Clear' : 'Needs attention' }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $step['instruction'] }}</p>
                            @if ($step['issues'] !== [])
                                <ul class="mt-3 list-disc space-y-1 pl-4 text-sm text-amber-900">
                                    @foreach ($step['issues'] as $issue)
                                        <li>{{ $issue }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="mt-3 text-sm text-lamu-800">No outstanding requirement in this area.</p>
                            @endif
                            <a href="{{ $href }}" class="mt-4 inline-flex rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900 hover:bg-white">{{ $step['action'] }} &rarr;</a>
                            @if (in_array($step['key'], ['documents', 'verification'], true) && ! auth()->user()->hasPermission('staff.verification'))
                                <p class="mt-2 text-xs text-stone-600">Verification staff must record the review decisions.</p>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </details>
    <div id="publication-decision" class="border-t border-sand-200 p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
                <h3 class="font-semibold text-lamu-900">7. Approve and publish</h3>
                <p class="mt-2 text-sm text-stone-600">
                    @if ($published)
                        This listing is already published. Continue to keep its documents, authority and verification checks current.
                    @elseif ($blockers !== [])
                        Publication is blocked. Resolve the outstanding checks below, then return here to publish.
                    @elseif (! $canTransitionToPublished)
                        The checks are clear, but the listing's current status does not allow publication. Review its status before proceeding.
                    @else
                        Publication checks are clear. Confirm that your document review is complete, then publish the listing.
                    @endif
                </p>
            </div>
            @if (! $published && $canTransitionToPublished)
                <form method="POST" action="{{ route('staff.properties.listing', $property) }}" data-turbo-frame="_top" data-confirm="Publish this listing? Every verification and listing check must be clear.">
                    @csrf
                    <button @disabled(! $readyToPublish) class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Approve and publish</button>
                </form>
            @endif
        </div>
        @if ($blockers !== [])
            <ul class="mt-4 grid list-disc gap-x-8 gap-y-2 rounded-xl bg-amber-50 py-4 pl-8 pr-4 text-sm text-amber-900 lg:grid-cols-2">
                @foreach ($blockers as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        @endif
        @error('listing')<p class="mt-3 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
    </div>
</section>
