<x-workspace-layout :heading="$property->title">
    <p class="mt-3 text-sm text-stone-600">{{ $property->passport->level->label() }} · {{ $property->passport->override->label() }}</p>
    <p class="mt-1 text-sm"><a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.verification.history', ['property' => $property, 'return' => request('return')]) }}">Verification history</a></p>
    @if ($property->passport->critical_exception)
        <p class="mt-2 text-sm text-stone-700">Critical exception: {{ $property->passport->critical_exception }}</p>
    @endif

    <form method="POST" action="{{ route('staff.verification.override', $property) }}" data-turbo-frame="_top" data-confirm="Save this verification override?" class="mt-6 flex flex-wrap items-end gap-3">
        @csrf
        @include('admin.approvals.return-field')
        @method('PUT')
        <select name="override" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
            @foreach ($overrides as $override)
                <option value="{{ $override->value }}">{{ $override->label() }}</option>
            @endforeach
        </select>
        <input name="critical_exception" placeholder="Critical exception" class="rounded-lg border border-sand-200 px-3 py-2">
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save override</button>
    </form>

    <div class="mt-8 space-y-4">
        @foreach ($property->verificationItems as $item)
            @php
                $next = match ($item->state) {
                    \App\Enums\VerificationItemState::NotSupplied => 'Request the missing evidence',
                    \App\Enums\VerificationItemState::Received, \App\Enums\VerificationItemState::UnderReview => 'Record the review decision',
                    \App\Enums\VerificationItemState::Failed => 'Request a replacement and record the exception',
                    \App\Enums\VerificationItemState::Expired => 'Schedule reverification',
                    \App\Enums\VerificationItemState::Verified => 'No action until the expiry date',
                    \App\Enums\VerificationItemState::NotApplicable => 'No action',
                };
            @endphp
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="font-semibold text-lamu-900">{{ $item->category->label() }}</h2>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    <div><dt class="text-stone-500">State</dt><dd>{{ $item->state->label() }}</dd></div>
                    <div><dt class="text-stone-500">Reviewer</dt><dd>{{ $item->reviewer?->name ?? 'Unassigned' }}</dd></div>
                    <div><dt class="text-stone-500">Review date</dt><dd>{{ $item->checked_at?->toFormattedDateString() ?? $item->review_due_at?->toFormattedDateString() ?? 'Not reviewed' }}</dd></div>
                    <div><dt class="text-stone-500">Expiry</dt><dd>{{ $item->expires_at?->toFormattedDateString() ?? 'No expiry' }}</dd></div>
                    <div><dt class="text-stone-500">Evidence</dt><dd>{{ $item->evidence_note ?: ($item->files->isEmpty() ? 'No evidence on file' : $item->files->count().' file'.($item->files->count() === 1 ? '' : 's')) }}</dd></div>
                    <div><dt class="text-stone-500">Next action</dt><dd>{{ $next }}</dd></div>
                </dl>
                @if ($item->notes)
                    <p class="mt-2 text-sm">{{ $item->notes }}</p>
                @endif
                <form method="POST" action="{{ route('staff.verification.review', $item) }}" data-turbo-frame="_top" data-confirm="Update this checklist item?" class="mt-3 grid gap-2 sm:grid-cols-2">
                    @csrf
                    @include('admin.approvals.return-field')
                    @method('PUT')
                    <select name="state" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                        @foreach ($states as $state)
                            <option value="{{ $state->value }}" @selected($item->state === $state)>{{ $state->label() }}</option>
                        @endforeach
                    </select>
                    <input name="evidence_note" value="{{ $item->evidence_note }}" placeholder="Review evidence" class="rounded-lg border border-sand-200 px-3 py-2">
                    <input type="date" name="expires_at" value="{{ $item->expires_at?->toDateString() }}" class="rounded-lg border border-sand-200 px-3 py-2" aria-label="Expiry date">
                    <input type="date" name="review_due_at" value="{{ $item->review_due_at?->toDateString() }}" class="rounded-lg border border-sand-200 px-3 py-2" aria-label="Review date">
                    <textarea name="notes" class="rounded-lg border border-sand-200 px-3 py-2 sm:col-span-2" placeholder="Notes">{{ $item->notes }}</textarea>
                    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save check</button>
                </form>
                <form method="POST" action="{{ route('staff.verification.evidence', $item) }}" enctype="multipart/form-data" data-turbo-frame="_top" data-confirm="Store this evidence privately?" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="file" name="file" required>
                    <select name="sensitivity" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                        @foreach ($sensitivities as $sensitivity)
                            <option value="{{ $sensitivity->value }}">{{ $sensitivity->label() }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-full border border-sand-200 px-5 py-3 text-sm font-medium">Upload evidence</button>
                </form>
                @foreach ($item->files as $file)
                    <form method="POST" action="{{ route('staff.verification.grant', $file) }}" data-turbo-frame="_top" data-confirm="Grant time-limited access to {{ $file->original_name }}? Access stays private and expires at the time you set." class="mt-3 flex flex-wrap items-end gap-3 text-sm">
                        @csrf
                        <p class="w-full text-stone-600">{{ $file->original_name }} · {{ $file->sensitivity->label() }}</p>
                        <input name="user_id" placeholder="User id" class="rounded-lg border border-sand-200 px-3 py-2" required>
                        <input type="datetime-local" name="expires_at" required class="rounded-lg border border-sand-200 px-3 py-2">
                        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Grant access</button>
                    </form>
                @endforeach
            </article>
        @endforeach
    </div>
</x-workspace-layout>
