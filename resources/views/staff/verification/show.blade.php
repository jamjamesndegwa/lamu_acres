<x-workspace-layout :heading="$property->title">
    <p class="mt-3 text-sm text-stone-600">{{ $property->passport->level->label() }} · {{ $property->passport->override->label() }}</p>
    @if ($property->passport->critical_exception)
        <p class="mt-2 text-sm text-stone-700">Critical exception: {{ $property->passport->critical_exception }}</p>
    @endif

    <form method="POST" action="{{ route('staff.verification.override', $property) }}" data-turbo-frame="_top" data-confirm="Save this verification override?" class="mt-6 flex flex-wrap items-end gap-3">
        @csrf
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
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="font-semibold text-lamu-900">{{ $item->category->label() }}</h2>
                <p class="mt-1 text-sm text-stone-600">{{ $item->state->label() }} @if ($item->reviewer) · {{ $item->reviewer->name }} @endif</p>
                @if ($item->notes)
                    <p class="mt-2 text-sm">{{ $item->notes }}</p>
                @endif
                <form method="POST" action="{{ route('staff.verification.review', $item) }}" data-turbo-frame="_top" data-confirm="Update this checklist item?" class="mt-3 grid gap-2 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <select name="state" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                        @foreach ($states as $state)
                            <option value="{{ $state->value }}" @selected($item->state === $state)>{{ $state->label() }}</option>
                        @endforeach
                    </select>
                    <input name="evidence_note" value="{{ $item->evidence_note }}" placeholder="Review evidence" class="rounded-lg border border-sand-200 px-3 py-2">
                    <input type="date" name="expires_at" class="rounded-lg border border-sand-200 px-3 py-2" aria-label="Expiry date">
                    <input type="date" name="review_due_at" class="rounded-lg border border-sand-200 px-3 py-2" aria-label="Review date">
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
                    <form method="POST" action="{{ route('staff.verification.grant', $file) }}" data-turbo-frame="_top" data-confirm="Grant time-limited access?" class="mt-3 flex flex-wrap items-end gap-3 text-sm">
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
